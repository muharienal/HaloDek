@props(['detail' => false])
@php
    $items = [];
    foreach (['success' => session('success'), 'error' => session('error'), 'warning' => session('warning'), 'info' => session('info')] as $type => $msg) {
        if ($msg) { $items[] = [$type, $msg]; }
    }
    if ($errors->any()) {
        $items[] = ['error', $detail ? $errors->first() : 'Data belum bisa disimpan. Periksa isian yang ditandai.'];
    }
    $titles = ['success' => 'Berhasil', 'error' => 'Gagal', 'warning' => 'Perhatian', 'info' => 'Info'];
@endphp
<div class="toasts" id="toasts" aria-live="polite">
    @foreach ($items as [$type, $msg])
        <div class="toast toast-{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}" data-toast="{{ $type }}">
            <span class="toast-ic"><x-icon :name="$type === 'success' ? 'check-circle' : 'alert'" :size="20" /></span>
            <div class="toast-body"><strong>{{ $titles[$type] }}</strong><span>{{ $msg }}</span></div>
            <button class="toast-x" type="button" aria-label="Tutup">&times;</button>
            <i class="toast-bar"></i>
        </div>
    @endforeach
</div>
