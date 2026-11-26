<?php

namespace App\Models;

use App\Support\WorkHours;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    public const OPEN_STATUSES = ['menunggu', 'diproses'];
    public const CLOSED_STATUSES = ['selesai', 'ditolak', 'dibatalkan'];

    protected $fillable = [
        'report_number', 'user_id', 'reporter_name', 'team_id', 'category_id', 'custom_category',
        'asset_id', 'asset_code', 'asset_name', 'inventory_location_id', 'location_name', 'assigned_to_id', 'title', 'description', 'priority', 'status',
        'follow_up_note', 'first_response_at', 'resolved_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'satisfied' => 'boolean',
        'reminded_at' => 'datetime',
        'rejected_at' => 'datetime',
        'appealed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Laporan dihapus (soft delete, nomor tetap tercatat agar tidak dipakai ulang):
        // foto/lampirannya ikut dihapus dari disk karena tidak ada menu pemulihan.
        static::deleted(function (Report $r) {
            $r->attachments()->get()->each->delete();
        });

        // Laporan yang terkait barang dikabarkan ke Inventaris (riwayat aset) saat dibuat / berganti status.
        static::saved(function (Report $r) {
            if ($r->asset_id && ($r->wasRecentlyCreated || $r->wasChanged(['status', 'title', 'asset_id', 'assigned_to_id', 'follow_up_note', 'team_id', 'category_id']))) {
                app(\App\Services\InventoryClient::class)->pushReportLater($r);
            }
        });

        // Durasi jam kerja dihitung sekali saat waktunya berubah, agar statistik cukup AVG() di database.
        static::saving(function (Report $r) {
            $created = $r->created_at ?? now();
            if ($r->isDirty(['first_response_at', 'created_at']) || ($r->first_response_at && $r->respond_work_minutes === null)) {
                $r->respond_work_minutes = $r->first_response_at ? WorkHours::minutesBetween($created, $r->first_response_at) : null;
            }
            if ($r->isDirty(['resolved_at', 'created_at']) || ($r->resolved_at && $r->resolve_work_minutes === null)) {
                $r->resolve_work_minutes = $r->resolved_at ? WorkHours::minutesBetween($created, $r->resolved_at) : null;
            }
        });
    }

    // ---------------------------------------------------------------- relasi

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Laporan utama, bila laporan ini ditutup karena ganda. */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'duplicate_of_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ReportActivity::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    /** Barang terkait (salinan dari Inventaris saat laporan dibuat), mis. "IT-LPT-001 · Laptop Lenovo". */
    public function assetLabel(): ?string
    {
        if (! $this->asset_id && blank($this->asset_name)) {
            return null;
        }

        return trim(($this->asset_code ? $this->asset_code.' · ' : '').$this->asset_name, ' ·') ?: null;
    }

    // ---------------------------------------------------------------- scopes

    /** Batasi laporan sesuai hak akses user. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->whereIn('team_id', $user->handledTeamIds() ?: [0]);
        }

        return $query->where('user_id', $user->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** Laporan aktif yang belum ada penanggung jawabnya (antrean regu). */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->open()->whereNull('assigned_to_id');
    }

    // --------------------------------------------------------------- helper

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** Pelapor masih boleh membuka kembali laporan selesai selama batas hari ini belum lewat. */
    /** Pelapor boleh banding sekali atas laporan yang ditolak, paling lama reopen_days hari setelahnya. */
    public function canStillAppeal(): bool
    {
        return $this->status === 'ditolak'
            && $this->duplicate_of_id === null // laporan ganda tidak dibanding: masalahnya ditangani di laporan utama
            && $this->appealed_at === null
            && $this->rejected_at
            && $this->rejected_at->gt(now()->subDays((int) config('halodek.reopen_days', 7)));
    }

    public function canStillReopen(): bool
    {
        return $this->status === 'selesai'
            && $this->resolved_at
            && $this->resolved_at->gt(now()->subDays((int) config('halodek.reopen_days', 7)));
    }

    /** Potongan deskripsi satu baris, pembeda laporan yang judul otomatisnya sama. */
    public function summary(int $limit = 90): string
    {
        return \Illuminate\Support\Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $this->description)), $limit);
    }

    public function categoryDisplayName(): string
    {
        return $this->custom_category ?: ($this->category->name ?? '-');
    }

    public function statusLabel(): string
    {
        return config("halodek.statuses.{$this->status}.label", ucfirst($this->status));
    }

    public function statusTone(): string
    {
        return config("halodek.statuses.{$this->status}.tone", 'gray');
    }

    public function priorityLabel(): string
    {
        return config("halodek.priorities.{$this->priority}.label", ucfirst($this->priority));
    }

    public function priorityTone(): string
    {
        return config("halodek.priorities.{$this->priority}.tone", 'gray');
    }

    /** Lama penyelesaian dalam JAM KERJA (null bila belum selesai). */
    public function resolutionHours(): ?float
    {
        if (! $this->resolved_at) {
            return null;
        }
        $minutes = $this->resolve_work_minutes ?? WorkHours::minutesBetween($this->created_at, $this->resolved_at);

        return round($minutes / 60, 1);
    }

    /** Lama penyelesaian untuk tampilan, mis. "kurang dari 1 jam kerja". */
    public function resolutionText(): string
    {
        return $this->resolved_at ? WorkHours::human($this->resolve_work_minutes ?? WorkHours::minutesBetween($this->created_at, $this->resolved_at)) : '-';
    }

    /** Lama sampai respons pertama untuk tampilan (jam kerja). */
    public function responseText(): string
    {
        return $this->first_response_at ? WorkHours::human($this->respond_work_minutes ?? WorkHours::minutesBetween($this->created_at, $this->first_response_at)) : '-';
    }

    /** Batas respons: waktu paling lambat laporan diambil/ditugaskan (dihitung dalam jam kerja). */
    public function responseDeadline(): Carbon
    {
        $hours = (float) config('halodek.response_sla.'.$this->priority, config('halodek.response_sla.sedang', 4));

        return WorkHours::addHours($this->created_at, $hours);
    }

    /** Lewat batas = masih menunggu di antrean (belum ada penanggung jawab) melewati batas respons. */
    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->assigned_to_id === null && now()->gt($this->responseDeadline());
    }

    /** Sudah berapa jam kerja laporan menunggu sejak dibuat. */
    public function waitingWorkHours(): float
    {
        return round(WorkHours::minutesBetween($this->created_at, now()) / 60, 1);
    }

    /** Token pendek (16 hex) untuk halaman pantau tanpa login; diturunkan dari APP_KEY. */
    public function publicToken(): string
    {
        return substr(hash_hmac('sha256', 'pantau|'.$this->id, (string) config('app.key')), 0, 16);
    }

    /** Tautan pendek halaman pantau: /p/{id}/{token}. */
    public function publicUrl(): string
    {
        return route('reports.public', ['report' => $this->id, 'token' => $this->publicToken()]);
    }
}
