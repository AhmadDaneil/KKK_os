@if ($paginator->hasPages())
    <nav class="admin-invoice-page-nav" role="navigation" aria-label="Pagination Navigation">
        <div class="admin-invoice-page-controls">
            @if ($paginator->onFirstPage())
                <span class="admin-invoice-page-link is-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="admin-invoice-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="admin-invoice-page-ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="admin-invoice-page-link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="admin-invoice-page-link" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="admin-invoice-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">Next</a>
            @else
                <span class="admin-invoice-page-link is-disabled" aria-disabled="true">Next</span>
            @endif
        </div>

        <p class="admin-invoice-page-summary">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} invoices
        </p>
    </nav>
@endif
