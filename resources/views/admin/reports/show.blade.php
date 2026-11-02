@extends('layouts.app')
@section('title', $report->report_number)
@section('subtitle', $report->title)

@section('content')
@php $me = auth()->user(); @endphp
<div class="flex items-center justify-between wrap gap-3 mb-4">
    <a href="{{ route('admin.reports.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="16" /> Semua laporan</a>
    <div class="flex gap-2 wrap items-center">
        <x-badge :tone="$report->statusTone()">{{ $report->statusLabel() }}</x-badge>
        <x-badge :tone="$report->priorityTone()" plain>Prioritas {{ $report->priorityLabel() }}</x-badge>
        <a class="btn btn-secondary btn-sm" href="{{ route('admin.reports.print', $report) }}" target="_blank"><x-icon name="print" :size="16" /> Cetak</a>
        @can('claim', $report)
            <form method="POST" action="{{ route('admin.reports.take', $report) }}">@csrf<button class="btn btn-primary btn-sm">{{ $report->assigned_to_id ? 'Ambil Alih' : 'Ambil Laporan' }}</button></form>
        @endcan
        @can('release', $report)
            <form method="POST" action="{{ route('admin.reports.release', $report) }}" data-confirm="Lepas tugas ini? Laporan kembali ke antrean regu." data-confirm-title="Lepas tugas" data-confirm-ok="Lepas">@csrf<button class="btn btn-secondary btn-sm">Lepas Tugas</button></form>
        @endcan
    </div>
</div>

