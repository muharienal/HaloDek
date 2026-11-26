<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function submit(): void
    {
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->user())->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => 'Printer rusak', 'description' => 'Tidak bisa cetak',
        ])->assertRedirect();
    }

    public function test_fonnte_sends_only_on_new_report_with_link(): void
    {
        $this->seedTeams();
        $this->user(\App\Models\User::ROLE_MANAJER, null, ['phone' => '081211112222']);
        config(['services.whatsapp' => ['driver' => 'fonnte', 'token' => 'tok', 'timeout' => 5, 'manager_mode' => 'setiap']]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

        $this->submit();

        Http::assertSent(fn ($r) => $r->url() === 'https://api.fonnte.com/send'
            && $r->hasHeader('Authorization', 'tok')
            && $r['target'] === '6281211112222'
            && str_contains($r['message'], 'PRINTER RUSAK')
            && str_contains($r['message'], '/admin/laporan/'));
        Http::assertSentCount(1);
    }

    public function test_fonnte_failure_does_not_break_report_and_no_token_means_off(): void
    {
        $this->seedTeams();
        $this->user(\App\Models\User::ROLE_MANAJER, null, ['phone' => '081211112222']);
        config(['services.whatsapp' => ['driver' => 'fonnte', 'token' => 'tok', 'timeout' => 5, 'manager_mode' => 'setiap']]);
        Http::fake(['api.fonnte.com/*' => Http::response('err', 500)]);

        $this->submit();

        Http::assertSent(fn ($r) => $r->url() === 'https://api.fonnte.com/send' && $r->hasHeader('Authorization', 'tok'));
        $this->assertDatabaseCount('reports', 1);

        // Token kosong: tidak mencoba mengirim sama sekali.
        config(['services.whatsapp.token' => '']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
        $this->submit();
        Http::assertNothingSent();
    }

    public function test_public_link_is_short_readonly_and_hides_sensitive_data(): void
    {
        $this->seedTeams();
        $this->submit();
        $report = \App\Models\Report::firstOrFail();
        $url = $report->publicUrl();

        $this->assertLessThan(60, strlen(parse_url($url, PHP_URL_PATH)) + 20);
        $this->flushSession();
        $this->get($url)->assertOk()->assertSee('Printer rusak')->assertSee($report->report_number)->assertDontSee($report->user->nik)->assertDontSee('Halaman hanya-baca');
        $this->get(route('reports.public', ['report' => $report->id, 'token' => str_repeat('a', 16)]))->assertNotFound();
        $this->get(route('reports.public', ['report' => $report->id, 'token' => 'bukan-token']))->assertNotFound();
    }

    public function test_public_page_shows_photos_via_token_links_only(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seedTeams();
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->user())->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => 'Ada foto', 'description' => 'x',
            'photos' => [\Illuminate\Http\UploadedFile::fake()->image('a.jpg'), \Illuminate\Http\UploadedFile::fake()->image('b.png')],
        ])->assertRedirect();
        $report = \App\Models\Report::firstOrFail();
        $att = $report->attachments()->firstOrFail();

        $this->flushSession();
        $this->get($report->publicUrl())->assertOk()->assertSee('data-lightbox="bukti"', false);
        $this->get(route('reports.public.photo', ['report' => $report->id, 'token' => $report->publicToken(), 'attachment' => $att->id]))->assertOk();
        $this->get(route('reports.public.photo', ['report' => $report->id, 'token' => str_repeat('b', 16), 'attachment' => $att->id]))->assertNotFound();

        // Foto laporan lain tidak bisa dibuka lewat token laporan ini.
        $this->submit();
        $other = \App\Models\Report::where('id', '!=', $report->id)->firstOrFail();
        $this->get(route('reports.public.photo', ['report' => $other->id, 'token' => $other->publicToken(), 'attachment' => $att->id]))->assertNotFound();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function waConfig(array $extra = []): void
    {
        // Jam tetap sebelum jam ringkasan harian, agar ringkasan tidak ikut terkirim saat tes dijalankan sore hari.
        if (! \Illuminate\Support\Carbon::hasTestNow()) {
            \Illuminate\Support\Carbon::setTestNow('2026-11-16 10:00');
        }
        if (! \App\Models\User::where('role', \App\Models\User::ROLE_MANAJER)->exists()) {
            $this->user(\App\Models\User::ROLE_MANAJER, null, ['phone' => '081211112222']);
        }
        config(['services.whatsapp' => $extra + ['driver' => 'fonnte', 'token' => 'tok', 
            'timeout' => 5, 'manager_mode' => 'harian', 'digest_at' => '16:00', 'head_priorities' => ['tinggi', 'mendesak']]]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    }

    private function submitWith(string $priority): void
    {
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->user())->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => $priority, 'title' => 'Server mati', 'description' => 'Semua tidak bisa akses',
        ])->assertRedirect();
    }

    public function test_daily_mode_manager_gets_no_message_per_report(): void
    {
        $this->seedTeams();
        $this->waConfig();
        $this->submitWith('sedang');
        Http::assertNothingSent();
    }

    public function test_high_priority_goes_to_team_head_with_fallback_to_manager(): void
    {
        $this->seedTeams();
        $head = $this->user(\App\Models\User::ROLE_KEPALA_REGU, 'it', ['phone' => '081299990000']);
        $this->waConfig();

        $this->submitWith('mendesak');
        Http::assertSent(fn ($r) => $r['target'] === '6281299990000' && str_contains($r['message'], 'LAPORAN MENDESAK MASUK') && str_contains($r['message'], '/admin/laporan/'));
        Http::assertSentCount(1);

        // Kepala Regu belum mengisi nomor: dikirim ke Manajer agar tidak hilang.
        $head->update(['phone' => null]);
        $this->waConfig();
        $this->submitWith('tinggi');
        Http::assertSent(fn ($r) => $r['target'] === '6281211112222' && str_contains($r['message'], 'LAPORAN TINGGI MASUK'));
    }

    public function test_daily_digest_sent_once_after_set_time_on_workday(): void
    {
        $this->seedTeams();
        \Illuminate\Support\Carbon::setTestNow('2026-11-16 10:00'); // Senin
        $this->waConfig();
        $this->submitWith('sedang');
        $this->submitWith('mendesak');

        $this->actingAs($this->user())->get(route('dashboard'));
        Http::assertNotSent(fn ($r) => str_contains($r['message'], 'RINGKASAN'));

        \Illuminate\Support\Carbon::setTestNow('2026-11-16 16:05');
        $this->actingAs($this->user())->get(route('dashboard'));
        Http::assertSent(fn ($r) => $r['target'] === '6281211112222' && str_contains($r['message'], 'Ringkasan HaloDek')
            && str_contains($r['message'], 'Laporan baru: 2') && str_contains($r['message'], 'Server mati'));

        // Tidak dikirim dua kali di hari yang sama.
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
        $this->actingAs($this->user())->get(route('dashboard'));
        Http::assertNothingSent();

        // Sabtu bukan hari kerja.
        \Illuminate\Support\Carbon::setTestNow('2026-11-21 17:00');
        $this->actingAs($this->user())->get(route('dashboard'));
        Http::assertNothingSent();
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_off_driver_sends_nothing(): void
    {
        $this->seedTeams();
        config(['services.whatsapp.driver' => 'off']);
        Http::fake();

        $this->submit();

        Http::assertNothingSent();
    }
}
