<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Team;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kategori laporan (jenis pengaduan) dikelola oleh Kepala Regu masing-masing atau Superadmin. */
class CategoryController extends Controller
{
    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeLead($team->id);

        $category = $team->categories()->create($this->validated($request, $team));
        Audit::log('category.create', "Menambah kategori {$category->name} di regu {$team->name}.", $category);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeLead($category->team_id);

        $category->update($this->validated($request, $category->team, $category->id));
        Audit::log('category.update', "Mengubah kategori {$category->name}.", $category);

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeLead($category->team_id);

        if ($category->reports()->withTrashed()->exists()) {
            return back()->with('error', 'Kategori sudah dipakai laporan. Nonaktifkan saja agar riwayat tetap utuh.');
        }

        Audit::log('category.delete', "Menghapus kategori {$category->name}.", $category);
        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    private function validated(Request $request, Team $team, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->where('team_id', $team->id)->ignore($ignoreId)],
            'allow_custom' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Kategori ini sudah ada di regu tersebut.',
        ]);

        $data['allow_custom'] = $request->boolean('allow_custom');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function authorizeLead(?int $teamId): void
    {
        abort_unless($teamId && auth()->user()->leadsTeam($teamId), 403, 'Hanya Kepala Regu atau Superadmin yang dapat mengelola kategori.');
    }
}
