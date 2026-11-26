<?php

namespace App\Support;

/**
 * Pengaturan WhatsApp (Fonnte) yang diatur Superadmin di Administrasi > WhatsApp.
 * Bila sudah pernah disimpan di aplikasi, nilainya mengalahkan WA_* di .env.
 */
class WhatsAppSettings
{
    public const DRIVERS = ['fonnte' => 'Aktif', 'log' => 'Mode uji', 'off' => 'Mati'];

    public static function fromApp(): bool
    {
        return Settings::has('wa.driver');
    }

    /** Dipanggil saat aplikasi mulai (AppServiceProvider). */
    public static function apply(): void
    {
        $s = Settings::all();
        if (! array_key_exists('wa.driver', $s)) {
            return; // belum pernah diatur di aplikasi: tetap memakai .env
        }

        $map = [
            'driver' => $s['wa.driver'] ?? 'off',
            'token' => (string) ($s['wa.token'] ?? ''),
            'digest_at' => $s['wa.digest_at'] ?? '16:00',
            'head_priorities' => array_values(array_filter(explode(',', (string) ($s['wa.head_priorities'] ?? '')))),
        ];
        if (isset($s['wa.manager_mode'])) {
            $map['manager_mode'] = $s['wa.manager_mode']; // simpanan versi lama: dasar aturan bawaan Manajer
        }
        $rules = json_decode((string) ($s['wa.rules'] ?? ''), true);
        if (is_array($rules)) {
            $map['rules'] = $rules;
        }
        foreach ($map as $k => $v) {
            config(["services.whatsapp.{$k}" => $v]);
        }
    }
}
