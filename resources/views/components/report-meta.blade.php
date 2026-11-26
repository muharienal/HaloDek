@props(['report', 'flags' => true])
{{-- Keterangan laporan (bukan tombol): label kecil di atas, nilai di bawah. --}}
<dl {{ $attributes->class(['meta-list']) }}>
    <div><dt>Status</dt><dd><x-badge :tone="$report->statusTone()">{{ $report->statusLabel() }}</x-badge></dd></div>
    <div><dt>Prioritas</dt><dd><x-priority :report="$report" /></dd></div>
    @if ($flags && $report->duplicate_of_id)
        <div><dt>Catatan</dt><dd><span class="note-flag is-gray"><x-icon name="swap" :size="13" /> Laporan ganda</span></dd></div>
    @elseif ($flags && $report->appealed_at && $report->isOpen())
        <div><dt>Catatan</dt><dd><span class="note-flag is-orange"><x-icon name="flag" :size="13" /> Banding pelapor</span></dd></div>
    @elseif ($flags && $report->reopened_count)
        <div><dt>Catatan</dt><dd><span class="note-flag is-orange"><x-icon name="refresh" :size="13" /> Dibuka ulang {{ $report->reopened_count }}×</span></dd></div>
    @endif
</dl>
