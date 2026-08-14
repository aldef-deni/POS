@if ($paginator->hasPages())
    <nav class="between wrap g-12" style="padding:14px 20px;border-top:1px solid var(--border)">
        <div class="small muted">
            Menampilkan <span class="semi">{{ $paginator->firstItem() }}</span>–<span class="semi">{{ $paginator->lastItem() }}</span>
            dari <span class="semi">{{ $paginator->total() }}</span> data
        </div>

        <div class="pagination">
            @if ($paginator->onFirstPage())
                <span class="pagination__link is-disabled">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination__link" rel="prev">‹</a>
            @endif

            {{-- simplePaginate() does not supply $elements, so guard it. --}}
            @foreach ($elements ?? [] as $element)
                @if (is_string($element))
                    <span class="pagination__link is-disabled">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination__link is-active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination__link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination__link" rel="next">›</a>
            @else
                <span class="pagination__link is-disabled">›</span>
            @endif
        </div>
    </nav>
@endif
