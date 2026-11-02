<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCsvImport;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inventaris per regu. Setiap regu punya kategori sendiri (mis. Laptop, AC, Ruangan,
 * Software) dan di dalamnya item (barang, ruangan, lisensi, dst.).
 *
 *  - Lihat      : semua anggota regu (Superadmin: semua regu).
 *  - Item       : tambah & ubah oleh anggota regu; hapus oleh Kepala Regu / Superadmin.
 *  - Kategori   : kelola oleh Kepala Regu / Superadmin.
 */
class InventoryController extends Controller
{
    use HandlesCsvImport;

    // ----------------------------------------------------------------- daftar

    public function index(Request $request): View
    {
        $user = $request->user();
        $teams = $this->handledTeams();
        $team = $teams->firstWhere('id', $request->integer('team_id')) ?? $teams->first();

        abort_unless($team, 403, 'Anda belum terdaftar di regu mana pun.');

        $status = $request->query('status', 'semua');
        $search = trim((string) $request->query('q'));
        $categories = $team->inventoryCategories()->withCount('items')->get();
        $category = $categories->firstWhere('id', $request->integer('category'));

        $items = InventoryItem::with(['category', 'holder', 'location'])
            ->where('team_id', $team->id)
            ->when($category, fn ($q) => $q->where('inventory_category_id', $category->id))
            ->when($status === 'jatuh-tempo', fn ($q) => $q->dueSoon())
            ->when(! in_array($status, ['semua', 'jatuh-tempo'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhereHas('holder', fn ($h) => $h->where('name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
                ->orWhereHas('location', fn ($l) => $l->where('name', 'like', "%{$search}%"))))
            ->orderBy('code')
            ->paginate(config('halodek.per_page'))
            ->withQueryString();

        $totalItems = (int) $categories->sum('items_count');
        $locations = Location::orderBy('name')->get(['id', 'name']);
        $holders = User::active()->orderBy('name')->limit(1500)->get(['nik', 'name']);
        $canLead = $user->leadsTeam($team);

        return view('admin.inventory.index', compact(
            'teams', 'team', 'categories', 'category', 'items', 'status', 'search', 'totalItems', 'locations', 'holders', 'canLead'
        ));
    }

    public function show(InventoryItem $item): View
    {
        $this->authorizeTeam($item->team_id);

        $item->load(['team', 'category', 'holder', 'location']);
        $reports = $item->reports()->with(['category', 'team'])->latest()->paginate(10);

        return view('admin.inventory.show', compact('item', 'reports'));
    }

    // --------------------------------------------------------------- kategori

    public function storeCategory(Request $request): RedirectResponse
    {
        $team = $this->leadTeam((int) $request->input('team_id'));

        $category = $team->inventoryCategories()->create($this->validatedCategory($request, $team) + [
            'sort_order' => (int) $team->inventoryCategories()->max('sort_order') + 1,
        ]);
        Audit::log('inventory.category.create', "Menambah kategori inventaris {$category->name} di regu {$team->name}.", $category);

        return redirect()->route('admin.inventory.index', ['team_id' => $team->id, 'category' => $category->id])
            ->with('success', 'Kategori inventaris ditambahkan.');
    }

    public function updateCategory(Request $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        $team = $this->leadTeam($inventoryCategory->team_id);

        $inventoryCategory->update($this->validatedCategory($request, $team, $inventoryCategory->id));
        Audit::log('inventory.category.update', "Mengubah kategori inventaris {$inventoryCategory->name}.", $inventoryCategory);

        return back()->with('success', 'Kategori inventaris diperbarui.');
    }

    public function destroyCategory(InventoryCategory $inventoryCategory): RedirectResponse
    {
        $team = $this->leadTeam($inventoryCategory->team_id);

        if ($inventoryCategory->items()->exists()) {
            return back()->with('error', 'Kategori masih berisi item. Pindahkan atau hapus item-nya terlebih dahulu.');
        }

        Audit::log('inventory.category.delete', "Menghapus kategori inventaris {$inventoryCategory->name}.", $inventoryCategory);
        $inventoryCategory->delete();

        return redirect()->route('admin.inventory.index', ['team_id' => $team->id])->with('success', 'Kategori inventaris dihapus.');
    }

    // ------------------------------------------------------------------- item

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedItem($request);
        $item = InventoryItem::create($data);
        Audit::log('inventory.item.create', "Menambah inventaris {$item->code}.", $item);

        return back()->with('success', 'Item inventaris ditambahkan.');
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $this->authorizeTeam($item->team_id);

        $item->update($this->validatedItem($request, $item));
        Audit::log('inventory.item.update', "Mengubah inventaris {$item->code}.", $item);

        return back()->with('success', 'Item inventaris diperbarui.');
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $this->leadTeam($item->team_id);

        Audit::log('inventory.item.delete', "Menghapus inventaris {$item->code}.", $item);
        $item->delete();

        return back()->with('success', 'Item dihapus. Riwayat laporan terkait tetap tersimpan.');
    }

    // ------------------------------------------------------------ CSV (item)

    private const CSV_HEADER = ['category', 'code', 'name', 'status', 'holder_nik', 'location', 'acquired_at', 'due_date', 'note'];

    public function template(): StreamedResponse
    {
        return Csv::download('template-import-inventaris.csv', self::CSV_HEADER, [
            ['Laptop', 'AST-0147', 'Lenovo ThinkPad E14', 'digunakan', '00004', 'Ruang Finance', '2026-01-15', '2028-01-15', 'Garansi 2 tahun'],
            ['Ruangan', 'RG-001', 'Ruang Rapat Utama', 'tersedia', '', 'Gedung A Lt. 2', '', '2026-12-01', 'Kapasitas 20 orang'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $team = $this->handledTeams()->firstWhere('id', $request->integer('team_id')) ?? $this->handledTeams()->first();
        abort_unless($team, 403);

        $query = InventoryItem::with(['category', 'holder', 'location'])->where('team_id', $team->id)->orderBy('code');

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $i) {
                yield [
                    $i->category->name ?? '', $i->code, $i->name, $i->status, $i->holder->nik ?? '', $i->location->name ?? '',
                    $i->acquired_at?->toDateString(), $i->due_date?->toDateString(), $i->note,
                ];
            }
        })();

        return Csv::download('inventaris-'.$team->slug.'-'.now()->format('Ymd').'.csv', self::CSV_HEADER, $rows);
    }

    public function import(Request $request)
    {
        $team = $this->handledTeams()->firstWhere('id', $request->integer('team_id'));
        abort_unless($team, 403, 'Anda tidak menangani regu ini.');
        $statuses = array_keys(config('halodek.item_statuses'));

        $result = $this->runImport($request, ['category', 'code', 'name'], function (array $d) use ($team, $statuses) {
            $d['status'] = ($d['status'] ?? '') ?: 'tersedia';

            $v = Validator::make($d, [
                'category' => ['required', 'string', 'max:100'],
                'code' => ['required', 'string', 'max:50', Rule::unique('inventory_items', 'code')->where('team_id', $team->id)],
                'name' => ['required', 'string', 'max:150'],
                'status' => ['required', Rule::in($statuses)],
                'note' => ['nullable', 'string', 'max:1000'],
            ])->validate();

            $holder = null;
            if (! empty($d['holder_nik'])) {
                $holder = User::where('nik', $d['holder_nik'])->first();
                if (! $holder) {
                    throw ValidationException::withMessages(['holder_nik' => "NIK pemegang \"{$d['holder_nik']}\" tidak terdaftar."]);
                }
            }

            $category = InventoryCategory::firstOrCreate(['team_id' => $team->id, 'name' => $v['category']]);
            $location = ! empty($d['location']) ? Location::firstOrCreate(['name' => mb_substr($d['location'], 0, 150)]) : null;

            InventoryItem::create([
                'team_id' => $team->id,
                'inventory_category_id' => $category->id,
                'code' => $v['code'],
                'name' => $v['name'],
                'status' => $v['status'],
                'holder_id' => $holder?->id,
                'location_id' => $location?->id,
                'acquired_at' => $this->parseDate($d['acquired_at'] ?? null, 'acquired_at'),
                'due_date' => $this->parseDate($d['due_date'] ?? null, 'due_date'),
                'note' => ($v['note'] ?? null) ?: null,
            ]);
        });

        Audit::log('inventory.import', "Impor inventaris regu {$team->name}: {$result['ok']} berhasil.");

        if ($result['fatal']) {
            return redirect()->route('admin.inventory.index', ['team_id' => $team->id])->with('error', $result['fatal']);
        }

        $message = "{$result['ok']} item berhasil diimpor.".($result['errors'] ? ' '.count($result['errors']).' baris dilewati.' : '');

        return redirect()->route('admin.inventory.index', ['team_id' => $team->id])->with('success', $message)->with('importErrors', $result['errors']);
    }

    // ------------------------------------------------------------------- util

    /** Regu yang dipimpin user (Kepala Regu / Superadmin) atau 403. */
    private function leadTeam(?int $teamId): Team
    {
        abort_unless($teamId && auth()->user()->leadsTeam($teamId), 403, 'Hanya Kepala Regu atau Superadmin yang boleh melakukan ini.');

        return Team::findOrFail($teamId);
    }

    private function validatedCategory(Request $request, Team $team, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->where('team_id', $team->id)->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Kategori ini sudah ada di regu tersebut.',
        ]);
    }

