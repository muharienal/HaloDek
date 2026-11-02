@props(['id' => 'import-modal', 'action', 'template', 'title' => 'Impor dari CSV', 'columns' => ''])
<div class="modal" id="{{ $id }}" role="dialog" aria-modal="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-head">
                <h3>{{ $title }}</h3>
                <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button>
            </div>
            <div class="modal-body">
                <ol class="text-sm muted" style="padding-left:18px;margin:0 0 14px;display:grid;gap:4px">
                    <li>Unduh <a href="{{ $template }}"><strong>template CSV</strong></a> dan isi datanya.</li>
                    <li>Simpan dari Excel sebagai <em>CSV (Comma/Semicolon delimited)</em>.</li>
                    <li>Unggah file di bawah ini. Baris yang bermasalah dilewati dan dilaporkan.</li>
                </ol>
                @if ($columns)<p class="hint mb-3">Kolom: <span class="mono">{{ $columns }}</span></p>@endif
                <label class="drop" for="{{ $id }}-file" style="display:block">
                    <x-icon name="upload" :size="24" />
                    <p class="strong mt-2">Pilih file CSV</p>
                    <p class="hint">Maksimal 2 MB, 2000 baris</p>
                    <input type="file" name="file" id="{{ $id }}-file" accept=".csv,.txt,text/csv" required data-file-input data-max-files="1" data-max-kb="2048" data-file-list="{{ $id }}-list" data-file-error="{{ $id }}-err" class="sr-only">
                </label>
                <div class="file-list" id="{{ $id }}-list"></div>
                <p class="error mt-2" id="{{ $id }}-err" hidden></p>
                @error('file')<p class="error mt-2">{{ $message }}</p>@enderror
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary"><x-icon name="upload" :size="16" /> Impor</button>
            </div>
        </form>
    </div>
</div>
