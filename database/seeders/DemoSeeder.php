<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportService;
use App\Support\WorkHours;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh lengkap (pengembangan lokal / demo). Password akun = NIK; karyawan cukup NIK.
 *
 * Orang, lokasi (ID 1-10) dan aset (ID sesuai urutan) SAMA dengan DemoSeeder Inventaris.
 * Isi Inventaris lebih dulu (php artisan inventaris:data-contoh), lalu HaloDek (php artisan halodek:data-contoh).
 *
 * Laporan dibuat lewat alur aplikasi yang sebenarnya (ReportService) dengan jam "dimundurkan",
 * sehingga riwayat, waktu respons, batas respons, notifikasi, dan grafik semuanya wajar.
 */
class DemoSeeder extends Seeder
{
    /** Divisi => bidang. */
    private const ORG = [
        'SDM, Umum & K3' => ['Umum & IT', 'SDM', 'K3 & Keamanan'],
        'Keuangan' => ['Akuntansi & Keuangan'],
        'Operasional' => ['Produksi', 'Operasional Lapangan'],
        'Pemasaran' => ['Marketing & Penjualan'],
    ];

    /** Regu => bidang. */
    private const TEAM_DEPT = ['it' => 'Umum & IT', 'sarpras' => 'Umum & IT', 'kesekretariatan' => 'Umum & IT', 'keamanan' => 'K3 & Keamanan'];

    /** [nik, nama, peran, bidang, jabatan, regu, aktif] - sama dengan Inventaris. */
    private const PEOPLE = [
        ['00001', 'Superadmin HaloDek', 'superadmin', 'Umum & IT', 'Superadmin', null, true],
        ['00011', 'Bambang Hartono', 'manajer', 'Umum & IT', 'Manajer Umum & IT', null, true],
        ['00002', 'Dimas Pratama', 'kepala_regu', 'Umum & IT', 'Kepala Regu IT', 'it', true],
        ['00003', 'Sari Wulandari', 'kepala_regu', 'Umum & IT', 'Kepala Regu Sarana dan Prasarana', 'sarpras', true],
        ['00006', 'Maya Anggraini', 'kepala_regu', 'Umum & IT', 'Kepala Regu Kesekretariatan', 'kesekretariatan', true],
        ['00015', 'Hendra Kusuma', 'kepala_regu', 'K3 & Keamanan', 'Kepala Regu Keamanan', 'keamanan', true],
        ['00007', 'Rafi Maulana', 'staf', 'Umum & IT', 'Network Engineer', 'it', true],
        ['00018', 'Nadia Putri', 'staf', 'Umum & IT', 'IT Support', 'it', true],
        ['00012', 'Bayu Hidayat', 'staf', 'Umum & IT', 'Teknisi Gedung', 'sarpras', true],
        ['00013', 'Yoga Saputra', 'staf', 'Umum & IT', 'Driver & Kendaraan', 'sarpras', true],
        ['00014', 'Tari Wulan', 'staf', 'Umum & IT', 'Sekretaris', 'kesekretariatan', true],
        ['00016', 'Agus Salim', 'staf', 'K3 & Keamanan', 'Petugas Keamanan', 'keamanan', true],
        ['00017', 'Bima Perkasa', 'staf', 'K3 & Keamanan', 'Petugas Keamanan', 'keamanan', true],
        ['00004', 'Budi Santoso', 'karyawan', 'Akuntansi & Keuangan', 'Staf Keuangan', null, true],
        ['00005', 'Siti Aisyah', 'karyawan', 'SDM', 'Staf HRD', null, true],
        ['00008', 'Andi Wijaya', 'karyawan', 'Marketing & Penjualan', 'Staf Marketing', null, true],
        ['00009', 'Dewi Lestari', 'karyawan', 'Produksi', 'Supervisor Produksi', null, true],
        ['00010', 'Rizky Pratama', 'karyawan', 'Operasional Lapangan', 'Staf Operasional', null, true],
        ['00019', 'Fajar Nugroho', 'karyawan', 'Akuntansi & Keuangan', 'Staf Keuangan', null, true],
        ['00020', 'Lina Marlina', 'karyawan', 'Marketing & Penjualan', 'Staf Marketing', null, true],
        ['00021', 'Teguh Wibowo', 'karyawan', 'Produksi', 'Operator Produksi', null, true],
        ['00022', 'Wulan Sari', 'karyawan', 'SDM', 'Staf HRD', null, true],
        ['00023', 'Eko Prasetyo', 'karyawan', 'Operasional Lapangan', 'Staf Operasional', null, false],
    ];

