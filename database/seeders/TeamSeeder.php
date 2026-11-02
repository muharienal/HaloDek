<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Empat regu bawaan (IT, Sarana dan Prasarana, Kesekretariatan, Keamanan) beserta
 * kategori laporan dan kategori inventaris standarnya. Aman dijalankan berulang.
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            [
                'slug' => Team::SLUG_IT, 'name' => 'IT', 'code' => 'IT', 'icon' => 'server', 'color' => 'blue',
                'description' => 'Komputer, jaringan, aplikasi, email, dan akses sistem', 'sort_order' => 1,
                'categories' => [
                    'Komputer & Laptop', 'Printer & Scanner', 'Internet & WiFi', 'Aplikasi / Sistem Informasi',
                    'Email Korporat', 'Akses & Akun', 'Permintaan Perangkat / Software',
                ],
                'inventory' => ['Laptop & PC', 'Printer & Scanner', 'Jaringan', 'Software', 'Akses & Lisensi'],
            ],
            [
                'slug' => Team::SLUG_SARPRAS, 'name' => 'Sarana dan Prasarana', 'code' => 'SP', 'icon' => 'building', 'color' => 'amber',
                'description' => 'Gedung, kendaraan, kelistrikan, furniture, kebersihan, AC', 'sort_order' => 2,
                'categories' => [
                    'Gedung & Bangunan', 'Kendaraan Operasional', 'Kelistrikan', 'Meja Kursi & Furniture',
                    'Toilet & Sanitasi', 'Air & Plumbing', 'Kebersihan', 'AC',
                ],
                'inventory' => ['AC', 'Kendaraan', 'Ruangan', 'Furniture', 'Kelistrikan'],
            ],
            [
                'slug' => Team::SLUG_KESEKRETARIATAN, 'name' => 'Kesekretariatan', 'code' => 'KS', 'icon' => 'document', 'color' => 'violet',
                'description' => 'Surat menyurat, arsip, perlengkapan kantor, ruang rapat, dan perjalanan dinas', 'sort_order' => 3,
                'categories' => [
                    'Surat Menyurat', 'Arsip & Dokumen', 'Perlengkapan Kantor (ATK)', 'Ruang Rapat & Acara',
                    'Perjalanan Dinas', 'Ekspedisi & Kurir',
                ],
                'inventory' => ['Perlengkapan Kantor', 'Arsip', 'Ruang Rapat'],
            ],
            [
                'slug' => Team::SLUG_KEAMANAN, 'name' => 'Keamanan', 'code' => 'KM', 'icon' => 'shield', 'color' => 'rose',
                'description' => 'Keamanan area, kehilangan barang, akses gedung, CCTV, dan keselamatan', 'sort_order' => 4,
                'categories' => [
                    'Keamanan Area', 'Kehilangan Barang', 'Akses Gedung & Parkir', 'CCTV',
                    'Kecelakaan / Near Miss', 'APAR & Evakuasi',
                ],
                'inventory' => ['APAR & Pemadam', 'CCTV', 'Perlengkapan Jaga', 'Akses & Gerbang'],
            ],
        ];

        foreach ($teams as $data) {
            $categories = $data['categories'];
            $inventory = $data['inventory'];
            unset($data['categories'], $data['inventory']);

            $team = Team::updateOrCreate(['slug' => $data['slug']], $data + ['is_system' => true, 'is_active' => true]);

            foreach ($categories as $i => $name) {
                $team->categories()->firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
            }
            // Setiap regu punya kategori "Lainnya" dengan isian bebas.
            $team->categories()->firstOrCreate(['name' => 'Lainnya'], ['allow_custom' => true, 'sort_order' => 999]);

            foreach ($inventory as $i => $name) {
                $team->inventoryCategories()->firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
            }
        }
    }
}
