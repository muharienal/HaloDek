<?php

namespace Tests;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Beberapa pengguna dipakai bergantian dalam satu tes: hapus hash password sesi milik
     * pengguna sebelumnya agar AuthenticateSession tidak menganggapnya sesi yang dibajak.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }

    protected function seedTeams(): void
    {
        $this->seed(TeamSeeder::class);
    }

    protected function user(string $role = User::ROLE_KARYAWAN, ?string $teamSlug = null, array $attrs = []): User
    {
        if ($teamSlug) {
            $attrs['team_id'] = Team::where('slug', $teamSlug)->value('id');
        }

        return User::factory()->role($role)->create($attrs);
    }
}