    /** ID lokasi Inventaris => nama tampilan (sama dengan Location::fullName di Inventaris). */
    private const LOCATIONS = [
        1 => 'Ruang Rapat Utama (Gedung A, Lt. 2)', 2 => 'Ruang Finance (Gedung A, Lt. 1)', 3 => 'Ruang HRD (Gedung A, Lt. 1)',
        4 => 'Ruang Server (Gedung A, Lt. 1)', 5 => 'Workshop Produksi (Gedung B, Lt. 1)', 6 => 'Lobby & Resepsionis (Gedung A, Lt. 1)',
        7 => 'Gudang (Gedung C, Lt. 1)', 8 => 'Ruang Marketing (Gedung A, Lt. 2)', 9 => 'Pos Keamanan (Gerbang Utama)', 10 => 'Area Parkir (Gedung B)',
    ];

    /** ID aset Inventaris yang dipakai laporan contoh => [kode, nama]. */
    private const ASSETS = [
        1 => ['AST-0100', 'Lenovo ThinkPad E14 Gen 5'], 3 => ['AST-0102', 'HP ProBook 440 G10'], 4 => ['AST-0103', 'Dell Latitude 3420'],
        5 => ['AST-0104', 'Acer Aspire 5'], 6 => ['AST-0105', 'Lenovo ThinkPad E14 Gen 5'], 8 => ['AST-0107', 'HP ProBook 440 G10'],
        9 => ['AST-0108', 'Dell Latitude 3420'], 10 => ['AST-0109', 'Acer Aspire 5'],
        18 => ['AST-0200', 'Epson L3250'], 19 => ['AST-0201', 'HP LaserJet Pro M404dn'], 22 => ['NET-002', 'Access Point Ubiquiti U6 Lite (Lt. 2)'],
        30 => ['SP-AC-001', 'AC Daikin 2 PK'], 31 => ['SP-AC-002', 'AC Panasonic 1 PK'], 32 => ['SP-AC-003', 'AC Sharp 1 PK'],
        33 => ['SP-AC-004', 'AC Daikin 1,5 PK Ruang Server'], 34 => ['SP-KND-001', 'Toyota Hiace B 1234 XY'], 36 => ['SP-LST-001', 'Genset Perkins 100 kVA'],
        37 => ['SP-FRN-001', 'Kursi Kerja Ergonomis'], 38 => ['RG-001', 'Ruang Rapat Utama'], 39 => ['KS-RR-01', 'Smart TV Samsung 55"'],
        41 => ['KS-ARS-01', 'Lemari Arsip Besi 4 Laci'], 42 => ['KM-CCTV-01', 'CCTV Lobby'], 43 => ['KM-CCTV-02', 'CCTV Area Parkir'],
        45 => ['KM-APAR-01', 'APAR Powder 6 kg Workshop'],
    ];

