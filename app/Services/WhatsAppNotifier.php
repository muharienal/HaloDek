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
    public function newReport(Report $report): void
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg) || trim((string) $cfg['to']) === '') {
            return;
        }

        $targets = array_filter(array_map(fn ($n) => \App\Support\Phone::normalize($n) ?? trim($n), explode(',', (string) $cfg['to'])));
        $this->queue($targets, $this->message($report));
    }

    /** Kabar status ke pelapor. Hanya untuk status diproses/selesai/ditolak dan bila pelapor mengisi nomor WA. */
    public function reporterUpdate(Report $report): void
    {
        if (! $this->enabled(config('services.whatsapp')) || ! in_array($report->status, ['diproses', 'selesai', 'ditolak'], true)) {
            return;
        }

        $report->loadMissing(['user', 'team', 'assignee']);
        $to = $report->user?->waNumber();
        if (! $to) {
            return;
        }

        $this->queue([$to], $this->reporterMessage($report));
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

        dispatch(function () use ($cfg, $targets, $message) {
            foreach ($targets as $to) {
                try {
                    $this->send($cfg, $to, $message);
                } catch (Throwable $e) {
                    Log::warning('Notifikasi WhatsApp gagal: '.$e->getMessage());
                }
            }
        })->afterResponse();
    }

    public function message(Report $report): string
    {
        $report->loadMissing(['team', 'user', 'category', 'location', 'item']);

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
        if ($report->location) {
            $lines[] = 'Lokasi: '.$report->location->fullName();
        }
        if ($report->item) {
            $lines[] = 'Perangkat/aset: '.$report->item->name.($report->item->code ? ' ('.$report->item->code.')' : '');
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
