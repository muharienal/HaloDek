<?php

namespace App\Console\Commands;

use App\Services\Maintenance;
use Illuminate\Console\Command;

/** Menjalankan pembersihan secara manual. Biasanya tidak perlu: aplikasi menjalankannya sendiri sekali sehari. */
class CleanUp extends Command
{
    protected $signature = 'halodek:bersihkan {--dry-run : Hitung saja, tanpa menghapus}';

    protected $description = 'Bersihkan notifikasi & log audit lama serta foto yang tidak terpakai';

    public function handle(Maintenance $maintenance): int
    {
        $r = $maintenance->run((bool) $this->option('dry-run'));
        $verb = $this->option('dry-run') ? 'akan dihapus' : 'dihapus';

        $this->line("Notifikasi lama {$verb}: {$r['notifications']}");
        $this->line("Log audit lama {$verb}: {$r['audit_logs']}");
        $this->line("Foto laporan terhapus {$verb}: {$r['deleted_report_files']}");
        $this->line("Berkas foto yatim {$verb}: {$r['orphan_files']}");

        return self::SUCCESS;
    }
}
