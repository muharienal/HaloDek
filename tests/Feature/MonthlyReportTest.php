<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $slug, string $status, $created, $resolved = null, $respond = null): Report
    {
        $team = Team::where('slug', $slug)->first();
        $emp = User::where('role', 'karyawan')->first() ?? $this->user();

        $r = (new Report)->forceFill([
            'report_number' => strtoupper($slug).'-'.uniqid(), 'user_id' => $emp->id, 'reporter_name' => $emp->name, 'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->value('id'), 'priority' => 'sedang', 'title' => 'Uji '.$slug, 'description' => 'x',
            'status' => $status, 'created_at' => $created, 'resolved_at' => $resolved, 'first_response_at' => $respond,
        ]);
        $r->save();

        return $r;
    }

    public function test_page_numbers_are_correct_and_scoped_per_team(): void
    {
        $this->seedTeams();
        // Senin pertama bulan ini 07:00. Jam kerja Sen-Jum 07-16.
        $m = now()->startOfMonth()->modify('first monday of this month')->setTime(7, 0);
        $this->make('it', 'selesai', $m, $m->copy()->setTime(10, 0), $m->copy()->addHours(2));           // 3 jam kerja
        $this->make('it', 'selesai', $m, $m->copy()->addDay()->setTime(10, 0), $m->copy()->addHours(4)); // 9 + 3 = 12 jam kerja (bukan 27 jam kalender)
        $this->make('it', 'menunggu', $m);
        $this->make('keamanan', 'ditolak', $m);
        $this->make('it', 'selesai', $m->copy()->subMonths(2), $m->copy()->subMonths(2)->addHours(5)); // bulan lain, tidak dihitung

        $super = $this->user(User::ROLE_SUPERADMIN);
        $d = app(\App\Services\MonthlyReport::class)->build($super, $m);
        $this->assertSame(4, $d['total']);
        $this->assertSame(2, $d['selesai']);
        $this->assertSame(1, $d['menunggu']);
        $this->assertSame(1, $d['ditolak']);
        $this->assertSame(7.5, $d['avg_resolve']);    // (3 + 12) / 2 jam kerja
        $this->assertSame(3.0, $d['avg_respond']);    // (2 + 4) / 2
        $this->assertSame(50, $d['done_pct']);

        $this->actingAs($super)->get(route('admin.monthly.index', ['bulan' => $m->format('Y-m')]))->assertOk()->assertSee('7,5 jam kerja', false);

        // Staf regu Keamanan hanya melihat regunya sendiri.
        $staf = $this->user(User::ROLE_STAF, 'keamanan');
        $d2 = app(\App\Services\MonthlyReport::class)->build($staf, $m);
        $this->assertSame(1, $d2['total']);
        $this->assertCount(1, $d2['teams']);
    }

    public function test_karyawan_forbidden_and_bad_month_falls_back(): void
    {
        $this->seedTeams();
        $this->actingAs($this->user())->get(route('admin.monthly.index'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_SUPERADMIN))->get(route('admin.monthly.index', ['bulan' => "'; drop table--"]))->assertOk();
    }

    public function test_excel_download_is_valid_xlsx_and_print_view_works(): void
    {
        $this->seedTeams();
        $m = now()->startOfMonth()->addDay();
        $this->make('it', 'selesai', $m, $m->copy()->addHours(6), $m->copy()->addHour());
        $super = $this->user(User::ROLE_SUPERADMIN);

        $res = $this->actingAs($super)->get(route('admin.monthly.download', ['bulan' => $m->format('Y-m')]));
        $res->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $file = $res->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'xl/worksheets/sheet3.xml'] as $part) {
            $this->assertNotFalse($zip->getFromName($part), $part);
        }
        $this->assertStringContainsString('Total laporan masuk', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        copy($file, sys_get_temp_dir().'/halodek-test.xlsx');   // untuk diperiksa di luar tes

        $this->get(route('admin.monthly.print', ['bulan' => $m->format('Y-m')]))->assertOk()->assertSee('Laporan Bulanan');
    }
}
