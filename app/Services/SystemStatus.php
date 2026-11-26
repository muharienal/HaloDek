<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pemeriksaan kesehatan aplikasi untuk halaman "Status Sistem" (Superadmin).
 * Setiap pemeriksaan berdiri sendiri: satu yang gagal tidak menghentikan yang lain.
 *
 * Hasil: [ judul bagian => [ ['label', 'state' => ok|warn|error|info, 'detail'], ... ] ]
 */
class SystemStatus
{
    /** Tabel yang dipakai aplikasi (dicek satu per satu, termasuk kerusakan "doesn't exist in engine"). */
    public const TABLES = [
        'users', 'teams', 'categories', 'reports', 'report_activities', 'report_attachments',
        'user_notifications', 'announcements', 'audit_logs', 'migrations', 'failed_jobs',
    ];

    /** @return array<string, array<int, array{label:string, state:string, detail:string}>> */
    public function all(): array
    {
        return [
            'Database' => $this->guard(fn () => $this->database()),
            'Koneksi ke aplikasi lain' => $this->guard(fn () => [$this->inventaris(), $this->whatsapp()]),
            'Server & penyimpanan' => $this->guard(fn () => $this->server()),
            'Tugas otomatis' => $this->guard(fn () => $this->tasks()),
            'Pengaturan' => $this->guard(fn () => $this->settings()),
        ];
    }

    /** @return array<int, array{label:string, state:string, detail:string}> */
    public function database(): array
    {
        $rows = [];
        try {
            $pdo = DB::connection()->getPdo();
            $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $rows[] = $this->row('Koneksi database', 'ok', DB::getDriverName().' '.$version.' · '.DB::getDatabaseName());
        } catch (Throwable $e) {
            return [$this->row('Koneksi database', 'error', 'Tidak terhubung: '.$this->short($e->getMessage()).' Pastikan MySQL di Laragon menyala.')];
        }

        $broken = [];
        $missing = [];
        foreach (self::TABLES as $t) {
            try {
                DB::table($t)->limit(1)->exists();
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                str_contains($msg, 'doesn\'t exist in engine') || str_contains($msg, '1932') || str_contains($msg, 'is marked as crashed')
                    ? $broken[] = $t
                    : $missing[] = $t;
            }
        }
        $rows[] = $broken
            ? $this->row('Tabel rusak', 'error', implode(', ', $broken).'. Datanya tidak bisa dibaca MySQL. Pulihkan dari backup atau buat ulang tabelnya (lihat README, bagian error 500).')
            : $this->row('Tabel rusak', 'ok', 'Tidak ada. '.count(self::TABLES).' tabel terbaca normal.');
        if ($missing) {
            $rows[] = $this->row('Tabel hilang', 'error', implode(', ', $missing).'. Jalankan php artisan migrate.');
        }

        try {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $pending = array_values(array_diff($files, $migrator->getRepository()->getRan()));
            $rows[] = $pending
                ? $this->row('Pembaruan database', 'warn', count($pending).' belum dijalankan. Jalankan: php artisan migrate')
                : $this->row('Pembaruan database', 'ok', 'Semua migrasi sudah dijalankan.');
        } catch (Throwable $e) {
            $rows[] = $this->row('Pembaruan database', 'warn', 'Tidak bisa dicek: '.$this->short($e->getMessage()));
        }

        $legacy = array_values(array_filter(\App\Console\Commands\DropLegacyInventory::TABLES, fn ($t) => $this->hasTable($t)));
        if ($legacy) {
            $rows[] = $this->row('Tabel inventaris lama', 'info', implode(', ', $legacy).' masih ada (tidak dipakai lagi). Setelah data dipindah ke Inventaris, hapus dengan: php artisan halodek:hapus-tabel-lama');
        }

        return $rows;
    }

    /** @return array{label:string, state:string, detail:string} */
    public function inventaris(): array
    {
        $url = (string) config('services.inventaris.url');
        if ($url === '') {
            return $this->row('Inventaris', 'info', 'Tidak dipakai (INVENTARIS_URL kosong). Form laporan tetap jalan tanpa pilihan lokasi/barang.');
        }
        if (mb_strlen((string) config('services.inventaris.token')) < 16) {
            return $this->row('Inventaris', 'error', 'INVENTARIS_API_TOKEN kosong atau terlalu pendek (minimal 16 karakter).');
        }

        try {
            $start = microtime(true);
            $res = Http::baseUrl($url.'/api/v1/')->withToken((string) config('services.inventaris.token'))->acceptJson()
                ->timeout(5)->connectTimeout(3)->get('ping');
            $ms = (int) round((microtime(true) - $start) * 1000);
        } catch (Throwable $e) {
            return $this->row('Inventaris', 'error', "Tidak bisa dihubungi di {$url}. Pastikan Inventaris berjalan dan alamatnya benar.");
        }

        return match (true) {
            $res->successful() => $this->row('Inventaris', $ms > 2000 ? 'warn' : 'ok', "Terhubung ke {$url} ({$ms} ms).".($ms > 2000 ? ' Lambat.' : '')),
            $res->status() === 401 => $this->row('Inventaris', 'error', 'Token ditolak. INVENTARIS_API_TOKEN harus sama persis di kedua aplikasi.'),
            $res->status() === 503 => $this->row('Inventaris', 'error', 'Token API belum diatur di .env Inventaris.'),
            default => $this->row('Inventaris', 'error', "{$url} membalas HTTP {$res->status()}. Periksa INVENTARIS_URL."),
        };
    }

