<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()->load('team')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers(), 'different:current_password'],
        ], [
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password minimal 10 karakter.',
        ]);

        if ($data['password'] === $user->nik) {
            return back()->withErrors(['password' => 'Password tidak boleh sama dengan NIK.']);
        }

        $user->update(['password' => $data['password'], 'must_change_password' => false]);

        // Sesi & cookie "ingat saya" di perangkat lain ikut tidak berlaku setelah password berubah.
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Password berhasil diganti.');
    }
}
