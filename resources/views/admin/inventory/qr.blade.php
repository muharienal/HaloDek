<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Stiker QR · {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
    <script src="{{ asset('js/vendor/qrcode-generator-1.4.4.js') }}"></script>
    <script src="{{ asset('js/print.js') }}?v={{ filemtime(public_path('js/print.js')) }}" defer></script>
    <style>
        body{display:block;background:#fff;padding:20px;max-width:210mm;margin:0 auto;color:#0F1B2D}
        .noprint{display:grid;gap:10px;margin-bottom:16px}
        .qr-sheet{display:grid;grid-template-columns:repeat(3,1fr);gap:4mm}
        .qr-lbl{display:flex;gap:3mm;align-items:center;border:1px dashed #B8C4D8;border-radius:3mm;padding:3mm;height:36mm;break-inside:avoid;overflow:hidden}
        .qr-lbl .qr{width:28mm;height:28mm;flex-shrink:0}
        .qr-lbl .qr svg{width:100%;height:100%;display:block}
        .qr-lbl .t{min-width:0;display:grid;gap:.6mm;line-height:1.2}
        .qr-lbl .brand{font-size:7.5pt;font-weight:700;color:#263A86;letter-spacing:.04em}
        .qr-lbl .code{font-size:10.5pt;font-weight:700}
        .qr-lbl .name{font-size:8pt;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
        .qr-lbl .loc{font-size:7pt;color:#55627A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .qr-lbl .cta{font-size:7pt;font-weight:700;color:#C2410C;margin-top:.6mm}
        @media print{.noprint{display:none} body{padding:0} @page{size:A4;margin:10mm} .qr-lbl{border-color:#D5DCE8}}
    </style>
</head>
<body data-autoprint>
    <div class="noprint">
        <div class="flex items-center justify-between wrap gap-2">
            <div><div class="strong">Stiker QR · {{ $title }}</div><div class="text-sm muted">{{ $items->count() }} stiker · ukuran ±6×3,6 cm, 3 kolom per baris A4</div></div>
            <button type="button" class="btn btn-primary" data-print>Cetak Stiker</button>
        </div>
        <div class="alert alert-info" style="margin:0"><div>QR mengarah ke <strong>{{ $base }}</strong>. Pastikan alamat ini bisa dibuka dari HP karyawan (jaringan kantor yang sama). Jika berbeda, atur <code>HALODEK_QR_URL</code> di <code>.env</code> lalu cetak ulang.</div></div>
    </div>

    @if ($items->isEmpty())
        <p class="muted">Tidak ada barang aktif untuk dicetak.</p>
    @else
        <div class="qr-sheet">
            @foreach ($items as $item)
                <div class="qr-lbl">
                    <div class="qr" data-qr="{{ $item->scanUrl() }}" role="img" aria-label="QR {{ $item->code }}"></div>
                    <div class="t">
                        <span class="brand">HALODEK · {{ $item->team->name ?? '' }}</span>
                        <span class="code">{{ $item->code }}</span>
                        <span class="name">{{ $item->name }}</span>
                        @if ($item->location)<span class="loc">{{ $item->location->name }}</span>@endif
                        <span class="cta">Scan untuk lapor kendala</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <script nonce="{{ $cspNonce ?? '' }}">
        document.querySelectorAll('[data-qr]').forEach(function (el) {
            var q = qrcode(0, 'M');
            q.addData(el.getAttribute('data-qr'));
            q.make();
            el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
        });
    </script>
</body>
</html>
