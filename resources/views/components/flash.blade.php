@if (session('success'))
    <div class="alert alert-success" data-autohide role="status"><x-icon name="check-circle" :size="18" /><div>{{ session('success') }}</div><button class="close" type="button" aria-label="Tutup">&times;</button></div>
@endif
@if (session('tempPassword'))
    @php $tp = session('tempPassword'); @endphp
    <div class="alert alert-warning" role="alert"><x-icon name="key" :size="18" /><div>
        <strong>Password sementara untuk {{ $tp['name'] }} (NIK {{ $tp['nik'] }}):</strong>
        <div class="mono" style="font-size:18px;margin:6px 0;user-select:all">{{ $tp['password'] }}</div>
        <span class="text-sm">Catat dan sampaikan secara langsung sekarang — password ini tidak akan ditampilkan lagi. Pengguna wajib menggantinya saat login pertama.</span></div></div>
@endif
@if (session('error'))
    <div class="alert alert-error" role="alert"><x-icon name="alert" :size="18" /><div>{{ session('error') }}</div><button class="close" type="button" aria-label="Tutup">&times;</button></div>
@endif
@if (session('warning'))
    <div class="alert alert-warning" role="alert"><x-icon name="alert" :size="18" /><div>{{ session('warning') }}</div><button class="close" type="button" aria-label="Tutup">&times;</button></div>
@endif
@if (session('importErrors'))
    <div class="alert alert-warning"><div><strong>Baris yang dilewati:</strong>
        <ul>@foreach (array_slice(session('importErrors'), 0, 15) as $err)<li>{{ $err }}</li>@endforeach
            @if (count(session('importErrors')) > 15)<li>…dan {{ count(session('importErrors')) - 15 }} baris lainnya.</li>@endif</ul></div></div>
@endif
@if ($errors->any() && ! old('_modal'))
    <div class="alert alert-error" role="alert"><x-icon name="alert" :size="18" /><div><strong>Terjadi kesalahan:</strong>
        <ul>@foreach (collect($errors->all())->unique() as $err)<li>{{ $err }}</li>@endforeach</ul></div></div>
@endif
