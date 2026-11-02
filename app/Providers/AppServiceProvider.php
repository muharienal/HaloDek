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
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian');

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
            $view->with('pendingCount', $user->isAdmin()
                ? Report::unassigned()->whereIn('team_id', $user->handledTeamIds() ?: [0])->count()
                : 0);
        });
    }
}
