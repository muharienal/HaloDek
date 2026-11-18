<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Support\WorkHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Jam kerja, batas respons + pengingat, tombol WA, cek laporan ganda, dan aksi massal. */
class SlaAndBulkTest extends TestCase
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
            'priority' => 'sedang', 'title' => 'Laptop hang', 'description' => 'Sering macet.',
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    // ------------------------------------------------------------ jam kerja

    public function test_work_hours_skip_nights_and_weekends(): void
    {
        $mon = Carbon::parse('2026-11-16 07:00'); // Senin
        $this->assertSame(180, WorkHours::minutesBetween($mon, $mon->copy()->setTime(10, 0)));
        // Senin 15:00 -> Selasa 08:00 = 1 jam (Sen) + 1 jam (Sel)
        $this->assertSame(120, WorkHours::minutesBetween($mon->copy()->setTime(15, 0), $mon->copy()->addDay()->setTime(8, 0)));
        // Jumat 15:00 -> Senin 08:00 = 2 jam kerja (akhir pekan tidak dihitung)
        $this->assertSame(120, WorkHours::minutesBetween(Carbon::parse('2026-11-20 15:00'), Carbon::parse('2026-11-23 08:00')));
        // Dibuat Sabtu malam -> Senin 09:00 = 2 jam
        $this->assertSame(120, WorkHours::minutesBetween(Carbon::parse('2026-11-21 22:00'), Carbon::parse('2026-11-23 09:00')));
        // Batas 4 jam kerja dari Jumat 14:00 -> Senin 09:00
        $this->assertSame('2026-11-23 09:00', WorkHours::addHours(Carbon::parse('2026-11-20 14:00'), 4)->format('Y-m-d H:i'));
        // Dibuat malam -> batas dihitung dari 07:00 esok hari
        $this->assertSame('2026-11-17 08:00', WorkHours::addHours(Carbon::parse('2026-11-16 20:00'), 1)->format('Y-m-d H:i'));
        $this->assertSame('Sen–Jum 07.00–16.00', WorkHours::label());
    }

    public function test_holidays_from_config_are_skipped(): void
    {
        config(['halodek.work_hours.holidays' => ['2026-11-17']]);
        $this->assertSame(120, WorkHours::minutesBetween(Carbon::parse('2026-11-16 15:00'), Carbon::parse('2026-11-18 08:00')));
    }

    public function test_resolution_time_is_stored_in_work_hours(): void
    {
        Carbon::setTestNow('2026-11-16 15:00'); // Senin
        $r = $this->submit();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        Carbon::setTestNow('2026-11-17 09:00'); // Selasa
        $this->actingAs($this->staf)->post(route('admin.reports.close', $r), ['status' => 'selesai', 'close_note' => 'Beres.']);
        $r->refresh();
        $this->assertSame(180, $r->resolve_work_minutes); // 1 jam Senin + 2 jam Selasa (bukan 18 jam kalender)
        $this->assertSame(3.0, $r->resolutionHours());
        $this->assertSame(0, $r->respond_work_minutes);
    }

    // ----------------------------------------------------- batas & pengingat

    public function test_overdue_shows_on_dashboard_list_and_detail(): void
    {
        Carbon::setTestNow('2026-11-16 08:00');
        $r = $this->submit(['priority' => 'mendesak']); // batas 1 jam kerja -> 09:00
        Carbon::setTestNow('2026-11-16 08:30');
        $this->assertFalse($r->fresh()->isOverdue());
        $this->actingAs($this->head)->get(route('dashboard'))->assertDontSee('lewat batas respons');

        Carbon::setTestNow('2026-11-16 09:30');
        $this->assertTrue($r->fresh()->isOverdue());
        $this->actingAs($this->head)->get(route('dashboard'))->assertSee('1 laporan lewat batas respons')->assertSee('Lewat batas');
        $this->actingAs($this->head)->get(route('admin.reports.index', ['lewat' => 1]))->assertSee($r->report_number)->assertSee('Tampilkan semua');
        $this->actingAs($this->head)->get(route('admin.reports.show', $r))->assertSee('Batas respons')->assertSee('Lewat batas');

        // Setelah diambil, tidak lagi lewat batas.
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->assertFalse($r->fresh()->isOverdue());
    }

    public function test_reminder_command_sends_once_to_head_whatsapp(): void
    {
        config(['services.whatsapp.driver' => 'fonnte', 'services.whatsapp.token' => 'tkn', 'services.whatsapp.to' => '628111']);
        Http::fake(['*' => Http::response(['status' => true])]);
        $this->head->update(['phone' => '0812-3456-7000']);

        Carbon::setTestNow('2026-11-16 08:00');
        $r = $this->submit(['priority' => 'tinggi']); // batas 2 jam kerja -> 10:00
        Http::fake(['*' => Http::response(['status' => true])]); // abaikan WA laporan baru

        Carbon::setTestNow('2026-11-16 09:00');
        $this->artisan('halodek:pengingat')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow('2026-11-16 10:30');
        $this->artisan('halodek:pengingat')->assertSuccessful();
        Http::assertSent(fn ($req) => $req['target'] === '6281234567000' && str_contains($req['message'], 'PENGINGAT') && str_contains($req['message'], $r->report_number));
        $this->assertNotNull($r->fresh()->reminded_at);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->head->id, 'title' => 'Lewat batas respons '.$r->report_number]);

        // Tidak diulang.
        Http::fake(['*' => Http::response(['status' => true])]);
        $this->artisan('halodek:pengingat')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_reminder_falls_back_to_wa_to_and_is_quiet_outside_work_hours(): void
    {
        config(['services.whatsapp.driver' => 'fonnte', 'services.whatsapp.token' => 'tkn', 'services.whatsapp.to' => '0811 2222 3333']);
        Carbon::setTestNow('2026-11-16 08:00');
        $this->submit(['priority' => 'mendesak']);

        Http::fake(['*' => Http::response(['status' => true])]);
        Carbon::setTestNow('2026-11-16 20:00'); // malam: diam
        $this->artisan('halodek:pengingat')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow('2026-11-17 07:30');
        $this->artisan('halodek:pengingat')->assertSuccessful();
        Http::assertSent(fn ($req) => $req['target'] === '6281122223333');
    }

    public function test_reminder_runs_by_itself_when_someone_opens_the_app(): void
    {
        config(['services.whatsapp.driver' => 'fonnte', 'services.whatsapp.token' => 'tkn', 'services.whatsapp.to' => '']); // hanya ke Kepala Regu
        $this->head->update(['phone' => '081234567000']);
        Carbon::setTestNow('2026-11-16 08:00');
        $r = $this->submit(['priority' => 'mendesak']);

        config(['halodek.auto_reminder' => true]);
        \Illuminate\Support\Facades\Cache::flush();
        Http::fake(['*' => Http::response(['status' => true])]);
        Carbon::setTestNow('2026-11-16 09:30');

        // Pengguna mana pun membuka halaman -> pengingat terkirim tanpa scheduler.
        $this->actingAs($this->emp)->get(route('dashboard'))->assertOk();
        Http::assertSent(fn ($req) => $req['target'] === '6281234567000' && str_contains($req['message'], $r->report_number));
        $this->assertNotNull($r->fresh()->reminded_at);

        // Laporan lain lewat batas, tapi baru dicek lagi setelah 15 menit.
        $r2 = Report::create($r->only(['user_id', 'reporter_name', 'team_id', 'category_id', 'title', 'description']) + ['report_number' => 'IT-X-1', 'priority' => 'mendesak', 'status' => 'menunggu']);
        $r2->forceFill(['created_at' => Carbon::parse('2026-11-16 07:00')])->save();
        Http::fake(['*' => Http::response(['status' => true])]);
        $this->actingAs($this->emp)->get(route('dashboard'));
        Http::assertNothingSent();
        Carbon::setTestNow('2026-11-16 09:46');
        $this->actingAs($this->emp)->get(route('dashboard'));
        Http::assertSent(fn ($req) => str_contains($req['message'], 'IT-X-1'));
    }

    public function test_release_allows_reminding_again(): void
    {
        Carbon::setTestNow('2026-11-16 08:00');
        $r = $this->submit(['priority' => 'mendesak']);
        $r->forceFill(['reminded_at' => now()])->save();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->post(route('admin.reports.release', $r));
        $this->assertNull($r->fresh()->reminded_at);
    }

    // ------------------------------------------------- WA & laporan ganda

    public function test_contact_reporter_via_whatsapp_button(): void
    {
        $r = $this->submit();
        $this->actingAs($this->head)->get(route('admin.reports.show', $r))->assertDontSee('Hubungi via WA');
        $this->emp->update(['phone' => '0812 1111 2222']);
        $this->actingAs($this->head)->get(route('admin.reports.show', $r))
            ->assertSee('Hubungi via WA')->assertSee('https://wa.me/6281211112222?text=', false);
    }

    public function test_similar_reports_warning(): void
    {
        $team = Team::where('slug', 'it')->first();
        $cat = Category::where('team_id', $team->id)->where('allow_custom', false)->first();
        $loc = Location::create(['name' => 'Ruang Rapat A']);
        $other = $this->user();

        $this->actingAs($other)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => $cat->id, 'location_id' => $loc->id,
            'priority' => 'sedang', 'title' => 'Rahasia pribadi', 'description' => 'x',
        ]);
        $existing = Report::latest('id')->first();

        $res = $this->actingAs($this->emp)->getJson(route('employee.reports.similar', ['team_id' => $team->id, 'category_id' => $cat->id, 'location_id' => $loc->id]))->assertOk();
        $res->assertJsonCount(1)->assertJsonPath('0.number', $existing->report_number)->assertJsonPath('0.mine', false);
        $this->assertStringNotContainsString('Rahasia', $res->getContent()); // judul orang lain tidak dibocorkan
        $this->assertSame($existing->publicUrl(), $res->json('0.url'));

        // Lokasi lain / laporan sudah ditutup -> tidak muncul.
        $this->actingAs($this->emp)->getJson(route('employee.reports.similar', ['team_id' => $team->id, 'category_id' => $cat->id]))->assertJsonCount(0);
        $existing->update(['status' => 'selesai']);
        $this->actingAs($this->emp)->getJson(route('employee.reports.similar', ['team_id' => $team->id, 'category_id' => $cat->id, 'location_id' => $loc->id]))->assertJsonCount(0);
    }

    // --------------------------------------------------------- aksi massal

    public function test_bulk_claim_assign_and_close(): void
    {
        $a = $this->submit(); $b = $this->submit(); $c = $this->submit();

        $this->actingAs($this->staf)->post(route('admin.reports.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'ambil'])
            ->assertSessionHas('success', '2 laporan diambil.');
        $this->assertSame($this->staf->id, $a->fresh()->assigned_to_id);

        // Staf tidak boleh menugaskan.
        $this->actingAs($this->staf)->post(route('admin.reports.bulk'), ['ids' => [$c->id], 'action' => 'tugaskan', 'assigned_to_id' => $this->staf->id])
            ->assertSessionHas('error');
        $this->assertNull($c->fresh()->assigned_to_id);

        $this->actingAs($this->head)->post(route('admin.reports.bulk'), ['ids' => [$c->id], 'action' => 'tugaskan', 'assigned_to_id' => $this->staf->id])
            ->assertSessionHas('success');
        $this->assertSame($this->staf->id, $c->fresh()->assigned_to_id);
        $this->assertSame('diproses', $c->fresh()->status);

        // Tutup massal butuh catatan.
        $this->actingAs($this->staf)->post(route('admin.reports.bulk'), ['ids' => [$a->id, $b->id], 'action' => 'selesai'])->assertSessionHasErrors('bulk_note');
        $this->actingAs($this->staf)->post(route('admin.reports.bulk'), ['ids' => [$a->id, $b->id, $c->id], 'action' => 'selesai', 'bulk_note' => 'Sudah diperbaiki.'])
            ->assertSessionHas('success');
        foreach ([$a, $b, $c] as $r) {
            $this->assertSame('selesai', $r->fresh()->status);
            $this->assertSame('Sudah diperbaiki.', $r->fresh()->follow_up_note);
        }

        // Yang sudah ditutup dilewati.
        $this->actingAs($this->head)->post(route('admin.reports.bulk'), ['ids' => [$a->id], 'action' => 'ditolak', 'bulk_note' => 'x'])->assertSessionHas('error');
        $this->assertSame('selesai', $a->fresh()->status);
    }

    public function test_bulk_skips_reports_of_other_teams_and_assignee_outside_team(): void
    {
        $r = $this->submit();
        $otherHead = $this->user(User::ROLE_KEPALA_REGU, 'sarpras');
        $this->actingAs($otherHead)->post(route('admin.reports.bulk'), ['ids' => [$r->id], 'action' => 'ambil'])->assertSessionHas('error');
        $this->assertNull($r->fresh()->assigned_to_id);

        $outsider = $this->user(User::ROLE_STAF, 'sarpras');
        $this->actingAs($this->head)->post(route('admin.reports.bulk'), ['ids' => [$r->id], 'action' => 'tugaskan', 'assigned_to_id' => $outsider->id])->assertSessionHas('error');
        $this->assertNull($r->fresh()->assigned_to_id);

        $this->actingAs($this->emp)->post(route('admin.reports.bulk'), ['ids' => [$r->id], 'action' => 'ambil'])->assertForbidden();
    }

    public function test_list_page_renders_bulk_bar(): void
    {
        $this->submit();
        $this->actingAs($this->head)->get(route('admin.reports.index'))->assertSee('id="bulk-form"', false)->assertSee('Tugaskan ke…');
        $this->actingAs($this->staf)->get(route('admin.reports.index'))->assertSee('id="bulk-form"', false)->assertDontSee('Tugaskan ke…');
    }
}
