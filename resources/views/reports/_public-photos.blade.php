<div class="att-grid">
    @foreach ($photos as $p)
        @php $src = route('reports.public.photo', ['report' => $report->id, 'token' => $report->publicToken(), 'attachment' => $p->id]); @endphp
        <a class="att" href="{{ $src }}" data-lightbox="{{ $group }}" data-caption="{{ $report->report_number }} · Foto {{ $loop->iteration }} dari {{ $loop->count }}" title="Klik untuk memperbesar">
            <img src="{{ $src }}" alt="Foto {{ $loop->iteration }}" loading="lazy">
        </a>
    @endforeach
</div>
