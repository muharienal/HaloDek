@props(['attachments'])
@if ($attachments->isNotEmpty())
    <div class="att-grid">
        @foreach ($attachments as $att)
            @if ($att->isImage())
                <a class="att" href="{{ route('attachments.show', $att) }}" data-lightbox="att" data-caption="{{ $att->original_name }}" title="Klik untuk memperbesar">
                    <img src="{{ route('attachments.show', $att) }}" alt="{{ $att->original_name }}" loading="lazy">
                    <div class="cap truncate">{{ ['bukti' => 'Bukti', 'penyelesaian' => 'Hasil', 'komentar' => 'Balasan'][$att->kind] ?? $att->kind }}<div class="faint">{{ $att->humanSize() }}</div></div>
                </a>
            @else
                <a class="att" href="{{ route('attachments.show', $att) }}?unduh=1" title="{{ $att->original_name }}">
                    <div class="doc"><x-icon name="document" :size="30" /></div>
                    <div class="cap truncate">{{ $att->original_name }}<div class="faint">{{ $att->humanSize() }}</div></div>
                </a>
            @endif
        @endforeach
    </div>
@endif
