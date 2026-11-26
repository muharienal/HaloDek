<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengaturan aplikasi di database (tabel settings). Nilai rahasia (token) disimpan terenkripsi
 * dengan APP_KEY. Nilai disimpan di cache sampai ada perubahan.
 */
class Settings
{
    private const CACHE_KEY = 'halodek:settings';

    /** Kunci yang disimpan terenkripsi. */
    private const SECRET = ['wa.token'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @param array<string, ?string> $values */
    public static function putMany(array $values): void
    {
        $now = now();
        foreach ($values as $key => $value) {
            if ($value !== null && in_array($key, self::SECRET, true)) {
                $value = Crypt::encryptString($value);
            }
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => $now, 'created_at' => $now]);
        }
        self::forget();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Token tersimpan tetapi tidak bisa dibuka (APP_KEY berganti): perlu diisi ulang.
     */
    public static function secretUnreadable(string $key): bool
    {
        $raw = self::raw()[$key] ?? null;

        return $raw !== null && $raw !== '' && self::decrypt($raw) === null;
    }

    /** @return array<string, ?string> */
    public static function all(): array
    {
        $out = [];
        foreach (self::raw() as $key => $value) {
            $out[$key] = in_array($key, self::SECRET, true) && $value !== null && $value !== '' ? self::decrypt($value) : $value;
        }

        return $out;
    }

    /** @return array<string, ?string> */
    private static function raw(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')->all());
        } catch (Throwable) {
            return []; // tabel belum ada (sebelum migrate) atau database mati: pakai .env
        }
    }

    private static function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
