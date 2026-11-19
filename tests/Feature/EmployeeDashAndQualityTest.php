<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\MonthlyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Dasbor karyawan ringkas, riwayat tanpa catatan dobel, mutu layanan di laporan bulanan, template catatan. */
class EmployeeDashAndQualityTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submit(array $extra = []): Report
    {
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->emp)->post(route('employee.reports.store'), $extra + [
            'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->where('allow_custom', false)->value('id'),
            'priority' => 'sedang', 'title' => 'Laptop hang', 'description' => 'x',
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    public function test_employee_dashboard_has_problem_search_and_compact_list(): void
    {
        $r = $this->submit();
        $html = $this->actingAs($this->emp)->get(route('dashboard'))
            ->assertSee('data-cat-search', false)->assertSee('data-mode="link"', false)
            ->assertSee('team-pill', false)->assertDontSee('Ajukan Laporan ke…')
            ->assertSee('report-mini', false)->assertSee($r->report_number)
            ->getContent();
        $this->assertStringContainsString('"k":"wifi', $html); // kata kunci ikut dimuat untuk pencarian
    }

    public function test_create_form_preselects_category_from_link(): void
    {
        $cat = Category::where('name', 'Internet & WiFi')->first();
        $this->actingAs($this->emp)->get(route('employee.reports.create', ['regu' => 'it', 'kategori' => $cat->id]))
            ->assertOk()->assertSee('var oldCat = "'.$cat->id.'"', false)->assertSee('data-mode="pick"', false);
    }

    public function test_resolution_note_is_not_repeated_in_history(): void
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'Kabel LAN diganti baru.']);

        $html = $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertSee('Hasil Penyelesaian')->getContent();
        $timeline = substr($html, strpos($html, '<ul class="timeline">'));
        $timeline = substr($timeline, 0, strpos($timeline, '</ul>'));
        $this->assertStringNotContainsString('Kabel LAN diganti baru.', $timeline); // tidak diulang di riwayat
        $this->assertStringContainsString('menjadi Selesai', $timeline);
        // Admin tetap melihat catatan di riwayat.
        $this->actingAs($this->staf)->get(route('admin.reports.show', $r))->assertSee('Kabel LAN diganti baru.');
    }

    public function test_monthly_report_shows_on_time_satisfaction_and_reopened(): void
    {
        Carbon::setTestNow('2026-11-16 08:00'); // Senin
        $fast = $this->submit(['priority' => 'mendesak']);   // batas 1 jam
        $slow = $this->submit(['priority' => 'mendesak']);
        $open = $this->submit(['priority' => 'rendah']);

        Carbon::setTestNow('2026-11-16 08:30');
        $this->actingAs($this->staf)->post(route('admin.reports.take', $fast));                 // tepat waktu
        $this->actingAs($this->staf)->post(route('admin.reports.close', $fast), ['status' => 'selesai', 'close_note' => 'ok']);
        Carbon::setTestNow('2026-11-16 11:00');
        $this->actingAs($this->staf)->post(route('admin.reports.take', $slow));                 // terlambat (3 jam)
        $this->actingAs($this->staf)->post(route('admin.reports.close', $slow), ['status' => 'selesai', 'close_note' => 'ok']);

        $this->actingAs($this->emp)->patch(route('employee.reports.confirm', $fast));          // 👍
        $this->actingAs($this->emp)->patch(route('employee.reports.reopen', $slow), ['reason' => 'masih rusak']); // 👎

        $d = app(MonthlyReport::class)->build($this->head, Carbon::parse('2026-11-01'));
        $this->assertSame(2, $d['responded_n']);
        $this->assertSame(1, $d['on_time']);
        $this->assertSame(50, $d['on_time_pct']);
        $this->assertSame(1, $d['happy']);
        $this->assertSame(2, $d['answered']);
        $this->assertSame(50, $d['happy_pct']);
        $this->assertSame(1, $d['reopened']);
        $this->assertSame(1, $d['resolved_n']);

        $this->actingAs($this->head)->get(route('admin.monthly.index', ['bulan' => '2026-11']))
            ->assertSee('Diambil Tepat Waktu')->assertSee('Kepuasan Pelapor')->assertSee('Dibuka Ulang')
            ->assertSee('dari 1 laporan yang sudah selesai')->assertSee('dari 2 laporan yang sudah ditangani');
        $this->actingAs($this->head)->get(route('admin.monthly.print', ['bulan' => '2026-11']))->assertSee('Kepuasan pelapor');
        $this->actingAs($this->head)->get(route('admin.monthly.download', ['bulan' => '2026-11']))->assertOk();
    }

    public function test_note_templates_default_edit_and_used_in_close_popup(): void
    {
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->get(route('admin.reports.show', $r))
            ->assertSee('data-quick-note="Sudah diperbaiki, silakan dicek kembali."', false);

        $team = Team::where('slug', 'it')->first();
        // Staf tidak boleh mengubah template.
        $this->actingAs($this->staf)->patch(route('admin.teams.templates', $team), ['selesai' => 'x'])->assertForbidden();

        $this->actingAs($this->head)->patch(route('admin.teams.templates', $team), [
            'selesai' => "RAM sudah diganti.\r\n\r\n  Sudah di-reinstall.  \nRAM sudah diganti.",
            'ditolak' => '',
        ])->assertSessionHas('success');
        $tpl = $team->fresh()->noteTemplates();
        $this->assertSame(['RAM sudah diganti.', 'Sudah di-reinstall.'], $tpl['selesai']);
        $this->assertSame([], $tpl['ditolak']);

        $this->actingAs($this->staf)->get(route('admin.reports.show', $r))
            ->assertSee('data-quick-note="Sudah di-reinstall."', false)
            ->assertDontSee('Sudah diperbaiki, silakan dicek kembali.');
        $this->actingAs($this->head)->get(route('admin.teams.show', $team))->assertSee('Template Catatan')->assertSee('Sudah di-reinstall.');
    }
}
