<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function make(User $owner, string $slug, string $title): Report
    {
        $team = Team::where('slug', $slug)->first();

        return Report::create([
            'report_number' => strtoupper($slug).'-'.random_int(1000, 9999), 'user_id' => $owner->id, 'reporter_name' => $owner->name, 'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->value('id'), 'priority' => 'sedang', 'title' => $title, 'description' => 'x', 'status' => 'menunggu',
        ]);
    }

    public function test_results_are_scoped_by_role(): void
    {
        $this->seedTeams();
        $budi = $this->user(User::ROLE_KARYAWAN, null, ['name' => 'Budi Santoso', 'nik' => 'N-BUDI']);
        $sari = $this->user(User::ROLE_KARYAWAN, null, ['name' => 'Sari Dewi', 'nik' => 'N-SARI']);
        $this->make($budi, 'it', 'Printer macet');
        $this->make($sari, 'it', 'Printer lantai dua');
        $this->make($sari, 'keamanan', 'Printer kamera');

        // Karyawan hanya melihat miliknya.
        $this->actingAs($budi)->get(route('search', ['q' => 'printer']))->assertOk()->assertSee('Printer macet')->assertDontSee('lantai dua')->assertDontSee('Printer kamera');

        // Staf IT melihat semua laporan regu IT (cari lewat judul, nama pelapor, dan NIK) tapi bukan regu lain.
        $staf = $this->user(User::ROLE_STAF, 'it');
        $this->actingAs($staf)->get(route('search', ['q' => 'printer']))->assertSee('Printer macet')->assertSee('lantai dua')->assertDontSee('Printer kamera');
        $this->get(route('search', ['q' => 'Sari']))->assertSee('lantai dua')->assertDontSee('Printer kamera');
        $this->get(route('search', ['q' => 'N-BUDI']))->assertSee('Printer macet')->assertDontSee('lantai dua');

        // Superadmin melihat semuanya; nomor tiket juga bisa dicari.
        $super = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($super)->get(route('search', ['q' => 'printer']))->assertSee('Printer kamera');
        $number = Report::where('title', 'Printer kamera')->value('report_number');
        $this->get(route('search', ['q' => $number]))->assertSee('Printer kamera')->assertDontSee('Printer macet');
    }

    public function test_short_query_wildcards_and_guest(): void
    {
        $this->seedTeams();
        $emp = $this->user();
        $this->make($emp, 'it', 'Wifi lambat');

        $this->actingAs($emp)->get(route('search', ['q' => 'a']))->assertOk()->assertSee('minimal 2 karakter');
        $this->get(route('search', ['q' => '%%']))->assertOk()->assertDontSee('Wifi lambat');   // wildcard tidak menjadi pola
        $this->get(route('search', ['q' => '<script>alert(1)</script>']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);

        auth()->logout();
        $this->flushSession();
        $this->get(route('search', ['q' => 'wifi']))->assertRedirect(route('login'));
    }
}