    /**
     * Laporan contoh: [hari lalu, pelapor, regu, kategori, lokasi, aset, prioritas, deskripsi, alur, catatan].
     * Alur: menunggu | ambil | tugas | selesai | puas | tolak | banding | batal | buka_lagi | alih:<regu>:<kategori>[:selesai] | ganda:<nomor urut>
     */
    private const REPORTS = [
        [61, '00023', 'it', 'Komputer & Laptop', 7, 10, 'sedang', 'Laptop tidak bisa terhubung ke printer gudang sejak ganti jaringan.', 'puas'],
        [60, '00004', 'it', 'Komputer & Laptop', 2, 1, 'sedang', 'Laptop lambat sekali saat membuka Excel berukuran besar, kipas berbunyi keras.', 'puas'],
        [59, '00005', 'sarpras', 'AC', 3, 31, 'sedang', 'AC ruang HRD meneteskan air ke meja kerja.', 'puas'],
        [58, '00005', 'it', 'Printer & Scanner', 3, 19, 'tinggi', 'Kertas sering macet di tengah saat mencetak dokumen kontrak.', 'selesai'],
        [57, '00019', 'kesekretariatan', 'Surat Menyurat', null, null, 'rendah', 'Mohon dibuatkan surat keterangan kerja untuk pengajuan KPR.', 'puas'],
        [55, '00008', 'it', 'Internet & WiFi', 8, null, 'sedang', 'WiFi di Ruang Marketing sering putus saat video call dengan klien.', 'puas'],
        [54, '00009', 'sarpras', 'Kelistrikan', 5, null, 'tinggi', 'Tiga lampu di koridor depan workshop mati, area menjadi gelap saat sore.', 'selesai'],
        [53, '00021', 'keamanan', 'APAR & Evakuasi', 5, 45, 'tinggi', 'Segel APAR dekat pintu keluar workshop rusak dan jarum tekanan di zona merah.', 'puas'],
        [52, '00019', 'it', 'Akses & Akun', 2, null, 'tinggi', 'Akun ERP Accurate terkunci setelah salah memasukkan password tiga kali.', 'puas'],
        [49, '00009', 'it', 'Email Korporat', 5, null, 'rendah', 'Tidak bisa mengirim email dengan lampiran di atas 10 MB.', 'tolak',
            'Batas lampiran 10 MB adalah kebijakan perusahaan. Gunakan tautan OneDrive untuk berkas besar.'],
        [48, '00008', 'sarpras', 'AC', 1, 30, 'tinggi', 'AC Ruang Rapat Utama tidak dingin, ruangan panas saat rapat dengan tamu.', 'puas'],
        [47, '00005', 'kesekretariatan', 'Perlengkapan Kantor (ATK)', 3, null, 'rendah', 'Stok kertas A4 di ruang HRD habis, mohon dikirim 5 rim.', 'selesai'],
        [46, '00020', 'it', 'Permintaan Perangkat / Software', 8, null, 'rendah', 'Mohon dipasang Adobe Illustrator untuk desain materi promosi.', 'tolak',
            'Lisensi Adobe harus diajukan lewat pengadaan dengan persetujuan Manajer Marketing.'],
        [44, '00010', 'sarpras', 'Kendaraan Operasional', 10, 34, 'sedang', 'Mobil Hiace bunyi gesekan di roda depan saat mengerem.', 'selesai'],
        [43, '00010', 'it', 'Komputer & Laptop', 7, 5, 'sedang', 'Baterai laptop cepat habis, hanya tahan sekitar 40 menit.', 'selesai'],
        [41, '00020', 'keamanan', 'Kehilangan Barang', 10, null, 'sedang', 'Helm safety kuning hilang dari motor di parkiran Gedung B.', 'tolak',
            'Rekaman CCTV sudah dicek dan helm tidak ditemukan. Ajukan helm pengganti ke Regu Keamanan.'],
        [40, '00021', 'it', 'Komputer & Laptop', 5, 8, 'mendesak', 'Laptop tidak mau menyala sama sekali, padahal dipakai untuk input data produksi.', 'puas'],
        [39, '00019', 'sarpras', 'Meja Kursi & Furniture', 2, 37, 'rendah', 'Sandaran kursi kerja goyang dan hampir lepas.', 'selesai'],
        [37, '00022', 'it', 'Printer & Scanner', 3, 19, 'sedang', 'Hasil cetak printer HRD bergaris dan pudar.', 'buka_lagi'],
        [36, '00008', 'kesekretariatan', 'Ruang Rapat & Acara', 1, 38, 'sedang', 'Booking Ruang Rapat Utama untuk presentasi klien Kamis pukul 09.00-12.00.', 'puas'],
        [34, '00022', 'sarpras', 'Toilet & Sanitasi', 3, null, 'sedang', 'Flush toilet wanita lantai 1 tidak berfungsi.', 'puas'],
        [33, '00004', 'it', 'Printer & Scanner', 2, 18, 'rendah', 'Tinta printer Epson di Ruang Finance hampir habis, mohon diisi.', 'selesai'],
        [31, '00009', 'keamanan', 'Kecelakaan / Near Miss', 5, null, 'tinggi', 'Lantai workshop licin dekat mesin press, salah satu operator hampir terpeleset.', 'puas'],
        [30, '00005', 'it', 'Aplikasi / Sistem Informasi', 3, null, 'sedang', 'Aplikasi absensi tidak bisa dibuka, muncul pesan galat koneksi database.', 'puas'],
        [29, '00004', 'sarpras', 'Air & Plumbing', 2, null, 'rendah', 'Keran wastafel dekat Ruang Finance menetes terus.', 'selesai'],
        [27, '00008', 'it', 'Komputer & Laptop', 8, 3, 'sedang', 'Beberapa tombol keyboard laptop tidak berfungsi (huruf A dan S).', 'selesai'],
        [26, '00009', 'kesekretariatan', 'Perjalanan Dinas', null, null, 'sedang', 'Pengajuan tiket dan hotel untuk kunjungan ke pabrik Surabaya minggu depan.', 'selesai'],
        [24, '00009', 'it', 'Internet & WiFi', 5, null, 'tinggi', 'Internet di Workshop Produksi mati total sejak pagi.', 'puas'],
        [23, '00021', 'sarpras', 'Gedung & Bangunan', 7, null, 'tinggi', 'Atap gudang bocor saat hujan, air mengenai rak arsip di sudut kiri.', 'puas'],
        [21, '00004', 'keamanan', 'Akses Gedung & Parkir', 6, null, 'rendah', 'Kartu akses pintu lobby tidak terbaca di mesin.', 'selesai'],
        [20, '00019', 'it', 'Komputer & Laptop', 2, 6, 'sedang', 'Laptop sering restart sendiri saat menjalankan Windows Update.', 'selesai'],
        [18, '00020', 'sarpras', 'Kebersihan', 8, null, 'rendah', 'Tempat sampah di Ruang Marketing jarang dikosongkan, mulai berbau.', 'selesai'],
        [17, '00020', 'it', 'Lainnya', 8, null, 'rendah', 'Mohon bantuan menyambungkan laptop ke TV di Ruang Marketing untuk presentasi rutin.', 'selesai', 'Sambung TV presentasi'],
        [16, '00004', 'kesekretariatan', 'Arsip & Dokumen', 7, 41, 'rendah', 'Mohon diambilkan arsip faktur tahun 2023 dari lemari gudang.', 'selesai'],
        [15, '00014', 'sarpras', 'AC', 6, null, 'sedang', 'AC di lobby kurang dingin, tamu sering mengeluh.', 'selesai'],
        [14, '00010', 'it', 'Akses & Akun', 7, null, 'sedang', 'Belum mendapat akses folder bersama Operasional di server.', 'puas'],
        [13, '00005', 'sarpras', 'AC', 2, 32, 'sedang', 'AC Ruang Finance berbunyi keras dan berbau apek.', 'selesai'],
        [12, '00008', 'keamanan', 'CCTV', 10, 43, 'sedang', 'CCTV area parkir gambarnya buram dan berbayang.', 'tugas'],
        [11, '00021', 'it', 'Lainnya', 5, null, 'rendah', 'Lampu di atas meja kerja workshop berkedip-kedip.', 'alih:sarpras:Kelistrikan:selesai', 'Lampu berkedip'],
        [10, '00022', 'kesekretariatan', 'Ekspedisi & Kurir', 6, null, 'sedang', 'Mohon kirim dokumen kontrak ke kantor notaris hari ini.', 'tolak',
            'Pengiriman di hari yang sama harus diajukan sebelum pukul 10.00. Dijadwalkan besok pagi lewat permintaan baru.'],
        [9, '00022', 'it', 'Komputer & Laptop', 3, 9, 'tinggi', 'Layar laptop berkedip dan kadang menjadi hitam beberapa detik.', 'tugas'],
        [8, '00010', 'sarpras', 'Kendaraan Operasional', 10, 34, 'tinggi', 'Rem Hiace kembali berdecit dan kurang pakem saat turunan.', 'tugas'],
        [7, '00004', 'it', 'Aplikasi / Sistem Informasi', 2, null, 'mendesak', 'ERP tidak bisa menyimpan transaksi, muncul pesan "deadlock". Tutup buku bulan ini terhambat.', 'puas'],
        [6, '00009', 'sarpras', 'Kelistrikan', 5, 36, 'mendesak', 'Genset tidak otomatis menyala saat listrik PLN padam pagi ini.', 'ambil'],
        [5, '00005', 'it', 'Printer & Scanner', 3, 19, 'sedang', 'Printer HRD kembali macet, kertas tersangkut di rol belakang.', 'ambil'],
        [5, '00022', 'keamanan', 'Keamanan Area', 10, null, 'sedang', 'Lampu sorot parkiran mati, area gelap pada malam hari.', 'alih:sarpras:Kelistrikan'],
        [4, '00022', 'it', 'Printer & Scanner', 3, 19, 'sedang', 'Printer di HRD tidak bisa dipakai, kertas nyangkut terus.', 'ganda:45'],
        [4, '00020', 'kesekretariatan', 'Perlengkapan Kantor (ATK)', 8, null, 'rendah', 'Mohon map plastik dan pulpen untuk tim marketing.', 'ambil'],
        [3, '00020', 'it', 'Permintaan Perangkat / Software', 8, null, 'rendah', 'Mohon pengadaan mouse wireless untuk tim marketing (3 buah).', 'banding',
            'Permintaan perangkat baru melalui pengadaan.'],
        [3, '00002', 'sarpras', 'AC', 4, 33, 'mendesak', 'AC Ruang Server mati, suhu ruangan naik sampai 30 derajat.', 'ambil'],
        [2, '00008', 'it', 'Internet & WiFi', 1, 22, 'tinggi', 'WiFi di Ruang Rapat Utama sangat lambat saat rapat, tamu juga mengeluh.', 'tugas'],
        [2, '00008', 'sarpras', 'AC', 1, 30, 'sedang', 'AC ruang rapat bocor, air menetes ke lantai dekat stopkontak.', 'menunggu'],
        [1, '00019', 'it', 'Akses & Akun', 2, null, 'sedang', 'Mohon reset password email, saya lupa password setelah cuti.', 'menunggu'],
        [1, '00022', 'sarpras', 'Gedung & Bangunan', 3, null, 'rendah', 'Pintu Ruang HRD sulit ditutup dan engselnya berbunyi.', 'menunggu'],
        [1, '00010', 'kesekretariatan', 'Ruang Rapat & Acara', 1, 39, 'sedang', 'Smart TV ruang rapat tidak bisa screen mirroring dari laptop.', 'menunggu'],
        [0, '00009', 'it', 'Komputer & Laptop', 5, 4, 'tinggi', 'Laptop terkena tumpahan kopi, beberapa tombol lengket.', 'menunggu'],
        [0, '00021', 'sarpras', 'Air & Plumbing', 5, null, 'sedang', 'Pipa wastafel di workshop bocor, lantai jadi basah.', 'menunggu'],
        [0, '00019', 'keamanan', 'CCTV', 6, 42, 'mendesak', 'Monitor pos jaga tidak menampilkan gambar kamera lobby.', 'menunggu'],
        [0, '00010', 'it', 'Lainnya', 7, null, 'rendah', 'Webcam laptop tidak terdeteksi saat rapat Zoom.', 'batal', 'Webcam'],
    ];

