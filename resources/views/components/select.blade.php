@props(['name', 'label' => null, 'required' => false, 'hint' => null])
<div {{ $attributes->only('class')->class(['field']) }}>
    @if ($label)<label class="label" for="{{ $attributes->get('id', $name) }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @if ($required) required @endif
            {{ $attributes->except(['class', 'id'])->class(['select', 'is-invalid' => $errors->has($name)]) }}>{{ $slot }}</select>
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
