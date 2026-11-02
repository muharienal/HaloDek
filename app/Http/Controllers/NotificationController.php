<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = UserNotification::with('report')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    /** Tandai dibaca lalu arahkan ke tiket terkait. */
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => now()]);
        }

        $report = $notification->report;
        if (! $report || $report->trashed()) {
            return redirect()->route('notifications.index');
        }

        $user = $request->user();
        $route = ($user->isAdmin() && $user->handlesTeam($report->team_id) && $report->user_id !== $user->id)
            ? route('admin.reports.show', $report)
            : route('employee.reports.show', $report);

        return redirect($route);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        UserNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }
}
