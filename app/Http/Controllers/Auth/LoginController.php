<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Hash bcrypt tak bertuan, hanya untuk menyamakan waktu pengecekan. */
    private const DUMMY_HASH = '$2y$12$ABiNF0AR/3zIrpB/M/4zreuNZ5fi0EqenzIUapoSEXMvyM7VNKgPu';

    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Aturan masuk (dua jalur terpisah, ditentukan oleh tab yang dipilih):
     *  - Tab Karyawan : hanya akun role Karyawan, cukup NIK.
     *  - Tab Admin    : hanya Superadmin / Kepala Regu / Staf Regu, wajib NIK + password.
     * Akun karyawan TIDAK bisa masuk lewat tab Admin (walau memasukkan "password"),
     * dan akun admin tidak bisa masuk lewat tab Karyawan. Pesan galat dibuat sama untuk
     * NIK tak dikenal / salah jalur / password salah agar NIK tidak bisa ditebak.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'in:karyawan,admin'],
        ], [
            'nik.required' => 'NIK wajib diisi.',
        ]);

        $nik = trim($data['nik']);
        $remember = $request->boolean('remember');
        $isAdminTab = ($data['mode'] ?? 'karyawan') === 'admin';
        $user = User::where('nik', $nik)->first();

        return $isAdminTab
            ? $this->loginAdmin($request, $user, $nik, (string) ($data['password'] ?? ''), $remember)
            : $this->loginKaryawan($request, $user, $remember);
    }

    private function loginKaryawan(Request $request, ?User $user, bool $remember): RedirectResponse
    {
        if (! $user || ! $user->isKaryawan()) {
            throw ValidationException::withMessages([
                'nik' => 'NIK karyawan tidak ditemukan.',
            ]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['nik' => 'Akun Anda dinonaktifkan. Hubungi Superadmin.']);
        }

        Auth::login($user, $remember);

        return $this->finish($request);
    }

    private function loginAdmin(Request $request, ?User $user, string $nik, string $password, bool $remember): RedirectResponse
    {
        $request->merge(['mode' => 'admin']); // agar tab Admin tetap terbuka setelah galat

        if ($password === '') {
            throw ValidationException::withMessages(['password' => 'Password wajib diisi.']);
        }

        // Hanya role pengelola yang boleh lewat jalur ini. Hash::check dummy menjaga waktu respons
        // tetap serupa sehingga keberadaan NIK tidak bisa ditebak dari kecepatan balasan.
        if (! $user || ! $user->isAdmin()) {
            Hash::check($password, self::DUMMY_HASH);

            throw ValidationException::withMessages(['nik' => 'NIK atau password salah.']);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['nik' => 'NIK atau password salah.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['nik' => 'Akun Anda dinonaktifkan. Hubungi Superadmin.']);
        }

        Auth::login($user, $remember);

        return $this->finish($request);
    }

    private function finish(Request $request): RedirectResponse
    {
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
