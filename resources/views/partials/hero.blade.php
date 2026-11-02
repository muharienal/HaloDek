@php
    $hh = (int) now()->format('G');
    $hello = $hh < 11 ? 'Selamat pagi' : ($hh < 15 ? 'Selamat siang' : ($hh < 18 ? 'Selamat sore' : 'Selamat malam'));
    $who = \Illuminate\Support\Str::words(auth()->user()->name, 1, '');
@endphp
<section class="hero-card">
    <div class="hero-head">
        <div>
            <div class="hero-hello">{{ $hello }},</div>
            <h1>{{ $who }}</h1>
        </div>
        <span class="hero-date">{{ now()->translatedFormat('D, d M Y') }}</span>
    </div>
    <div class="hero-metrics">
        @foreach ($metrics as $m)
            <a href="{{ $m['href'] ?? '#' }}" class="hm {{ ! empty($m['warn']) ? 'warn' : '' }}"><span class="hm-n">{{ $m['value'] }}</span><span class="hm-l">{{ $m['label'] }}</span></a>
        @endforeach
    </div>
</section>
