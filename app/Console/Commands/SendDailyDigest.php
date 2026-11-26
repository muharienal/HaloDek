<?php

namespace App\Console\Commands;

use App\Services\DailyDigest;
use Illuminate\Console\Command;

/**
 * Kirim ringkasan harian sekarang. Biasanya TIDAK perlu dijadwalkan:
 * aplikasi mengirimnya sendiri setelah jam ringkasan saat ada yang membuka HaloDek.
 */
class SendDailyDigest extends Command
{
    protected $signature = 'halodek:ringkasan-harian {--uji : Tampilkan isinya saja, tanpa mengirim}';

    protected $description = 'Kirim ringkasan laporan hari ini ke WhatsApp';

    public function handle(DailyDigest $digest): int
    {
        $message = $digest->message(now());
        if ($message === null) {
            $this->info('Tidak ada laporan untuk diringkas hari ini.');

            return self::SUCCESS;
        }

        if ($this->option('uji')) {
            $this->line($message);

            return self::SUCCESS;
        }

        $sent = $digest->send(now());
        $sent > 0
            ? $this->info("Ringkasan terkirim ke {$sent} nomor.")
            : $this->warn('Ringkasan tidak terkirim. Periksa Administrasi › WhatsApp (status, token, penerima Ringkasan harian) dan log.');

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}
