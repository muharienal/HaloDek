<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun awal Superadmin.
 *  - Lokal / testing : password = NIK (hanya untuk pengembangan).
 *  - Selain itu      : password ACAK, dicetak sekali di terminal, wajib diganti saat login pertama.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $dev = app()->environment('local', 'testing');

        if (! $dev && User::where('nik', '00001')->exists()) {
            return;
        }

        $user = $this->make('00001', 'Superadmin HaloDek', User::ROLE_SUPERADMIN, 'IT', 'Superadmin', null, ! $dev);

        if (! $dev && $user->wasRecentlyCreated) {
            $password = User::temporaryPassword(16);
            $user->update(['password' => $password, 'must_change_password' => true]);
            $this->command?->warn("Superadmin dibuat. NIK: 00001  Password sementara: {$password}  (catat sekarang, wajib diganti saat login pertama)");
        }
    }

    public static function make(string $nik, string $name, string $role, ?string $dept, ?string $position, ?string $teamSlug, bool $force): User
    {
        $user = User::firstOrCreate(['nik' => $nik], [
            'name' => $name,
            'role' => $role,
            'department' => $dept,
            'position' => $position,
            'team_id' => $teamSlug ? Team::where('slug', $teamSlug)->value('id') : null,
            'password' => $nik,
            'must_change_password' => $force,
            'is_active' => true,
        ]);

        return $user;
    }
}
