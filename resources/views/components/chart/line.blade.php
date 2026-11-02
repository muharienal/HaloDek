{{-- Grafik garis/area SVG murni (tanpa library). Props: labels[], series[ ['name','color','data'[]] ] --}}
@props(['labels', 'series', 'height' => 220])
@php
    $w = 640; $h = $height; $pl = 34; $pr = 12; $pt = 14; $pb = 28;
    $n = max(count($labels), 2);
    $max = max(1, collect($series)->flatMap(fn ($s) => $s['data'])->max());
    $step = (int) ceil($max / 4) ?: 1; $top = $step * 4;
    $x = fn ($i) => $pl + ($w - $pl - $pr) * ($i / ($n - 1));
    $y = fn ($v) => $pt + ($h - $pt - $pb) * (1 - $v / $top);
@endphp
<svg class="chart-svg" viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="Grafik tren">
    @for ($g = 0; $g <= 4; $g++)
        @php $gv = $step * $g; @endphp
        <line x1="{{ $pl }}" x2="{{ $w - $pr }}" y1="{{ $y($gv) }}" y2="{{ $y($gv) }}" stroke="#E2E8F0" stroke-dasharray="{{ $g ? '3 4' : '0' }}" />
        <text x="{{ $pl - 8 }}" y="{{ $y($gv) + 4 }}" text-anchor="end">{{ $gv }}</text>
    @endfor
    @foreach ($labels as $i => $label)
        @if ($i % max(1, (int) floor(count($labels) / 7)) === 0)
            <text x="{{ $x($i) }}" y="{{ $h - 8 }}" text-anchor="middle">{{ $label }}</text>
        @endif
    @endforeach
    @foreach ($series as $s)
        @php
            $pts = collect($s['data'])->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
            $area = round($x(0), 1).','.round($y(0), 1).' '.$pts.' '.round($x(count($s['data']) - 1), 1).','.round($y(0), 1);
        @endphp
        <polygon points="{{ $area }}" fill="{{ $s['color'] }}" opacity=".08" />
        <polyline points="{{ $pts }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
        @foreach ($s['data'] as $i => $v)
            <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($v), 1) }}" r="3.2" fill="#fff" stroke="{{ $s['color'] }}" stroke-width="2"><title>{{ $labels[$i] }} — {{ $s['name'] }}: {{ $v }}</title></circle>
        @endforeach
    @endforeach
</svg>
<div class="legend mt-3">
    @foreach ($series as $s)<span><i style="background: {{ $s['color'] }}"></i>{{ $s['name'] }}</span>@endforeach
</div>
