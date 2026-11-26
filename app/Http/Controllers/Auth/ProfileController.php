<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Phone;
use App\Support\WaRules;
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

        $data = ['phone' => $phone];
        if ($request->boolean('wa_prefs')) {
            // Yang tidak dicentang = dimatikan. Kabar yang sedang dimatikan Superadmin tidak ikut diubah.
            $choices = array_keys(WaRules::personalChoices((string) $user->role));
            $on = array_intersect($choices, (array) $request->input('wa_on', []));
            $keep = array_diff((array) ($user->wa_off ?? []), $choices);
            $off = array_values(array_unique(array_merge($keep, array_diff($choices, $on))));
            $data['wa_off'] = $off ?: null;
        }
        $user->update($data);

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

        $when = \App\Support\WaRules::promptPhrase((string) $request->user()->role);

        return back()->with('success', 'Nomor WhatsApp disimpan.'.($when && WaRules::active() ? ' Anda akan mendapat kabar '.$when.'.' : ''));
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
