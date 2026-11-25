<?php

namespace App\Console\Commands;

use App\Services\DailyDigest;
use Illuminate\Console\Command;

/**
 * Kirim ringkasan harian ke manajer (WA_TO) sekarang. Biasanya TIDAK perlu dijadwalkan:
 * aplikasi mengirimnya sendiri setelah WA_RINGKASAN_JAM saat ada yang membuka HaloDek.
 */
class SendDailyDigest extends Command
{
    protected $signature = 'halodek:ringkasan-harian {--uji : Tampilkan isinya saja, tanpa mengirim}';

    protected $description = 'Kirim ringkasan laporan hari ini ke WhatsApp manajer (WA_TO)';

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
            : $this->warn('Ringkasan tidak terkirim. Periksa WA_DRIVER, WA_TO, WA_TOKEN, dan log.');

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}
