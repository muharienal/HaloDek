<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat jejak audit untuk aksi penting admin (pengguna, regu, aset, dsb.).
 */
class Audit
{
    public static function log(string $action, string $description, ?Model $subject = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'ip_address' => request()?->ip(),
        ]);
    }
}