<div class="grid cols-main-r" style="align-items:start">
    <div style="display:grid;gap:20px;min-width:0">
        <div class="card">
            <div class="card-head"><h2>{{ $report->title }}</h2><x-team-chip :team="$report->team" /></div>
            <div class="card-body">
                <div class="flex gap-3 items-center mb-3">
                    <span class="avatar">{{ $report->user?->initials() ?? '?' }}</span>
                    <div><div class="strong">{{ $report->reporter_name }}</div>
                        <div class="text-xs muted">{{ $report->user ? 'NIK '.$report->user->nik.($report->user->department ? ' · '.$report->user->department : '').($report->user->phone ? ' · '.$report->user->phone : '') : 'Akun pelapor sudah dihapus' }}</div></div>
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
        </div>
    </div>

    <div style="display:grid;gap:20px">
        {{-- ===== TINDAK LANJUT ===== --}}
        @if ($report->status === 'dibatalkan')
            <div class="alert alert-info" style="margin:0"><x-icon name="x-circle" :size="18" /><div>Laporan ini dibatalkan oleh pelapor sehingga tidak dapat ditindaklanjuti.</div></div>
        @elseif (! $me->can('manage', $report))
            <div class="card" style="border-color: var(--primary)">
                <div class="card-head"><h2>Tindak Lanjut</h2></div>
                <div class="card-body" style="display:grid;gap:12px">
                    @if ($report->assignee)
                        <p class="text-sm">Laporan ini ditangani <strong>{{ $report->assignee->name }}</strong>. Hanya penanggung jawab dan Kepala Regu yang dapat mengubah statusnya.</p>
                    @else
                        <p class="text-sm">Laporan ini masih di antrean regu. Ambil laporan untuk mulai mengerjakannya.</p>
                    @endif
                    @can('claim', $report)
                        <form method="POST" action="{{ route('admin.reports.take', $report) }}">@csrf<button class="btn btn-primary btn-block">Ambil Laporan</button></form>
                    @endcan
                </div>
            </div>
        @else
        <div class="card" style="border-color: var(--primary)">
            <div class="card-head"><h2>Tindak Lanjut</h2></div>
            <form method="POST" action="{{ route('admin.reports.update', $report) }}" enctype="multipart/form-data" class="card-body" style="display:grid;gap:14px">
                @csrf @method('PATCH')
                @can('assign', $report)
                    <x-select name="assigned_to_id" label="Ditugaskan kepada" hint="Kosongkan agar laporan tetap di antrean regu (staf dapat mengambil sendiri).">
                        <option value="">— Belum ditugaskan —</option>
                        @foreach ($staff as $s)<option value="{{ $s->id }}" @selected(old('assigned_to_id', $report->assigned_to_id) == $s->id)>{{ $s->name }} — {{ $s->id === $me->id ? 'saya, ' : '' }}{{ config('halodek.roles.'.$s->role) }}</option>@endforeach
                    </x-select>
                    <x-select name="priority" label="Prioritas" required>
                        @foreach (config('halodek.priorities') as $k => $p)<option value="{{ $k }}" @selected(old('priority', $report->priority) === $k)>{{ $p['label'] }}</option>@endforeach
                    </x-select>
                @endcan
                <x-select name="status" label="Status" required>
                    @foreach (['menunggu', 'diproses', 'selesai', 'ditolak'] as $s)
                        <option value="{{ $s }}" @selected(old('status', $report->status) === $s)>{{ config("halodek.statuses.$s.label") }}</option>
                    @endforeach
                </x-select>
                <x-textarea name="follow_up_note" label="Catatan tindak lanjut" rows="4" maxlength="3000" counter :value="old('follow_up_note')" />
                <div class="field">
                    <span class="label">Foto hasil pekerjaan (opsional)</span>
                    <input type="file" class="input" name="files[]" multiple accept="image/jpeg,image/png,image/webp" data-file-input data-max-files="{{ config('halodek.attachments.max_files') }}" data-max-kb="{{ config('halodek.attachments.max_kb') }}" data-file-error="u-err">
                    <p class="error" id="u-err" hidden></p>
                    @error('files.*')<span class="error">{{ $message }}</span>@enderror
                </div>
                <button class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan Tindak Lanjut</button>
            </form>
        </div>
        @endif

        <div class="card">
            <div class="card-head"><h2>Informasi</h2></div>
            <div class="card-body" style="display:grid;gap:14px">
                <div><div class="text-xs muted">Kategori</div><div>{{ $report->categoryDisplayName() }}</div></div>
                <div><div class="text-xs muted">Diajukan</div><div>{{ $report->created_at->translatedFormat('d M Y, H:i') }}</div></div>
                <div><div class="text-xs muted">Penanggung jawab</div><div>@if ($report->assignee)<span class="strong">{{ $report->assignee->name }}</span> <span class="muted">· {{ $report->assignee->roleLabel() }}</span>@else<span class="badge tone-amber plain">Belum ditugaskan (antrean regu)</span>@endif</div></div>
                <div><div class="text-xs muted">Respons pertama</div><div>{{ $report->first_response_at ? $report->first_response_at->translatedFormat('d M Y, H:i').' ('.$report->created_at->diffForHumans($report->first_response_at, true).')' : 'Belum ada respons' }}</div></div>
                @if ($report->resolved_at)<div><div class="text-xs muted">Selesai</div><div>{{ $report->resolved_at->translatedFormat('d M Y, H:i') }} <span class="muted">({{ $report->resolutionHours() }} jam)</span></div></div>@endif
                @if ($report->location)<div><div class="text-xs muted">Lokasi</div><div>{{ $report->location->fullName() }}</div></div>@endif
                @if ($report->item)<div><div class="text-xs muted">Inventaris terkait</div><a href="{{ route('admin.inventory.items.show', $report->item) }}">{{ $report->item->label() }}</a></div>@endif
            </div>
        </div>

        @if ($history->isNotEmpty())
            <div class="card">
                <div class="card-head"><h2>Riwayat Laporan Barang Ini</h2></div>
                <div class="card-body" style="display:grid;gap:10px">
                    @foreach ($history as $h)
                        <a href="{{ route('admin.reports.show', $h) }}" style="text-decoration:none;color:inherit"><div class="mono strong">{{ $h->report_number }}</div><div class="text-sm muted truncate">{{ $h->title }} · {{ $h->created_at->translatedFormat('d M Y') }}</div></a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body" style="display:grid;gap:10px">
                @if ($otherTeams->isNotEmpty() && $me->can('assign', $report))
                    <button class="btn btn-secondary" data-modal-open="transfer-modal"><x-icon name="swap" :size="16" /> Alihkan ke Regu Lain</button>
                @endif
                @can('delete', $report)
                    <form method="POST" action="{{ route('admin.reports.destroy', $report) }}" data-confirm="Hapus laporan {{ $report->report_number }}? Laporan akan disembunyikan dari semua pengguna." data-confirm-title="Hapus laporan">
                        @csrf @method('DELETE')<button class="btn btn-soft-danger btn-block"><x-icon name="trash" :size="16" /> Hapus Laporan</button></form>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
    @if ($otherTeams->isNotEmpty() && auth()->user()->can('assign', $report))
        <x-form-modal id="transfer-modal" create-title="Alihkan Laporan" :action="route('admin.reports.transfer', $report)" submit="Alihkan">
            @php $map = $otherTeams->mapWithKeys(fn ($t) => [$t->id => $t->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()]); @endphp
            <div style="display:grid;gap:16px">
                <x-select name="team_id" label="Regu tujuan" required id="tr-team">
                    <option value="">— Pilih regu —</option>
                    @foreach ($otherTeams as $t)<option value="{{ $t->id }}" @selected(old('team_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </x-select>
                <x-select name="category_id" label="Kategori di regu tujuan" required id="tr-cat"><option value="">— Pilih regu dahulu —</option></x-select>
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
        cat.innerHTML = '<option value="">— Pilih kategori —</option>';
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
