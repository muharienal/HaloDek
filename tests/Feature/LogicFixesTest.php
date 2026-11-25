<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Status laporan, penanggung jawab, dan banding harus selalu konsisten. */
class LogicFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $emp;
    private User $head;
    private User $staf;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
        $this->emp = $this->user();
        $this->head = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $this->staf = $this->user(User::ROLE_STAF, 'it');
        $this->super = $this->user(User::ROLE_SUPERADMIN);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(): Report
    {
        $t = Team::where('slug', 'it')->first();
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $t->id, 'category_id' => Category::where('team_id', $t->id)->where('allow_custom', false)->value('id'),
            'priority' => 'sedang', 'description' => 'Tidak bisa login',
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    public function test_user_with_open_tasks_cannot_be_deactivated_or_deleted(): void
    {
        $r = $this->report();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));

        $this->actingAs($this->super)->patch(route('admin.users.toggle', $this->staf))->assertSessionHas('error');
        $this->assertTrue($this->staf->fresh()->is_active);

        $this->actingAs($this->super)->delete(route('admin.users.destroy', $this->staf))->assertSessionHas('error');
        $this->assertNotNull($this->staf->fresh());

        $this->actingAs($this->super)->patch(route('admin.users.update', $this->staf), [
            'nik' => $this->staf->nik, 'name' => $this->staf->name, 'role' => 'manajer',
        ])->assertSessionHas('error');
        $this->assertSame(User::ROLE_STAF, $this->staf->fresh()->role);

        // Setelah dilepas, boleh dinonaktifkan.
        $this->actingAs($this->staf)->post(route('admin.reports.release', $r));
        $this->actingAs($this->super)->patch(route('admin.users.toggle', $this->staf))->assertSessionHas('success');
        $this->assertFalse($this->staf->fresh()->is_active);
    }

    public function test_team_with_open_reports_cannot_be_deactivated(): void
    {
        $team = Team::create(['name' => 'Gudang', 'slug' => 'gudang', 'code' => 'GD', 'is_active' => true]);
        Category::create(['team_id' => $team->id, 'name' => 'Umum', 'is_active' => true]);
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => $team->categories()->value('id'), 'priority' => 'sedang', 'description' => 'x',
        ]);

        $this->actingAs($this->super)->patch(route('admin.teams.update', $team), ['name' => 'Gudang', 'code' => 'GD', 'icon' => config('halodek.team_icons')[0], 'color' => array_key_first(config('halodek.team_colors')), 'is_active' => 0])
            ->assertSessionHas('error');
        $this->assertTrue($team->fresh()->is_active);
    }

    public function test_status_waiting_always_means_no_assignee(): void
    {
        $r = $this->report();
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), ['status' => 'diproses', 'priority' => 'sedang', 'assigned_to_id' => $this->staf->id]);
        $this->assertSame(['diproses', $this->staf->id], [$r->fresh()->status, $r->fresh()->assigned_to_id]);

        // Dikembalikan ke Menunggu -> penanggung jawab dilepas, bisa diambil lagi.
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), ['status' => 'menunggu', 'priority' => 'sedang', 'assigned_to_id' => $this->staf->id]);
        $this->assertSame(['menunggu', null], [$r->fresh()->status, $r->fresh()->assigned_to_id]);
        $this->assertTrue($this->staf->can('claim', $r->fresh()));

        // Penanggung jawab dikosongkan saat Diproses -> kembali Menunggu.
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), ['status' => 'diproses', 'priority' => 'sedang', 'assigned_to_id' => $this->staf->id]);
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), ['status' => 'diproses', 'priority' => 'sedang', 'assigned_to_id' => '']);
        $this->assertSame(['menunggu', null], [$r->fresh()->status, $r->fresh()->assigned_to_id]);
    }

    public function test_cancelled_report_cannot_be_revived(): void
    {
        $r = $this->report();
        $this->actingAs($this->emp)->patch(route('employee.reports.cancel', $r));

        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), ['status' => 'diproses', 'priority' => 'sedang'])->assertSessionHas('error');
        $this->assertSame('dibatalkan', $r->fresh()->status);
        $this->actingAs($this->head)->post(route('admin.reports.transfer', $r), ['team_id' => Team::where('slug', 'sarpras')->value('id'), 'category_id' => 1, 'reason' => 'x']);
        $this->assertSame('dibatalkan', $r->fresh()->status);
    }

    public function test_second_claim_on_stale_page_gets_message_instead_of_overwriting(): void
    {
        $r = $this->report();
        $other = $this->user(User::ROLE_STAF, 'it');
        $stale = $r->fresh(); // halaman staf kedua dibuka sebelum staf pertama mengambil

        $this->actingAs($this->staf)->post(route('admin.reports.take', $r))->assertSessionHas('success');

        $this->expectException(\App\Exceptions\ReportConflict::class);
        app(\App\Services\ReportService::class)->claim($stale, $other);
    }

    public function test_claim_conflict_shows_friendly_message(): void
    {
        $r = $this->report();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r), ['status' => 'selesai', 'priority' => 'sedang', 'follow_up_note' => 'beres']);

        // Laporan sudah selesai: tombol lama ditekan -> pesan, bukan error.
        $other = $this->user(User::ROLE_STAF, 'it');
        $this->actingAs($other)->post(route('admin.reports.take', $r))->assertForbidden();
    }

    public function test_reporter_can_appeal_rejection_once(): void
    {
        $r = $this->report();
        $this->actingAs($this->head)->post(route('admin.reports.close', $r), ['status' => 'ditolak', 'close_note' => 'Bukan wewenang IT']);
        $this->assertNotNull($r->fresh()->rejected_at);

        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertSee('Ajukan banding');
        $this->actingAs($this->emp)->patch(route('employee.reports.appeal', $r), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->emp)->patch(route('employee.reports.appeal', $r), ['reason' => 'Laptop kantor, bukan pribadi'])->assertSessionHas('success');

        $f = $r->fresh();
        $this->assertSame('menunggu', $f->status);
        $this->assertNotNull($f->appealed_at);
        $this->assertNull($f->follow_up_note);
        $this->assertTrue($f->activities()->where('type', 'appealed')->exists());
        $this->assertTrue($this->head->notifications()->where('title', 'like', 'Banding laporan%')->exists());
        $this->actingAs($this->head)->get(route('admin.reports.index'))->assertSee('Banding pelapor');

        // Ditolak lagi -> final, tidak bisa banding kedua kali.
        $this->actingAs($this->head)->post(route('admin.reports.close', $r), ['status' => 'ditolak', 'close_note' => 'Tetap ditolak']);
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertDontSee('Ajukan banding')->assertSee('Keputusan ini final');
        $this->actingAs($this->emp)->patch(route('employee.reports.appeal', $r), ['reason' => 'lagi'])->assertForbidden();
    }

    public function test_appeal_window_and_only_owner(): void
    {
        Carbon::setTestNow('2026-11-16 08:00');
        $r = $this->report();
        $this->actingAs($this->head)->post(route('admin.reports.close', $r), ['status' => 'ditolak', 'close_note' => 'x']);

        $this->actingAs($this->user())->patch(route('employee.reports.appeal', $r), ['reason' => 'x'])->assertForbidden();

        Carbon::setTestNow('2026-11-30 08:00'); // lewat batas hari
        $this->actingAs($this->emp)->patch(route('employee.reports.appeal', $r), ['reason' => 'x'])->assertForbidden();
    }

    public function test_cancel_after_admin_took_it_gives_message(): void
    {
        $r = $this->report();
        $stale = $r->fresh();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));

        $this->expectException(\App\Exceptions\ReportConflict::class);
        app(\App\Services\ReportService::class)->cancel($stale, $this->emp);
    }
}
