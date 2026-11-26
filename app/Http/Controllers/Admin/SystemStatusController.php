<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemStatus;
use App\Services\WhatsAppNotifier;
use App\Support\Audit;
use App\Support\Phone;
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
            'canTestWa' => in_array(config('services.whatsapp.driver'), ['fonnte', 'log'], true)
                && request()->user()->waNumber() !== null,
        ]);
    }

    /** Kirim pesan uji ke satu nomor (bawaan: nomor sendiri) untuk memastikan WhatsApp terkirim. */
    public function testWhatsApp(Request $request, WhatsAppNotifier $wa): RedirectResponse
    {
        $raw = trim((string) $request->input('to', ''));
        $to = $raw !== '' ? Phone::normalize($raw) : $request->user()->waNumber();
        if (! $to) {
            return back()->withInput()->with('error', $raw !== ''
                ? 'Nomor uji tidak valid. Contoh: 0812-3456-7890.'
                : 'Isi nomor tujuan pesan uji, atau No. WhatsApp di Profil Anda.');
        }

        $sent = $wa->sendNow([$to], "*Pesan uji HaloDek*\n\nWhatsApp HaloDek berfungsi. Dikirim oleh {$request->user()->name}, ".now()->translatedFormat('d M Y H:i').' WIB.');
        Audit::log('system.wa_test', 'Mengirim pesan uji WhatsApp ke '.Phone::pretty($to).'.');

        return back()->with($sent > 0 ? 'success' : 'error', $sent > 0
            ? 'Pesan uji terkirim ke '.Phone::pretty($to).'. Cek WhatsApp penerima.'
            : 'Pesan uji gagal terkirim. Periksa token dan status perangkat di Administrasi › WhatsApp, atau Error terakhir di Status Sistem.');
    }
}
