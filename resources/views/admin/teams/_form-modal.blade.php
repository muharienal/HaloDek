<x-form-modal :id="$id" create-title="Tambah Regu" edit-title="Ubah Regu" :action="$action" size="lg">
    <div style="display:grid;gap:16px">
        <div class="form-grid">
            <x-input name="name" label="Nama regu" required maxlength="100" placeholder="Mis. Regu K3 & Keselamatan" />
            <x-input name="code" label="Kode tiket" required maxlength="6" placeholder="Mis. K3" style="text-transform:uppercase" />
        </div>
        <x-input name="description" label="Deskripsi singkat" maxlength="255" placeholder="Contoh: Keselamatan kerja, APAR, P3K" />
        <div class="form-grid">
            <x-select name="icon" label="Ikon" required>
                @foreach (config('halodek.team_icons') as $ic)<option value="{{ $ic }}" @selected(old('icon') === $ic)>{{ ucfirst($ic) }}</option>@endforeach
            </x-select>
            <x-select name="color" label="Warna" required>
                @foreach (config('halodek.team_colors') as $name => $hex)<option value="{{ $name }}" @selected(old('color') === $name)>{{ ucfirst($name) }}</option>@endforeach
            </x-select>
        </div>
        <div class="form-grid">
            <div class="field"><span class="label">Status</span>
                <label class="check" style="padding-top:8px"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Aktif menerima laporan</label></div>
        </div>
    </div>
</x-form-modal>
