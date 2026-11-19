<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien API ke aplikasi "Inventaris Umum & IT".
 *
 * Prinsip: HaloDek tidak boleh ikut mati bila Inventaris sedang mati.
 * Semua pemanggilan memakai batas waktu pendek; kegagalan dicatat ke log
 * dan dikembalikan sebagai hasil kosong/null.
 */
class InventoryClient
{
    public function enabled(): bool
    {
        return config('services.inventaris.url') !== '' && filled(config('services.inventaris.token'));
    }

    /** Alamat halaman web Inventaris (untuk tautan admin). */
    public function webUrl(string $path = ''): ?string
    {
        $base = config('services.inventaris.url');

        return $base !== '' ? $base.($path !== '' ? '/'.ltrim($path, '/') : '') : null;
    }

    public function assetWebUrl(int $id): ?string
    {
        return $this->webUrl('aset/'.$id);
    }

    /** @return array<int, array{id:int, name:string, label:string}> */
    public function locations(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        // Daftar lokasi jarang berubah: simpan 10 menit. Kegagalan tidak di-cache.
        $cached = Cache::get('inventaris:locations');
        if (is_array($cached)) {
            return $cached;
        }

        $rows = $this->get('locations');
        if ($rows === null) {
            return [];
        }

        $rows = array_values(array_map(fn ($l) => [
            'id' => (int) $l['id'], 'name' => (string) $l['name'], 'label' => (string) ($l['label'] ?? $l['name']),
        ], $rows));
        Cache::put('inventaris:locations', $rows, now()->addMinutes(10));

        return $rows;
    }

    /** @return array{id:int, name:string, label:string}|null */
    public function location(?int $id): ?array
    {
        if (! $id) {
            return null;
        }

        foreach ($this->locations() as $l) {
            if ($l['id'] === $id) {
                return $l;
            }
        }

        return null;
    }

    /**
     * Aset yang boleh dilaporkan oleh pelapor: dipegang NIK-nya, milik bersama di lokasi yang dipilih,
     * atau aset yang baru dipindai QR-nya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function assets(string $unit, ?string $holderNik, ?int $locationId, ?int $includeId = null): array
    {
        if (! $this->enabled() || $unit === '') {
            return [];
        }

        return $this->get('assets', array_filter([
            'unit' => $unit,
            'holder_nik' => $holderNik,
            'location_id' => $locationId,
            'include_id' => $includeId,
        ])) ?? [];
    }

    /** @return array<string, mixed>|null */
    public function asset(int $id): ?array
    {
        return $this->enabled() ? $this->get('assets/'.$id) : null;
    }

    /**
     * Kabarkan Inventaris bahwa ada laporan kendala untuk sebuah aset (dibuat atau berubah status),
     * agar riwayat aset di Inventaris ikut mencatatnya. Idempoten di sisi Inventaris.
     */
    public function pushReport(Report $report): bool
    {
        if (! $this->enabled() || ! $report->asset_id) {
            return false;
        }

        try {
            $response = $this->http()->post('report-events', [
                'report_id' => $report->id,
                'report_number' => $report->report_number,
                'asset_id' => $report->asset_id,
                'title' => $report->title,
                'status' => $report->status,
                'status_label' => $report->statusLabel(),
                'reporter_name' => $report->user?->name,
                'url' => route('admin.reports.show', $report),
                'reported_at' => $report->created_at?->toIso8601String(),
            ]);

            if (! $response->successful()) {
                Log::warning('Inventaris menolak report-event', ['report' => $report->report_number, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('Inventaris tidak dapat dihubungi (report-event)', ['report' => $report->report_number, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Kirim setelah respons dikirim ke browser agar pengguna tidak menunggu Inventaris. */
    public function pushReportLater(Report $report): void
    {
        if (! $this->enabled() || ! $report->asset_id) {
            return;
        }

        $id = $report->id;
        $sent = false; // callback "terminating" bisa terpanggil lagi bila aplikasi melayani >1 request (mis. Octane/tes)
        app()->terminating(function () use ($id, &$sent) {
            if ($sent) {
                return;
            }
            $sent = true;
            if ($r = Report::with('user')->find($id)) {
                app(self::class)->pushReport($r);
            }
        });
    }

    /** Cek koneksi untuk halaman status/README. */
    public function ping(): bool
    {
        return $this->enabled() && $this->get('ping') !== null;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.inventaris.url').'/api/v1/')
            ->withToken((string) config('services.inventaris.token'))
            ->acceptJson()
            ->timeout((int) config('services.inventaris.timeout', 4))
            ->connectTimeout(2);
    }

    /** @return array<mixed>|null  "data" dari respons, atau null bila gagal/404. */
    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = $this->http()->get($path, $query);
        } catch (Throwable $e) {
            Log::warning('Inventaris tidak dapat dihubungi', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            Log::warning('Inventaris membalas galat', ['path' => $path, 'status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        return is_array($json) && array_key_exists('data', $json) ? (is_array($json['data']) ? $json['data'] : null) : (is_array($json) ? $json : null);
    }
}