    private const NOTES = [
        'claim' => ['Saya cek ke lokasi sekarang.', 'Sedang saya tangani.', 'Saya ambil, menuju lokasi.'],
        'done' => ['Sudah diperbaiki dan dicoba bersama pelapor, berfungsi normal.', 'Selesai ditangani, sudah dicek kembali.', 'Komponen sudah diganti dan diuji.'],
        'reply' => ['Terima kasih, sudah normal kembali.', 'Baik, saya tunggu di ruangan.', 'Mohon dibantu secepatnya ya.'],
        'internal' => ['Perlu cek garansi dulu sebelum ganti suku cadang.', 'Kemungkinan perlu vendor, saya koordinasikan.'],
    ];

    private array $u = [];
    private ReportService $svc;
    private Carbon $realNow;

    public function run(): void
    {
        if (! app()->environment('local', 'testing') && ! app()->bound('demo.allowed')) {
            $this->command?->error('Data contoh memakai password = NIK. Jalankan lewat: php artisan halodek:data-contoh');

            return;
        }

        $this->call(TeamSeeder::class);
        $this->svc = app(ReportService::class);
        $this->realNow = Carbon::now();

        // Selama pengisian: tanpa WhatsApp dan tanpa kirim ke Inventaris (dikirim sekaligus sesudahnya).
        $wa = config('services.whatsapp.driver');
        $inv = config('services.inventaris.url');
        config(['services.whatsapp.driver' => 'off', 'services.inventaris.url' => '']);

        try {
            $this->organization();
            $this->announcements();
            if (! Report::withTrashed()->exists()) {
                $this->reports();
            }
            // Eko sudah keluar: dinonaktifkan setelah laporannya tercatat (aset Inventaris masih atas namanya).
            $this->u['00023']->update(['is_active' => false]);
            // Notifikasi yang sudah lewat dua hari dianggap sudah dibaca agar lonceng tidak penuh saat demo.
            DB::table('user_notifications')->where('created_at', '<', $this->realNow->copy()->subDays(2))->update(['is_read' => true, 'read_at' => $this->realNow]);
        } finally {
            Carbon::setTestNow();
            config(['services.whatsapp.driver' => $wa, 'services.inventaris.url' => $inv]);
        }
    }

