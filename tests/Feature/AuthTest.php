<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_karyawan_logs_in_with_nik_only(): void
    {
        $user = User::factory()->create(['nik' => '12345']);

        $this->post('/login', ['nik' => '12345'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_unknown_nik_is_rejected(): void
    {
        $this->post('/login', ['nik' => '00000'])->assertSessionHasErrors('nik');
        $this->assertGuest();
    }

    public function test_admin_cannot_login_with_nik_only(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_KEPALA_REGU, User::ROLE_STAF] as $i => $role) {
            $nik = '9000'.$i;
            User::factory()->role($role)->create(['nik' => $nik, 'password' => 'rahasia123']);

            $this->post('/login', ['nik' => $nik, 'mode' => 'admin'])
                ->assertSessionHasErrors('password')
                ->assertSessionHasInput('mode', 'admin');

            // Di tab Karyawan, akun pengelola ditolak walau NIK-nya benar.
            $this->post('/login', ['nik' => $nik, 'mode' => 'karyawan'])->assertSessionHasErrors('nik');
            $this->assertGuest();
        }
    }

    public function test_admin_requires_correct_password(): void
    {
        $user = User::factory()->role(User::ROLE_KEPALA_REGU)->create(['nik' => '22222', 'password' => 'rahasia123']);

        $this->post('/login', ['nik' => '22222', 'password' => 'salah', 'mode' => 'admin'])->assertSessionHasErrors('nik');
        $this->assertGuest();

        $this->post('/login', ['nik' => '22222', 'password' => 'rahasia123', 'mode' => 'admin'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_karyawan_password_field_is_ignored_even_if_wrong(): void
    {
        $user = User::factory()->create(['nik' => '33333', 'password' => 'abc12345']);

        $this->post('/login', ['nik' => '33333', 'password' => 'apa-saja', 'mode' => 'karyawan'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_accounts_cannot_login(): void
    {
        User::factory()->create(['nik' => '777', 'is_active' => false]);
        User::factory()->role(User::ROLE_KEPALA_REGU)->create(['nik' => '778', 'password' => 'abc12345', 'is_active' => false]);

        $this->post('/login', ['nik' => '777'])->assertSessionHasErrors('nik');
        $this->assertGuest();
        $this->post('/login', ['nik' => '778', 'password' => 'abc12345', 'mode' => 'admin'])->assertSessionHasErrors('nik');
        $this->assertGuest();
    }

    public function test_admin_default_password_must_be_changed_before_anything_else(): void
    {
        $user = User::factory()->role(User::ROLE_KEPALA_REGU)->create(['must_change_password' => true, 'password' => '99999', 'nik' => '99999']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('profile.edit'));
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => '99999', 'password' => 'baruAman123', 'password_confirmation' => 'baruAman123',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_karyawan_cannot_login_through_admin_tab_even_with_password(): void
    {
        $user = User::factory()->create(['nik' => '44444', 'password' => '44444']);

        // Password = NIK (kebiasaan lama) maupun password apa pun: tab Admin menolak akun karyawan.
        foreach (['44444', 'apa-saja'] as $pw) {
            $this->post('/login', ['nik' => '44444', 'password' => $pw, 'mode' => 'admin'])->assertSessionHasErrors('nik');
            $this->assertGuest();
        }
        $this->post('/login', ['nik' => '44444', 'mode' => 'admin'])->assertSessionHasErrors('password');
        $this->assertGuest();

        // Jalur resminya tetap bisa.
        $this->post('/login', ['nik' => '44444', 'mode' => 'karyawan'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_error_messages_do_not_reveal_whether_nik_exists_on_admin_tab(): void
    {
        User::factory()->create(['nik' => '55550']);
        $a = $this->post('/login', ['nik' => '55550', 'password' => 'x', 'mode' => 'admin'])->assertSessionHasErrors('nik');
        $b = $this->post('/login', ['nik' => '99999', 'password' => 'x', 'mode' => 'admin'])->assertSessionHasErrors('nik');
        $this->assertSame(session('errors')->first('nik'), $a->baseResponse->getSession()->get('errors')->first('nik'));
        $this->assertNotNull($b);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['nik' => '12121', 'password' => 'salah', 'mode' => 'admin'])->assertSessionHasErrors('nik');
        }
        $this->post('/login', ['nik' => '12121', 'password' => 'salah', 'mode' => 'admin'])->assertStatus(429);
    }

    public function test_karyawan_is_never_forced_to_change_password(): void
    {
        $user = User::factory()->create(['must_change_password' => true, 'nik' => '55555']);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertDontSee('Ganti password bawaan');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Karyawan')->assertSee('Admin');
    }

    public function test_login_page_is_never_cached_and_expired_token_goes_back_to_login(): void
    {
        $res = $this->get('/login')->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        $this->withMiddleware()->post('/login', ['nik' => '1'], ['X-CSRF-TOKEN' => 'salah'])->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }
}
