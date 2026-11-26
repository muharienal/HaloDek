<?php

namespace App\Providers;

use App\Models\Report;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu instance per request: penugasan massal ke orang yang sama digabung jadi satu pesan WA.
        $this->app->singleton(\App\Services\WhatsAppNotifier::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian');

        \App\Support\Roles::boot();
        \App\Support\WhatsAppSettings::apply();

        Paginator::defaultView('pagination.halodek');
        Paginator::defaultSimpleView('pagination.halodek');

        // Data notifikasi untuk bel di topbar (hanya dihitung untuk layout utama).
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $view->with('unreadCount', UserNotification::where('user_id', $user->id)->where('is_read', false)->count());
            $view->with('recentNotifications', UserNotification::where('user_id', $user->id)->latest()->take(5)->get());
            // Pengingat nomor WhatsApp: karyawan tanpa nomor melihatnya sekali tiap login, hanya bila
            // WhatsApp menyala dan ada kabar yang memang dikirim untuk perannya.
            $needsWa = $user->isKaryawan() && ! $user->waNumber() && \App\Support\WaRules::active()
                && \App\Support\WaRules::personalChoices((string) $user->role) !== [];
            $showWaPrompt = $needsWa && ! session()->has('wa_prompt_seen');
            if ($showWaPrompt) {
                session()->put('wa_prompt_seen', true);
            }
            $view->with('showWaPrompt', $showWaPrompt);
            // Ajakan pasang aplikasi (PWA): sekali tiap login; browser sendiri yang memastikan belum terpasang.
            $showPwaPrompt = ! session()->has('pwa_prompt_seen') && ! $user->must_change_password;
            if ($showPwaPrompt) {
                session()->put('pwa_prompt_seen', true);
            }
            $view->with('showPwaPrompt', $showPwaPrompt);
            $view->with('needsWa', $needsWa);

            $view->with('pendingCount', $user->isAdmin()
                ? Report::unassigned()->whereIn('team_id', $user->handledTeamIds() ?: [0])->count()
                : 0);
        });
    }
}
