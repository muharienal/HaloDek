<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API data karyawan untuk aplikasi Inventaris. */
class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(string $token = 'token-rahasia-0123456789'): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    public function test_employees_endpoint_requires_token_and_returns_only_karyawan(): void
    {
        config(['services.inventaris.token' => '']);
        $this->getJson('/api/v1/employees', $this->auth())->assertStatus(503);

        config(['services.inventaris.token' => 'token-rahasia-0123456789']);
        $this->getJson('/api/v1/employees')->assertStatus(401);
        $this->getJson('/api/v1/employees', $this->auth('salah-salah-salah-salah'))->assertStatus(401);

        $this->seedTeams();
        $this->user(User::ROLE_KARYAWAN, null, ['nik' => '12345', 'name' => 'Rina Kartika', 'department' => 'Keuangan']);
        $this->user(User::ROLE_KARYAWAN, null, ['nik' => '12346', 'name' => 'Lama', 'is_active' => false]);
        $this->user(User::ROLE_STAF, 'it', ['nik' => '00009']);

        $res = $this->getJson('/api/v1/employees', $this->auth())->assertOk()->assertJsonPath('count', 2)
            ->assertJsonFragment(['nik' => '12345', 'name' => 'Rina Kartika', 'department' => 'Keuangan', 'active' => true])
            ->assertJsonFragment(['nik' => '12346', 'active' => false])
            ->assertJsonMissing(['nik' => '00009']);
        $this->assertArrayNotHasKey('password', $res->json('data.0'));
        $this->getJson('/api/v1/ping', $this->auth())->assertOk()->assertJsonPath('ok', true);
    }
}
