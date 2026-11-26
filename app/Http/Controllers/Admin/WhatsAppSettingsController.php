<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SystemStatus;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\WaRules;
use App\Support\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Administrasi > WhatsApp (Superadmin): koneksi Fonnte dan siapa menerima kabar apa.
 * Disimpan di database (token terenkripsi) sehingga tidak perlu mengubah .env.
 */
class WhatsAppSettingsController extends Controller
{
    public function edit(Request $request, SystemStatus $status): View
    {
        $cfg = config('services.whatsapp');
        $token = trim((string) ($cfg['token'] ?? ''));

        $active = User::active()->get(['id', 'role', 'phone']);
        $roleStats = [];
        foreach (array_keys(WaRules::defaults()) as $role) {
            $group = $active->where('role', $role);
            $roleStats[$role] = ['total' => $group->count(), 'wa' => $group->filter(fn ($u) => $u->waNumber())->count()];
        }

        return view('admin.whatsapp.edit', [
            'cfg' => $cfg,
            'rules' => WaRules::all(),
            'roleStats' => $roleStats,
            'hasToken' => $token !== '',
            'tokenTail' => $token !== '' ? mb_substr($token, -4) : null,
            'tokenUnreadable' => Settings::secretUnreadable('wa.token'),
            'fromApp' => WhatsAppSettings::fromApp(),
            // Pengecekan ke Fonnte hanya bila diminta, agar halaman tetap cepat walau internet lambat.
            'device' => $request->boolean('cek') ? $status->whatsapp() : null,
            'canTest' => in_array($cfg['driver'] ?? 'off', ['fonnte', 'log'], true),
            'testNumber' => $request->user()->waNumber(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $priorities = array_keys(config('halodek.priorities'));
        $data = $request->validate([
            'driver' => ['required', Rule::in(array_keys(WhatsAppSettings::DRIVERS))],
            'token' => ['nullable', 'string', 'max:200'],
            'clear_token' => ['nullable', 'boolean'],
            'digest_at' => ['required', 'date_format:H:i'],
            'rules' => ['nullable', 'array'],
            'head_priorities' => ['nullable', 'array'],
            'head_priorities.*' => [Rule::in($priorities)],
        ], [
            'driver.required' => 'Pilih status WhatsApp.',
            'digest_at.required' => 'Isi jam kirim ringkasan harian.',
            'digest_at.date_format' => 'Jam ringkasan harus berformat JJ:MM, mis. 16:00.',
            'token.max' => 'Token terlalu panjang. Salin ulang token dari fonnte.com › Device.',
        ]);

        // Token: kosong = tetap memakai token lama (termasuk yang masih di .env).
        $newToken = preg_replace('/\s+/', '', (string) ($data['token'] ?? ''));
        $token = match (true) {
            $newToken !== '' => $newToken,
            $request->boolean('clear_token') => '',
            default => trim((string) config('services.whatsapp.token', '')),
        };
        if ($data['driver'] === 'fonnte' && $token === '') {
            throw ValidationException::withMessages(['token' => 'Isi token Fonnte untuk mengaktifkan WhatsApp.']);
        }

        Settings::putMany([
            'wa.driver' => $data['driver'],
            'wa.token' => $token === '' ? null : $token,
            'wa.to' => null, // fitur "nomor tambahan" dihapus
            'wa.digest_at' => $data['digest_at'],
            'wa.head_priorities' => implode(',', array_values(array_intersect($priorities, $data['head_priorities'] ?? []))),
            'wa.rules' => json_encode(WaRules::fromInput((array) ($data['rules'] ?? []))),
        ]);
        WhatsAppSettings::apply();

        $tokenNote = $newToken !== '' ? 'token diganti' : ($token === '' ? 'tanpa token' : 'token tetap');
        Audit::log('settings.whatsapp', 'Mengubah pengaturan WhatsApp: '.WhatsAppSettings::DRIVERS[$data['driver']].', '.$tokenNote.'.');

        return redirect()->route('admin.wa.edit', $data['driver'] === 'fonnte' ? ['cek' => 1] : [])
            ->with('success', 'Pengaturan WhatsApp disimpan.');
    }
}
