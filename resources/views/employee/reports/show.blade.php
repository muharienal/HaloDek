@extends('layouts.app')
@section('title', $report->report_number)
@section('subtitle', $report->title)

@section('content')
@php $me = auth()->user(); @endphp
<div class="flex items-center justify-between wrap gap-3 mb-4">
    <a href="{{ route('employee.reports.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="16" /> Kembali</a>
    <div class="flex gap-2 wrap">
        <x-badge :tone="$report->statusTone()">{{ $report->statusLabel() }}</x-badge>
        <x-badge :tone="$report->priorityTone()" plain>Prioritas {{ $report->priorityLabel() }}</x-badge>
    </div>
</div>

<div class="grid cols-main-r" style="align-items:start">
    <div style="display:grid;gap:20px;min-width:0">
        <div class="card">
            <div class="card-head"><h2>{{ $report->title }}</h2><x-team-chip :team="$report->team" /></div>
            <div class="card-body">
                <p class="pre-line">{{ $report->description }}</p>
                @php $evidence = $report->attachments->whereIn('kind', ['bukti', 'komentar', 'penyelesaian']); @endphp
                @if ($evidence->isNotEmpty())
                    <div class="divider"></div>
                    <p class="label mb-2">Lampiran</p>
                    @include('partials.attachments', ['attachments' => $evidence])
                @endif
            </div>
        </div>

        @if ($report->follow_up_note && in_array($report->status, ['selesai', 'ditolak', 'diproses']))
            <div class="card" style="border-color: {{ $report->status === 'ditolak' ? '#FECACA' : '#BBF7D0' }}">
                <div class="card-head"><h2>{{ ['selesai' => 'Hasil Penyelesaian', 'ditolak' => 'Alasan Penolakan', 'diproses' => 'Catatan Terbaru'][$report->status] }}</h2></div>
                <div class="card-body pre-line">{{ $report->follow_up_note }}</div>
            </div>
        @endif

        {{-- ===== PERCAKAPAN & RIWAYAT ===== --}}
        <div class="card">
            <div class="card-head"><h2>Riwayat &amp; Percakapan</h2></div>
            <div class="card-body">
                @include('partials.timeline', ['activities' => $activities, 'viewer' => $me])
            </div>
            @can('comment', $report)
                <form method="POST" action="{{ route('employee.reports.comment', $report) }}" enctype="multipart/form-data" class="card-foot" style="display:grid;gap:10px">
                    @csrf
                    <x-textarea name="message" rows="3" maxlength="2000" required placeholder="Tulis balasan atau informasi tambahan untuk regu…" />
                    <div class="flex items-center justify-between wrap gap-3">
                        <label class="btn btn-secondary btn-sm" style="cursor:pointer"><x-icon name="paperclip" :size="15" /> Lampirkan
                            <input type="file" name="files[]" multiple class="sr-only" accept="image/jpeg,image/png,image/webp" data-file-input data-max-files="{{ config('halodek.attachments.max_files') }}" data-max-kb="{{ config('halodek.attachments.max_kb') }}" data-file-list="reply-files" data-file-error="reply-err"></label>
                        <button class="btn btn-primary btn-sm"><x-icon name="chat" :size="15" /> Kirim Balasan</button>
                    </div>
                    <div class="file-list" id="reply-files" style="margin-top:0"></div>
                    <p class="error" id="reply-err" hidden></p>
                </form>
            @endcan
        </div>
    </div>

    {{-- ===== SISI KANAN ===== --}}
    <div style="display:grid;gap:20px">
        <div class="card">
            <div class="card-head"><h2>Informasi</h2></div>
            <div class="card-body" style="display:grid;gap:14px">
                <div><div class="text-xs muted">Kategori</div><div>{{ $report->categoryDisplayName() }}</div></div>
                <div><div class="text-xs muted">Diajukan</div><div>{{ $report->created_at->translatedFormat('d M Y, H:i') }}</div></div>
                
                @if ($report->resolved_at)<div><div class="text-xs muted">Selesai pada</div><div>{{ $report->resolved_at->translatedFormat('d M Y, H:i') }} <span class="muted">({{ $report->resolutionHours() }} jam)</span></div></div>@endif
                <div><div class="text-xs muted">Penanggung jawab</div><div>{{ $report->assignee->name ?? 'Belum ditugaskan' }}</div></div>
                @if ($report->location)<div><div class="text-xs muted">Lokasi</div><div>{{ $report->location->fullName() }}</div></div>@endif
                @if ($report->item)<div><div class="text-xs muted">Barang / ruangan</div><div>{{ $report->item->label() }}</div></div>@endif
            </div>
        </div>

        @can('cancel', $report)
            <div class="card">
                <div class="card-body">
                    <p class="strong">Batalkan laporan?</p>
                    <form method="POST" action="{{ route('employee.reports.cancel', $report) }}" class="mt-3" data-confirm="Batalkan laporan {{ $report->report_number }}? Tindakan ini tidak dapat diurungkan." data-confirm-title="Batalkan laporan" data-confirm-ok="Ya, batalkan">
                        @csrf @method('PATCH')
                        <input class="input mb-2" name="reason" placeholder="Alasan pembatalan (opsional)" maxlength="500">
                        <button class="btn btn-soft-danger btn-sm"><x-icon name="x-circle" :size="15" /> Batalkan Laporan</button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
</div>
@endsection
