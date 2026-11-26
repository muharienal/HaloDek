<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_MANAJER = 'manajer';
    public const ROLE_KEPALA_REGU = 'kepala_regu';
    public const ROLE_STAF = 'staf';
    public const ROLE_KARYAWAN = 'karyawan';

    /**
     * Role pengelola (punya akses area admin, masuk dengan NIK + password). Karyawan hanya melapor.
     * Manajer = supervisi: melihat semua regu tanpa menangani laporan.
     */
    public const ADMIN_ROLES = [
        self::ROLE_SUPERADMIN,
        self::ROLE_MANAJER,
        self::ROLE_KEPALA_REGU,
        self::ROLE_STAF,
    ];

    /** Role yang menangani laporan (mengambil, membalas, menyelesaikan). */
    public const ACT_ROLES = [self::ROLE_SUPERADMIN, self::ROLE_KEPALA_REGU, self::ROLE_STAF];

    /** Role yang wajib terikat ke satu regu. */
    public const TEAM_ROLES = [self::ROLE_KEPALA_REGU, self::ROLE_STAF];

    protected $fillable = [
        'nik', 'name', 'phone', 'wa_off', 'department', 'position', 'division_id', 'department_id', 'position_id',
        'role', 'team_id', 'password', 'must_change_password', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'wa_off' => 'array',
    ];

    /** Nomor HP otomatis disimpan dalam format 62xxxxxxxxxx (siap dipakai Fonnte). */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v === null || trim((string) $v) === '' ? null : (Phone::normalize((string) $v) ?? trim((string) $v)),
        );
    }

    /** Nomor WhatsApp yang valid (62...), atau null bila belum diisi / tidak valid. */
    public function waNumber(): ?string
    {
        return Phone::normalize($this->phone);
    }

    /** Pengguna ini mau menerima kabar WhatsApp jenis ini (aturan peran + pilihan pribadinya)? */
    public function wantsWa(string $event, ?string $priority = null): bool
    {
        return $this->is_active
            && \App\Support\WaRules::roleWants((string) $this->role, $event, $priority)
            && ! in_array($event, (array) ($this->wa_off ?? []), true);
    }

    /** Cache per-instance untuk daftar ID regu yang ditangani. */
    private ?array $handledTeamIds = null;

    // ---------------------------------------------------------------- relasi

    /** Regu tempat Kepala Regu / Staf bertugas. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /** Bidang. (Nama kolom teks lamanya "department" tetap dipakai sebagai salinan nama.) */
    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /** "Divisi › Bidang" untuk tampilan ringkas. */
    public function orgPath(): string
    {
        return collect([$this->division?->name, $this->bidang?->name ?? $this->department])->filter()->unique()->implode(' › ');
    }

    protected static function booted(): void
    {
        // Pilihan struktur organisasi -> salinan nama teks (dipakai tampilan, ekspor, API Inventaris).
        // Bidang menentukan divisinya, agar keduanya tidak pernah bertentangan.
        static::saving(function (User $u) {
            if ($u->isDirty('department_id')) {
                $b = $u->department_id ? Department::find($u->department_id) : null;
                $u->department = $b?->name;
                if ($b?->division_id) {
                    $u->division_id = $b->division_id;
                }
            }
            if ($u->isDirty('position_id')) {
                $u->position = $u->position_id ? Position::whereKey($u->position_id)->value('name') : null;
            }
        });
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function assignedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'assigned_to_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    // ------------------------------------------------------------- password

    /** Password sementara acak (12 karakter, huruf + angka, tanpa karakter yang mirip). */
    public static function temporaryPassword(int $length = 12): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $letters.$digits;

        $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }

    /** Password acak yang tidak diketahui siapa pun (akun karyawan masuk lewat NIK saja). */
    public static function unusablePassword(): string
    {
        return bin2hex(random_bytes(24));
    }

    // ----------------------------------------------------------------- role

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isManajer(): bool
    {
        return $this->role === self::ROLE_MANAJER;
    }

    /** Boleh menangani laporan & mengubah data (semua pengelola kecuali manajer supervisi). */
    public function canAct(): bool
    {
        return in_array($this->role, self::ACT_ROLES, true);
    }

    public function isHead(): bool
    {
        return $this->role === self::ROLE_KEPALA_REGU;
    }

    public function isStaf(): bool
    {
        return $this->role === self::ROLE_STAF;
    }

    /** Pengelola = Superadmin, Kepala Regu, atau Staf. */
    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function isKaryawan(): bool
    {
        return $this->role === self::ROLE_KARYAWAN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return config("halodek.roles.{$this->role}", ucfirst((string) $this->role));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

        return mb_strtoupper($first.$second);
    }

    // ----------------------------------------------------------------- regu

    /**
     * ID regu yang boleh ditangani user ini.
     * Superadmin & Manajer = semua regu, Kepala Regu/Staf = regunya sendiri, karyawan = tidak ada.
     */
    public function handledTeamIds(): array
    {
        if ($this->handledTeamIds !== null) {
            return $this->handledTeamIds;
        }

        if ($this->isSuperadmin() || $this->isManajer()) {
            return $this->handledTeamIds = Team::pluck('id')->all();
        }

        if (! $this->isAdmin() || ! $this->team_id) {
            return $this->handledTeamIds = [];
        }

        return $this->handledTeamIds = [(int) $this->team_id];
    }

    public function forgetHandledTeams(): void
    {
        $this->handledTeamIds = null;
    }

    public function handlesTeam(int|Team|null $team): bool
    {
        if ($team === null) {
            return false;
        }

        $id = $team instanceof Team ? $team->id : $team;

        return in_array($id, $this->handledTeamIds(), true);
    }

    /** Superadmin, atau Kepala Regu dari regu tersebut. */
    public function leadsTeam(int|Team|null $team): bool
    {
        if ($team === null) {
            return false;
        }

        $id = $team instanceof Team ? $team->id : $team;

        return $this->isSuperadmin() || ($this->isHead() && (int) $this->team_id === (int) $id);
    }

    // --------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', self::ADMIN_ROLES);
    }
}
