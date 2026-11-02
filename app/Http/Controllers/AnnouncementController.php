<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    /** Pengumuman selalu untuk semua pengguna, siapa pun admin yang membuatnya. */
    public function index(Request $request): View
    {
        $announcements = Announcement::with('user')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->current())
            ->latestFirst()
            ->paginate(10);

        return view('announcements.index', compact('announcements'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $announcement = Announcement::create($this->validated($request) + ['user_id' => $request->user()->id]);

        Audit::log('announcement.create', "Membuat pengumuman \"{$announcement->title}\".", $announcement);

        return back()->with('success', 'Pengumuman berhasil dipublikasikan.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->guardOwnership($announcement);

        $announcement->update($this->validated($request) + ['team_id' => null]);

        return back()->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->guardOwnership($announcement);

        Audit::log('announcement.delete', "Menghapus pengumuman \"{$announcement->title}\".", $announcement);
        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'is_pinned' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'body.required' => 'Isi pengumuman wajib diisi.',
        ]);

        $data['is_pinned'] = $request->boolean('is_pinned');

        return $data;
    }

    private function guardOwnership(Announcement $announcement): void
    {
        $user = auth()->user();
        abort_unless($user->isSuperadmin() || ($user->isAdmin() && $announcement->user_id === $user->id), 403);
    }
}
