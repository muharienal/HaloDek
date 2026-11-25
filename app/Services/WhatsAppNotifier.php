<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WhatsApp satu arah:
 *  - ke manajer (WA_TO) saat ada laporan BARU masuk (semua regu);
 *  - ke pelapor (nomor yang ia isi sendiri) saat laporannya diproses, selesai, atau ditolak.
 * Gagal kirim tidak pernah mengganggu pembuatan laporan; dikirim setelah respons selesai.
 */
class WhatsAppNotifier
{
    /**
     * Laporan baru:
     *  - prioritas tinggi/mendesak (WA_KEPALA_PRIORITAS) -> Kepala Regu regu tujuan (cadangan: WA_TO);
     *  - manajer (WA_TO) hanya bila WA_MANAJER=setiap; bawaannya ringkasan harian (DailyDigest).
     */
    public function newReport(Report $report): void
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg)) {
            return;
        }

        try {
            $sent = [];
            if (in_array($report->priority, $this->headPriorities($cfg), true)) {
                $sent = $this->headAlert($report, 'LAPORAN '.mb_strtoupper($report->priorityLabel()).' MASUK');
            }

            if (($cfg['manager_mode'] ?? 'harian') === 'setiap') {
                $targets = array_values(array_diff($this->managerNumbers($cfg), $sent));
                if ($targets) {
                    $this->queue($targets, $this->message($report));
                }
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp laporan baru gagal disiapkan: '.$e->getMessage());
        }
    }

    /**
     * Pesan penting untuk Kepala Regu regu laporan (nomor dari profilnya). Bila belum ada Kepala Regu
     * dengan nomor WA, dikirim ke WA_TO agar tidak hilang. Mengembalikan nomor tujuan.
     *
     * @return array<int, string>
     */
    public function headAlert(Report $report, string $headline, ?string $note = null): array
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg)) {
            return [];
        }

        try {
            $report->loadMissing(['team', 'user', 'category']);
            $targets = \App\Models\User::active()->where('team_id', $report->team_id)->where('role', \App\Models\User::ROLE_KEPALA_REGU)
                ->get()->map->waNumber()->filter()->unique()->values()->all();
            if (! $targets) {
                $targets = $this->managerNumbers($cfg);
            }
            if ($targets) {
                $this->queue($targets, $this->headMessage($report, $headline, $note));
            }

            return $targets;
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp Kepala Regu gagal disiapkan: '.$e->getMessage());

            return [];
        }
    }

    public function headMessage(Report $report, string $headline, ?string $note = null): string
    {
        $report->loadMissing(['team', 'user', 'category']);
        $desc = trim(preg_replace('/\s+/', ' ', (string) ($note ?? $report->description)));
        $desc = mb_strlen($desc) > 200 ? mb_substr($desc, 0, 200).'…' : $desc;

        $lines = [
            '*'.$headline.'*',
            '',
            '*'.mb_strtoupper($report->title).'*',
            'No. laporan: '.$report->report_number,
            'Regu: Regu '.($report->team->name ?? '-'),
            'Prioritas: '.$report->priorityLabel(),
        ];
        if ($report->location_name) {
            $lines[] = 'Lokasi: '.$report->location_name;
        }
        $lines[] = 'Pelapor: '.($report->reporter_name ?: ($report->user->name ?? '-'));
        $lines[] = '';
        $lines[] = ($note !== null ? 'Alasan pelapor:' : 'Keterangan:');
        $lines[] = $desc;
        $lines[] = '';
        $lines[] = 'Tangani di HaloDek:';
        $lines[] = route('admin.reports.show', $report);

        return implode("\n", $lines);
    }

    /** Kirim teks bebas ke nomor manajer (WA_TO). Dipakai ringkasan harian & pesan uji. Langsung, tidak ditunda. */
    public function sendToManagers(string $message): int
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg)) {
            return 0;
        }

        $sent = 0;
        foreach ($this->managerNumbers($cfg) as $to) {
            try {
                $this->send($cfg, $to, $message);
                $sent++;
            } catch (Throwable $e) {
                Log::warning('WhatsApp ke manajer gagal: '.$e->getMessage());
            }
        }

        return $sent;
    }

    /** @return array<int, string> */
    public function managerNumbers(?array $cfg = null): array
    {
        $cfg ??= config('services.whatsapp');

        return array_values(array_unique(array_filter(array_map(
            fn ($n) => \App\Support\Phone::normalize($n) ?? trim($n),
            explode(',', (string) ($cfg['to'] ?? ''))
        ))));
    }

    /** @return array<int, string> */
    private function headPriorities(array $cfg): array
    {
        return $cfg['head_priorities'] ?? ['tinggi', 'mendesak'];
    }

    /** Kabar status ke pelapor. Hanya untuk status diproses/selesai/ditolak dan bila pelapor mengisi nomor WA. */
    public function reporterUpdate(Report $report): void
    {
        if (! $this->enabled(config('services.whatsapp')) || ! in_array($report->status, ['diproses', 'selesai', 'ditolak'], true)) {
            return;
        }

        try {
            $report->loadMissing(['user', 'team', 'assignee']);
            $to = $report->user?->waNumber();
            if ($to) {
                $this->queue([$to], $this->reporterMessage($report));
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp ke pelapor gagal disiapkan: '.$e->getMessage());
        }
    }

    /**
     * Pengingat ke Kepala Regu (nomor di profil) untuk laporan yang lewat batas respons.
     * Bila tak satu pun Kepala Regu punya nomor, dikirim ke WA_TO. Dikirim langsung (dipanggil dari perintah terjadwal).
     *
     * @param  \Illuminate\Support\Collection<int, Report>  $reports
     * @return int jumlah nomor yang berhasil dikirimi
     */
    public function overdueReminder(\App\Models\Team $team, \Illuminate\Support\Collection $reports): int
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg) || $reports->isEmpty()) {
            return 0;
        }

        $targets = \App\Models\User::active()->where('team_id', $team->id)->where('role', \App\Models\User::ROLE_KEPALA_REGU)
            ->get()->map->waNumber()->filter()->unique()->values()->all();
        if (! $targets) {
            $targets = array_filter(array_map(fn ($n) => \App\Support\Phone::normalize($n) ?? trim($n), explode(',', (string) $cfg['to'])));
        }

        $message = $this->overdueMessage($team, $reports);
        $sent = 0;
        foreach (array_unique($targets) as $to) {
            try {
                $this->send($cfg, $to, $message);
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Pengingat WhatsApp gagal: '.$e->getMessage());
            }
        }

        return $sent;
    }

    /** @param  \Illuminate\Support\Collection<int, Report>  $reports */
    public function overdueMessage(\App\Models\Team $team, \Illuminate\Support\Collection $reports): string
    {
        $lines = [
            '*PENGINGAT: LAPORAN BELUM DIAMBIL*',
            '',
            'Regu '.$team->name.', '.$reports->count().' laporan sudah melewati batas respons:',
        ];
        foreach ($reports->take(10)->values() as $i => $r) {
            $lines[] = '';
            $lines[] = ($i + 1).'. '.$r->report_number.' · '.$r->priorityLabel();
            $lines[] = '   '.\Illuminate\Support\Str::limit($r->title, 80).' (menunggu '.$this->hoursText($r->waitingWorkHours()).')';
            $lines[] = '   '.$r->publicUrl();
        }
        if ($reports->count() > 10) {
            $lines[] = '';
            $lines[] = '…dan '.($reports->count() - 10).' laporan lain.';
        }
        $lines[] = '';
        $lines[] = 'Mohon segera diambil atau ditugaskan di HaloDek.';

        return implode("\n", $lines);
    }

    private function hoursText(float $h): string
    {
        return ($h == (int) $h ? (int) $h : str_replace('.', ',', (string) $h)).' jam kerja';
    }

    public function reporterMessage(Report $report): string
    {
        $report->loadMissing(['user', 'team', 'assignee']);
        $first = \Illuminate\Support\Str::words($report->user->name ?? 'Karyawan', 1, '');
        $note = trim(preg_replace('/\s+/', ' ', (string) $report->follow_up_note));
        $note = mb_strlen($note) > 200 ? mb_substr($note, 0, 200).'…' : $note;

        [$head, $intro, $noteLabel] = match ($report->status) {
            'selesai' => ['LAPORAN ANDA SUDAH SELESAI', 'laporan Anda sudah selesai ditangani.', 'Catatan penyelesaian'],
            'ditolak' => ['LAPORAN ANDA TIDAK DAPAT DIPROSES', 'mohon maaf, laporan Anda tidak dapat dilanjutkan.', 'Alasan'],
            default => ['LAPORAN ANDA SEDANG DIPROSES', 'laporan Anda sedang ditangani.', 'Catatan penanggung jawab'],
        };

        $lines = [
            '*'.$head.'*',
            '',
            'Halo '.$first.', '.$intro,
            '',
            '*'.mb_strtoupper($report->title).'*',
            'No. laporan: '.$report->report_number,
            'Regu: Regu '.($report->team->name ?? '-'),
        ];
        if ($report->status === 'diproses' && $report->assignee) {
            $lines[] = 'Penanggung jawab: '.$report->assignee->name;
        }
        if ($note !== '') {
            $lines[] = $noteLabel.': '.$note;
        }
        if ($report->status === 'selesai') {
            $lines[] = '';
            $lines[] = 'Belum beres? Buka laporan ini di HaloDek (menu Laporan Saya), lalu pilih *Belum beres* dalam '.config('halodek.reopen_days').' hari.';
        }
        $lines[] = '';
        $lines[] = 'Pantau status terkini:';
        $lines[] = $report->publicUrl();

        return implode("\n", $lines);
    }

    private function enabled(array $cfg): bool
    {
        return ($cfg['driver'] ?? 'off') !== 'off';
    }

    /** Kirim setelah respons ke browser selesai; gagal kirim hanya dicatat di log. */
    private function queue(array $targets, string $message): void
    {
        $cfg = config('services.whatsapp');

        // Callback "terminating" bisa terpanggil lagi bila aplikasi melayani >1 request (tes/Octane):
        // tanda $done mencegah pesan yang sama terkirim ulang.
        $done = false;
        app()->terminating(function () use ($cfg, $targets, $message, &$done) {
            if ($done) {
                return;
            }
            $done = true;
            foreach ($targets as $to) {
                try {
                    $this->send($cfg, $to, $message);
                } catch (Throwable $e) {
                    Log::warning('Notifikasi WhatsApp gagal: '.$e->getMessage());
                }
            }
        });
    }

    public function message(Report $report): string
    {
        $report->loadMissing(['team', 'user', 'category']);

        $reporter = $report->reporter_name ?: ($report->user->name ?? '-');
        $meta = array_filter([$report->user->nik ?? null ? 'NIK '.$report->user->nik : null, $report->user->department ?? null]);
        $desc = trim(preg_replace('/\s+/', ' ', (string) $report->description));
        $desc = mb_strlen($desc) > 200 ? mb_substr($desc, 0, 200).'…' : $desc;

        $lines = [
            '*LAPORAN BARU MASUK*',
            '',
            '*'.mb_strtoupper($report->title).'*',
            'No. laporan: '.$report->report_number,
            'Regu tujuan: Regu '.($report->team->name ?? '-'),
            'Kategori: '.$report->categoryDisplayName(),
            'Prioritas: '.$report->priorityLabel(),
        ];
        if ($report->location_name) {
            $lines[] = 'Lokasi: '.$report->location_name;
        }
        if ($report->assetLabel()) {
            $lines[] = 'Barang: '.$report->asset_name.($report->asset_code ? ' ('.$report->asset_code.')' : '');
        }
        $lines[] = '';
        $lines[] = 'Pelapor: '.$reporter.($meta ? ' ('.implode(', ', $meta).')' : '');
        $lines[] = 'Waktu: '.$report->created_at->translatedFormat('d M Y, H:i').' WIB';
        $lines[] = '';
        $lines[] = 'Keterangan:';
        $lines[] = $desc;
        $lines[] = '';
        $lines[] = 'Pantau status terkini:';
        $lines[] = $report->publicUrl();

        return implode("\n", $lines);
    }

    private function send(array $cfg, string $to, string $message): void
    {
        match ($cfg['driver']) {
            'log' => Log::info("[WA ke {$to}]\n{$message}"),
            'fonnte' => $this->check(Http::timeout($cfg['timeout'])->withHeaders(['Authorization' => $cfg['token']])
                ->asForm()->post('https://api.fonnte.com/send', ['target' => $to, 'message' => $message, 'countryCode' => '62'])),
            'wablas' => $this->check(Http::timeout($cfg['timeout'])
                ->withHeaders(['Authorization' => $cfg['token'].($cfg['secret'] ? '.'.$cfg['secret'] : '')])
                ->asForm()->post(rtrim($cfg['wablas_url'], '/').'/api/send-message', ['phone' => $to, 'message' => $message])),
            default => null,
        };
    }

    private function check($response): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }
    }
}
