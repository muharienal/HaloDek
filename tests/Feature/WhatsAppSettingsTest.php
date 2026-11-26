<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsAppNotifier;
use App\Support\Settings;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Administrasi > WhatsApp: pengaturan Fonnte dari aplikasi, bukan .env. */
class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'FonnteTokenRahasia-9xYz';

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        $this->super = $this->user(User::ROLE_SUPERADMIN);
        config(['services.whatsapp' => ['driver' => 'off', 'token' => '', 'timeout' => 5,
            'manager_mode' => 'harian', 'digest_at' => '16:00', 'head_priorities' => ['tinggi', 'mendesak']]]);
    }

    private function save(array $over = [])
    {
        return $this->actingAs($this->super)->put(route('admin.wa.update'), $over + [
            'driver' => 'fonnte', 'token' => self::TOKEN, 'digest_at' => '15:30', 'head_priorities' => ['mendesak'],
        ]);
    }

    public function test_only_superadmin_can_open_and_save(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAJER))->get(route('admin.wa.edit'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_KEPALA_REGU, 'it'))->put(route('admin.wa.update'), ['driver' => 'off'])->assertForbidden();
        $this->actingAs($this->super)->get(route('admin.wa.edit'))->assertOk()->assertSee('Siapa menerima kabar apa')->assertSee('Belum pernah disimpan')
            ->assertDontSee('Nomor tambahan');
        $this->actingAs($this->super)->get(route('admin.users.index'))->assertSee(route('admin.wa.edit'), false);
    }

    public function test_saves_encrypted_token_and_overrides_config(): void
    {
        $this->save()->assertRedirect(route('admin.wa.edit', ['cek' => 1]))->assertSessionHas('success');

        $cfg = config('services.whatsapp');
        $this->assertSame(['fonnte', self::TOKEN, '15:30', ['mendesak']],
            [$cfg['driver'], $cfg['token'], $cfg['digest_at'], $cfg['head_priorities']]);
        $this->assertArrayNotHasKey('to', $cfg);

        // Tersimpan terenkripsi, tidak polos di database.
        $raw = DB::table('settings')->where('key', 'wa.token')->value('value');
        $this->assertNotSame(self::TOKEN, $raw);
        $this->assertStringNotContainsString(self::TOKEN, $raw);

        // Berlaku setelah aplikasi dimulai ulang (request berikutnya): .env kalah.
        config(['services.whatsapp.token' => 'dari-env', 'services.whatsapp.driver' => 'off']);
        Settings::forget();
        WhatsAppSettings::apply();
        $this->assertSame([self::TOKEN, 'fonnte'], [config('services.whatsapp.token'), config('services.whatsapp.driver')]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.whatsapp']);
        $this->assertStringNotContainsString(self::TOKEN, (string) DB::table('audit_logs')->where('action', 'settings.whatsapp')->value('description'));
    }

    public function test_token_is_never_rendered_and_blank_keeps_it(): void
    {
        $this->save();
        $html = $this->actingAs($this->super)->get(route('admin.wa.edit'))->assertOk()->assertSee('Token tersimpan (berakhiran')->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $html);

        // Kosong = token lama tetap.
        $this->save(['token' => ''])->assertSessionHas('success');
        $this->assertSame(self::TOKEN, config('services.whatsapp.token'));

        // Hapus token saat masih Aktif -> ditolak; saat Mati -> boleh.
        $this->save(['token' => '', 'clear_token' => 1])->assertSessionHasErrors('token');
        $this->assertSame(self::TOKEN, config('services.whatsapp.token'));
        $this->save(['token' => '', 'clear_token' => 1, 'driver' => 'off'])->assertSessionHas('success');
        $this->assertSame('', config('services.whatsapp.token'));
        $this->assertNull(DB::table('settings')->where('key', 'wa.token')->value('value'));
    }

    public function test_token_from_env_is_kept_when_first_saved(): void
    {
        config(['services.whatsapp.token' => 'token-lama-env', 'services.whatsapp.driver' => 'fonnte']);
        $this->save(['token' => ''])->assertSessionHas('success');
        Settings::forget();
        WhatsAppSettings::apply();
        $this->assertSame('token-lama-env', config('services.whatsapp.token'));
    }

    public function test_validation_errors(): void
    {
        $this->save(['token' => '', 'driver' => 'fonnte'])->assertSessionHasErrors('token');
        $this->save(['digest_at' => '25:00'])->assertSessionHasErrors('digest_at');
        $this->save(['driver' => 'wablas'])->assertSessionHasErrors('driver');
        $this->save(['head_priorities' => ['kiamat']])->assertSessionHasErrors('head_priorities.0');
        $this->assertDatabaseMissing('settings', ['key' => 'wa.driver']);

        $this->save(['digest_at' => ''])->assertSessionHasErrors('digest_at');

        // Kosong boleh: tidak ada prioritas penting.
        $this->save(['head_priorities' => null])->assertSessionHasNoErrors();
        $this->assertSame([], config('services.whatsapp.head_priorities'));
    }

    public function test_sending_uses_token_from_menu(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
        $this->save();
        $this->assertSame(2, app(WhatsAppNotifier::class)->sendNow(['6281234567890', '6281311112222', '6281234567890'], 'Halo'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.fonnte.com/send') && $r->header('Authorization')[0] === self::TOKEN);

        // Status Mati: tidak ada yang dikirim.
        $this->save(['driver' => 'off']);
        Http::fake();
        $this->assertSame(0, app(WhatsAppNotifier::class)->sendNow(['6281234567890'], 'Halo'));
    }

    public function test_device_check_and_test_message_from_page(): void
    {
        Http::fake([
            'api.fonnte.com/device' => Http::response(['status' => true, 'device' => '62899', 'device_status' => 'connect', 'quota' => '100']),
            'api.fonnte.com/send' => Http::response(['status' => true]),
        ]);
        $this->save();
        $this->actingAs($this->super)->get(route('admin.wa.edit', ['cek' => 1]))->assertOk()->assertSee('Fonnte terhubung')->assertSee('Kirim pesan uji ke');
        $this->actingAs($this->super)->post(route('admin.status.wa-test'))->assertSessionHas('error'); // superadmin belum punya No. WA
        $this->actingAs($this->super)->from(route('admin.wa.edit'))->post(route('admin.status.wa-test'), ['to' => '0857-1111-2222'])
            ->assertRedirect(route('admin.wa.edit'))->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/send') && $r['target'] === '6285711112222' && str_contains($r['message'], 'Pesan uji HaloDek'));
        $this->actingAs($this->super)->post(route('admin.status.wa-test'), ['to' => '123'])->assertSessionHas('error');
    }

    public function test_status_page_without_token_points_to_menu(): void
    {
        config(['services.whatsapp.driver' => 'fonnte', 'services.whatsapp.token' => '', 'services.inventaris.url' => '']);
        $this->actingAs($this->super)->get(route('admin.status.index'))->assertOk()
            ->assertSee('Token Fonnte belum diisi')->assertSee('Atur WhatsApp')->assertDontSee('WA_TOKEN');
    }
}
