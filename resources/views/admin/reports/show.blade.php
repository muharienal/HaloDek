@extends('layouts.app')
@section('title', $report->report_number)
@section('subtitle', $report->title)

@section('content')
@php $me = auth()->user(); @endphp
<div class="detail-head mb-4">
    <div class="detail-nav">
        <a href="{{ route('admin.reports.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="16" /> Semua Laporan</a>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.reports.print', $report) }}" target="_blank" title="Cetak laporan"><x-icon name="print" :size="16" /> Cetak</a>
    </div>
    @php
        $canClose = $report->isOpen() && $me->can('manage', $report);
        $canClaim = $me->can('claim', $report);
        // Satu tombol utama saja: "Ambil" bila belum ada penanggung jawab, selain itu "Selesaikan".
        $claimIsMain = $canClaim && ! $report->assigned_to_id;
    @endphp
    <div class="detail-panel">
        <x-report-meta :report="$report" />
        @if ($canClaim || $canClose || $me->can('release', $report))
            <div class="detail-actions" role="group" aria-label="Tindakan">
                @can('release', $report)
                    <form method="POST" action="{{ route('admin.reports.release', $report) }}" data-confirm="Lepas laporan ini? Laporan kembali menunggu diambil anggota regu." data-confirm-title="Lepas laporan" data-confirm-ok="Lepas">@csrf<button class="btn btn-secondary btn-sm"><x-icon name="logout" :size="16" /> Lepas</button></form>
                @endcan
                @if ($canClaim && ! $claimIsMain)
                    <form method="POST" action="{{ route('admin.reports.take', $report) }}">@csrf<button class="btn btn-secondary btn-sm"><x-icon name="user" :size="16" /> Ambil Alih</button></form>
                @endif
                @if ($canClose)
                    <button type="button" class="btn btn-soft-danger btn-sm" data-modal-open="close-ditolak"><x-icon name="x-circle" :size="16" /> Tolak</button>
                    <button type="button" class="btn {{ $claimIsMain ? 'btn-secondary' : 'btn-success' }} btn-sm" data-modal-open="close-selesai"><x-icon name="check-circle" :size="16" /> Selesaikan</button>
                @endif
                @if ($claimIsMain)
                    <form method="POST" action="{{ route('admin.reports.take', $report) }}">@csrf<button class="btn btn-primary btn-sm"><x-icon name="user" :size="16" /> Ambil Laporan</button></form>
                @endif
            </div>
        @endif
    </div>
</div>

@if ($report->duplicate_of_id && $report->duplicateOf)
    <div class="alert alert-info"><x-icon name="swap" :size="18" /><div>Laporan ini digabung ke <a class="strong" href="{{ route('admin.reports.show', $report->duplicateOf) }}">{{ $report->duplicateOf->report_number }}</a> karena masalahnya sama. Penanganan dilanjutkan di sana.</div></div>
@endif

