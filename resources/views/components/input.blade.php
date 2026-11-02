@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'list' => null])
<div {{ $attributes->only('class')->class(['field']) }}>
    @if ($label)<label class="label" for="{{ $attributes->get('id', $name) }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($required) required @endif @if ($list) list="{{ $list }}" @endif
           {{ $attributes->except(['class', 'id'])->class(['input', 'is-invalid' => $errors->has($name)]) }}>
    @if ($hint)<span class="hint">{{ $hint }}</span>@endif
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
