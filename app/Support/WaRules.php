<?php

namespace App\Support;

use App\Models\User;

/**
 * Aturan kabar WhatsApp per peran (diatur Superadmin di Administrasi > WhatsApp) dan
 * pilihan pribadi tiap pengguna (Profil). Seseorang menerima sebuah kabar bila:
 * peran mengizinkan + pengguna tidak mematikannya + punya No. WhatsApp + akun aktif.
 */
class WaRules
{
    public const OFF = 'off';
    public const PENTING = 'penting';
    public const SEMUA = 'semua';

    /**
     * key => [judul, penjelasan, peran yang bisa menerima, bertingkat?]
     * "bertingkat" = pilihan Tidak / Prioritas penting saja / Semua; lainnya hanya aktif/mati.
     */
    public const EVENTS = [
        'baru' => ['Laporan baru', 'Masuk ke regu, termasuk yang dialihkan.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU, User::ROLE_STAF], true],
        'tugas' => ['Ditugaskan ke saya', 'Ditunjuk sebagai penanggung jawab.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU, User::ROLE_STAF], false],
        'keberatan' => ['Banding & dibuka lagi', 'Pelapor banding atau menyatakan belum beres.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU, User::ROLE_STAF], false],
        'lewat_batas' => ['Lewat batas respons', 'Laporan belum diambil melewati batas waktu.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU, User::ROLE_STAF], false],
        'ringkasan' => ['Ringkasan harian', 'Rekap sekali sehari pada hari kerja.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU], false],
        'laporan_saya' => ['Status laporan saya', 'Laporan yang ia buat diproses, selesai, atau ditolak.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU, User::ROLE_STAF, User::ROLE_KARYAWAN], false],
        'balasan' => ['Balasan baru', 'Ada balasan di laporan atau tugasnya.',
            [User::ROLE_SUPERADMIN, User::ROLE_MANAJER, User::ROLE_KEPALA_REGU, User::ROLE_STAF, User::ROLE_KARYAWAN], false],
    ];

    /** Bawaan bila Superadmin belum pernah menyimpan aturan (mengikuti perilaku lama .env). */
    public static function defaults(): array
    {
        $cfg = config('services.whatsapp', []);
        $mode = $cfg['manager_mode'] ?? 'harian';
        $on = fn (bool $v) => $v ? '1' : '0';

        return [
            User::ROLE_SUPERADMIN => ['baru' => self::OFF, 'tugas' => '1', 'keberatan' => '0', 'lewat_batas' => '0', 'ringkasan' => '0', 'laporan_saya' => '1', 'balasan' => '0'],
            User::ROLE_MANAJER => ['baru' => $mode === 'setiap' ? self::SEMUA : self::OFF, 'tugas' => '1', 'keberatan' => '0', 'lewat_batas' => '0', 'ringkasan' => $on($mode === 'harian'), 'laporan_saya' => '1', 'balasan' => '0'],
            User::ROLE_KEPALA_REGU => ['baru' => self::PENTING, 'tugas' => '1', 'keberatan' => '1', 'lewat_batas' => '1', 'ringkasan' => '0', 'laporan_saya' => '1', 'balasan' => '0'],
            User::ROLE_STAF => ['baru' => self::OFF, 'tugas' => '1', 'keberatan' => '1', 'lewat_batas' => '0', 'laporan_saya' => '1', 'balasan' => '0'],
            User::ROLE_KARYAWAN => ['laporan_saya' => '1', 'balasan' => '0'],
        ];
    }

    /** Aturan berlaku: bawaan ditimpa yang disimpan (kunci tak dikenal diabaikan). */
    public static function all(): array
    {
        $saved = (array) (config('services.whatsapp.rules') ?? []);
        $out = [];
        foreach (self::defaults() as $role => $events) {
            foreach ($events as $event => $default) {
                $v = $saved[$role][$event] ?? $default;
                $out[$role][$event] = self::clean($event, (string) $v, $default);
            }
        }

        return $out;
    }

    public static function applies(string $role, string $event): bool
    {
        return isset(self::EVENTS[$event]) && in_array($role, self::EVENTS[$event][2], true);
    }

    public static function leveled(string $event): bool
    {
        return (bool) (self::EVENTS[$event][3] ?? false);
    }

    /** Nilai aturan peran: '0'/'1', atau off/penting/semua untuk kabar bertingkat. */
    public static function value(string $role, string $event): string
    {
        return self::all()[$role][$event] ?? '0';
    }

    /** Peran ini menerima kabar ini (untuk prioritas tertentu bila bertingkat)? */
    public static function roleWants(string $role, string $event, ?string $priority = null): bool
    {
        if (! self::applies($role, $event)) {
            return false;
        }
        $v = self::value($role, $event);
        if (! self::leveled($event)) {
            return $v === '1';
        }

        return $v === self::SEMUA || ($v === self::PENTING && in_array($priority, self::importantPriorities(), true));
    }

    /** Peran yang menerima kabar ini, dipakai untuk menyaring query penerima. */
    public static function rolesFor(string $event, ?string $priority = null): array
    {
        return array_values(array_filter(array_keys(self::defaults()), fn ($r) => self::roleWants($r, $event, $priority)));
    }

    /** @return array<int, string> */
    public static function importantPriorities(): array
    {
        return array_values((array) (config('services.whatsapp.head_priorities') ?? ['tinggi', 'mendesak']));
    }

    /** WhatsApp sedang menyala (Fonnte aktif atau mode uji)? */
    public static function active(): bool
    {
        $cfg = config('services.whatsapp', []);

        return ($cfg['driver'] ?? 'off') === 'log' || (($cfg['driver'] ?? 'off') === 'fonnte' && trim((string) ($cfg['token'] ?? '')) !== '');
    }

    /** Kalimat "kapan" untuk ajakan mengisi nomor WA, mengikuti kabar yang menyala untuk peran ini. */
    public static function promptPhrase(string $role): string
    {
        $parts = [];
        foreach (array_keys(self::personalChoices($role)) as $k) {
            $parts[] = match ($k) {
                'laporan_saya' => 'laporan Anda diproses, selesai, atau ditolak',
                'balasan' => 'ada balasan di laporan Anda',
                'baru' => 'ada laporan baru di regu Anda',
                'tugas' => 'Anda ditugaskan menangani laporan',
                'keberatan' => 'pelapor banding atau menyatakan belum beres',
                'lewat_batas' => 'ada laporan lewat batas respons',
                'ringkasan' => 'ringkasan harian dikirim',
                default => null,
            };
        }
        $parts = array_values(array_filter($parts));
        if (count($parts) > 1) {
            $last = array_pop($parts);

            return 'saat '.implode(', saat ', $parts).', dan saat '.$last;
        }

        return $parts ? 'saat '.$parts[0] : '';
    }

    /** Kabar yang bisa dipilih sendiri oleh pengguna dengan peran ini (yang tidak dimatikan Superadmin). */
    public static function personalChoices(string $role): array
    {
        $out = [];
        foreach (self::EVENTS as $key => [$label, $hint]) {
            if (! self::applies($role, $key)) {
                continue;
            }
            $v = self::value($role, $key);
            if ($v === '0' || $v === self::OFF) {
                continue;
            }
            $out[$key] = $label.($key === 'baru' && $v === self::PENTING ? ' (prioritas '.implode('/', array_map(fn ($p) => config("halodek.priorities.$p.label", $p), self::importantPriorities())).')' : '');
        }

        return $out;
    }

    /**
     * Validasi & rapikan isian form aturan. Peran/kabar yang tidak berlaku diabaikan.
     *
     * @return array<string, array<string, string>>
     */
    public static function fromInput(array $input): array
    {
        $out = [];
        foreach (self::defaults() as $role => $events) {
            foreach (array_keys($events) as $event) {
                $raw = $input[$role][$event] ?? null;
                $out[$role][$event] = self::leveled($event)
                    ? self::clean($event, (string) $raw, self::OFF)
                    : ($raw ? '1' : '0');
            }
        }

        return $out;
    }

    private static function clean(string $event, string $v, string $default): string
    {
        if (self::leveled($event)) {
            return in_array($v, [self::OFF, self::PENTING, self::SEMUA], true) ? $v : $default;
        }

        return in_array($v, ['0', '1'], true) ? $v : $default;
    }
}
