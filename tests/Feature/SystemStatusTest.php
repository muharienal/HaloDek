<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_sees_status_page(): void
    {
        $this->seedTeams();
        $this->actingAs($this->user(User::ROLE_MANAJER))->get(route('admin.status.index'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_KEPALA_REGU, 'it'))->get(route('admin.status.index'))->assertForbidden();

        config(['services.inventaris.url' => '', 'services.whatsapp.driver' => 'off']);
        $this->actingAs($this->user(User::ROLE_SUPERADMIN))->get(route('admin.status.index'))
            ->assertOk()->assertSee('Koneksi database')->assertSee('Tabel rusak')->assertSee('Inventaris')->assertSee('Error terakhir');
    }

    public function test_reports_connection_problems_and_recent_errors(): void
    {
        $this->seedTeams();
        config([
            'services.inventaris.url' => 'http://inv.test', 'services.inventaris.token' => 'token-rahasia-0123456789',
            'services.whatsapp' => ['driver' => 'fonnte', 'token' => 'tok', 'timeout' => 5,
                'manager_mode' => 'harian', 'digest_at' => '16:00', 'head_priorities' => ['tinggi']],
        ]);
        Http::fake([
            'inv.test/*' => Http::response(['message' => 'Token API tidak valid.'], 401),
            'api.fonnte.com/device' => Http::response(['status' => true, 'device' => '62899', 'device_status' => 'disconnect', 'quota' => '900']),
            'api.fonnte.com/send' => Http::response(['status' => true]),
        ]);

        $log = storage_path('logs/laravel.log');
        $old = File::exists($log) ? File::get($log) : null;
        // Waktu paling baru agar tetap termasuk 10 galat terakhir walau log tes sudah berisi galat lain.
        File::append($log, '['.now()->addYear()->format('Y-m-d H:i:s').'] local.ERROR: SQLSTATE[42S02]: Table \'halodek.report_activities\' doesn\'t exist in engine {"userId":1,"exception":"[object] ..."}'."\n");

        try {
            $super = $this->user(User::ROLE_SUPERADMIN, null, ['phone' => '081233334444']);
            $this->actingAs($super)->get(route('admin.status.index'))
                ->assertOk()->assertSee('Token ditolak')->assertSee('TERPUTUS')->assertSee("doesn't exist in engine")->assertDontSee('userId');

            $this->actingAs($super)->post(route('admin.status.wa-test'))->assertSessionHas('success');
            Http::assertSent(fn ($r) => str_contains($r->url(), '/send') && $r['target'] === '6281233334444' && str_contains($r['message'], 'Pesan uji HaloDek'));
        } finally {
            $old === null ? File::delete($log) : File::put($log, $old);
        }
    }

    public function test_legacy_inventory_tables_can_be_dropped_with_backup(): void
    {
        $this->assertTrue(Schema::hasTable('inventory_items'));
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->artisan('halodek:hapus-tabel-lama', ['--paksa' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('inventory_items'));
        $this->assertFalse(Schema::hasTable('locations'));
        $this->assertCount(1, \Illuminate\Support\Facades\Storage::disk('local')->files('backup'));

        $this->artisan('halodek:hapus-tabel-lama', ['--paksa' => true])->expectsOutputToContain('Tidak ada tabel')->assertSuccessful();
    }
}
