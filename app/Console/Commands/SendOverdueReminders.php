<?php

namespace App\Console\Commands;

use App\Services\OverdueReminder;
use Illuminate\Console\Command;

/**
 * Menjalankan pengingat secara manual (untuk uji coba). Dalam pemakaian biasa TIDAK perlu dijadwalkan:
 * aplikasi sudah menjalankannya sendiri saat ada yang membuka HaloDek.
 */
class SendOverdueReminders extends Command
{
    protected $signature = 'halodek:pengingat {--dry-run : Tampilkan saja, tanpa mengirim}';

    protected $description = 'Ingatkan Kepala Regu tentang laporan yang lewat batas respons (uji manual)';

    public function handle(OverdueReminder $reminder): int
    {
        if (! $reminder->isWorkTime()) {
            $this->info('Di luar jam kerja, tidak ada pengingat dikirim.');

            return self::SUCCESS;
        }

        $summary = $reminder->run((bool) $this->option('dry-run'));
        if (! $summary) {
            $this->info('Tidak ada laporan lewat batas yang perlu diingatkan.');
        }
        foreach ($summary as $row) {
            $this->line("Regu {$row['team']}: {$row['count']} laporan lewat batas".($this->option('dry-run') ? '.' : ", WhatsApp terkirim ke {$row['sent']} nomor."));
        }

        return self::SUCCESS;
    }
}
