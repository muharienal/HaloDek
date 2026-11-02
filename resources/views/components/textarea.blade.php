@props(['name', 'label' => null, 'value' => null, 'required' => false, 'hint' => null, 'counter' => false])
<div {{ $attributes->only('class')->class(['field']) }}>
    @if ($label)<label class="label" for="{{ $attributes->get('id', $name) }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>@endif
    <textarea name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @if ($required) required @endif
              @if ($counter) data-counter="{{ $name }}-counter" @endif
              {{ $attributes->except(['class', 'id'])->class(['textarea', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    <div class="flex justify-between gap-3">
        <span>@if ($hint)<span class="hint">{{ $hint }}</span>@endif @error($name)<span class="error">{{ $message }}</span>@enderror</span>
        @if ($counter)<span class="hint" id="{{ $name }}-counter"></span>@endif
    </div>
</div>
