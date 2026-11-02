<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $report->report_number }} — {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
</head>
<body>
<header class="topnav-wrap">
    <div class="topnav">
        <span class="lockup">
            <span class="co-tile"><img src="{{ asset('images/logo-pcs.png') }}" alt="{{ config('halodek.company') }}"></span>
            <span class="lockup-sep"></span>
            <span class="lockup-mark"><x-icon name="halodek" :size="24" /></span>
            <span class="lockup-name">{{ config('halodek.name') }}</span>
        </span>
    </div>
</header>

<main class="shell">
    <div class="page-head"><h1>{{ $report->report_number }}</h1></div>

    <div class="flex gap-2 wrap items-center mb-4">
        <x-badge :tone="$report->statusTone()">{{ $report->statusLabel() }}</x-badge>
        <x-badge :tone="$report->priorityTone()" plain>Prioritas {{ $report->priorityLabel() }}</x-badge>
    </div>

    <div class="grid cols-main-r" style="align-items:start">
        <div style="display:grid;gap:20px;min-width:0">
            <div class="card">
                <div class="card-head"><h2>{{ $report->title }}</h2><x-team-chip :team="$report->team" /></div>
                <div class="card-body">
                    <div class="flex gap-3 items-center mb-3">
                        <span class="avatar">{{ $report->user?->initials() ?? '?' }}</span>
                        <div><div class="strong">{{ $report->reporter_name }}</div>
                            <div class="text-xs muted">{{ $report->user?->department ?: 'Pelapor' }}</div></div>
                    </div>
                    <p class="pre-line">{{ $report->description }}</p>
                    @if ($evidence->isNotEmpty())
                        <div class="divider"></div>
                        <p class="label mb-2">Lampiran</p>
                        @include('reports._public-photos', ['photos' => $evidence, 'group' => 'bukti'])
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Riwayat</h2></div>
                <div class="card-body">
                    <ul class="timeline">
                        @foreach ($activities as $a)
                            <li class="tl-item t-{{ $a->type }}">
                                <span class="tl-dot"><x-icon :name="$a->icon()" :size="15" /></span>
                                <div class="flex items-center gap-2 wrap text-sm">
                                    <strong>{{ $a->user->name ?? 'Sistem' }}</strong>
                                    <span class="faint text-xs">{{ $a->created_at->translatedFormat('d M Y, H:i') }}</span>
                                </div>
                                <div class="text-sm muted mt-1">{{ $a->message }}</div>
                                @if (! empty($a->meta['note']))<div class="bubble pre-line text-sm">{{ $a->meta['note'] }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div style="display:grid;gap:20px;min-width:0">
            <div class="card">
                <div class="card-head"><h2>Informasi</h2></div>
                <div class="card-body" style="display:grid;gap:14px">
                    <div><div class="text-xs muted">Regu tujuan</div><div>Regu {{ $report->team->name }}</div></div>
                    <div><div class="text-xs muted">Kategori</div><div>{{ $report->categoryDisplayName() }}</div></div>
                    <div><div class="text-xs muted">Diajukan</div><div>{{ $report->created_at->translatedFormat('d M Y, H:i') }}</div></div>
                    <div><div class="text-xs muted">Penanggung jawab</div><div>@if ($report->assignee)<span class="strong">{{ $report->assignee->name }}</span> <span class="muted">· {{ $report->assignee->roleLabel() }}</span>@else<span class="badge tone-amber plain">Belum ditugaskan</span>@endif</div></div>
                    @if ($report->resolved_at)<div><div class="text-xs muted">Selesai</div><div>{{ $report->resolved_at->translatedFormat('d M Y, H:i') }} <span class="muted">({{ $report->resolutionHours() }} jam)</span></div></div>@endif
                    @if ($report->location)<div><div class="text-xs muted">Lokasi</div><div>{{ $report->location->fullName() }}</div></div>@endif
                    @if ($report->item)<div><div class="text-xs muted">Inventaris terkait</div><div>{{ $report->item->label() }}</div></div>@endif
                </div>
            </div>

            @if ($report->follow_up_note || $results->isNotEmpty())
                <div class="card" style="border-color: var(--primary)">
                    <div class="card-head"><h2>Tindak Lanjut</h2></div>
                    <div class="card-body">
                        @if ($report->follow_up_note)<p class="pre-line">{{ $report->follow_up_note }}</p>@endif
                        @if ($results->isNotEmpty())
                            @if ($report->follow_up_note)<div class="divider"></div>@endif
                            <p class="label mb-2">Foto hasil pekerjaan</p>
                            @include('reports._public-photos', ['photos' => $results, 'group' => 'hasil'])
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</main>

<footer class="footer">© {{ date('Y') }} IT {{ config('halodek.company') }}</footer>
<script src="{{ asset('js/halodek.js') }}?v={{ filemtime(public_path('js/halodek.js')) }}"></script>
</body>
</html>
