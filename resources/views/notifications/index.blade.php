@extends('layouts.app')
@section('title', 'Notifikasi')
@section('subtitle', 'Pembaruan laporan dan penugasan untuk Anda')

@section('content')
    <div class="card">
        <div class="card-head">
            <h2>Semua Notifikasi</h2>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')
                <button class="btn btn-secondary btn-sm"><x-icon name="check-circle" :size="16" /> Tandai semua dibaca</button></form>
        </div>
        @forelse ($notifications as $n)
            <div class="flex items-start gap-3" style="padding:14px 20px;border-bottom:1px solid var(--line-soft);{{ $n->is_read ? '' : 'background:#EFF6FF' }}">
                <span class="avatar sm shrink-0" style="background:{{ $n->is_read ? '#CBD5E1' : 'var(--primary)' }}"><x-icon name="bell" :size="14" /></span>
                <a href="{{ route('notifications.open', $n) }}" class="grow" style="color:inherit;text-decoration:none">
                    <div class="strong">{{ $n->title }}</div>
                    <div class="text-sm muted">{{ $n->message }}</div>
                    <div class="text-xs faint mt-1">{{ $n->created_at->translatedFormat('d M Y H:i') }} · {{ $n->created_at->diffForHumans() }}</div>
                </a>
                <form method="POST" action="{{ route('notifications.destroy', $n) }}" data-no-lock>@csrf @method('DELETE')
                    <button class="btn btn-ghost btn-icon" aria-label="Hapus"><x-icon name="trash" :size="16" /></button></form>
            </div>
        @empty
            <x-empty icon="bell" title="Tidak ada notifikasi">Anda akan diberi tahu saat ada pembaruan laporan.</x-empty>
        @endforelse
        {{ $notifications->links() }}
    </div>
@endsection
