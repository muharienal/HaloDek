<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
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

        $user = $this->make('00001', 'Superadmin HaloDek', User::ROLE_SUPERADMIN, null, 'Superadmin', null, ! $dev);

        if (! $dev && $user->wasRecentlyCreated) {
            $password = User::temporaryPassword(16);
            $user->update(['password' => $password, 'must_change_password' => true]);
            $this->command?->warn("Superadmin dibuat. NIK: 00001  Password sementara: {$password}  (catat sekarang, wajib diganti saat login pertama)");
        }
    }

    public static function make(string $nik, string $name, string $role, ?string $dept, ?string $position, ?string $teamSlug, bool $force): User
    {
        // Bidang IT & Umum berada di Divisi "SDM, Umum & K3"; bidang lain di Divisi "Operasional" (contoh).
        $bidang = null;
        if ($dept) {
            $divName = in_array($dept, ['IT', 'Umum', 'Umum & IT'], true) ? 'SDM, Umum & K3' : 'Operasional';
            $division = Division::firstOrCreate(['name' => $divName]);
            $bidang = Department::firstOrCreate(['name' => $dept], ['division_id' => $division->id]);
        }

        $user = User::firstOrCreate(['nik' => $nik], [
            'name' => $name,
            'role' => $role,
            'department_id' => $bidang?->id,
            'position_id' => $position ? Position::firstOrCreate(['name' => $position])->id : null,
            'team_id' => $teamSlug ? Team::where('slug', $teamSlug)->value('id') : null,
            'password' => $nik,
            'must_change_password' => $force,
            'is_active' => true,
        ]);

        return $user;
    }
}
