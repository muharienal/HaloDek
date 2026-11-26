<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Nama & penjelasan peran. Kunci peran (superadmin, manajer, kepala_regu, staf, karyawan) dan hak
 * aksesnya tetap di aplikasi; Superadmin hanya boleh mengganti nama tampilan dan penjelasannya.
 */
class Roles
{
    public const CACHE_KEY = 'halodek:peran';

    /** Terapkan nama peran dari database ke config('halodek.roles'), sekali per request. */
    public static function boot(): void
    {
        foreach (self::overrides() as $role => $row) {
            if (array_key_exists($role, (array) config('halodek.roles'))) {
                config(["halodek.roles.{$role}" => $row['label']]);
                if ($row['description']) {
                    config(["halodek.role_descriptions.{$role}" => $row['description']]);
                }
            }
        }
    }

    public static function description(string $role): string
    {
        return (string) config("halodek.role_descriptions.{$role}", '');
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array{label:string, description:?string}> */
    private static function overrides(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('role_settings')->get()
                ->mapWithKeys(fn ($r) => [$r->role => ['label' => $r->label, 'description' => $r->description]])->all());
        } catch (Throwable) {
            return []; // tabel belum ada (sebelum migrate) atau database mati
        }
    }
}
