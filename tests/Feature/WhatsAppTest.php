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
        config(['services.whatsapp' => ['driver' => 'fonnte', 'to' => '62811111', 'token' => 'tok', 'secret' => '', 'wablas_url' => 'https://x.test', 'timeout' => 5]]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);

        $this->submit();

        Http::assertSent(fn ($r) => $r->url() === 'https://api.fonnte.com/send'
            && $r->hasHeader('Authorization', 'tok')
            && $r['target'] === '62811111'
            && str_contains($r['message'], 'PRINTER RUSAK')
            && str_contains($r['message'], '/p/'));
        Http::assertSentCount(1);
    }

    public function test_wablas_driver_and_failure_does_not_break_report(): void
    {
        $this->seedTeams();
        config(['services.whatsapp' => ['driver' => 'wablas', 'to' => '62811111', 'token' => 'tok', 'secret' => 'sec', 'wablas_url' => 'https://w.test', 'timeout' => 5]]);
        Http::fake(['w.test/*' => Http::response('err', 500)]);

        $this->submit();

        Http::assertSent(fn ($r) => $r->url() === 'https://w.test/api/send-message' && $r->hasHeader('Authorization', 'tok.sec'));
        $this->assertDatabaseCount('reports', 1);
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

    public function test_off_driver_sends_nothing(): void
    {
        $this->seedTeams();
        config(['services.whatsapp.driver' => 'off']);
        Http::fake();

        $this->submit();

        Http::assertNothingSent();
    }
}
