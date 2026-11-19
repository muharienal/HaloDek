<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integrasi dengan aplikasi Inventaris Umum & IT (lewat API):
 * lokasi & barang di form lapor, QR barang, salinan data di laporan, dan kabar laporan ke Inventaris.
 */
class QrScanTest extends TestCase
{
    use RefreshDatabase;

    private Team $it;
    private User $emp;
    private User $holder;
    private User $head;
    private array $laptop;
    private \ArrayObject $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
        $this->it = Team::where('slug', 'it')->first();
        $this->emp = $this->user(User::ROLE_KARYAWAN, null, ['nik' => '81001']);
        $this->holder = $this->user(User::ROLE_KARYAWAN, null, ['nik' => '81002']);
        $this->head = $this->user(User::ROLE_KEPALA_REGU, 'it');

        $this->laptop = $this->fakeAsset(100, 'it', ['code' => 'AST-0100', 'name' => 'Lenovo ThinkPad', 'label' => 'AST-0100 · Lenovo ThinkPad',
            'holder' => ['nik' => '81002', 'name' => 'Pemegang']]);
        $this->events = $this->fakeInventaris([
            $this->laptop,
            $this->fakeAsset(101, 'it', ['code' => 'AST-0101', 'name' => 'Laptop Saya', 'holder' => ['nik' => '81001', 'name' => 'Saya']]),
            $this->fakeAsset(102, 'sarpras', ['code' => 'SP-AC-1', 'name' => 'AC Ruang Rapat', 'category' => 'AC']),
            $this->fakeAsset(103, 'it', ['code' => 'AST-0103', 'name' => 'Printer lama', 'active' => false, 'status' => 'nonaktif']),
        ]);
    }

    private function komputer(): int
    {
        return Category::where('team_id', $this->it->id)->where('name', 'Komputer & Laptop')->value('id');
    }

    public function test_guest_scan_goes_to_login_then_back(): void
    {
        $this->get('/q/100')->assertRedirect(route('login'));
        $this->post('/login', ['nik' => $this->emp->nik])->assertRedirect(url('/q/100'));
    }

    public function test_form_lists_locations_and_own_items_from_inventaris(): void
    {
        $this->actingAs($this->emp)->get(route('employee.reports.create'))
            ->assertOk()->assertSee('Ruang Rapat A (Gedung A, Lt. 2)');

        // Milik sendiri saja; barang orang lain tidak muncul tanpa scan.
        $this->actingAs($this->emp)->getJson(route('employee.reports.items', ['team_id' => $this->it->id]))
            ->assertOk()->assertJsonFragment(['id' => 101])->assertJsonMissing(['id' => 100]);

        // Barang bersama di lokasi terpilih (unit sarpras).
        $sarpras = Team::where('slug', 'sarpras')->first();
        $this->actingAs($this->emp)->getJson(route('employee.reports.items', ['team_id' => $sarpras->id, 'location_id' => 1]))
            ->assertJsonFragment(['id' => 102]);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/assets') && str_contains($r->url(), 'holder_nik=81001') && str_contains($r->url(), 'unit=it'));
    }

    public function test_employee_scan_prefills_form_and_can_report_item_held_by_someone_else(): void
    {
        $komputer = $this->komputer();

        $this->actingAs($this->emp)->get(route('scan', 100))
            ->assertRedirect(route('employee.reports.create', ['regu' => 'it', 'kategori' => $komputer, 'lokasi' => 1, 'barang' => 100]));
        $this->assertSame(100, session('qr_item'));

        $this->actingAs($this->emp)->withSession(['qr_item' => 100])
            ->get(route('employee.reports.create', ['regu' => 'it', 'kategori' => $komputer, 'lokasi' => 1, 'barang' => 100]))
            ->assertSee('Melaporkan barang AST-0100')->assertSee('var oldAsset = 100', false);

        // Dipegang orang lain, tapi karena dipindai boleh dipilih.
        $this->actingAs($this->emp)->withSession(['qr_item' => 100])->getJson(route('employee.reports.items', ['team_id' => $this->it->id, 'location_id' => 1]))
            ->assertJsonFragment(['id' => 100]);

        $this->actingAs($this->emp)->withSession(['qr_item' => 100])->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $komputer, 'location_id' => 1,
            'asset_id' => 100, 'priority' => 'sedang', 'description' => 'Layar berkedip.',
        ])->assertRedirect();

        $r = Report::latest('id')->first();
        $this->assertSame(100, (int) $r->asset_id);
        $this->assertSame('AST-0100', $r->asset_code);
        $this->assertSame('Lenovo ThinkPad', $r->asset_name);
        $this->assertSame('Ruang Rapat A (Gedung A, Lt. 2)', $r->location_name);
        $this->assertSame('Komputer & Laptop – Ruang Rapat A', $r->title);
        $this->assertNull(session('qr_item'));

        // Snapshot tampil walau Inventaris mati.
        $this->inventarisDown = true;
        $this->actingAs($this->emp)->get(route('employee.reports.show', $r))->assertSee('AST-0100 · Lenovo ThinkPad')->assertSee('Ruang Rapat A (Gedung A, Lt. 2)');
    }

    public function test_report_events_are_pushed_to_inventaris_on_create_and_status_change(): void
    {
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $this->komputer(), 'asset_id' => 101, 'priority' => 'sedang', 'description' => 'Keyboard rusak.',
        ])->assertRedirect();
        $r = Report::latest('id')->first();
        $this->assertSame(1, (int) $r->inventory_location_id, 'lokasi diambil dari lokasi barang');

        $this->assertCount(1, $this->events);
        $this->assertSame(['report_id' => $r->id, 'asset_id' => 101, 'status' => 'menunggu'], array_intersect_key($this->events[0], array_flip(['report_id', 'asset_id', 'status'])));

        $this->actingAs($this->head)->post(route('admin.reports.take', $r))->assertRedirect();
        $this->assertCount(2, $this->events);
        $this->assertSame('diproses', $this->events[1]['status']);

        // Laporan tanpa barang tidak dikirim.
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $this->komputer(), 'priority' => 'sedang', 'title' => 'Tanpa barang', 'description' => 'x',
        ]);
        $this->assertCount(2, $this->events);
    }

    public function test_without_scanning_other_peoples_item_is_still_refused(): void
    {
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $this->komputer(),
            'asset_id' => 100, 'priority' => 'sedang', 'title' => 'x', 'description' => 'x',
        ])->assertSessionHasErrors('asset_id');

        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $this->komputer(), 'location_id' => 999,
            'priority' => 'sedang', 'title' => 'x', 'description' => 'x',
        ])->assertSessionHasErrors('location_id');
    }

    public function test_admin_scan_opens_inventaris_unless_reporting(): void
    {
        $this->actingAs($this->head)->get(route('scan', 100))->assertRedirect('http://inventaris.test/aset/100');
        $this->actingAs($this->head)->get(route('scan', ['item' => 100, 'lapor' => 1]))->assertRedirect();
        $this->assertSame(100, session('qr_item'));

        // Kepala regu lain = diperlakukan seperti pelapor.
        $otherHead = $this->user(User::ROLE_KEPALA_REGU, 'sarpras');
        $this->actingAs($otherHead)->get(route('scan', 100))->assertRedirect(route('employee.reports.create', ['regu' => 'it', 'kategori' => $this->komputer(), 'lokasi' => 1, 'barang' => 100]));
    }

    public function test_inactive_unknown_item_and_inventaris_down(): void
    {
        $this->actingAs($this->emp)->get(route('scan', 103))->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->actingAs($this->emp)->get('/q/99999')->assertRedirect(route('employee.reports.create'))->assertSessionHas('error');

        // Inventaris mati: form tetap bisa dipakai, tanpa pilihan lokasi.
        $this->inventarisDown = true;
        \Illuminate\Support\Facades\Cache::forget('inventaris:locations');
        $this->actingAs($this->emp)->get(route('employee.reports.create'))->assertOk()->assertDontSee('Ruang Rapat A');
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $this->komputer(), 'priority' => 'sedang', 'description' => 'Internet lambat.',
        ])->assertRedirect();
        $this->assertSame('Komputer & Laptop', Report::latest('id')->value('title'));
    }

    public function test_not_configured_means_no_calls(): void
    {
        config(['services.inventaris.url' => '', 'services.inventaris.token' => '']);
        Http::fake();
        $this->actingAs($this->emp)->get(route('employee.reports.create'))->assertOk();
        $this->actingAs($this->head)->get(route('dashboard'))->assertOk()->assertDontSee('inventaris.test');
        Http::assertNothingSent();
    }

    public function test_admin_detail_links_to_inventaris_and_dashboard_lists_frequent_items(): void
    {
        $this->actingAs($this->emp)->post(route('employee.reports.store'), [
            'team_id' => $this->it->id, 'category_id' => $this->komputer(), 'asset_id' => 101, 'priority' => 'sedang', 'description' => 'x',
        ]);
        $r = Report::latest('id')->first();
        $this->actingAs($this->head)->get(route('admin.reports.show', $r))
            ->assertSee('href="http://inventaris.test/aset/101"', false)->assertSee('AST-0101 · Laptop Saya');
        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertSee('Barang Paling Sering Dilaporkan')->assertSee('AST-0101')->assertDontSee('Buka Inventaris');
    }

    public function test_no_inline_event_handlers_blocked_by_csp(): void
    {
        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $f) {
            $this->assertDoesNotMatchRegularExpression('/\son(click|load|change|submit|input)=/', file_get_contents($f), $f);
        }
        $super = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($super)->get(route('admin.monthly.index'))->assertSee('data-autosubmit', false)->assertDontSee('onchange=', false);
    }

    public function test_wording_beranda_peran_log_aktivitas(): void
    {
        $super = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($super)->get(route('dashboard'))->assertSee('>Beranda</a>', false)->assertDontSee('>Dashboard<', false)->assertSee('Log Aktivitas');
        $this->actingAs($super)->get(route('admin.users.index'))->assertSee('<th>Peran</th>', false);
        $this->actingAs($super)->get(route('admin.audit.index'))->assertSee('Log Aktivitas');
    }
}
