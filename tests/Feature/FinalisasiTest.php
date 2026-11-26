<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsAppNotifier;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Finalisasi 26 Nov 2026: laporan ganda, WA sekaligus, tautan cek aset, format angka. */
class FinalisasiTest extends TestCase
{
    use RefreshDatabase;

    private Team $it;
    private User $emp;
    private User $emp2;
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 10:00');
        $this->seedTeams();
        Storage::fake('local');
        $this->it = Team::where('slug', 'it')->first();
        $this->emp = $this->user(User::ROLE_KARYAWAN, null, ['nik' => '81001']);
        $this->emp2 = $this->user(User::ROLE_KARYAWAN, null, ['nik' => '81002']);
        $this->head = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $this->fakeInventaris([
            $this->fakeAsset(100, 'it', ['code' => 'AST-0100', 'name' => 'Lenovo', 'location' => ['id' => 1, 'name' => 'Ruang Rapat A', 'label' => 'Ruang Rapat A'],
                'check_url' => 'http://inventaris.test/cek/100/abcdefabcdefabcdef12']),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(User $by, array $over = [])
    {
        return $this->actingAs($by)->post(route('employee.reports.store'), $over + [
            'team_id' => $this->it->id, 'category_id' => Category::where('team_id', $this->it->id)->where('name', 'Komputer & Laptop')->value('id'),
            'location_id' => 1, 'asset_id' => 100, 'priority' => 'sedang', 'description' => 'Layar berkedip',
        ]);
    }

    public function test_second_report_for_same_asset_needs_confirmation(): void
    {
        $this->report($this->emp)->assertRedirect();
        $first = Report::sole();

        // Orang lain melapor barang yang sama: diminta konfirmasi, belum tersimpan.
        $this->report($this->emp2, ['description' => 'Keyboard macet'])->assertRedirect()->assertSessionHas('duplicates');
        $this->assertSame(1, Report::count());
        $this->actingAs($this->emp2)->from(route('employee.reports.create'))->followingRedirects()->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => Category::where('team_id', $this->it->id)->where('name', 'Komputer & Laptop')->value('id'),
            'location_id' => 1, 'asset_id' => 100, 'priority' => 'sedang', 'description' => 'Keyboard macet',
        ])->assertOk()->assertSee('Barang ini masih punya laporan aktif')->assertSee($first->report_number)->assertSee('name="confirm_duplicate"', false);

        $this->report($this->emp2, ['description' => 'Keyboard macet', 'confirm_duplicate' => 1])->assertRedirect();
        $this->assertSame(2, Report::count());

        // Cek "laporan serupa" cukup dengan barangnya (alur pindai QR, kategori belum dipilih).
        $this->actingAs($this->emp2)->getJson(route('employee.reports.similar', ['team_id' => $this->it->id, 'asset_id' => 100]))
            ->assertOk()->assertJsonCount(2);
    }

    public function test_merge_duplicate_closes_report_and_informs_reporter(): void
    {
        config(['services.whatsapp' => ['driver' => 'fonnte', 'token' => 'tok', 'timeout' => 5, 'head_priorities' => ['tinggi', 'mendesak']]]);
        $this->emp2->update(['phone' => '081255556666']);
        $this->report($this->emp);
        $this->report($this->emp2, ['description' => 'Layar juga berkedip', 'confirm_duplicate' => 1]);
        [$master, $dup] = [Report::orderBy('id')->first(), Report::orderByDesc('id')->first()];

        $page = $this->actingAs($this->head)->get(route('admin.reports.show', $dup))->assertOk()->assertSee('Gabungkan (Laporan Ganda)');
        $page->assertSee('href="http://inventaris.test/cek/100/abcdefabcdefabcdef12"', false); // tautan cek aset publik

        $this->actingAs($this->head)->post(route('admin.reports.merge', $dup), ['master_id' => $master->id])
            ->assertRedirect(route('admin.reports.show', $master));
        $dup->refresh();
        $this->assertSame(['ditolak', $master->id], [$dup->status, $dup->duplicate_of_id]);
        $this->assertFalse($dup->canStillAppeal());
        $this->assertDatabaseHas('report_activities', ['report_id' => $master->id, 'type' => 'merged']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.fonnte.com/send') && $r['target'] === '6281255556666'
            && str_contains($r['message'], 'LAPORAN ANDA DIGABUNG') && str_contains($r['message'], $master->publicUrl()));

        // Pelapor melihat tautan ke laporan utama, tanpa tombol banding.
        $this->actingAs($this->emp2)->get(route('employee.reports.show', $dup))->assertSee($master->report_number)->assertDontSee('Ajukan Banding');
        // Tidak bisa digabung dua kali / ke laporan yang ditutup.
        $this->actingAs($this->head)->post(route('admin.reports.merge', $master), ['master_id' => $dup->id])->assertSessionHas('error');

        // Ternyata bukan ganda: dibuka lagi oleh petugas -> tanda laporan ganda hilang.
        $this->actingAs($this->head)->patch(route('admin.reports.update', $dup), ['status' => 'menunggu', 'priority' => 'sedang']);
        $this->assertSame(['menunggu', null], [$dup->fresh()->status, $dup->fresh()->duplicate_of_id]);
    }

    public function test_lists_show_description_snippet(): void
    {
        $this->report($this->emp, ['description' => 'Kipas laptop berisik sekali sejak kemarin']);
        $this->actingAs($this->head)->get(route('admin.reports.index'))->assertSee('Kipas laptop berisik sekali');
        $this->actingAs($this->head)->get(route('dashboard'))->assertSee('Kipas laptop berisik sekali');
        $this->actingAs($this->emp)->get(route('employee.reports.index'))->assertSee('Kipas laptop berisik sekali');
        $this->actingAs($this->head)->get(route('admin.reports.index', ['q' => 'berisik']))->assertSee(Report::sole()->report_number);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->head->id]);
        $this->assertStringContainsString('Kipas laptop', (string) \DB::table('user_notifications')->where('user_id', $this->head->id)->value('message'));
    }

    public function test_whatsapp_sends_one_request_for_many_numbers_and_detects_rejection(): void
    {
        config(['services.whatsapp' => ['driver' => 'fonnte', 'token' => 'tok', 'timeout' => 5]]);
        Http::swap(new \Illuminate\Http\Client\Factory()); // lepas tiruan Inventaris dari setUp
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
        $this->assertSame(3, app(WhatsAppNotifier::class)->sendNow(['6281100000001', '6281100000002', '6281100000003', '6281100000001'], 'Halo'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['target'] === '6281100000001,6281100000002,6281100000003');

        // Fonnte menjawab HTTP 200 tetapi status false (mis. perangkat terputus): dihitung gagal.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'device disconnected'])]);
        $this->assertSame(0, app(WhatsAppNotifier::class)->sendNow(['6281100000001'], 'Halo'));
    }

    public function test_number_format_and_dashboard_label(): void
    {
        $this->assertSame(['51,7', '12', '-', '1.234,5 jam kerja'], [Num::dec(51.7), Num::dec(12.0), Num::dec(null), Num::workHours(1234.5)]);
        $this->actingAs($this->head)->get(route('dashboard'))->assertSee('Rata-rata Selesai (90 hari)');
    }
}
