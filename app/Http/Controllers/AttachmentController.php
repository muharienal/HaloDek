<?php

namespace App\Http\Controllers;

use App\Models\ReportAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /** Lampiran hanya dapat dibuka oleh pelapor dan admin regu terkait. */
    public function show(Request $request, ReportAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->report);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // Hanya tipe yang diizinkan yang boleh tampil di browser; selain itu selalu diunduh sebagai biner.
        $safe = [
            'image/jpeg' => true, 'image/png' => true, 'image/webp' => true,
        ];
        $mime = strtolower((string) $attachment->mime);
        $inlineOk = isset($safe[$mime]);

        $headers = [
            'Content-Type' => $inlineOk ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ];

        return ($request->boolean('unduh') || ! $inlineOk)
            ? Storage::disk('local')->download($attachment->path, $attachment->original_name, $headers)
            : Storage::disk('local')->response($attachment->path, $attachment->original_name, $headers);
    }
}
