<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WhatsApp satu arah: hanya saat ada laporan BARU masuk (semua regu).
 * Gagal kirim tidak pernah mengganggu pembuatan laporan; dikirim setelah respons selesai.
 */
class WhatsAppNotifier
{
    public function newReport(Report $report): void
    {
        $cfg = config('services.whatsapp');
        if (($cfg['driver'] ?? 'off') === 'off' || trim((string) $cfg['to']) === '') {
            return;
        }

        $message = $this->message($report);
        $targets = array_filter(array_map('trim', explode(',', (string) $cfg['to'])));

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
            'No. tiket: '.$report->report_number,
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
