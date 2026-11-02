<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $action = $request->query('action');

        $logs = AuditLog::with('user')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->when($action, fn ($q) => $q->where('action', 'like', $action.'%'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $groups = ['user' => 'Pengguna', 'team' => 'Regu', 'category' => 'Kategori', 'inventory' => 'Inventaris', 'location' => 'Lokasi', 'announcement' => 'Pengumuman', 'report' => 'Laporan'];

        return view('admin.audit.index', compact('logs', 'search', 'action', 'groups'));
    }
}
