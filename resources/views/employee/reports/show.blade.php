@extends('layouts.app')
@section('title', $report->report_number)
@section('subtitle', $report->title)

@section('content')
@php $me = auth()->user(); @endphp
<div class="detail-head mb-4">
    <div class="detail-nav">
        <a href="{{ route('employee.reports.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="16" /> Kembali</a>
    </div>
    <div class="detail-panel"><x-report-meta :report="$report" /></div>
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
                {{-- Sudah beres? Pelapor menjawab langsung di bawah hasil penyelesaian. --}}
                @can('confirm', $report)
                    <div class="card-foot" style="display:grid;gap:12px">
                        <div><p class="strong" style="margin:0">Apakah masalahnya sudah beres?</p>
                            <p class="text-sm muted" style="margin:4px 0 0">Jika belum, laporan dibuka kembali dan diteruskan ke {{ $report->assignee->name ?? 'regu '.$report->team->name }}.</p></div>
                        <div class="confirm-actions">
                            <form method="POST" action="{{ route('employee.reports.confirm', $report) }}">@csrf @method('PATCH')
                                <button class="btn btn-success btn-block">👍 Sudah beres</button></form>
                            @can('reopen', $report)<button type="button" class="btn btn-secondary btn-block" data-modal-open="reopen-modal">👎 Belum beres</button>@endcan
                        </div>
                    </div>
                @elseif ($report->satisfied && auth()->user()->can('reopen', $report))
                    <div class="card-foot text-sm muted">Anda menandai laporan ini sudah beres. Masalahnya muncul lagi? <button type="button" class="link-btn strong" data-modal-open="reopen-modal">Buka kembali</button></div>
                @endcan
                {{-- Ditolak: pelapor boleh banding sekali, ditinjau ulang Kepala Regu. --}}
                @if ($report->status === 'ditolak')
                    @can('appeal', $report)
                        <div class="card-foot" style="display:grid;gap:10px">
                            <div><p class="strong" style="margin:0">Tidak setuju dengan penolakan ini?</p>
                                <p class="text-sm muted" style="margin:4px 0 0">Ajukan banding sekali. Kepala Regu {{ $report->team->name }} akan meninjau ulang laporan Anda.</p></div>
                            <div><button type="button" class="btn btn-secondary" data-modal-open="appeal-modal">Ajukan banding</button></div>
                        </div>
                    @elseif ($report->appealed_at)
                        <div class="card-foot text-sm muted">Banding sudah diajukan {{ $report->appealed_at->translatedFormat('d M Y') }} dan laporan ditolak kembali setelah ditinjau. Keputusan ini final.</div>
                    @endcan
                @endif
            </div>
        @endif

        {{-- ===== PERCAKAPAN & RIWAYAT ===== --}}
        <div class="card">
            <div class="card-head"><h2>Riwayat &amp; Percakapan</h2></div>
            <div class="card-body">
                @include('partials.timeline', ['activities' => $activities, 'viewer' => $me, 'hideNote' => in_array($report->status, ['selesai', 'ditolak'], true) ? $report->follow_up_note : null])
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
                
                @if ($report->resolved_at)<div><div class="text-xs muted">Selesai pada</div><div>{{ $report->resolved_at->translatedFormat('d M Y, H:i') }} <span class="muted">({{ $report->resolutionText() }})</span></div></div>@endif
                <div><div class="text-xs muted">Penanggung jawab</div><div>{{ $report->assignee->name ?? 'Belum ditugaskan' }}</div></div>
                @if ($report->location_name)<div><div class="text-xs muted">Lokasi</div><div>{{ $report->location_name }}</div></div>@endif
                @if ($report->assetLabel())<div><div class="text-xs muted">Barang terkait</div><div>{{ $report->assetLabel() }}</div></div>@endif
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

@push('modals')
    @can('appeal', $report)
        <div class="modal" id="appeal-modal" role="dialog" aria-modal="true" aria-labelledby="appeal-title">
            <div class="modal-dialog sm">
                <form method="POST" action="{{ route('employee.reports.appeal', $report) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_modal" value="appeal-modal">
                    <div class="modal-head"><h3 id="appeal-title">Ajukan Banding</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button></div>
                    <div class="modal-body" style="display:grid;gap:12px">
                        <x-textarea name="reason" label="Mengapa laporan ini perlu ditinjau ulang?" rows="4" maxlength="1000" required placeholder="Mis. Printer bukan milik pribadi, dipakai bersama di ruang Keuangan." />
                        <p class="hint" style="margin:0">Banding hanya bisa diajukan sekali, paling lama {{ config('halodek.reopen_days') }} hari setelah laporan ditolak.</p>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn btn-primary">Kirim Banding</button></div>
                </form>
            </div>
        </div>
    @endcan
    @can('reopen', $report)
        <div class="modal" id="reopen-modal" role="dialog" aria-modal="true" aria-labelledby="reopen-title">
            <div class="modal-dialog sm">
                <form method="POST" action="{{ route('employee.reports.reopen', $report) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_modal" value="reopen-modal">
                    <div class="modal-head"><h3 id="reopen-title">Buka Kembali Laporan</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button></div>
                    <div class="modal-body" style="display:grid;gap:12px">
                        <x-textarea name="reason" label="Apa yang masih bermasalah?" rows="4" maxlength="1000" required placeholder="Mis. Laptop masih hang setiap membuka Excel." />
                        <p class="hint" style="margin:0">Bisa dibuka kembali paling lama {{ config('halodek.reopen_days') }} hari setelah laporan diselesaikan.</p>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn btn-primary">Buka Kembali</button></div>
                </form>
            </div>
        </div>
    @endcan
@endpush
