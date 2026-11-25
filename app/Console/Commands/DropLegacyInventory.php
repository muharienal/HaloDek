<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Menghapus tabel inventaris versi lama (sebelum ada aplikasi Inventaris):
 * locations, inventory_categories, inventory_items, serta kolom lama reports.inventory_item_id / location_id.
 *
 * Aman: isi tabel lebih dulu disalin ke storage/app/backup/*.json. Jalankan SETELAH
 * `php artisan inventaris:impor-halodek` di aplikasi Inventaris (bila data lama masih dibutuhkan),
 * karena perintah impor itu membaca tabel-tabel ini.
 */
class DropLegacyInventory extends Command
{
    protected $signature = 'halodek:hapus-tabel-lama {--paksa : Lewati pertanyaan konfirmasi}';

    protected $description = 'Hapus tabel inventaris lama (locations, inventory_categories, inventory_items) setelah dicadangkan ke JSON';

    public const TABLES = ['inventory_items', 'inventory_categories', 'locations'];

    public function handle(): int
    {
        $present = array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));
        // SQLite (tes) tidak bisa melepas foreign key: kolom lama di sana dibiarkan.
        $oldColumns = DB::getDriverName() === 'sqlite' ? [] : array_values(array_filter(['inventory_item_id', 'location_id'], fn ($c) => Schema::hasColumn('reports', $c)));

        if (! $present && ! $oldColumns) {
            $this->info('Tidak ada tabel inventaris lama. Tidak ada yang perlu dihapus.');

            return self::SUCCESS;
        }

        $counts = [];
        foreach ($present as $t) {
            $counts[$t] = DB::table($t)->count();
            $this->line("  {$t}: {$counts[$t]} baris");
        }
        if ($oldColumns) {
            $this->line('  kolom lama di reports: '.implode(', ', $oldColumns));
        }

        if (array_sum($counts) > 0) {
            $this->warn('Tabel lama masih berisi data. Pastikan sudah dipindah ke Inventaris (php artisan inventaris:impor-halodek di folder Inventaris).');
        }
        if (! $this->option('paksa') && ! $this->confirm('Hapus tabel dan kolom di atas? Isinya dicadangkan dulu ke storage/app/backup.', false)) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        // 1) Cadangan JSON (termasuk pemetaan laporan -> barang/lokasi lama).
        $backup = ['dibuat' => now()->toIso8601String(), 'tabel' => []];
        foreach ($present as $t) {
            $backup['tabel'][$t] = DB::table($t)->orderBy('id')->get()->all();
        }
        if ($oldColumns) {
            $backup['tabel']['reports_lama'] = DB::table('reports')->where(function ($q) use ($oldColumns) {
                foreach ($oldColumns as $c) {
                    $q->orWhereNotNull($c);
                }
            })->get(array_merge(['id', 'report_number'], $oldColumns))->all();
        }
        $file = 'backup/inventaris-lama-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Cadangan: '.Storage::disk('local')->path($file));

        // 2) Lepas kolom lama di reports, lalu hapus tabelnya.
        try {
            if ($oldColumns) {
                Schema::table('reports', function ($table) use ($oldColumns) {
                    foreach ($oldColumns as $c) {
                        $table->dropForeign([$c]);
                    }
                });
                Schema::table('reports', fn ($table) => $table->dropColumn($oldColumns));
            }

            Schema::disableForeignKeyConstraints();
            foreach ($present as $t) {
                Schema::dropIfExists($t);
            }
            Schema::enableForeignKeyConstraints();
        } catch (Throwable $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Gagal menghapus: '.$e->getMessage());
            $this->line('Cadangan tetap tersimpan. Data laporan tidak diubah.');

            return self::FAILURE;
        }

        $this->info('Selesai. Tabel lama dihapus: '.($present ? implode(', ', $present) : '-').'.');

        return self::SUCCESS;
    }
}
