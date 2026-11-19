<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Maintenance;
use App\Support\WorkHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Foto ikut terhapus, durasi jam kerja yang konsisten, dan pembersihan data otomatis. */
class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private User $emp;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
        $this->emp = $this->user();
        $this->super = $this->user(User::ROLE_SUPERADMIN);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reportWithPhoto(): Report
    {
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->where('allow_custom', false)->value('id'),
            'priority' => 'sedang', 'title' => 'Laptop hang', 'description' => 'x',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    public function test_deleting_report_removes_its_photos_from_disk(): void
    {
        $r = $this->reportWithPhoto();
        $paths = $r->attachments()->pluck('path')->all();
        $this->assertCount(2, $paths);
        foreach ($paths as $p) {
            Storage::disk('local')->assertExists($p);
        }

        $this->actingAs($this->super)->delete(route('admin.reports.destroy', $r))->assertRedirect();

        $this->assertSoftDeleted($r);
        $this->assertSame(0, ReportAttachment::where('report_id', $r->id)->count());
        foreach ($paths as $p) {
            Storage::disk('local')->assertMissing($p);
        }
    }

    public function test_cleanup_removes_old_data_and_orphan_files(): void
    {
        // Laporan yang dihapus sebelum perbaikan: fotonya masih ada.
        $r = $this->reportWithPhoto();
        $old = $r->attachments()->first()->path;
        Report::withoutEvents(fn () => $r->delete());
        Storage::disk('local')->assertExists($old);

        // Berkas yatim lama dan yang baru diunggah.
        Storage::disk('local')->put('lampiran/2025/01/yatim.jpg', 'x');
        touch(Storage::disk('local')->path('lampiran/2025/01/yatim.jpg'), now()->subDays(3)->getTimestamp());
        Storage::disk('local')->put('lampiran/2026/11/baru.jpg', 'x');

        $u = $this->emp;
        $n = fn (bool $read, int $days) => UserNotification::create(['user_id' => $u->id, 'title' => 't', 'message' => 'm', 'is_read' => $read])
            ->forceFill(['created_at' => now()->subDays($days)])->save();
        $n(true, 100);   // dibaca, lama -> hapus
        $n(true, 10);    // dibaca, baru -> simpan
        $n(false, 100);  // belum dibaca, 100 hari -> simpan
        $n(false, 200);  // belum dibaca, sangat lama -> hapus
        AuditLog::create(['action' => 'x', 'description' => 'lama'])->forceFill(['created_at' => now()->subDays(400)])->save();
        AuditLog::create(['action' => 'x', 'description' => 'baru']);

        $dry = app(Maintenance::class)->run(true);
        $this->assertSame(['notifications' => 2, 'audit_logs' => 1, 'deleted_report_files' => 2, 'orphan_files' => 1], $dry);
        Storage::disk('local')->assertExists($old);

        $this->artisan('halodek:bersihkan')->assertSuccessful();
        $this->assertSame(2, UserNotification::where('user_id', $u->id)->where('title', 't')->count());
        $this->assertSame(1, AuditLog::where('action', 'x')->count());
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertMissing('lampiran/2025/01/yatim.jpg');
        Storage::disk('local')->assertExists('lampiran/2026/11/baru.jpg');

        // Foto laporan aktif tetap aman.
        $live = $this->reportWithPhoto();
        app(Maintenance::class)->run();
        foreach ($live->attachments as $a) {
            Storage::disk('local')->assertExists($a->path);
        }
    }

    public function test_cleanup_runs_by_itself_once_a_day(): void
    {
        config(['halodek.auto_maintenance' => true]);
        Cache::flush();
        AuditLog::create(['action' => 'x', 'description' => 'lama'])->forceFill(['created_at' => now()->subDays(400)])->save();

        $this->actingAs($this->emp)->get(route('dashboard'))->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'x')->count());

        AuditLog::create(['action' => 'x', 'description' => 'lama'])->forceFill(['created_at' => now()->subDays(400)])->save();
        $this->actingAs($this->emp)->get(route('dashboard'));
        $this->assertSame(1, AuditLog::where('action', 'x')->count()); // belum sehari
    }

    public function test_durations_use_work_hours_and_short_text(): void
    {
        $this->assertSame('kurang dari 1 jam kerja', WorkHours::human(0));
        $this->assertSame('kurang dari 1 jam kerja', WorkHours::human(59));
        $this->assertSame('1 jam kerja', WorkHours::human(60));
        $this->assertSame('6.5 jam kerja', WorkHours::human(390));
        $this->assertSame('–', WorkHours::human(null));

        Carbon::setTestNow('2026-11-16 09:00'); // Senin
        $r = $this->reportWithPhoto();
        $staf = $this->user(User::ROLE_STAF, 'it');
        Carbon::setTestNow('2026-11-16 09:20');
        $this->actingAs($staf)->post(route('admin.reports.take', $r));
        $this->actingAs($staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'Beres.']);

        $this->actingAs($staf)->get(route('admin.reports.show', $r))
            ->assertSee('kurang dari 1 jam kerja')->assertDontSee('(0 jam kerja)');
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertSee('kurang dari 1 jam kerja');

        // Respons pertama dihitung jam kerja, bukan "6 hari" kalender.
        Carbon::setTestNow('2026-11-20 15:00'); // Jumat sore
        $r2 = $this->reportWithPhoto();
        Carbon::setTestNow('2026-11-23 10:00'); // Senin pagi
        $this->actingAs($staf)->post(route('admin.reports.take', $r2));
        $this->actingAs($staf)->get(route('admin.reports.show', $r2))->assertSee('(4 jam kerja)', false)->assertDontSee('hari)');
    }

    public function test_priority_dropdown_shows_short_labels(): void
    {
        $r = $this->reportWithPhoto();
        $head = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $this->actingAs($head)->get(route('admin.reports.show', $r))
            ->assertSee('>Rendah</option>', false)
            ->assertDontSee('Rendah — Bisa menunggu', false);
    }
}
