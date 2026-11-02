@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Navigasi halaman">
        <span class="text-sm muted">Menampilkan {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} data</span>
        @if ($paginator->hasPages())
            <div class="pages">
                @if ($paginator->onFirstPage())<span class="pg off">‹</span>@else<a class="pg" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>@endif
                @foreach ($elements as $element)
                    @if (is_string($element))<span class="pg off">{{ $element }}</span>@endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())<span class="pg current" aria-current="page">{{ $page }}</span>
                            @else<a class="pg" href="{{ $url }}">{{ $page }}</a>@endif
                        @endforeach
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())<a class="pg" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>@else<span class="pg off">›</span>@endif
            </div>
        @endif
    </nav>
@endif
