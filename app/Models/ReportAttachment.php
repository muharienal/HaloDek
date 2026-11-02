<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportAttachment extends Model
{
    protected $fillable = ['report_id', 'uploaded_by', 'kind', 'path', 'original_name', 'mime', 'size'];

    protected static function booted(): void
    {
        // Berkas fisik ikut terhapus saat record dihapus (mis. hard delete).
        static::deleting(function (self $attachment) {
            Storage::disk('local')->delete($attachment->path);
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : number_format($kb, 0).' KB';
    }
}
