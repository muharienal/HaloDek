<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh: akun demo (Kepala Regu + Staf tiap regu), lokasi, inventaris, pengumuman, dan laporan.
 * Semua akun demo memakai password = NIK.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo memakai password = NIK: DILARANG di server sungguhan.
        if (! app()->environment('local', 'testing')) {
            $this->command?->error('DemoSeeder hanya boleh dijalankan saat APP_ENV=local.');

            return;
        }

        $this->call([TeamSeeder::class, UserSeeder::class]);

        // ---- akun demo: tiap regu punya satu Kepala Regu dan beberapa Staf
        $K = User::ROLE_KEPALA_REGU;
        $S = User::ROLE_STAF;
        $heads = [
            'it' => UserSeeder::make('00002', 'Dimas Pratama', $K, 'IT', 'Kepala Regu IT', 'it', false),
            'sarpras' => UserSeeder::make('00003', 'Sari Wulandari', $K, 'Umum', 'Kepala Regu Sarana dan Prasarana', 'sarpras', false),
            'kesekretariatan' => UserSeeder::make('00006', 'Maya Anggraini', $K, 'Umum', 'Kepala Regu Kesekretariatan', 'kesekretariatan', false),
            'keamanan' => UserSeeder::make('00015', 'Hendra Kusuma', $K, 'Umum', 'Kepala Regu Keamanan', 'keamanan', false),
        ];
        $crew = [
            'it' => [
                UserSeeder::make('00007', 'Rafi Maulana', $S, 'IT', 'Network Engineer', 'it', false),
                UserSeeder::make('00011', 'Nadia Putri', $S, 'IT', 'IT Support', 'it', false),
            ],
            'sarpras' => [
                UserSeeder::make('00012', 'Bayu Hidayat', $S, 'Umum', 'Teknisi Gedung', 'sarpras', false),
                UserSeeder::make('00013', 'Yoga Saputra', $S, 'Umum', 'Driver & Kendaraan', 'sarpras', false),
            ],
            'kesekretariatan' => [
                UserSeeder::make('00014', 'Tari Wulan', $S, 'Umum', 'Sekretaris', 'kesekretariatan', false),
            ],
            'keamanan' => [
                UserSeeder::make('00016', 'Agus Salim', $S, 'Umum', 'Petugas Keamanan', 'keamanan', false),
                UserSeeder::make('00017', 'Bima Perkasa', $S, 'Umum', 'Petugas Keamanan', 'keamanan', false),
            ],
        ];
        $staff = collect([
            ['00004', 'Budi Santoso', 'Finance'], ['00005', 'Siti Aisyah', 'HRD'], ['00008', 'Andi Wijaya', 'Marketing'],
            ['00009', 'Dewi Lestari', 'Produksi'], ['00010', 'Rizky Pratama', 'Operasional'],
        ])->map(fn ($r) => UserSeeder::make($r[0], $r[1], User::ROLE_KARYAWAN, $r[2], 'Karyawan', null, false));

        // ---- lokasi
        $locs = collect([
            ['Ruang Rapat Utama', 'Gedung A', '2'], ['Ruang Finance', 'Gedung A', '1'], ['Ruang HRD', 'Gedung A', '1'],
            ['Ruang Server', 'Gedung A', '1'], ['Workshop Produksi', 'Gedung B', '1'], ['Lobby & Resepsionis', 'Gedung A', '1'], ['Gudang', 'Gedung C', '1'],
        ])->map(fn ($r) => Location::firstOrCreate(['name' => $r[0]], ['building' => $r[1], 'floor' => $r[2]]));

        // ---- inventaris per regu
        $team = fn (string $slug) => Team::where('slug', $slug)->first();
        $cat = fn (string $slug, string $name) => $team($slug)->inventoryCategories()->where('name', $name)->first();
        $item = function (string $slug, string $catName, string $code, string $name, array $extra = []) use ($team, $cat) {
            return InventoryItem::firstOrCreate(['team_id' => $team($slug)->id, 'code' => $code], [
                'inventory_category_id' => $cat($slug, $catName)->id, 'name' => $name, 'status' => 'tersedia',
            ] + $extra);
        };

        foreach ($staff as $i => $u) {
            $item('it', 'Laptop & PC', sprintf('AST-%04d', 100 + $i), ['Lenovo ThinkPad E14', 'ASUS Vivobook', 'HP ProBook 440', 'Dell Latitude 3420', 'Acer Aspire 5'][$i % 5], [
                'status' => 'digunakan', 'holder_id' => $u->id, 'location_id' => $locs[$i % 4]->id,
                'acquired_at' => now()->subMonths(10 + $i * 3)->toDateString(), 'due_date' => now()->addMonths(14 - $i * 3)->toDateString(), 'note' => 'Garansi',
            ]);
        }
        $item('it', 'Printer & Scanner', 'AST-0200', 'Epson L3250', ['location_id' => $locs[1]->id]);
        $item('it', 'Software', 'SW-001', 'Microsoft 365 Business (50 lisensi)', ['status' => 'digunakan', 'due_date' => now()->addMonths(8)->toDateString(), 'note' => '42 dari 50 lisensi terpakai']);
        $item('it', 'Software', 'SW-002', 'Antivirus Endpoint (60 lisensi)', ['status' => 'digunakan', 'due_date' => now()->addDays(18)->toDateString(), 'note' => '58 dari 60 lisensi terpakai']);
        $item('it', 'Akses & Lisensi', 'AKS-001', 'VPN Perusahaan — Budi Santoso', ['status' => 'digunakan', 'holder_id' => $staff[0]->id, 'due_date' => now()->addDays(25)->toDateString()]);
        $item('sarpras', 'AC', 'SP-AC-001', 'Daikin 2 PK', ['location_id' => $locs[0]->id, 'due_date' => now()->addDays(9)->toDateString(), 'note' => 'Servis berkala 6 bulan']);
        $item('sarpras', 'AC', 'SP-AC-002', 'Panasonic 1 PK', ['location_id' => $locs[2]->id, 'due_date' => now()->addDays(60)->toDateString()]);
        $item('sarpras', 'Kendaraan', 'SP-KND-001', 'Toyota Hiace B 1234 XY', ['status' => 'digunakan', 'location_id' => $locs[6]->id, 'due_date' => now()->addDays(20)->toDateString(), 'note' => 'Servis & pajak']);
        $item('sarpras', 'Ruangan', 'RG-001', 'Ruang Rapat Utama', ['location_id' => $locs[0]->id, 'note' => 'Kapasitas 20 orang']);
        $item('kesekretariatan', 'Ruang Rapat', 'KS-RR-01', 'Ruang Rapat Kecil Lt. 1', ['location_id' => $locs[2]->id]);
        $item('kesekretariatan', 'Perlengkapan Kantor', 'KS-ATK-01', 'Stok kertas A4 (rim)', ['note' => 'Minimal stok 20 rim']);
        $item('keamanan', 'APAR & Pemadam', 'KM-APAR-01', 'APAR Powder 6 kg — Workshop', ['location_id' => $locs[4]->id, 'due_date' => now()->addDays(5)->toDateString(), 'note' => 'Isi ulang tahunan']);
        $item('keamanan', 'CCTV', 'KM-CCTV-01', 'CCTV Lobby', ['status' => 'digunakan', 'location_id' => $locs[5]->id]);

        // ---- pengumuman
        $super = User::where('nik', '00001')->first();
        Announcement::firstOrCreate(['title' => 'Selamat datang di HaloDek'], [
            'body' => "Mulai sekarang seluruh pengaduan IT, sarana prasarana, dan keselamatan kerja disampaikan lewat HaloDek.\nPilih regu yang sesuai, jelaskan masalahnya, dan pantau tindak lanjutnya langsung dari aplikasi.",
            'user_id' => $super->id, 'is_pinned' => true,
        ]);
        Announcement::firstOrCreate(['title' => 'Pemeliharaan jaringan Sabtu malam'], [
            'body' => 'Tim IT akan melakukan pemeliharaan jaringan pukul 22.00–24.00. Koneksi dapat terputus sesaat.', 'user_id' => $heads['it']->id, 'team_id' => $team('it')->id,
        ]);

        // ---- laporan contoh (hanya bila belum ada)
        if (Report::withTrashed()->exists()) {
            return;
        }
        $this->seedReports($staff, $heads, $crew, $locs);
    }

    private function seedReports($staff, array $heads, array $crew, $locs): void
    {
        $service = app(ReportService::class);
        mt_srand(42);

        $samples = [
            'it' => [
                ['Laptop sering hang saat membuka Excel', 'Sejak dua hari terakhir laptop macet setiap membuka file Excel besar dan harus restart.', 'Komputer & Laptop'],
                ['Printer ruang Finance tidak bisa mencetak', 'Antrean cetak menumpuk dan printer menampilkan lampu merah berkedip.', 'Printer & Scanner'],
                ['WiFi lantai 2 sangat lambat', 'Video meeting sering terputus, terutama pada jam 10.00–12.00.', 'Internet & WiFi'],
                ['Tidak bisa login ke aplikasi ERP', 'Muncul pesan "akun terkunci" setelah salah memasukkan password.', 'Akses & Akun'],
                ['Permintaan mouse dan keyboard baru', 'Mouse sering double click dan beberapa tombol keyboard tidak berfungsi.', 'Permintaan Perangkat / Software'],
                ['Email tidak bisa mengirim lampiran > 10 MB', 'Lampiran proposal selalu ditolak server.', 'Email Korporat'],
            ],
            'sarpras' => [
                ['AC ruang rapat tidak dingin', 'AC menyala tetapi udara yang keluar hanya angin biasa, ruangan panas saat meeting.', 'AC'],
                ['Lampu koridor Gedung B mati', 'Tiga lampu di koridor depan workshop mati sehingga gelap saat sore.', 'Kelistrikan'],
                ['Keran toilet lantai 1 bocor', 'Air menetes terus dari keran wastafel toilet pria.', 'Air & Plumbing'],
                ['Kursi kerja patah sandaran', 'Sandaran kursi di meja admin goyang dan hampir lepas.', 'Meja Kursi & Furniture'],
                ['Mobil operasional bunyi di roda depan', 'Terdengar bunyi gesekan saat mengerem. Mohon dicek sebelum perjalanan dinas.', 'Kendaraan Operasional'],
                ['Atap gudang bocor saat hujan', 'Rembesan air mengenai rak arsip di sudut kiri gudang.', 'Gedung & Bangunan'],
            ],
            'kesekretariatan' => [
                ['Permintaan surat keterangan kerja', 'Mohon dibuatkan surat keterangan kerja untuk keperluan pengajuan KPR.', 'Surat Menyurat'],
                ['Stok kertas A4 di ruang HRD habis', 'Kertas untuk printer HRD habis sejak kemarin, mohon dikirim 5 rim.', 'Perlengkapan Kantor (ATK)'],
                ['Booking ruang rapat untuk training Kamis', 'Butuh ruang rapat kapasitas 20 orang pukul 09.00–15.00.', 'Ruang Rapat & Acara'],
            ],
            'keamanan' => [
                ['APAR di workshop kedaluwarsa', 'Segel APAR dekat pintu keluar sudah rusak dan tanggal isi ulang terlewat.', 'APAR & Evakuasi'],
                ['Kehilangan helm safety di area parkir', 'Helm safety warna kuning hilang dari motor di parkiran Gedung B.', 'Kehilangan Barang'],
                ['CCTV lobby tidak menampilkan gambar', 'Monitor pos jaga menampilkan layar hitam untuk kamera lobby.', 'CCTV'],
            ],
        ];

        $priorities = ['rendah', 'sedang', 'sedang', 'tinggi', 'mendesak'];
        $i = 0;

        foreach ($samples as $slug => $items) {
            $team = Team::where('slug', $slug)->first();
            $head = $heads[$slug];
            $crewOf = $crew[$slug] ?? [];
            foreach ($items as [$title, $desc, $catName]) {
                $i++;
                $user = $staff[$i % $staff->count()];
                $category = Category::where('team_id', $team->id)->where('name', $catName)->first();
                $daysAgo = mt_rand(0, 13);
                $createdAt = now()->subDays($daysAgo)->subMinutes(mt_rand(10, 600));

                $report = $service->create($user, $team, $category, [
                    'title' => $title, 'description' => $desc, 'priority' => $priorities[$i % 5],
                    'location_id' => $locs[$i % $locs->count()]->id,
                    'inventory_item_id' => ($slug === 'it' && $catName === 'Komputer & Laptop') ? $user->heldItems()->value('id') : null,
                ]);

                // Geser waktu ke masa lalu agar grafik tren terlihat hidup.
                $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
                $report->activities()->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);

                // Variasi alur: antrean (belum ditugaskan), ditugaskan kepala, diambil staf, selesai, ditolak.
                $outcome = $i % 6;
                $worker = $crewOf ? $crewOf[$i % count($crewOf)] : $head;

                if ($outcome === 0) {
                    continue; // tetap di antrean regu
                }
                if ($outcome === 1) {
                    $service->claim($report, $worker); // staf mengambil sendiri
                    continue;
                }
                $service->followUp($report, $head, ['status' => 'diproses', 'assigned_to_id' => $worker->id, 'follow_up_note' => 'Laporan diterima, petugas menuju lokasi.']);
                if ($outcome === 5) {
                    continue; // sedang diproses (ditugaskan kepala)
                }
                if ($outcome === 4) {
                    $service->followUp($report->fresh(), $head, ['status' => 'ditolak', 'follow_up_note' => 'Di luar lingkup layanan; mohon ajukan lewat prosedur pengadaan.']);
                    $report->refresh();
                    $report->forceFill(['resolved_at' => null])->saveQuietly();
                    continue;
                }
                $service->followUp($report->fresh(), $worker, ['status' => 'selesai', 'follow_up_note' => 'Sudah ditangani dan dicek kembali, berfungsi normal.']);
                $report->refresh();
                $resolved = $createdAt->copy()->addHours(mt_rand(1, 20));
                $report->forceFill(['resolved_at' => min($resolved, now()), 'first_response_at' => $createdAt->copy()->addMinutes(mt_rand(5, 90))])->saveQuietly();
            }
        }

        // Beberapa laporan lama yang dibuat sebelum tanggal hari ini sudah ditandai selesai di atas;
        // hapus notifikasi lama yang tidak relevan agar bel tidak penuh saat demo.
        DB::table('user_notifications')->where('created_at', '<', now()->subDays(3))->delete();
    }
}