    private function organization(): void
    {
        $dept = [];
        $i = 0;
        foreach (self::ORG as $divName => $bidang) {
            $div = Division::firstOrCreate(['name' => $divName], ['sort_order' => ++$i]);
            foreach ($bidang as $j => $name) {
                $dept[$name] = Department::firstOrCreate(['name' => $name], ['division_id' => $div->id, 'sort_order' => $j + 1]);
            }
        }
        foreach (self::TEAM_DEPT as $slug => $name) {
            Team::where('slug', $slug)->update(['department_id' => $dept[$name]->id]);
        }

        $positions = collect(self::PEOPLE)->pluck(4)->unique()->values();
        foreach ($positions as $k => $name) {
            Position::firstOrCreate(['name' => $name], ['sort_order' => $k + 1]);
        }

        foreach (self::PEOPLE as [$nik, $name, $role, $bidang, $jabatan, $team]) {
            $user = User::firstOrNew(['nik' => $nik]);
            $user->forceFill([
                'name' => $name, 'role' => $role,
                'department_id' => $dept[$bidang]->id,
                'position_id' => Position::where('name', $jabatan)->value('id'),
                'team_id' => $team ? Team::where('slug', $team)->value('id') : null,
                'password' => $nik,
                'must_change_password' => false,
                'is_active' => true,
            ])->save();
            $this->u[$nik] = $user;
        }
    }

