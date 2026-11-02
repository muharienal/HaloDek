<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman pantau laporan TANPA login, hanya-baca. Diakses lewat tautan bertanda tangan
 * (token pendek turunan APP_KEY, dicek dengan hash_equals) yang dikirim di notifikasi WhatsApp; tautan yang
 * diubah sedikit pun ditolak (404). Menampilkan ringkasan laporan + foto bukti/hasil penanganan;
 * tanpa NIK, balasan, maupun catatan internal.
 */
class PublicReportController extends Controller
{
    /** Jenis foto yang boleh tampil di halaman publik (balasan/komentar tidak). */
    private const PUBLIC_KINDS = ['bukti', 'penyelesaian'];

    public function show(Report $report, string $token): View
    {
        $this->guard($report, $token);
        $report->load(['team', 'category', 'location', 'item', 'assignee', 'user']);

        $activities = $report->activities()
            ->where('is_internal', false)
            ->whereIn('type', ['created', 'status', 'assigned', 'claimed', 'released', 'priority', 'transferred', 'cancelled'])
            ->get();

        $photos = $report->attachments()->whereIn('kind', self::PUBLIC_KINDS)->where('mime', 'like', 'image/%')->orderBy('id')->get();

        return view('reports.public', [
            'report' => $report,
            'activities' => $activities,
            'evidence' => $photos->where('kind', 'bukti')->values(),
            'results' => $photos->where('kind', 'penyelesaian')->values(),
        ]);
    }

    public function photo(Report $report, string $token, ReportAttachment $attachment): StreamedResponse
    {
        $this->guard($report, $token);
        $allowed = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true];
        $mime = strtolower((string) $attachment->mime);

        abort_unless(
            $attachment->report_id === $report->id
                && in_array($attachment->kind, self::PUBLIC_KINDS, true)
                && isset($allowed[$mime])
                && Storage::disk('local')->exists($attachment->path),
            404
        );

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /** Token salah = 404 (bukan 403) agar keberadaan laporan tidak terbongkar. */
    private function guard(Report $report, string $token): void
    {
        abort_unless(hash_equals($report->publicToken(), $token), 404);
    }
}
