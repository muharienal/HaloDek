<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Data karyawan untuk aplikasi Inventaris (pemegang aset).
 * Hanya peran Karyawan; tidak ada password atau data rahasia lain.
 * Karyawan nonaktif ikut dikirim (active=false) agar Inventaris ikut menonaktifkannya.
 */
class EmployeeApiController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['ok' => true, 'app' => 'HaloDek', 'time' => now()->toIso8601String()]);
    }

    public function index(): JsonResponse
    {
        $rows = User::where('role', User::ROLE_KARYAWAN)->orderBy('nik')
            ->get(['nik', 'name', 'department', 'position', 'phone', 'is_active'])
            ->map(fn (User $u) => [
                'nik' => $u->nik,
                'name' => $u->name,
                'department' => $u->department,
                'position' => $u->position,
                'phone' => $u->phone,
                'active' => (bool) $u->is_active,
            ]);

        // "app" dipakai Inventaris untuk memastikan yang menjawab memang HaloDek.
        return response()->json(['app' => 'HaloDek', 'data' => $rows, 'count' => $rows->count()]);
    }
}
