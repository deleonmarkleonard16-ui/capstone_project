@if ($paginator->hasPages())
    <nav class="system-pagination d-flex flex-wrap align-items-center justify-content-between" role="navigation" aria-label="Pagination Navigation">
        <p class="pagination-summary order-2 order-sm-1">
            Showing <strong>{{ $paginator->firstItem() }}</strong>&ndash;<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ $paginator->total() }}</strong> results
        </p>

        <ul class="pagination order-1 order-sm-2">
            @if ($paginator->onFirstPage())
                <li class="page-item pagination-direction disabled" aria-disabled="true">
                    <span class="page-link"><span aria-hidden="true">&lsaquo;</span> Back</span>
                </li>
            @else
                <li class="page-item pagination-direction">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><span aria-hidden="true">&lsaquo;</span> Back</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item d-none d-sm-block disabled" aria-disabled="true">
                        <span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active d-none d-sm-block" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item d-none d-sm-block">
                                <a class="page-link" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item pagination-direction">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next <span aria-hidden="true">&rsaquo;</span></a>
                </li>
            @else
                <li class="page-item pagination-direction disabled" aria-disabled="true">
                    <span class="page-link">Next <span aria-hidden="true">&rsaquo;</span></span>
                </li>
            @endif
        </ul>
    </nav>
@endif