<div class="grid cols-main-r" style="align-items:start">
    <div style="display:grid;gap:20px;min-width:0">
        <div class="card">
            <div class="card-head"><h2>{{ $report->title }}</h2><x-team-chip :team="$report->team" /></div>
            <div class="card-body">
                @php
                    $waNo = $report->user?->waNumber();
                    $waText = 'Halo '.\Illuminate\Support\Str::words($report->reporter_name, 1, '').', saya '.$me->name.' dari Regu '.$report->team->name.' (HaloDek), terkait laporan '.$report->report_number.' "'.$report->title.'".';
                @endphp
                <div class="flex gap-3 items-center wrap mb-3">
                    <span class="avatar">{{ $report->user?->initials() ?? '?' }}</span>
                    <div class="grow" style="min-width:0"><div class="strong">{{ $report->reporter_name }}</div>
                        <div class="text-xs muted">{{ $report->user ? 'NIK '.$report->user->nik.($report->user->department ? ' · '.$report->user->department : '').($waNo ? ' · '.\App\Support\Phone::pretty($waNo) : '') : 'Akun pelapor sudah dihapus' }}</div></div>
                    @if ($waNo)
                        <a class="btn btn-wa btn-sm" href="https://wa.me/{{ $waNo }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener noreferrer"><x-icon name="chat" :size="15" /> Hubungi via WA</a>
                    @endif
                </div>
                <p class="pre-line">{{ $report->description }}</p>
                @if ($report->attachments->isNotEmpty())
                    <div class="divider"></div>
                    <p class="label mb-2">Lampiran</p>
                    @include('partials.attachments', ['attachments' => $report->attachments])
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Riwayat &amp; Percakapan</h2></div>
            <div class="card-body">@include('partials.timeline', ['activities' => $activities, 'viewer' => $me])</div>
            @can('comment', $report)
            <form method="POST" action="{{ route('admin.reports.comment', $report) }}" enctype="multipart/form-data" class="card-foot" style="display:grid;gap:10px">
                @csrf
                <x-textarea name="message" rows="3" maxlength="2000" required placeholder="Tulis balasan untuk pelapor, atau centang “internal” untuk catatan antar-anggota regu…" />
                <div class="flex items-center justify-between wrap gap-3">
                    <div class="flex items-center gap-4 wrap">
                        <label class="btn btn-secondary btn-sm" style="cursor:pointer"><x-icon name="paperclip" :size="15" /> Lampirkan
                            <input type="file" name="files[]" multiple class="sr-only" accept="image/jpeg,image/png,image/webp" data-file-input data-max-files="{{ config('halodek.attachments.max_files') }}" data-max-kb="{{ config('halodek.attachments.max_kb') }}" data-file-list="c-files" data-file-error="c-err"></label>
                        <label class="check"><input type="checkbox" name="is_internal" value="1" @checked(old('is_internal'))> <x-icon name="lock" :size="14" /> Catatan internal</label>
                    </div>
                    <button class="btn btn-primary btn-sm"><x-icon name="chat" :size="15" /> Kirim</button>
                </div>
                <div class="file-list" id="c-files" style="margin-top:0"></div><p class="error" id="c-err" hidden></p>
            </form>
            @endcan
        </div>
    </div>

    <div style="display:grid;gap:20px">
        {{-- ===== TINDAK LANJUT ===== --}}
        @if ($report->status === 'dibatalkan')
            <div class="alert alert-info" style="margin:0"><x-icon name="x-circle" :size="18" /><div>Laporan ini dibatalkan oleh pelapor sehingga tidak dapat ditindaklanjuti.</div></div>
        @elseif (! $me->can('manage', $report))
            <div class="card" style="border-color: var(--primary)">
                <div class="card-head"><h2>Penanganan</h2></div>
                <div class="card-body" style="display:grid;gap:12px">
                    @if ($me->isManajer())
                        <p class="text-sm">Anda memantau sebagai <strong>Manajer</strong>. {{ $report->assignee ? 'Laporan ini ditangani '.$report->assignee->name.'.' : 'Laporan ini belum diambil anggota regu.' }}</p>
                    @elseif ($report->assignee)
                        <p class="text-sm">Laporan ini ditangani <strong>{{ $report->assignee->name }}</strong>. Hanya penanggung jawab dan Kepala Regu yang dapat mengubah statusnya.</p>
                    @else
                        <p class="text-sm">Laporan ini menunggu diambil. Tekan <strong>Ambil Laporan</strong> di atas untuk mulai mengerjakannya.</p>
                    @endif
                </div>
            </div>
        @else
        @php $canAssign = $me->can('assign', $report); $manualOpen = $errors->hasAny(['status', 'follow_up_note', 'files.*']); @endphp
        <div class="card">
            <form method="POST" action="{{ route('admin.reports.update', $report) }}" enctype="multipart/form-data" class="card-body" style="display:grid;gap:14px">
                @csrf @method('PATCH')
                @if ($canAssign)
                    <h2 style="margin:0;font-size:15.5px">Penugasan</h2>
                    <x-select name="assigned_to_id" label="Penanggung jawab" hint="Kosongkan agar laporan menunggu diambil (staf dapat mengambil sendiri).">
                        <option value="">- Belum ada -</option>
                        @foreach ($staff as $s)<option value="{{ $s->id }}" @selected(old('assigned_to_id', $report->assigned_to_id) == $s->id)>{{ $s->name }} - {{ $s->id === $me->id ? 'saya, ' : '' }}{{ config('halodek.roles.'.$s->role) }}</option>@endforeach
                    </x-select>
                    <x-select name="priority" label="Prioritas" required>
                        @foreach (config('halodek.priorities') as $k => $p)<option value="{{ $k }}" @selected(old('priority', $report->priority) === $k)>{{ $p['label'] }}</option>@endforeach
                    </x-select>
                @endif
                <details class="more" @if ($manualOpen || ! $report->isOpen()) open @endif>
                    <summary>{{ $report->isOpen() ? 'Ubah status manual' : 'Ubah status' }}</summary>
                    <div style="display:grid;gap:14px">
                        @if ($report->isOpen())<p class="hint" style="margin:0">Untuk menyelesaikan atau menolak, cukup pakai tombol <strong>Selesaikan</strong> / <strong>Tolak</strong> di atas.</p>@endif
                        <x-select name="status" label="Status" required>
                            @foreach (['menunggu', 'diproses', 'selesai', 'ditolak'] as $s)
                                <option value="{{ $s }}" @selected(old('status', $report->status) === $s)>{{ config("halodek.statuses.$s.label") }}</option>
                            @endforeach
                        </x-select>
                        <x-textarea name="follow_up_note" label="Catatan untuk pelapor" rows="3" maxlength="3000" counter :value="old('follow_up_note')" hint="Wajib saat mengubah ke Selesai atau Ditolak." />
                        <div class="field">
                            <span class="label">Foto hasil pekerjaan (opsional)</span>
                            <input type="file" class="input" name="files[]" multiple accept="image/jpeg,image/png,image/webp" data-file-input data-max-files="{{ config('halodek.attachments.max_files') }}" data-max-kb="{{ config('halodek.attachments.max_kb') }}" data-file-error="u-err">
                            <p class="error" id="u-err" hidden></p>
                            @error('files.*')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </details>
                <button class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan Perubahan</button>
            </form>
        </div>
        @endif

        <div class="card">
            <div class="card-head"><h2>Informasi</h2></div>
            <div class="card-body" style="display:grid;gap:14px">
                <div><div class="text-xs muted">Kategori</div><div>{{ $report->categoryDisplayName() }}</div></div>
                <div><div class="text-xs muted">Diajukan</div><div>{{ $report->created_at->translatedFormat('d M Y, H:i') }}</div></div>
                {{-- Sudah tampil sebagai pilihan di kartu Penugasan bagi yang bisa menugaskan: tidak diulang. --}}
                @unless ($report->isOpen() && $me->can('assign', $report))
                <div><div class="text-xs muted">Penanggung jawab</div><div>@if ($report->assignee)<span class="strong">{{ $report->assignee->name }}</span> <span class="muted">· {{ $report->assignee->roleLabel() }}</span>@else<span class="badge tone-amber plain">Belum ada (menunggu diambil)</span>@endif</div></div>
                @endunless
                @if ($report->isOpen() && ! $report->assigned_to_id)
                    @php $deadline = $report->responseDeadline(); @endphp
                    <div><div class="text-xs muted">Batas respons</div><div>{{ $deadline->translatedFormat('d M Y, H:i') }}
                        @if ($report->isOverdue())<span class="note-flag is-red"><x-icon name="clock" :size="13" /> Lewat batas</span>@endif</div>
                        <div class="text-xs faint">{{ config('halodek.response_sla.'.$report->priority) }} jam kerja untuk prioritas {{ $report->priorityLabel() }}</div></div>
                @endif
                <div><div class="text-xs muted">Respons pertama</div><div>@if ($report->first_response_at){{ $report->first_response_at->translatedFormat('d M Y, H:i') }} <span class="muted">({{ $report->responseText() }})</span>@else Belum ada respons @endif</div></div>
                @if ($report->resolved_at)<div><div class="text-xs muted">Selesai</div><div>{{ $report->resolved_at->translatedFormat('d M Y, H:i') }} <span class="muted">({{ $report->resolutionText() }})</span></div></div>@endif
                @if ($report->status === 'selesai')<div><div class="text-xs muted">Konfirmasi pelapor</div><div>{{ $report->satisfied ? 'Sudah beres' : 'Belum dijawab' }}</div></div>@endif
                @if ($report->location_name)<div><div class="text-xs muted">Lokasi</div><div>{{ $report->location_name }}</div></div>@endif
                @if ($report->assetLabel())<div><div class="text-xs muted">Barang terkait</div>
                    @if ($assetUrl)<a href="{{ $assetUrl }}" target="_blank" rel="noopener" title="Lihat data barang di Inventaris">{{ $report->assetLabel() }}</a>@else<div>{{ $report->assetLabel() }}</div>@endif</div>@endif
            </div>
        </div>

        @if ($history->isNotEmpty())
            <div class="card">
                <div class="card-head"><h2>Riwayat Laporan Barang Ini</h2></div>
                <div class="card-body" style="display:grid;gap:10px">
                    @foreach ($history as $h)
                        <a href="{{ route('admin.reports.show', $h) }}" style="text-decoration:none;color:inherit">
                            <div class="flex items-center justify-between gap-2"><span class="mono strong">{{ $h->report_number }}</span><x-badge :tone="$h->statusTone()">{{ $h->statusLabel() }}</x-badge></div>
                            <div class="text-sm muted truncate">{{ $h->created_at->translatedFormat('d M Y') }}</div></a>
                    @endforeach
                </div>
            </div>
        @endif

        @php $canTransfer = $otherTeams->isNotEmpty() && $me->can('assign', $report); $canMerge = $mergeTargets->isNotEmpty(); @endphp
        @if ($canTransfer || $canMerge || $me->can('delete', $report))
        <div class="card">
            <div class="card-head"><h2>Tindakan lain</h2></div>
            <div class="card-body" style="display:grid;gap:10px">
                @if ($canTransfer)
                    <button class="btn btn-secondary" data-modal-open="transfer-modal"><x-icon name="swap" :size="16" /> Alihkan ke Regu Lain</button>
                @endif
                @if ($canMerge)
                    <button class="btn btn-secondary" data-modal-open="merge-modal"><x-icon name="list" :size="16" /> Gabungkan (Laporan Ganda)</button>
                @endif
                @can('delete', $report)
                    <form method="POST" action="{{ route('admin.reports.destroy', $report) }}" data-confirm="Hapus laporan {{ $report->report_number }}? Laporan akan disembunyikan dari semua pengguna." data-confirm-title="Hapus laporan">
                        @csrf @method('DELETE')<button class="btn btn-soft-danger btn-block"><x-icon name="trash" :size="16" /> Hapus Laporan</button></form>
                @endcan
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('modals')
    @if ($report->isOpen() && auth()->user()->can('manage', $report))
        @foreach (['selesai' => ['Selesaikan Laporan', 'Hasil penyelesaian', 'Mis. RAM sudah diganti, laptop normal kembali.', 'Tandai Selesai', 'btn-success'], 'ditolak' => ['Tolak Laporan', 'Alasan penolakan', 'Mis. Permintaan ini ditangani langsung oleh atasan masing-masing.', 'Tolak Laporan', 'btn-danger']] as $st => [$ttl, $lbl, $ph, $btn, $cls])
            <div class="modal" id="close-{{ $st }}" role="dialog" aria-modal="true" aria-labelledby="close-{{ $st }}-title">
                <div class="modal-dialog sm">
                    <form method="POST" action="{{ route('admin.reports.close', $report) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="_modal" value="close-{{ $st }}">
                        <input type="hidden" name="status" value="{{ $st }}">
                        <div class="modal-head"><h3 id="close-{{ $st }}-title">{{ $ttl }}</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button></div>
                        <div class="modal-body" style="display:grid;gap:14px">
                            <p class="muted text-sm" style="margin:0">Catatan ini dikirim ke <strong>{{ $report->reporter_name }}</strong>{{ $report->user?->waNumber() ? ' (juga lewat WhatsApp)' : '' }}.</p>
                            @php $quick = $report->team->noteTemplates()[$st] ?? []; @endphp
                            @if ($quick)
                                <div class="quick-notes" data-quick-target="close-note-{{ $st }}" aria-label="Catatan cepat">
                                    @foreach ($quick as $q)<button type="button" class="quick-note" data-quick-note="{{ $q }}">{{ $q }}</button>@endforeach
                                </div>
                            @endif
                            <x-textarea name="close_note" id="close-note-{{ $st }}" :label="$lbl" rows="4" maxlength="3000" required :placeholder="$ph" :value="old('status') === $st ? old('close_note') : null" />
                            @if ($st === 'selesai')
                                <div class="field">
                                    <span class="label">Foto hasil pekerjaan (opsional)</span>
                                    <input type="file" class="input" name="files[]" multiple accept="image/jpeg,image/png,image/webp" data-file-input data-max-files="{{ config('halodek.attachments.max_files') }}" data-max-kb="{{ config('halodek.attachments.max_kb') }}" data-file-error="cs-err">
                                    <p class="error" id="cs-err" hidden></p>
                                </div>
                            @endif
                        </div>
                        <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn {{ $cls }}">{{ $btn }}</button></div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
    @if ($mergeTargets->isNotEmpty())
        <x-form-modal id="merge-modal" create-title="Gabungkan Laporan Ganda" :action="route('admin.reports.merge', $report)" submit="Gabungkan">
            <div style="display:grid;gap:14px">
                <x-select name="master_id" label="Laporan utama" required>
                    <option value="">- Pilih laporan yang sama -</option>
                    @foreach ($mergeTargets as $m)
                        <option value="{{ $m->id }}" @selected(old('master_id') == $m->id)>{{ $m->report_number }}{{ $m->asset_id && $m->asset_id === $report->asset_id ? ' · barang sama' : '' }} · {{ \Illuminate\Support\Str::limit($m->description, 60) }}</option>
                    @endforeach
                </x-select>
                <p class="hint" style="margin:0">Laporan {{ $report->report_number }} ditutup dan ditautkan ke laporan utama. Pelapor dikabari bahwa masalahnya sudah ditangani di laporan itu.</p>
            </div>
        </x-form-modal>
    @endif
    @if ($otherTeams->isNotEmpty() && auth()->user()->can('assign', $report))
        <x-form-modal id="transfer-modal" create-title="Alihkan Laporan" :action="route('admin.reports.transfer', $report)" submit="Alihkan">
            @php $map = $otherTeams->mapWithKeys(fn ($t) => [$t->id => $t->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()]); @endphp
            <div style="display:grid;gap:16px">
                <x-select name="team_id" label="Regu tujuan" required id="tr-team">
                    <option value="">- Pilih regu -</option>
                    @foreach ($otherTeams as $t)<option value="{{ $t->id }}" @selected(old('team_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </x-select>
                <x-select name="category_id" label="Kategori di regu tujuan" required id="tr-cat"><option value="">- Pilih regu dahulu -</option></x-select>
                <x-textarea name="reason" label="Alasan pengalihan" rows="3" maxlength="500" required />
            </div>
        </x-form-modal>
    @endif
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var map = @json($otherTeams->mapWithKeys(fn ($t) => [$t->id => $t->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()]));
    var team = document.getElementById('tr-team'), cat = document.getElementById('tr-cat');
    if (!team) { return; }
    var oldCat = @json(old('category_id'));
    function fill() {
        cat.innerHTML = '<option value="">- Pilih kategori -</option>';
        (map[team.value] || []).forEach(function (c) {
            var o = document.createElement('option'); o.value = c.id; o.textContent = c.name;
            if (String(oldCat) === String(c.id)) { o.selected = true; }
            cat.appendChild(o);
        });
        oldCat = null;
    }
    team.addEventListener('change', fill); fill();
})();
</script>
@endpush
