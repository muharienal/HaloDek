<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Data pegawai untuk aplikasi Inventaris. HaloDek adalah sumber tunggal data orang (NIK, nama,
 * divisi, bidang, jabatan, No. WA); Inventaris hanya memberi akses ke orang dari daftar ini.
 * Semua peran dikirim (pengelola juga bisa memegang aset). Tanpa password atau data rahasia lain.
 * Yang nonaktif ikut dikirim (active=false) agar Inventaris ikut menonaktifkannya.
 */
class EmployeeApiController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['ok' => true, 'app' => 'HaloDek', 'time' => now()->toIso8601String()]);
    }

    public function index(): JsonResponse
    {
        $rows = User::orderBy('nik')
            ->with('division:id,name')
            ->get(['nik', 'name', 'role', 'division_id', 'department', 'position', 'phone', 'is_active'])
            ->map(fn (User $u) => [
                'nik' => $u->nik,
                'name' => $u->name,
                'division' => $u->division?->name,
                'department' => $u->department,
                'position' => $u->position,
                'phone' => $u->phone,
                'role' => $u->role, // peran di HaloDek (informasi saja; akses Inventaris diatur di Inventaris)
                'active' => (bool) $u->is_active,
            ]);

        // "app" dipakai Inventaris untuk memastikan yang menjawab memang HaloDek.
        return response()->json(['app' => 'HaloDek', 'data' => $rows, 'count' => $rows->count()]);
    }

    /**
     * Struktur organisasi (Divisi -> Bidang) agar Inventaris bisa mencatat aset milik bidang/divisi.
     * Dicocokkan lewat id; nama boleh berubah.
     */
    public function organization(): JsonResponse
    {
        return response()->json(['app' => 'HaloDek', 'data' => [
            'divisions' => Division::ordered()->get(['id', 'name', 'sort_order'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'sort_order' => (int) $d->sort_order])->values(),
            'departments' => Department::ordered()->get(['id', 'division_id', 'name', 'sort_order'])
                ->map(fn ($d) => ['id' => $d->id, 'division_id' => $d->division_id, 'name' => $d->name, 'sort_order' => (int) $d->sort_order])->values(),
        ]]);
    }
}
