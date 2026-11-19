<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Team;
use App\Services\InventoryClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tujuan QR yang ditempel di barang (stiker dicetak dari aplikasi Inventaris): /q/{id}.
 * {id} = ID aset di Inventaris; datanya diambil lewat API.
 *
 * - Karyawan (dan admin yang memilih "Buat laporan") -> form lapor dengan regu, lokasi, barang,
 *   dan tebakan kategori sudah terisi. Barang yang dipindai boleh dilaporkan walau bukan pegangannya.
 * - Admin regu pemilik barang -> halaman aset di Inventaris (bila alamatnya diatur).
 * Belum login -> diarahkan ke login, lalu kembali ke sini.
 */
class ScanController extends Controller
{
    public function __invoke(Request $request, int $item, InventoryClient $inventory): RedirectResponse
    {
        $user = $request->user();
        $asset = $inventory->asset($item);

        if (! $asset) {
            return redirect()->route('employee.reports.create')
                ->with('error', $inventory->enabled()
                    ? 'Barang dari QR ini tidak ditemukan di Inventaris. Silakan isi laporan secara manual.'
                    : 'Data barang sedang tidak bisa diambil dari Inventaris. Silakan isi laporan secara manual.');
        }

        $team = Team::active()->where('slug', (string) ($asset['unit'] ?? ''))->first();

        if ($team && $user->isAdmin() && $user->handlesTeam($team->id) && ! $request->boolean('lapor')) {
            return redirect()->away($inventory->assetWebUrl($item) ?? route('admin.reports.index'));
        }

        if (empty($asset['active']) || ! $team) {
            return redirect()->route('dashboard')->with('error', "Barang {$asset['code']} sudah tidak aktif. Silakan ajukan laporan biasa bila masih ada kendala.");
        }

        // Izinkan barang ini dipilih di form lapor (lihat Employee\ReportController::itemOptions).
        $request->session()->put('qr_item', $item);

        return redirect()->route('employee.reports.create', array_filter([
            'regu' => $team->slug,
            'kategori' => $this->guessCategory($team, (string) ($asset['category'] ?? '')),
            'lokasi' => $asset['location']['id'] ?? null,
            'barang' => $item,
        ]));
    }

    /**
     * Tebak kategori laporan dari kategori aset, mis. "Laptop & PC" -> "Komputer & Laptop",
     * "Jaringan" -> "Internet & WiFi" (lewat kata kunci). Tidak ketemu -> pelapor memilih sendiri.
     */
    private function guessCategory(Team $team, string $assetCategory): ?int
    {
        $categories = Category::active()->where('team_id', $team->id)->where('allow_custom', false)->orderBy('sort_order')->get();
        $source = mb_strtolower($assetCategory);
        if ($source === '' || $categories->isEmpty()) {
            return null;
        }

        if ($same = $categories->first(fn ($c) => mb_strtolower($c->name) === $source)) {
            return $same->id;
        }

        $words = array_filter(preg_split('/[^\pL\pN]+/u', $source), fn ($w) => mb_strlen($w) >= 3);
        foreach ($words as $w) {
            $hit = $categories->first(fn ($c) => preg_match('/\b'.preg_quote($w, '/').'\b/u', mb_strtolower($c->name.', '.$c->keywords)));
            if ($hit) {
                return $hit->id;
            }
        }

        return null;
    }
}
