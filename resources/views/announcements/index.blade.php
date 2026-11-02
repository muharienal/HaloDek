@extends('layouts.app')
@section('title', 'Pengumuman')
@section('subtitle', 'Informasi terbaru dari tim pengelola')

@section('content')
    @php $me = auth()->user(); @endphp

    @if ($me->isAdmin())
        <div class="flex justify-end mb-4">
            <button class="btn btn-primary" data-modal-open="announcement-modal"><x-icon name="plus" :size="16" /> Buat Pengumuman</button>
        </div>
    @endif

    <div style="display:grid;gap:14px">
        @forelse ($announcements as $a)
            <article class="card pad">
                <div class="flex items-start justify-between gap-3">
                    <div class="grow">
                        <div class="flex items-center gap-2 wrap">
                            @if ($a->is_pinned)<span class="badge tone-amber plain"><x-icon name="pin" :size="12" /> Disematkan</span>@endif
                            @if ($a->isExpired())<span class="badge tone-red plain">Kedaluwarsa</span>@endif
                        </div>
                        <h2 class="text-lg mt-2">{{ $a->title }}</h2>
                        <p class="pre-line mt-2">{{ $a->body }}</p>
                        <p class="text-xs faint mt-3">Oleh {{ $a->user->name ?? 'Admin' }} · {{ $a->created_at->translatedFormat('d M Y H:i') }}@if ($a->expires_at) · berlaku sampai {{ $a->expires_at->translatedFormat('d M Y') }}@endif</p>
                    </div>
                    @if ($me->isSuperadmin() || ($me->isAdmin() && $a->user_id === $me->id))
                        <div class="flex gap-2 shrink-0">
                            <button class="btn btn-secondary btn-icon btn-sm" aria-label="Ubah" data-modal-open="announcement-modal" data-title="Ubah Pengumuman"
                                    data-action="{{ route('admin.announcements.update', $a) }}"
                                    data-fill="{{ json_encode(['title' => $a->title, 'body' => $a->body, 'is_pinned' => $a->is_pinned, 'expires_at' => $a->expires_at?->toDateString()]) }}"><x-icon name="edit" :size="16" /></button>
                            <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" data-confirm="Hapus pengumuman &quot;{{ $a->title }}&quot;?" data-confirm-title="Hapus pengumuman">
                                @csrf @method('DELETE')<button class="btn btn-soft-danger btn-icon btn-sm" aria-label="Hapus"><x-icon name="trash" :size="16" /></button></form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="card"><x-empty icon="megaphone" title="Belum ada pengumuman" /></div>
        @endforelse
    </div>
    <div class="card mt-4">{{ $announcements->links() }}</div>
@endsection

@if (auth()->user()->isAdmin())
    @push('modals')
        <x-form-modal id="announcement-modal" create-title="Buat Pengumuman" edit-title="Ubah Pengumuman" :action="route('admin.announcements.store')">
            <div style="display:grid;gap:16px">
                <x-input name="title" label="Judul" required maxlength="150" />
                <x-textarea name="body" label="Isi pengumuman" required maxlength="2000" rows="5" counter />
                <x-input name="expires_at" type="date" label="Berlaku sampai" />
                <label class="check"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned'))> Sematkan di bagian atas</label>
            </div>
        </x-form-modal>
    @endpush
@endif
