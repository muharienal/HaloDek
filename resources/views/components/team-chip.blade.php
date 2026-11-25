@props(['team'])
@if ($team)<span {{ $attributes->class(['chip']) }}><span class="chip-ico" style="background: {{ $team->hex() }}1F; color: {{ $team->hex() }}"><x-icon :name="$team->icon" :size="13" /></span>{{ $team->name }}</span>@endif
