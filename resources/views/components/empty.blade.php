@props(['icon' => 'inbox', 'title' => 'Belum ada data'])
<div {{ $attributes->class(['empty']) }}>
    <div class="ico"><x-icon :name="$icon" :size="26" /></div>
    <p class="strong">{{ $title }}</p>
    @if (trim($slot))<p class="text-sm mt-1">{{ $slot }}</p>@endif
</div>
