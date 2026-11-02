<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Lampiran disimpan di disk privat (storage/app/lampiran) dan hanya bisa
 * diakses lewat route yang dicek hak aksesnya — tidak perlu storage:link.
 */
class AttachmentService
{
    /**
     * @param  array<UploadedFile>  $files
     */
    public function storeMany(Report $report, array $files, string $kind, ?User $by = null): void
    {
        foreach (array_filter($files) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $report->attachments()->create([
                'uploaded_by' => $by?->id,
                'kind' => $kind,
                'path' => $file->store('lampiran/'.now()->format('Y/m'), 'local'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }
}
