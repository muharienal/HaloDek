@props(['id', 'createTitle', 'editTitle' => null, 'action', 'size' => '', 'files' => false, 'submit' => 'Simpan'])
@php $isRestored = old('_modal') === $id; @endphp
<div class="modal" id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="modal-dialog {{ $size }}">
        <form method="POST" action="{{ $action }}" data-modal-form data-create-action="{{ $action }}" @if ($files) enctype="multipart/form-data" @endif>
            @csrf
            <input type="hidden" name="_modal" value="{{ $id }}">
            <input type="hidden" name="_action" value="{{ old('_action', $action) }}">
            <div class="modal-head">
                <h3 id="{{ $id }}-title" data-modal-title data-create-title="{{ $createTitle }}" data-edit-title="{{ $editTitle ?? $createTitle }}">{{ $createTitle }}</h3>
                <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button>
            </div>
            <div class="modal-body">
                @if ($isRestored && $errors->any())
                    <div class="alert alert-error"><div><strong>Periksa kembali isian Anda:</strong>
                        <ul>@foreach (collect($errors->all())->unique()->take(6) as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
                @endif
                {{ $slot }}
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary"><x-icon name="save" :size="16" /> {{ $submit }}</button>
            </div>
        </form>
    </div>
</div>
