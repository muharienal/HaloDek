<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout_show_alert(): void
    {
        $this->seedTeams();
        $emp = $this->user();

        $this->post(route('login'), ['mode' => 'karyawan', 'nik' => $emp->nik])->assertRedirect();
        $this->get(route('dashboard'))->assertSee('Selamat datang', false)->assertSee('data-toast="success"', false);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee('Anda sudah keluar.')->assertSee('data-toast="success"', false);
    }

    public function test_failed_login_shows_error_alert(): void
    {
        $this->seedTeams();
        $this->from(route('login'))->post(route('login'), ['mode' => 'karyawan', 'nik' => 'TIDAKADA'])->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee('data-toast="error"', false);
    }

    public function test_action_shows_alert(): void
    {
        $this->seedTeams();
        $staf = $this->user(User::ROLE_STAF, 'it');

        $this->actingAs($staf)->post(route('admin.announcements.store'), ['title' => 'Info', 'body' => 'Isi'])->assertSessionHas('success');
        $this->get(route('dashboard'))->assertOk();
    }
}
