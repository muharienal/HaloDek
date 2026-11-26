<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Kosongkan SEMUA data HaloDek lalu isi dengan data contoh (password = NIK).
 * Jalankan SETELAH php artisan inventaris:data-contoh di folder Inventaris, agar laporan contoh
 * menunjuk aset dan lokasi yang benar.
 */
class ResetDemoData extends Command
{
    protected $signature = 'halodek:data-contoh {--paksa : Lewati konfirmasi}';

    protected $description = 'HAPUS semua data HaloDek lalu isi data contoh (untuk uji coba)';

    public function handle(): int
    {
        $this->warn('Semua data HaloDek (laporan, lampiran, pengguna, pengumuman, pengaturan WhatsApp) akan DIHAPUS dan diganti data contoh.');
        $this->warn('Akun contoh memakai password = NIK dan tanpa No. WhatsApp. Jangan dipakai sebagai data sungguhan.');
        if (! $this->option('paksa') && $this->ask('Ketik HAPUS untuk melanjutkan') !== 'HAPUS') {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        $this->info('Mengosongkan database...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->output->write(Artisan::output());

        Storage::disk('local')->deleteDirectory('lampiran');
        Storage::disk('local')->deleteDirectory('backup');
        Artisan::call('cache:clear'); // nama peran & pengaturan WhatsApp tersimpan di cache

        $this->info('Mengisi data contoh (butuh beberapa detik)...');
        app()->instance('demo.allowed', true);
        Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->output->write(Artisan::output());

        $this->info('Mengirim riwayat laporan ke Inventaris...');
        Artisan::call('halodek:kirim-ulang-inventaris');
        $this->output->write(Artisan::output());

        $this->newLine();
        $this->info('Selesai. Masuk dengan NIK = password (karyawan cukup NIK):');
        $this->table(['NIK', 'Peran'], [
            ['00001', 'Superadmin'], ['00011', 'Manajer'], ['00002', 'Kepala Regu IT'], ['00003', 'Kepala Regu Sarana dan Prasarana'],
            ['00006', 'Kepala Regu Kesekretariatan'], ['00015', 'Kepala Regu Keamanan'], ['00007', 'Staf IT'], ['00004', 'Karyawan'],
        ]);
        $this->line('WhatsApp dalam keadaan Mati. Isi No. WhatsApp di profil akun yang ingin dicoba, lalu aktifkan di Administrasi › WhatsApp.');

        return self::SUCCESS;
    }
}
