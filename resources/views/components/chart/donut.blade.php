{{-- Donut SVG murni. Props: items[ ['name','value','color'] ], center (teks tengah) --}}
@props(['items', 'center' => null, 'size' => 150])
@php
    $total = max(1, collect($items)->sum('value'));
    $r = 52; $c = 2 * M_PI * $r; $offset = 0;
@endphp
<div class="donut-wrap">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 140 140" role="img" aria-label="Grafik proporsi">
        <circle cx="70" cy="70" r="{{ $r }}" fill="none" stroke="#F1F5F9" stroke-width="18" />
        @foreach ($items as $it)
            @php $len = $it['value'] / $total * $c; @endphp
            @if ($it['value'] > 0)
                <circle cx="70" cy="70" r="{{ $r }}" fill="none" stroke="{{ $it['color'] }}" stroke-width="18"
                        stroke-dasharray="{{ round($len, 2) }} {{ round($c - $len, 2) }}" stroke-dashoffset="{{ round(-$offset, 2) }}" transform="rotate(-90 70 70)"><title>{{ $it['name'] }}: {{ $it['value'] }}</title></circle>
            @endif
            @php $offset += $len; @endphp
        @endforeach
        <text x="70" y="68" text-anchor="middle" style="font-size:22px;font-weight:800;fill:#0F172A">{{ $center ?? collect($items)->sum('value') }}</text>
        <text x="70" y="86" text-anchor="middle" style="font-size:10px">total</text>
    </svg>
    <ul class="donut-legend">
        @foreach ($items as $it)
            <li><i style="width:10px;height:10px;border-radius:3px;background:{{ $it['color'] }};display:inline-block"></i><span class="grow truncate">{{ $it['name'] }}</span><strong>{{ $it['value'] }}</strong></li>
        @endforeach
    </ul>
</div>
