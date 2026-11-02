<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCsvImport;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocationController extends Controller
{
    use HandlesCsvImport;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $locations = Location::query()
            ->withCount(['items', 'reports'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('building', 'like', "%{$search}%")
                ->orWhere('pic_name', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(config('halodek.per_page'))
            ->withQueryString();

        return view('admin.locations.index', compact('locations', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $location = Location::create($this->validated($request));
        Audit::log('location.create', "Menambah lokasi {$location->name}.", $location);

        return back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request, $location->id));
        Audit::log('location.update', "Mengubah lokasi {$location->name}.", $location);

        return back()->with('success', 'Lokasi berhasil diperbarui.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->items()->exists() || $location->reports()->exists()) {
            return back()->with('error', 'Lokasi masih dipakai oleh item inventaris atau laporan, sehingga tidak dapat dihapus.');
        }

        Audit::log('location.delete', "Menghapus lokasi {$location->name}.", $location);
        $location->delete();

        return back()->with('success', 'Lokasi berhasil dihapus.');
    }

    public function template(): StreamedResponse
    {
        return Csv::download('template-import-lokasi.csv', ['name', 'building', 'floor', 'pic_name', 'note'], [
            ['Ruang Rapat Utama', 'Gedung A', '2', 'Budi Santoso', ''],
        ]);
    }

    public function import(Request $request)
    {
        $result = $this->runImport($request, ['name'], function (array $d) {
            $v = Validator::make($d, [
                'name' => ['required', 'string', 'max:150', 'unique:locations,name'],
                'building' => ['nullable', 'string', 'max:100'],
                'floor' => ['nullable', 'string', 'max:30'],
                'pic_name' => ['nullable', 'string', 'max:150'],
                'note' => ['nullable', 'string', 'max:1000'],
            ])->validate();

            Location::create(array_map(fn ($x) => $x === '' ? null : $x, $v));
        });

        Audit::log('location.import', "Impor lokasi: {$result['ok']} berhasil.");

        return $this->importResponse($result, 'admin.locations.index', 'lokasi');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('locations', 'name')->ignore($ignoreId)],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:30'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Nama lokasi wajib diisi.',
            'name.unique' => 'Nama lokasi ini sudah ada.',
        ]);
    }
}