    private function announcements(): void
    {
        $super = $this->u['00001'];
        $rows = [
            [40, 'Selamat datang di HaloDek', "Semua laporan IT, sarana prasarana, kesekretariatan, dan keamanan disampaikan lewat HaloDek.\nKetik masalahnya atau pindai QR di barang, jelaskan singkat, lalu pantau penanganannya dari aplikasi.", $super, null, true],
            [9, 'Pemeliharaan jaringan Sabtu malam', 'Tim IT melakukan pemeliharaan jaringan pukul 22.00-24.00. Koneksi internet dapat terputus sesaat.', $this->u['00002'], 'it', false],
            [6, 'Pemeriksaan APAR bulanan', 'Regu Keamanan memeriksa seluruh APAR pada Jumat ini. Mohon area di sekitar APAR tidak terhalang barang.', $this->u['00015'], 'keamanan', false],
            [2, 'Pengecatan ulang Gedung B', 'Mulai Senin depan dilakukan pengecatan koridor Gedung B. Harap berhati-hati dengan tangga dan cat basah.', $this->u['00003'], 'sarpras', false],
        ];
        foreach ($rows as [$days, $title, $body, $by, $team, $pinned]) {
            $this->travel($this->workday($days, 9));
            Announcement::firstOrCreate(['title' => $title], [
                'body' => $body, 'user_id' => $by->id, 'team_id' => $team ? Team::where('slug', $team)->value('id') : null, 'is_pinned' => $pinned,
            ]);
        }
    }

