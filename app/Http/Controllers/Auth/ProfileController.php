<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const PHONE_ERROR = 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.';

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()->load('team')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $phone = trim((string) $request->input('phone'));
        if ($phone !== '' && ! Phone::normalize($phone)) {
            return back()->withInput()->withErrors(['phone' => self::PHONE_ERROR]);
        }

        $user->update(['phone' => $phone]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /** Dari popup/badge pengingat: simpan nomor WhatsApp (kolom phone). Kosong = berhenti menerima. */
    public function whatsapp(Request $request): RedirectResponse
    {
        $phone = trim((string) $request->input('phone'));

        if ($phone === '') {
            $request->user()->update(['phone' => null]);

            return back()->with('success', 'Nomor WhatsApp dihapus. Kabar laporan tidak lagi dikirim lewat WhatsApp.');
        }

        if (! Phone::normalize($phone)) {
            return back()->withInput()->withErrors(['phone' => self::PHONE_ERROR]);
        }

        $request->user()->update(['phone' => $phone]);

        return back()->with('success', 'Nomor WhatsApp disimpan. Anda akan mendapat kabar saat laporan diproses atau selesai.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            // Aturan dibuat longgar: cukup minimal 6 karakter, bebas isinya.
            'password' => ['required', 'confirmed', 'string', 'min:6', 'max:255', 'different:current_password'],
        ], [
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        $user->update(['password' => $data['password'], 'must_change_password' => false]);

        // Sesi & cookie "ingat saya" di perangkat lain ikut tidak berlaku setelah password berubah.
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Password berhasil diganti.');
    }
}
