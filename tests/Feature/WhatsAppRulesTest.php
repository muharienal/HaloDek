<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\DailyDigest;
use App\Services\ReportService;
use App\Support\WaRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kabar WhatsApp per peran (Administrasi > WhatsApp) + pilihan pribadi di Profil. */
class WhatsAppRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private User $manajer;
    private User $head;
    private User $staf;
    private User $stafLain;
    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 10:00'); // Senin, jam kerja, sebelum jam ringkasan
        $this->seedTeams();
        $this->super = $this->user(User::ROLE_SUPERADMIN, null, ['phone' => '081200000001']);
        $this->manajer = $this->user(User::ROLE_MANAJER, null, ['phone' => '081200000002']);
        $this->head = $this->user(User::ROLE_KEPALA_REGU, 'it', ['phone' => '081200000003']);
        $this->staf = $this->user(User::ROLE_STAF, 'it', ['phone' => '081200000004']);
        $this->stafLain = $this->user(User::ROLE_STAF, Team::SLUG_SARPRAS, ['phone' => '081200000005']);
        $this->karyawan = $this->user(User::ROLE_KARYAWAN, null, ['phone' => '081200000006']);
        config(['services.whatsapp' => ['driver' => 'fonnte', 'token' => 'tok', 'timeout' => 5,
            'manager_mode' => 'harian', 'digest_at' => '16:00', 'head_priorities' => ['tinggi', 'mendesak']]]);
        $this->fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fake(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    }

    /** @return array<int, string> nomor tujuan yang dikirimi */
    private function targets(?string $contains = null): array
    {
        return collect(Http::recorded())->map(fn ($p) => $p[0])
            ->filter(fn ($r) => str_contains($r->url(), '/send') && ($contains === null || str_contains($r['message'], $contains)))
            ->flatMap(fn ($r) => explode(',', $r['target']))->sort()->values()->all();
    }

    private function rules(array $over): void
    {
        $r = WaRules::all();
        foreach ($over as $role => $events) {
            $r[$role] = array_merge($r[$role], $events);
        }
        config(['services.whatsapp.rules' => $r]);
    }

    private function submit(string $priority = 'sedang', ?User $by = null, string $team = 'it'): Report
    {
        $t = Team::where('slug', $team)->first();
        $this->actingAs($by ?? $this->karyawan)->post(route('employee.reports.store'), [
            'team_id' => $t->id, 'category_id' => Category::where('team_id', $t->id)->value('id'),
            'priority' => $priority, 'title' => 'Printer macet', 'description' => 'Kertas tersangkut',
        ])->assertRedirect();

        return Report::latest('id')->first();
    }

    public function test_defaults_keep_old_behaviour(): void
    {
        $this->submit('sedang');
        $this->assertSame([], $this->targets());

        $this->fake();
        $this->submit('mendesak');
        $this->assertSame(['6281200000003'], $this->targets('LAPORAN MENDESAK MASUK'), 'hanya Kepala Regu IT');
    }

    public function test_new_report_scope_per_role_and_reporter_excluded(): void
    {
        $this->rules([
            User::ROLE_MANAJER => ['baru' => 'semua'],
            User::ROLE_STAF => ['baru' => 'semua'],
            User::ROLE_KEPALA_REGU => ['baru' => 'off'],
        ]);
        $this->submit('rendah');
        // Manajer: semua regu; staf: hanya regu IT (staf Umum tidak); Kepala Regu dimatikan.
        $this->assertSame(['6281200000002', '6281200000004'], $this->targets('LAPORAN BARU MASUK'));

        // Staf IT yang membuat laporan sendiri tidak dikabari laporannya sendiri.
        $this->fake();
        $this->submit('rendah', $this->staf);
        $this->assertSame(['6281200000002'], $this->targets('LAPORAN BARU MASUK'));
    }

    public function test_important_report_without_any_recipient_falls_back_to_managers_then_superadmin(): void
    {
        $this->rules([User::ROLE_KEPALA_REGU => ['baru' => 'off']]);
        $this->submit('tinggi');
        $this->assertSame(['6281200000002'], $this->targets('LAPORAN TINGGI MASUK'));

        // Tanpa Manajer ber-No. WA: ke Superadmin.
        $this->manajer->update(['phone' => null]);
        $this->fake();
        $this->submit('tinggi');
        $this->assertSame(['6281200000001'], $this->targets('LAPORAN TINGGI MASUK'));

        // Laporan biasa tanpa penerima: tidak ada cadangan.
        $this->fake();
        $this->submit('rendah');
        $this->assertSame([], $this->targets());
    }

    public function test_personal_mute_and_inactive_or_no_phone_are_skipped(): void
    {
        $this->rules([User::ROLE_STAF => ['baru' => 'semua']]);
        $this->actingAs($this->staf)->put(route('profile.update'), ['phone' => '081200000004', 'wa_prefs' => 1, 'wa_on' => ['tugas']])->assertSessionHas('success');
        $this->assertContains('baru', $this->staf->fresh()->wa_off);
        $this->user(User::ROLE_STAF, 'it', ['phone' => '081200000099', 'is_active' => false]);
        $this->user(User::ROLE_STAF, 'it', ['phone' => null]);

        $this->fake();
        $this->submit('mendesak');
        $this->assertSame(['6281200000003'], $this->targets('MASUK'));
    }

    public function test_profile_shows_only_enabled_choices_and_keeps_hidden_mutes(): void
    {
        $this->actingAs($this->karyawan)->get(route('profile.edit'))->assertOk()->assertSee('Kabar WhatsApp untuk saya')
            ->assertSee('Status laporan saya')->assertDontSee('Ringkasan harian');

        // Superadmin mematikan "balasan" untuk staf: pilihan hilang, pilihan pribadi lama tetap tersimpan.
        $this->staf->update(['wa_off' => ['balasan']]);
        $this->rules([User::ROLE_STAF => ['balasan' => '0']]);
        $this->actingAs($this->staf)->get(route('profile.edit'))->assertDontSee('Balasan baru');
        $this->actingAs($this->staf)->put(route('profile.update'), ['phone' => '081200000004', 'wa_prefs' => 1, 'wa_on' => ['tugas', 'keberatan', 'laporan_saya']]);
        $this->assertSame(['balasan'], $this->staf->fresh()->wa_off);
        $this->actingAs($this->staf)->put(route('profile.update'), ['phone' => '081200000004', 'wa_prefs' => 1, 'wa_on' => ['tugas', 'keberatan', 'laporan_saya', 'hack']]);
        $this->assertSame(['balasan'], $this->staf->fresh()->wa_off);
    }

    public function test_assignment_message_and_bulk_is_merged(): void
    {
        $r1 = $this->submit('sedang');
        $r2 = $this->submit('rendah');
        $this->fake();

        $this->actingAs($this->head)->patch(route('admin.reports.update', $r1), ['status' => 'menunggu', 'priority' => 'sedang', 'assigned_to_id' => $this->staf->id]);
        $this->assertSame(['6281200000004'], $this->targets('TUGAS BARU UNTUK ANDA'));
        $this->assertSame(['6281200000006'], $this->targets('SEDANG DIPROSES'), 'pelapor tetap dapat kabar diproses');

        // Massal ke orang yang sama: satu pesan berisi daftar.
        $r3 = $this->submit('rendah');
        $this->fake();
        $this->actingAs($this->head)->post(route('admin.reports.bulk'), ['ids' => [$r2->id, $r3->id], 'action' => 'tugaskan', 'assigned_to_id' => $this->staf->id]);
        $this->assertSame(['6281200000004'], $this->targets('2 TUGAS BARU UNTUK ANDA'));

        // Ambil sendiri: tidak ada pesan tugas.
        $r4 = $this->submit('rendah');
        $this->fake();
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r4));
        $this->assertSame([], $this->targets('TUGAS BARU'));
    }

    public function test_replies_go_both_ways_only_when_enabled(): void
    {
        $r = $this->submit('sedang');
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->fake();

        $this->actingAs($this->staf)->post(route('admin.reports.comment', $r), ['message' => 'Sudah saya cek']);
        $this->assertSame([], $this->targets('BALASAN BARU'), 'bawaan: balasan mati');

        $this->rules([User::ROLE_KARYAWAN => ['balasan' => '1'], User::ROLE_STAF => ['balasan' => '1']]);
        $this->actingAs($this->staf)->post(route('admin.reports.comment', $r), ['message' => 'Sudah saya cek']);
        $this->actingAs($this->staf)->post(route('admin.reports.comment', $r), ['message' => 'Catatan rahasia', 'is_internal' => 1]);
        $this->assertSame(['6281200000006'], $this->targets('Sudah saya cek'));
        $this->assertSame([], $this->targets('Catatan rahasia'));

        $this->actingAs($this->karyawan)->post(route('employee.reports.comment', $r), ['message' => 'Masih macet']);
        $this->assertSame(['6281200000004'], $this->targets('Masih macet'));
    }

    public function test_reopen_and_appeal_go_to_head_and_assignee_only(): void
    {
        $r = $this->submit('sedang');
        $this->actingAs($this->staf)->post(route('admin.reports.take', $r));
        $this->actingAs($this->staf)->patch(route('admin.reports.update', $r), ['status' => 'selesai', 'follow_up_note' => 'Beres']);
        $this->user(User::ROLE_STAF, 'it', ['phone' => '081200000077']); // staf IT lain, bukan penanggung jawab
        $this->fake();

        $this->actingAs($this->karyawan)->patch(route('employee.reports.reopen', $r), ['reason' => 'Masih macet lagi']);
        $this->assertSame(['6281200000003', '6281200000004'], $this->targets('BELUM BERES'));

        $r2 = $this->submit('sedang');
        $this->actingAs($this->head)->patch(route('admin.reports.update', $r2), ['status' => 'ditolak', 'priority' => 'sedang', 'follow_up_note' => 'Bukan wewenang']);
        $this->fake();
        $this->actingAs($this->karyawan)->patch(route('employee.reports.appeal', $r2), ['reason' => 'Mohon dicek ulang']);
        $this->assertSame(['6281200000003'], $this->targets('BANDING'));
    }

    public function test_transfer_notifies_target_team(): void
    {
        $umumHead = $this->user(User::ROLE_KEPALA_REGU, Team::SLUG_SARPRAS, ['phone' => '081200000055']);
        $r = $this->submit('tinggi');
        $this->fake();
        $umum = Team::where('slug', Team::SLUG_SARPRAS)->first();
        $this->actingAs($this->super)->post(route('admin.reports.transfer', $r), [
            'team_id' => $umum->id, 'category_id' => Category::where('team_id', $umum->id)->value('id'), 'reason' => 'Salah regu',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['6281200000055'], $this->targets('DIALIHKAN KE REGU'));
        $this->assertNotNull($umumHead);
    }

    public function test_overdue_reminder_follows_rules(): void
    {
        $this->rules([User::ROLE_STAF => ['lewat_batas' => '1'], User::ROLE_MANAJER => ['lewat_batas' => '1']]);
        Carbon::setTestNow('2026-11-16 08:00');
        $r = $this->submit('tinggi');
        Carbon::setTestNow('2026-11-16 10:30');
        $this->fake();
        $this->artisan('halodek:pengingat')->assertSuccessful();
        // Kepala Regu IT + Staf IT + Manajer; staf regu lain tidak.
        $this->assertSame(['6281200000002', '6281200000003', '6281200000004'], $this->targets($r->report_number));
    }

    public function test_daily_digest_global_and_per_team_for_heads(): void
    {
        $this->rules([User::ROLE_KEPALA_REGU => ['ringkasan' => '1'], User::ROLE_SUPERADMIN => ['ringkasan' => '1']]);
        $this->submit('sedang');
        $this->submit('sedang', null, Team::SLUG_SARPRAS);
        $this->fake();

        Carbon::setTestNow('2026-11-16 16:10');
        $this->assertTrue(app(DailyDigest::class)->due());
        $this->actingAs($this->karyawan)->get(route('dashboard'));

        $this->assertSame(['6281200000001', '6281200000002'], $this->targets('Laporan baru: 2'), 'rekap semua regu');
        $this->assertSame(['6281200000003'], $this->targets('Ringkasan HaloDek* · Regu IT'));
        $sent = collect(Http::recorded())->map(fn ($p) => $p[0])->first(fn ($r) => in_array('6281200000003', explode(',', $r['target']), true));
        $this->assertStringContainsString('Laporan baru: 1', $sent['message']);
    }

    public function test_admin_saves_rules_from_menu(): void
    {
        $this->actingAs($this->super)->put(route('admin.wa.update'), [
            'driver' => 'fonnte', 'token' => 'tok-baru', 'digest_at' => '16:00', 'head_priorities' => ['mendesak'],
            'rules' => [
                'staf' => ['baru' => 'penting', 'tugas' => '1', 'keberatan' => '0', 'lewat_batas' => '1', 'laporan_saya' => '1', 'balasan' => '0'],
                'kepala_regu' => ['baru' => 'penting', 'tugas' => '1', 'keberatan' => '1', 'lewat_batas' => '1', 'ringkasan' => '0', 'laporan_saya' => '1', 'balasan' => '0'],
                'karyawan' => ['laporan_saya' => '0', 'balasan' => '1'],
                'manajer' => ['baru' => 'bohong'],
                'hantu' => ['baru' => 'semua'],
            ],
        ])->assertSessionHas('success');

        $this->assertSame('penting', WaRules::value('staf', 'baru'));
        $this->assertSame('0', WaRules::value('karyawan', 'laporan_saya'));
        $this->assertSame('off', WaRules::value('manajer', 'baru'), 'nilai tak dikenal = mati');
        $this->assertArrayNotHasKey('hantu', WaRules::all());

        $page = $this->actingAs($this->super)->get(route('admin.wa.edit'))->assertOk()->assertSee('Siapa menerima kabar apa')->getContent();
        $this->assertStringContainsString('name="rules[staf][baru]"', $page);
        $this->assertStringNotContainsString('tok-baru', $page);

        // Prioritas penting kini hanya "mendesak".
        $this->fake();
        $this->submit('tinggi');
        $this->assertSame([], $this->targets('MASUK'));
        $this->submit('mendesak');
        $this->assertSame(['6281200000003', '6281200000004'], $this->targets('LAPORAN MENDESAK MASUK'));
    }
}