    private function reports(): void
    {
        mt_srand(2026);
        $made = [];

        foreach (self::REPORTS as $n => $row) {
            [$days, $nik, $slug, $catName, $locId, $assetId, $priority, $desc, $flow] = $row;
            $extra = $row[9] ?? null;
            $team = Team::where('slug', $slug)->firstOrFail();
            $category = Category::where('team_id', $team->id)->where('name', $catName)->firstOrFail();
            $reporter = $this->u[$nik];

            // Hari ini: 1-3 jam lalu (atau jam 9 bila belum jam kerja); lainnya: jam kerja acak.
            $at = $days === 0
                ? $this->realNow->copy()->subMinutes(mt_rand(40, 180))
                : $this->workday($days, mt_rand(8, 14));
            $this->travel($at);

            $location = $locId ? self::LOCATIONS[$locId] : null;
            $custom = $category->allow_custom ? ($extra ?: 'Lainnya') : null;
            $report = $this->svc->create($reporter, $team, $category, [
                'title' => trim(($custom ?: $category->name).($location ? ' - '.preg_replace('/\s*\(.*\)$/', '', $location) : '')),
                'description' => $desc,
                'priority' => $priority,
                'custom_category' => $custom,
                'inventory_location_id' => $locId,
                'location_name' => $location,
            ] + ($assetId ? ['asset_id' => $assetId, 'asset_code' => self::ASSETS[$assetId][0], 'asset_name' => self::ASSETS[$assetId][1]] : []));
            $made[$n + 1] = $report;

            $this->flow($report, $flow, $extra, $made);
        }
    }

