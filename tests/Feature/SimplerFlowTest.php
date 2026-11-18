<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Alur yang dipersingkat: tutup cepat, buka kembali, judul otomatis, cari kategori, ekspor Excel. */
class SimplerFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $emp;
    private User $head;
    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
        $this->emp = $this->user();
        $this->head = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $this->staf = $this->user(User::ROLE_STAF, 'it');
    }

    private function submit(array $extra = []): Report
    {
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->emp)->post(route('employee.reports.store'), $extra + [
            'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->where('allow_custom', false)->value('id'),
            'priority' => 'sedang', 'title' => 'Laptop hang', 'description' => 'Sering macet.',
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    private function resolved(): Report
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'RAM diganti.'])->assertRedirect();

        return $r->refresh();
    }

    public function test_quick_close_needs_note_and_notifies_reporter(): void
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));

        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'ditolak', 'close_note' => ''])
            ->assertSessionHasErrors(['close_note' => 'Tuliskan alasan penolakan agar pelapor memahaminya.']);
        $this->assertSame('diproses', $r->refresh()->status);

        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'ditolak', 'close_note' => 'Di luar wewenang IT.'])
            ->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('ditolak', $r->status);
        $this->assertSame('Di luar wewenang IT.', $r->follow_up_note);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->emp->id, 'report_id' => $r->id]);

        // Laporan yang sudah ditutup tidak bisa ditutup lagi lewat tombol cepat.
        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'x'])->assertStatus(422);
    }

    public function test_quick_close_respects_permissions(): void
    {
        $r = $this->submit();
        $this->actingAs($this->head)->post(route('admin.reports.take', $r));
        // Staf bukan penanggung jawab -> tidak boleh menutup.
        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'x'])->assertForbidden();
        $this->actingAs($this->emp)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'x'])->assertForbidden();
    }

    public function test_detail_page_shows_quick_buttons_only_to_handler(): void
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->get(route('admin.reports.show', $r))->assertDontSee('data-modal-open="close-selesai"', false);
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->get(route('admin.reports.show', $r))
            ->assertSee('data-modal-open="close-selesai"', false)
            ->assertSee('data-modal-open="close-ditolak"', false)
            ->assertSee('Ubah status manual');
    }

    public function test_reporter_confirms_fixed(): void
    {
        $r = $this->resolved();
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertSee('Apakah masalahnya sudah beres?');

        $this->actingAs($this->emp)->patch(route('employee.reports.confirm', $r))->assertSessionHas('success');
        $r->refresh();
        $this->assertTrue($r->satisfied);
        $this->assertSame('selesai', $r->status);
        $this->assertDatabaseHas('report_activities', ['report_id' => $r->id, 'type' => 'confirmed']);
        // Hanya sekali.
        $this->actingAs($this->emp)->patch(route('employee.reports.confirm', $r))->assertForbidden();
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertDontSee('Apakah masalahnya sudah beres?')->assertSee('Buka kembali');
    }

    public function test_reporter_reopens_back_to_assignee(): void
    {
        $r = $this->resolved();
        $this->actingAs($this->emp)->patch(route('employee.reports.reopen', $r), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->actingAs($this->emp)->patch(route('employee.reports.reopen', $r), ['reason' => 'Masih hang.'])->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('diproses', $r->status);
        $this->assertSame($this->staf->id, $r->assigned_to_id);
        $this->assertNull($r->resolved_at);
        $this->assertFalse($r->satisfied);
        $this->assertSame(1, $r->reopened_count);
        $this->assertNull($r->follow_up_note);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->staf->id, 'title' => 'Laporan dibuka kembali '.$r->report_number]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->head->id, 'title' => 'Laporan dibuka kembali '.$r->report_number]);
        $this->actingAs($this->staf)->get(route('admin.reports.show', $r))->assertSee('Dibuka ulang 1×');

        // Diselesaikan lagi -> pelapor ditanya ulang.
        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'AP kedua dipasang.']);
        $this->assertNull($r->refresh()->satisfied);
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertSee('Apakah masalahnya sudah beres?')->assertDontSee('Anda menandai');
    }

    public function test_reopen_is_limited_to_owner_and_window(): void
    {
        $r = $this->resolved();
        $other = $this->user();
        $this->actingAs($other)->patch(route('employee.reports.reopen', $r), ['reason' => 'x'])->assertForbidden();

        $r->forceFill(['resolved_at' => now()->subDays(config('halodek.reopen_days') + 1)])->save();
        $this->actingAs($this->emp)->patch(route('employee.reports.reopen', $r), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertDontSee('Belum beres');
    }

    public function test_title_is_optional_and_generated(): void
    {
        $loc = Location::create(['name' => 'Ruang Rapat A']);
        $cat = Category::where('team_id', Team::where('slug', 'it')->value('id'))->where('name', 'Internet & WiFi')->first();
        $r = $this->submit(['title' => '', 'category_id' => $cat->id, 'location_id' => $loc->id]);
        $this->assertSame('Internet & WiFi – Ruang Rapat A', $r->title);

        $lain = Category::where('team_id', $cat->team_id)->where('allow_custom', true)->first();
        $r = $this->submit(['title' => '', 'category_id' => $lain->id, 'custom_category' => 'Proyektor rusak']);
        $this->assertSame('Proyektor rusak', $r->title);
    }

    public function test_create_form_has_priority_hints_and_category_keywords(): void
    {
        $this->actingAs($this->emp)->get(route('employee.reports.create'))
            ->assertSee('Pekerjaan terhenti atau ada bahaya')
            ->assertSee('cat-q', false)
            ->assertSee('wifi', false)
            ->assertSee('Sudah dicoba restart?', false);
    }

    public function test_head_can_edit_category_keywords_and_hint(): void
    {
        $cat = Category::where('team_id', $this->head->team_id)->first();
        $this->actingAs($this->head)->patch(route('admin.categories.update', $cat), [
            'name' => $cat->name, 'keywords' => ' Laptop,  PC ,laptop ,, ', 'hint' => ' Sebutkan merek. ', 'is_active' => 1,
        ])->assertSessionHas('success');
        $cat->refresh();
        $this->assertSame('laptop, pc', $cat->keywords);
        $this->assertSame('Sebutkan merek.', $cat->hint);
    }

    public function test_export_is_excel(): void
    {
        $this->resolved();
        $res = $this->actingAs($this->head)->get(route('admin.reports.export'));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $res->headers->get('Content-Disposition'));
    }

    public function test_header_search_filters_list_pages(): void
    {
        $this->submit();
        $this->actingAs($this->head)->get(route('admin.reports.index', ['status' => 'aktif', 'q' => 'Laptop']))
            ->assertSee('Cari di daftar ini')
            ->assertSee('name="status" value="aktif"', false)
            ->assertDontSee('id="q" name="q"', false)
            ->assertSee('Laptop hang');
        $this->actingAs($this->head)->get(route('dashboard'))->assertSee('action="'.route('search').'"', false);
    }

    public function test_wording_is_consistent(): void
    {
        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertSee('Perlu Ditangani')->assertSee('Menunggu Diambil')
            ->assertDontSee('Antrean')->assertDontSee('tiket');
        $this->actingAs($this->emp)->get(route('dashboard'))->assertDontSee('tiket')->assertDontSee('Antrean');
        $this->assertSame('Layanan Pelaporan Terpadu', config('halodek.tagline'));
    }

    public function test_employee_closed_filter_matches_dashboard_card(): void
    {
        $r = $this->submit();
        $this->actingAs($this->emp)->patch(route('employee.reports.cancel', $r));
        $keep = $this->submit(['title' => 'Masih aktif']);
        $this->actingAs($this->emp)->get(route('dashboard'))->assertSee(route('employee.reports.index', ['status' => 'ditutup']), false);
        $this->actingAs($this->emp)->get(route('employee.reports.index', ['status' => 'ditutup']))
            ->assertSee($r->report_number)->assertDontSee($keep->report_number);
    }
}
