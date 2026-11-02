@props(['activities', 'viewer'])
<ul class="timeline">
    @foreach ($activities as $a)
        @php
            $isComment = $a->type === 'comment';
            $mine = $a->user_id === $viewer->id;
            $cls = $a->is_internal ? 't-internal' : 't-'.$a->type;
        @endphp
        <li class="tl-item {{ $cls }}">
            <span class="tl-dot"><x-icon :name="$a->is_internal ? 'lock' : $a->icon()" :size="15" /></span>
            <div class="flex items-center gap-2 wrap text-sm">
                <strong>{{ $a->user->name ?? 'Sistem' }}</strong>
                @if ($a->is_internal)<span class="badge tone-violet plain" style="font-size:11px;padding:0 8px">Catatan internal</span>@endif
                <span class="faint text-xs" title="{{ $a->created_at->translatedFormat('d M Y H:i') }}">{{ $a->created_at->diffForHumans() }}</span>
            </div>
            @if ($isComment)
                <div class="bubble {{ $a->is_internal ? 'internal' : ($mine ? 'mine' : '') }} pre-line">{{ $a->message }}</div>
            @else
                <div class="text-sm muted mt-1">{{ $a->message }}</div>
                @if (! empty($a->meta['note']))<div class="bubble pre-line text-sm">{{ $a->meta['note'] }}</div>@endif
            @endif
        </li>
    @endforeach
</ul>
