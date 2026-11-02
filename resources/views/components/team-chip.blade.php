@props(['team'])
@if ($team)<span class="chip" style="background: {{ $team->hex() }}1A; color: {{ $team->hex() }}; border-color: {{ $team->hex() }}40"><x-icon :name="$team->icon" :size="13" /> {{ $team->name }}</span>@endif