    /** Jalankan alur penanganan dengan waktu yang maju wajar (berhenti bila melewati waktu sebenarnya). */
    private function flow(Report $report, string $flow, ?string $extra, array $made): void
    {
        $team = $report->team;
        $head = User::where('role', User::ROLE_KEPALA_REGU)->where('team_id', $team->id)->first();
        $crew = User::where('role', User::ROLE_STAF)->where('team_id', $team->id)->orderBy('nik')->get();
        $worker = $crew->isNotEmpty() ? $crew[$report->id % $crew->count()] : $head;
        $reporter = $report->user;
        $pick = fn (string $k) => self::NOTES[$k][$report->id % count(self::NOTES[$k])];

        if ($flow === 'menunggu') {
            return;
        }
        if ($flow === 'batal') {
            $this->step(5, 30) && $this->svc->cancel($report, $reporter, 'Sudah normal lagi setelah laptop dinyalakan ulang.');

            return;
        }
        if (str_starts_with($flow, 'ganda:')) {
            $master = $made[(int) substr($flow, 6)] ?? null;
            if ($master && $this->step(20, 90)) {
                $this->svc->markDuplicate($report->fresh(), $head, $master->fresh());
            }

            return;
        }
        if (str_starts_with($flow, 'alih:')) {
            [, $toSlug, $toCat] = explode(':', $flow) + [null, null, null];
            $to = Team::where('slug', $toSlug)->firstOrFail();
            if (! $this->step(15, 60)) {
                return;
            }
            $report = $this->svc->transfer($report, $head, $to, Category::where('team_id', $to->id)->where('name', $toCat)->firstOrFail(), 'Masalah kelistrikan, ditangani Sarana dan Prasarana.');
            if (! str_ends_with($flow, ':selesai')) {
                return;
            }
            $this->flow($report->fresh(), 'selesai', null, $made);

            return;
        }
        if (in_array($flow, ['tolak', 'banding'], true)) {
            if (! $this->step(20, 180)) {
                return;
            }
            $this->svc->followUp($report, $head, ['status' => 'ditolak', 'follow_up_note' => $extra ?: 'Di luar lingkup layanan regu.']);
            if ($flow === 'banding' && $this->step(60, 240)) {
                $this->svc->appeal($report->fresh(), $reporter, 'Mouse lama sudah rusak dan menghambat pekerjaan, mohon dipertimbangkan sebagai penggantian, bukan pengadaan baru.');
            }

            return;
        }

        // Ditangani: diambil sendiri oleh staf (ganjil) atau ditugaskan Kepala Regu (genap / "tugas").
        if (! $this->step(10, 150)) {
            return;
        }
        if ($flow === 'ambil' || ($flow !== 'tugas' && $report->id % 2 === 1)) {
            $this->svc->claim($report, $worker);
            $this->svc->comment($report->fresh(), $worker, $pick('claim'));
        } else {
            $this->svc->followUp($report, $head, ['status' => 'diproses', 'assigned_to_id' => $worker->id, 'follow_up_note' => 'Mohon segera dicek ke lokasi.']);
        }
        if ($report->id % 3 === 0 && $this->step(20, 120)) {
            $this->svc->comment($report->fresh(), $reporter, $pick('reply'));
        }
        if ($report->id % 4 === 0 && $this->step(10, 60)) {
            $this->svc->comment($report->fresh(), $worker, $pick('internal'), true);
        }
        if (in_array($flow, ['ambil', 'tugas'], true)) {
            return;
        }

        // Selesai dalam 1-16 jam kerja.
        if (! $this->step(60, 960)) {
            return;
        }
        $this->svc->followUp($report->fresh(), $worker, ['status' => 'selesai', 'follow_up_note' => $pick('done')]);

        if ($flow === 'buka_lagi') {
            if (! $this->step(120, 600)) {
                return;
            }
            $this->svc->reopen($report->fresh(), $reporter, 'Masih bergaris setelah dua hari dipakai.');
            if ($this->step(30, 240)) {
                $this->svc->followUp($report->fresh(), $head, ['status' => 'diproses', 'assigned_to_id' => $worker->id, 'follow_up_note' => 'Dicek ulang, kemungkinan drum printer aus.']);
            }
            if ($this->step(120, 900)) {
                $this->svc->followUp($report->fresh(), $worker, ['status' => 'selesai', 'follow_up_note' => 'Drum printer diganti, hasil cetak sudah bersih.']);
                $this->step(60, 300) && $this->svc->confirm($report->fresh(), $reporter);
            }

            return;
        }
        if ($flow === 'puas' && $this->step(30, 480)) {
            $this->svc->confirm($report->fresh(), $reporter);
        }
    }

    /**
     * Majukan jam sejumlah menit KERJA acak. False bila hasilnya melewati waktu sebenarnya
     * (langkah ini "belum terjadi"), sehingga laporan terbaru tetap terbuka secara alami.
     */
    private function step(int $minMinutes, int $maxMinutes): bool
    {
        $next = WorkHours::addHours(Carbon::now(), mt_rand($minMinutes, $maxMinutes) / 60);
        if ($next->gte($this->realNow->copy()->subMinutes(5))) {
            return false;
        }
        $this->travel($next);

        return true;
    }

    private function travel(Carbon $at): void
    {
        Carbon::setTestNow($at->copy());
    }

    /** Tanggal $days hari kerja lalu (Sabtu/Minggu digeser ke Jumat) pada jam tertentu. */
    private function workday(int $days, int $hour): Carbon
    {
        $d = $this->realNow->copy()->subDays($days)->setTime($hour, mt_rand(0, 59));
        while (! WorkHours::isWorkday($d)) {
            $d->subDay();
        }

        return $d;
    }
}
