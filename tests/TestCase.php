<?php

namespace Tests;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Beberapa pengguna dipakai bergantian dalam satu tes: hapus hash password sesi milik
     * pengguna sebelumnya agar AuthenticateSession tidak menganggapnya sesi yang dibajak.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }

    /** true = tiruan Inventaris membalas 500 (seolah server mati). */
    protected bool $inventarisDown = false;

    /**
     * Tiruan API aplikasi Inventaris (lihat InventoryApiController di aplikasi Inventaris):
     * aturan filter /assets sama dengan aslinya. Mengembalikan daftar POST report-events yang diterima.
     *
     * @param  array<int, array<string, mixed>>  $assets
     */
    protected function fakeInventaris(array $assets = [], ?array $locations = null): \ArrayObject
    {
        config(['services.inventaris.url' => 'http://inventaris.test', 'services.inventaris.token' => 'test-token']);
        \Illuminate\Support\Facades\Cache::forget('inventaris:locations');
        $locations ??= [['id' => 1, 'name' => 'Ruang Rapat A', 'label' => 'Ruang Rapat A (Gedung A, Lt. 2)']];
        $events = new \ArrayObject();

        \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $req) use ($assets, $locations, $events) {
            if ($this->inventarisDown) {
                return \Illuminate\Support\Facades\Http::response(null, 500);
            }
            if (($req->header('Authorization')[0] ?? '') !== 'Bearer test-token') {
                return \Illuminate\Support\Facades\Http::response(['message' => 'Token salah'], 401);
            }
            $path = parse_url($req->url(), PHP_URL_PATH);
            parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);

            if ($path === '/api/v1/locations') {
                return \Illuminate\Support\Facades\Http::response(['data' => $locations]);
            }
            if ($path === '/api/v1/assets') {
                $rows = array_values(array_filter($assets, fn ($a) => ($a['unit'] ?? null) === ($q['unit'] ?? '') && ($a['active'] ?? true) && (
                    (isset($q['holder_nik']) && ($a['holder']['nik'] ?? null) === $q['holder_nik'])
                    || (isset($q['location_id']) && empty($a['holder']) && ($a['location']['id'] ?? null) == $q['location_id'])
                    || (isset($q['include_id']) && $a['id'] == $q['include_id'])
                )));

                return \Illuminate\Support\Facades\Http::response(['data' => $rows]);
            }
            if (preg_match('#^/api/v1/assets/(\d+)$#', $path, $m)) {
                foreach ($assets as $a) {
                    if ($a['id'] == $m[1]) {
                        return \Illuminate\Support\Facades\Http::response(['data' => $a]);
                    }
                }

                return \Illuminate\Support\Facades\Http::response(['message' => 'Aset tidak ditemukan.'], 404);
            }
            if ($path === '/api/v1/report-events' && $req->method() === 'POST') {
                $events->append($req->data());

                return \Illuminate\Support\Facades\Http::response(['ok' => true], 201);
            }

            return \Illuminate\Support\Facades\Http::response([], 404);
        });

        return $events;
    }

    /** Bentuk data aset sama seperti API Inventaris. */
    protected function fakeAsset(int $id, string $unit, array $over = []): array
    {
        return array_replace([
            'id' => $id, 'code' => 'AST-'.$id, 'name' => 'Barang '.$id, 'label' => 'AST-'.$id.' · Barang '.$id,
            'unit' => $unit, 'category' => 'Laptop & PC', 'status' => 'digunakan', 'active' => true,
            'location' => ['id' => 1, 'name' => 'Ruang Rapat A', 'label' => 'Ruang Rapat A (Gedung A, Lt. 2)'],
            'holder' => null, 'url' => 'http://inventaris.test/aset/'.$id,
        ], $over);
    }

    protected function seedTeams(): void
    {
        $this->seed(TeamSeeder::class);
    }

    protected function user(string $role = User::ROLE_KARYAWAN, ?string $teamSlug = null, array $attrs = []): User
    {
        if ($teamSlug) {
            $attrs['team_id'] = Team::where('slug', $teamSlug)->value('id');
        }

        return User::factory()->role($role)->create($attrs);
    }
}
