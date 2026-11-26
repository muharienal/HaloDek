<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReporterWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function fonnte(): void
    {
        config(['services.whatsapp' => ['driver' => 'fonnte', 'to' => '', 'token' => 'tok', 'timeout' => 5]]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
    }

    private function reportBy(User $emp): Report
    {
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($emp)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => 'Printer rusak', 'description' => 'Tidak bisa cetak',
        ])->assertRedirect();

        return Report::firstOrFail();
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('6281234567890', Phone::normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', Phone::normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', Phone::normalize('81234567890'));
        $this->assertSame('6281234567890', Phone::normalize('+62 0812 3456 7890'));
        $this->assertNull(Phone::normalize('021 555 1234'));
        $this->assertNull(Phone::normalize('abc'));
        $this->assertNull(Phone::normalize('0812'));
        $this->assertSame('0812-3456-7890', Phone::pretty('6281234567890'));
    }

    public function test_prompt_shows_once_per_login_for_karyawan_without_number(): void
    {
        $this->seedTeams();
        config(['services.whatsapp.driver' => 'log']); // ajakan isi nomor hanya muncul bila WhatsApp menyala
        $emp = $this->user();

        $this->actingAs($emp)->get(route('dashboard'))->assertOk()->assertSee('id="wa-modal"', false)->assertSee('data-auto-open', false)
            ->assertSee('saat laporan Anda diproses, selesai, atau ditolak');
        // WhatsApp dimatikan: tidak ada ajakan sama sekali.
        config(['services.whatsapp.driver' => 'off']);
        $this->flushSession();
        $this->actingAs($emp)->get(route('dashboard'))->assertOk()->assertDontSee('data-auto-open', false)->assertDontSee('class="wa-chip"', false);
        config(['services.whatsapp.driver' => 'log']);
        $this->flushSession();
        $this->actingAs($emp)->get(route('dashboard'))->assertSee('data-auto-open', false);
        // halaman berikutnya di sesi yang sama tidak membukanya otomatis lagi (badge di header tetap ada)
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-auto-open', false)->assertSee('class="wa-chip"', false);

        // login baru (sesi baru) -> muncul lagi
        $this->post(route('logout'));
        $this->post(route('login'), ['mode' => 'karyawan', 'nik' => $emp->nik])->assertRedirect();
        $this->get(route('dashboard'))->assertSee('data-auto-open', false);
    }

    public function test_prompt_not_shown_when_number_saved_or_for_admin(): void
    {
        $this->seedTeams();
        $this->actingAs($this->user(User::ROLE_KARYAWAN, null, ['phone' => '6281234567890']))->get(route('dashboard'))->assertDontSee('id="wa-modal"', false);
        $this->actingAs($this->user(User::ROLE_STAF, 'it'))->get(route('dashboard'))->assertDontSee('id="wa-modal"', false);
    }

    public function test_save_invalid_and_remove_number(): void
    {
        $this->seedTeams();
        $emp = $this->user();

        $this->actingAs($emp)->put(route('profile.whatsapp'), ['phone' => '0812 3456 7890'])->assertSessionHas('success');
        $this->assertSame('6281234567890', $emp->fresh()->phone);

        $this->put(route('profile.whatsapp'), ['phone' => '12345'])->assertSessionHasErrors('phone');
        $this->assertSame('6281234567890', $emp->fresh()->phone);

        $this->put(route('profile.whatsapp'), ['phone' => ''])->assertSessionHas('success');
        $this->assertNull($emp->fresh()->phone);
    }

    public function test_reporter_gets_wa_when_claimed_and_finished_with_link(): void
    {
        $this->seedTeams();
        $this->fonnte();
        $emp = $this->user(User::ROLE_KARYAWAN, null, ['name' => 'Budi Santoso', 'phone' => '6281234567890']);
        $report = $this->reportBy($emp);
        $staf = $this->user(User::ROLE_STAF, 'it', ['name' => 'Tari Wulan']);

        $this->actingAs($staf)->post(route('admin.reports.take', $report))->assertSessionHas('success');
        Http::assertSent(fn ($r) => $r['target'] === '6281234567890'
            && str_contains($r['message'], 'SEDANG DIPROSES')
            && str_contains($r['message'], 'Halo Budi')
            && str_contains($r['message'], 'Penanggung jawab: Tari Wulan')
            && str_contains($r['message'], '/p/'));

        $this->patch(route('admin.reports.update', $report), ['status' => 'selesai', 'follow_up_note' => 'Kabel sudah diganti'])->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => str_contains($r['message'], 'SUDAH SELESAI') && str_contains($r['message'], 'Catatan penyelesaian: Kabel sudah diganti'));
        // (kalimat 'diproses' bisa tercatat ulang di tes karena aplikasi dipakai ulang antar-request; di produksi tiap request proses baru)
    }

    public function test_no_wa_without_number_or_when_driver_off(): void
    {
        $this->seedTeams();
        $this->fonnte();
        $emp = $this->user();                 // tanpa nomor
        $report = $this->reportBy($emp);
        $staf = $this->user(User::ROLE_STAF, 'it');
        $this->actingAs($staf)->post(route('admin.reports.take', $report));
        Http::assertNothingSent();

        $emp->update(['phone' => '6281234567890']);
        config(['services.whatsapp.driver' => 'off']);
        $this->patch(route('admin.reports.update', $report), ['status' => 'selesai', 'follow_up_note' => 'ok']);
        Http::assertNothingSent();
    }

    public function test_single_number_everywhere_auto_62_and_badge_after_dismiss(): void
    {
        $this->seedTeams();
        config(['services.whatsapp.driver' => 'log']); // ajakan isi nomor hanya muncul bila WhatsApp menyala
        $emp = $this->user();

        // Profil: hanya satu kolom nomor, tanpa kartu "Cara Masuk".
        $page = $this->actingAs($emp)->get(route('profile.edit'))->assertOk();
        $page->assertSee('name="phone"', false)->assertDontSee('name="whatsapp"', false)->assertDontSee('Cara Masuk');

        // Badge di header muncul selama belum ada nomor, termasuk di halaman berikutnya (setelah popup ditutup).
        $this->get(route('dashboard'))->assertSee('class="wa-chip"', false);
        $this->get(route('profile.edit'))->assertSee('class="wa-chip"', false)->assertSee('id="wa-modal"', false);

        // Simpan lewat profil: otomatis 62.
        $this->put(route('profile.update'), ['phone' => '0812-3456-7890'])->assertSessionHasNoErrors();
        $this->assertSame('6281234567890', $emp->fresh()->phone);
        $this->get(route('dashboard'))->assertDontSee('class="wa-chip"', false);

        $this->put(route('profile.update'), ['phone' => 'abc'])->assertSessionHasErrors('phone');

        // Admin yang mengisi nomor pengguna juga ternormalisasi.
        $u = $this->user(User::ROLE_KARYAWAN, null, ['phone' => '083111200037']);
        $this->assertSame('6283111200037', $u->fresh()->phone);
    }
}