    /** @return array{label:string, state:string, detail:string} */
    public function whatsapp(): array
    {
        $cfg = config('services.whatsapp');
        $driver = $cfg['driver'] ?? 'off';
        $withWa = \App\Models\User::active()->whereNotNull('phone')->where('phone', '!=', '')->get(['id', 'phone'])->filter(fn ($u) => $u->waNumber())->count();
        $summary = "{$withWa} pengguna aktif punya No. WhatsApp.";
        $menu = ' Atur di Administrasi › WhatsApp.';

        if ($driver === 'log') {
            return $this->row('WhatsApp', 'info', 'Mode uji: pesan hanya ditulis ke log, tidak terkirim. '.$summary);
        }
        if ($driver !== 'fonnte') {
            return $this->row('WhatsApp', 'info', ($driver === 'off' ? 'Nonaktif.' : "Pengirim \"{$driver}\" tidak dikenal (hanya Fonnte), WhatsApp dianggap mati.").$menu);
        }
        if (blank($cfg['token'] ?? null)) {
            return $this->row('WhatsApp', 'error', 'Token Fonnte belum diisi.'.$menu);
        }

        try {
            $res = Http::timeout(6)->withHeaders(['Authorization' => $cfg['token']])->asForm()->post('https://api.fonnte.com/device');
            $j = (array) $res->json();
        } catch (Throwable $e) {
            return $this->row('WhatsApp', 'warn', 'Fonnte tidak bisa dihubungi dari server ini (internet?). '.$summary);
        }

        if (! $res->successful() || ($j['status'] ?? null) === false) {
            return $this->row('WhatsApp', 'error', 'Fonnte menolak token: '.$this->short((string) ($j['reason'] ?? $j['detail'] ?? 'HTTP '.$res->status())).'.'.$menu);
        }
        $connected = ($j['device_status'] ?? '') === 'connect';
        $quota = isset($j['quota']) ? ' Kuota: '.$j['quota'].'.' : '';

        return $connected
            ? $this->row('WhatsApp', 'ok', 'Fonnte terhubung'.(isset($j['device']) ? ' ('.$j['device'].')' : '').'.'.$quota.' '.$summary)
            : $this->row('WhatsApp', 'error', 'Perangkat Fonnte TERPUTUS. Buka fonnte.com > Device, lalu pindai ulang QR dengan HP.'.$quota);
    }

    /** @return array<int, array{label:string, state:string, detail:string}> */
    public function server(): array
    {
        $rows = [$this->row('PHP', version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'error', PHP_VERSION.' · '.PHP_OS_FAMILY)];

        $need = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'curl', 'zip'];
        $lack = array_values(array_filter($need, fn ($e) => ! extension_loaded($e)));
        $rows[] = $lack
            ? $this->row('Ekstensi PHP', 'error', 'Belum aktif: '.implode(', ', $lack).'. Aktifkan di Menu Laragon > PHP > Extensions.')
            : $this->row('Ekstensi PHP', 'ok', implode(', ', $need));

        $up = (string) ini_get('upload_max_filesize');
        $post = (string) ini_get('post_max_size');
        $rows[] = $this->row('Batas unggah', 'info', "{$up} per berkas, {$post} per kiriman (foto dikecilkan otomatis di browser).");

        $dirs = ['storage/logs', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/framework/views', 'storage/app'];
        $notWritable = array_values(array_filter($dirs, fn ($d) => ! is_dir(base_path($d)) || ! is_writable(base_path($d))));
        $rows[] = $notWritable
            ? $this->row('Folder penyimpanan', 'error', 'Tidak bisa ditulis / tidak ada: '.implode(', ', $notWritable))
            : $this->row('Folder penyimpanan', 'ok', 'Semua folder storage bisa ditulis.');

        $free = @disk_free_space(storage_path());
        if ($free !== false) {
            $gb = round($free / 1073741824, 1);
            $rows[] = $this->row('Sisa disk', $gb < 1 ? 'error' : ($gb < 5 ? 'warn' : 'ok'), str_replace('.', ',', (string) $gb).' GB');
        }

        $size = 0;
        foreach (glob(storage_path('app/lampiran/*/*/*')) ?: [] as $f) {
            $size += @filesize($f) ?: 0;
        }
        $rows[] = $this->row('Foto laporan', 'info', str_replace('.', ',', (string) round($size / 1048576, 1)).' MB');

        return $rows;
    }

