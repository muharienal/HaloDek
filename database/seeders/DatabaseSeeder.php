<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TeamSeeder::class,
            UserSeeder::class,
        ]);

        // Data contoh (akun demo dengan password = NIK) TIDAK dibuat otomatis.
        // Hanya untuk pengembangan lokal: php artisan db:seed --class=DemoSeeder
    }
}