    private function validatedItem(Request $request, ?InventoryItem $item = null): array
    {
        $teamId = $item?->team_id ?? (int) $request->input('team_id');
        $this->authorizeTeam($teamId);

        $data = $request->validate([
            'inventory_category_id' => ['required', 'integer', Rule::exists('inventory_categories', 'id')->where('team_id', $teamId)],
            'code' => ['required', 'string', 'max:50', Rule::unique('inventory_items', 'code')->where('team_id', $teamId)->ignore($item?->id)],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(config('halodek.item_statuses')))],
            'holder_nik' => ['nullable', 'string', 'max:50'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'acquired_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'inventory_category_id.required' => 'Pilih kategori inventaris.',
            'inventory_category_id.exists' => 'Kategori tidak termasuk regu ini.',
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode ini sudah dipakai di regu tersebut.',
            'name.required' => 'Nama wajib diisi.',
        ]);

        $holderId = null;
        if (! empty($data['holder_nik'])) {
            $holderId = User::where('nik', trim($data['holder_nik']))->value('id');
            if (! $holderId) {
                throw ValidationException::withMessages(['holder_nik' => 'NIK pemegang tidak terdaftar.']);
            }
        }
        unset($data['holder_nik']);

        return $data + ['holder_id' => $holderId, 'team_id' => $teamId];
    }
}