    /** @return array<int, array{label:string, state:string, detail:string}> */
    public function tasks(): array
    {
        $when = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->translatedFormat('d M Y H:i') : null;

        $reminder = Cache::get('halodek:pengingat:terakhir');
        $cleanup = Cache::get('halodek:pembersihan:terakhir');
        $digest = app(DailyDigest::class)->lastSent();

        return [
            $this->row('Pengingat lewat batas', config('halodek.auto_reminder', true) ? 'ok' : 'info',
                config('halodek.auto_reminder', true) ? 'Dicek tiap '.config('halodek.reminder_every_minutes', 15).' menit saat aplikasi dibuka. Terakhir: '.($when($reminder) ?? 'belum pernah') : 'Dimatikan.'),
            $this->row('Pembersihan data', 'ok', 'Sekali sehari. Terakhir: '.($when($cleanup) ?? 'belum pernah')),
            $this->row('Ringkasan harian manajer', 'info', ($digest ? 'Terakhir: '.$digest : 'Belum pernah terkirim').'. Perintah manual: php artisan halodek:ringkasan-harian'),
        ];
    }

    /** @return array<int, array{label:string, state:string, detail:string}> */
    public function settings(): array
    {
        $rows = [];
        $debug = (bool) config('app.debug');
        $rows[] = $this->row('Mode debug', $debug ? 'warn' : 'ok', $debug ? 'APP_DEBUG=true: detail error tampil ke pengguna. Matikan setelah selesai memeriksa.' : 'Mati (aman).');

        $appUrl = rtrim((string) config('app.url'), '/');
        $current = rtrim(request()->getSchemeAndHttpHost(), '/');
        $rows[] = $appUrl !== $current
            ? $this->row('APP_URL', 'warn', "Diatur {$appUrl}, tetapi halaman ini dibuka lewat {$current}. Link di WhatsApp memakai alamat yang dibuka pengguna atau APP_URL; pastikan alamat itu bisa dibuka dari HP.")
            : $this->row('APP_URL', 'ok', $appUrl);

        $rows[] = request()->isSecure()
            ? $this->row('HTTPS', 'ok', 'Koneksi terenkripsi.')
            : $this->row('HTTPS', 'warn', 'Masih http://. Password terkirim tanpa enkripsi, dan tombol "Pasang Aplikasi" belum bisa sekali klik (browser hanya mengizinkannya lewat HTTPS). Cara mengaktifkan: README bagian "Mengaktifkan HTTPS".');

        return $rows;
    }

    /**
     * Error terakhir dari storage/logs (tanpa jejak tumpukan).
     *
     * @return array<int, array{time:string, level:string, message:string}>
     */
    public function recentErrors(int $limit = 10): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $found = [];
        foreach (array_slice($files, 0, 3) as $file) {
            $size = @filesize($file) ?: 0;
            $fh = @fopen($file, 'rb');
            if (! $fh) {
                continue;
            }
            fseek($fh, max(0, $size - 512 * 1024)); // cukup 512 KB terakhir
            $chunk = (string) stream_get_contents($fh);
            fclose($fh);

            if (preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $chunk, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    $msg = preg_replace('/\s*\{"(userId|exception)".*$/', '', $hit[3]);
                    $found[] = ['time' => $hit[1], 'level' => $hit[2], 'message' => $this->short((string) $msg, 400)];
                }
            }
            if (count($found) >= $limit) {
                break;
            }
        }
        usort($found, fn ($a, $b) => strcmp($b['time'], $a['time']));

        return array_slice($found, 0, $limit);
    }

    /** @return array{label:string, state:string, detail:string} */
    private function row(string $label, string $state, string $detail): array
    {
        return ['label' => $label, 'state' => $state, 'detail' => $detail];
    }

    /** @return array<int, array{label:string, state:string, detail:string}> */
    private function guard(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            return [$this->row('Pemeriksaan', 'error', 'Gagal diperiksa: '.$this->short($e->getMessage()))];
        }
    }

    private function hasTable(string $t): bool
    {
        try {
            return Schema::hasTable($t);
        } catch (Throwable) {
            return false;
        }
    }

    private function short(string $s, int $max = 220): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max).'…' : $s;
    }
}
