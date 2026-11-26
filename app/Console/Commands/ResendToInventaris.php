<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\InventoryClient;
use Illuminate\Console\Command;

/**
 * Kirim ulang semua laporan yang terkait aset ke Inventaris. Dipakai sekali setelah pembaruan
 * (agar laporan lama ikut menjadi riwayat perbaikan), atau setelah Inventaris sempat mati.
 * Aman diulang: Inventaris memperbarui data per nomor laporan.
 */
class ResendToInventaris extends Command
{
    protected $signature = 'halodek:kirim-ulang-inventaris';

    protected $description = 'Kirim ulang laporan yang terkait aset ke Inventaris (riwayat aset & perbaikan)';

    public function handle(InventoryClient $inventory): int
    {
        if (! $inventory->enabled()) {
            $this->error('Inventaris belum diatur (INVENTARIS_URL / INVENTARIS_API_TOKEN).');

            return self::FAILURE;
        }

        $ok = $fail = 0;
        Report::with(['user', 'team', 'assignee', 'category'])->whereNotNull('asset_id')->orderBy('id')
            ->chunkById(100, function ($reports) use ($inventory, &$ok, &$fail) {
                foreach ($reports as $r) {
                    $inventory->pushReport($r) ? $ok++ : $fail++;
                }
            });

        $this->info("Terkirim: {$ok}. Gagal: {$fail}.");
        if ($fail) {
            $this->warn('Yang gagal biasanya aset yang sudah dihapus di Inventaris. Detail ada di storage/logs.');
        }

        return self::SUCCESS;
    }
}
