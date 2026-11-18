<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pencarian global: nomor tiket, judul, dan pelapor (nama/NIK).
 * Hasil selalu dibatasi hak akses lewat Report::visibleTo (karyawan = laporannya sendiri, regu = regunya).
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        // Buang karakter wildcard LIKE agar input pengguna tidak menjadi pola.
        $q = trim((string) preg_replace('/[%_\\\\]+/', ' ', mb_substr((string) $request->query('q'), 0, 60)));
        $reports = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $reports = Report::query()
                ->visibleTo($user)
                ->where(function ($w) use ($like, $user) {
                    $w->where('report_number', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('reporter_name', 'like', $like);
                    if ($user->isAdmin()) {
                        $w->orWhereHas('user', fn ($u) => $u->where('nik', 'like', $like));
                    }
                })
                ->with(['team', 'category'])
                ->latest()
                ->limit(30)
                ->get();
        }

        return view('search.index', ['q' => $q, 'reports' => $reports, 'tooShort' => $q !== '' && mb_strlen($q) < 2]);
    }
}
