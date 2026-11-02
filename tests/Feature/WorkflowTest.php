<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $emp;
    private User $head;
    private User $staf;
    private User $otherStaf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
        $this->emp = $this->user();
        $this->head = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $this->staf = $this->user(User::ROLE_STAF, 'it');
        $this->otherStaf = $this->user(User::ROLE_STAF, 'sarpras');
    }

    private function submit(string $slug = 'it'): Report
    {
        $team = Team::where('slug', $slug)->first();
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'tinggi', 'title' => 'Laptop hang', 'description' => 'Sering macet.',
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    public function test_new_report_lands_in_team_queue_and_notifies_head_and_staff(): void
    {
        $r = $this->submit();
        $this->assertSame('menunggu', $r->status);
        $this->assertNull($r->assigned_to_id);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->head->id, 'report_id' => $r->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $this->otherStaf->id]);
    }

    public function test_staf_can_claim_unassigned_but_not_someone_elses(): void
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r))->assertRedirect();
        $r->refresh();
        $this->assertSame($this->staf->id, $r->assigned_to_id);
        $this->assertSame('diproses', $r->status);

        $staf2 = $this->user(User::ROLE_STAF, 'it');
        $this->actingAs($staf2)->post(route('admin.reports.take', $r))->assertForbidden();
        $this->assertSame($this->staf->id, $r->fresh()->assigned_to_id);
    }

    public function test_head_assigns_and_staf_cannot_assign_or_change_priority(): void
    {
        $r = $this->submit();
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), [
            'status' => 'diproses', 'priority' => 'rendah', 'assigned_to_id' => $this->staf->id,
        ])->assertRedirect();
        $r->refresh();
        $this->assertSame($this->staf->id, $r->assigned_to_id);
        $this->assertSame('rendah', $r->priority);

        $this->actingAs($this->staf)->patch(route('admin.reports.update', $r), [
            'status' => 'diproses', 'priority' => 'kritis', 'assigned_to_id' => $this->head->id,
        ])->assertRedirect();
        $r->refresh();
        $this->assertSame('rendah', $r->priority);
        $this->assertSame($this->staf->id, $r->assigned_to_id);
    }

    public function test_cannot_assign_to_staf_from_another_team(): void
    {
        $r = $this->submit();
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), [
            'status' => 'diproses', 'priority' => 'rendah', 'assigned_to_id' => $this->otherStaf->id,
        ])->assertSessionHasErrors('assigned_to_id');
    }

    public function test_head_can_handle_it_personally_and_staf_can_release(): void
    {
        $r = $this->submit();
        $this->actingAs($this->head)->post(route('admin.reports.take', $r))->assertRedirect();
        $this->assertSame($this->head->id, $r->fresh()->assigned_to_id);

        $r2 = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r2));
        $this->actingAs($this->staf)->post(route('admin.reports.release', $r2))->assertRedirect();
        $r2->refresh();
        $this->assertNull($r2->assigned_to_id);
        $this->assertSame('menunggu', $r2->status);
    }

    public function test_finishing_needs_note_and_records_resolution_time_without_rating(): void
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->patch(route('admin.reports.update', $r), ['status' => 'selesai'])->assertSessionHasErrors('follow_up_note');
        $this->actingAs($this->staf)->patch(route('admin.reports.update', $r), ['status' => 'selesai', 'follow_up_note' => 'Sudah diganti RAM.'])->assertRedirect();
        $r->refresh();
        $this->assertSame('selesai', $r->status);
        $this->assertNotNull($r->resolved_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->head->id, 'report_id' => $r->id]);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('employee.reports.rate'));
    }

    public function test_staf_of_other_team_cannot_see_or_touch_report(): void
    {
        $r = $this->submit();
        $this->actingAs($this->otherStaf)->get(route('admin.reports.show', $r))->assertForbidden();
        $this->actingAs($this->otherStaf)->post(route('admin.reports.take', $r))->assertForbidden();
    }

    public function test_employee_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->emp)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->emp)->get(route('admin.inventory.index'))->assertForbidden();
    }

    public function test_dashboards_render_for_every_role(): void
    {
        $this->submit();
        foreach ([$this->emp, $this->head, $this->staf, $this->user(User::ROLE_SUPERADMIN)] as $u) {
            $this->actingAs($u)->get('/dashboard')->assertOk()->assertDontSee('SLA');
        }
        $this->actingAs($this->head)->get(route('admin.reports.index', ['assigned' => 'none']))->assertOk();
        $this->actingAs($this->staf)->get(route('admin.reports.index', ['assigned' => 'me']))->assertOk();
    }
}
