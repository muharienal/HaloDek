<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemStatus;
use App\Services\WhatsAppNotifier;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman "Status Sistem" (Superadmin): kesehatan database, koneksi, penyimpanan, dan error terakhir. */
class SystemStatusController extends Controller
{
    public function index(SystemStatus $status): View
    {
        $sections = $status->all();
        $states = collect($sections)->flatten(1)->pluck('state');

        return view('admin.status.index', [
            'sections' => $sections,
            'errors_count' => $states->filter(fn ($s) => $s === 'error')->count(),
            'warn_count' => $states->filter(fn ($s) => $s === 'warn')->count(),
            'recent' => $status->recentErrors(),
            'canTestWa' => config('services.whatsapp.driver') !== 'off' && app(WhatsAppNotifier::class)->managerNumbers() !== [],
        ]);
    }

    /** Kirim pesan uji ke nomor manajer (WA_TO) untuk memastikan WhatsApp benar-benar terkirim. */
    public function testWhatsApp(Request $request, WhatsAppNotifier $wa): RedirectResponse
    {
        $sent = $wa->sendToManagers("*PESAN UJI HALODEK*\n\nWhatsApp HaloDek berfungsi. Dikirim oleh {$request->user()->name} dari halaman Status Sistem, ".now()->translatedFormat('d M Y H:i').' WIB.');
        Audit::log('system.wa_test', "Mengirim pesan uji WhatsApp ke {$sent} nomor.");

        return back()->with($sent > 0 ? 'success' : 'error', $sent > 0
            ? "Pesan uji terkirim ke {$sent} nomor WA_TO. Cek WhatsApp penerima."
            : 'Pesan uji gagal terkirim. Lihat bagian WhatsApp dan Error terakhir di halaman ini.');
    }
}
