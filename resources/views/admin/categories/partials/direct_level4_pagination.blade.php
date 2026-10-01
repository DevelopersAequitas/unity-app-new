<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex align-items-center gap-2 small text-muted">
        @if($paginator->total() > 0)
            <span>Showing <strong class="text-dark">{{ $paginator->firstItem() ?? 0 }}</strong> to <strong class="text-dark">{{ $paginator->lastItem() ?? 0 }}</strong> of <strong class="text-dark">{{ number_format($paginator->total()) }}</strong> items</span>
        @else
            <span>0 items</span>
        @endif
        
        <div class="d-inline-flex align-items-center ms-2 gap-1">
            <label for="directL4PerPage" class="small text-muted mb-0">Show:</label>
            <select id="directL4PerPage" class="form-select form-select-sm py-0 px-2" style="font-size: 0.75rem; width: auto; height: 26px;">
                <option value="25" {{ $paginator->perPage() == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ $paginator->perPage() == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ $paginator->perPage() == 100 ? 'selected' : '' }}>100</option>
                <option value="200" {{ $paginator->perPage() == 200 ? 'selected' : '' }}>200</option>
            </select>
        </div>
    </div>

    @if($paginator->hasPages())
        @php
            $currentPage = $paginator->currentPage();
            $lastPage = $paginator->lastPage();
            $start = max(1, $currentPage - 2);
            $end = min($lastPage, $currentPage + 2);
            if ($start > 1) {
                $end = min($lastPage, $start + 4);
            }
            if ($end < $lastPage) {
                $start = max(1, $end - 4);
            }
        @endphp
        <nav aria-label="Direct subcategories pagination">
            <ul class="pagination pagination-sm mb-0">
                {{-- First Page --}}
                <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                    <a class="page-link ajax-page-link" href="#" data-page="1" aria-label="First" title="First Page">
                        <i class="bi bi-chevron-double-left"></i>
                    </a>
                </li>

                {{-- Previous Page --}}
                <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                    <a class="page-link ajax-page-link" href="#" data-page="{{ $currentPage - 1 }}" aria-label="Previous" title="Previous Page">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                {{-- Window Start Ellipsis --}}
                @if($start > 1)
                    <li class="page-item">
                        <a class="page-link ajax-page-link" href="#" data-page="1">1</a>
                    </li>
                    @if($start > 2)
                        <li class="page-item disabled"><span class="page-link py-1 px-2 border-0 bg-transparent text-muted">…</span></li>
                    @endif
                @endif

                {{-- Page Numbers --}}
                @for($page = $start; $page <= $end; $page++)
                    <li class="page-item {{ $page == $currentPage ? 'active' : '' }}">
                        <a class="page-link ajax-page-link" href="#" data-page="{{ $page }}">{{ $page }}</a>
                    </li>
                @endfor

                {{-- Window End Ellipsis --}}
                @if($end < $lastPage)
                    @if($end < $lastPage - 1)
                        <li class="page-item disabled"><span class="page-link py-1 px-2 border-0 bg-transparent text-muted">…</span></li>
                    @endif
                    <li class="page-item">
                        <a class="page-link ajax-page-link" href="#" data-page="{{ $lastPage }}">{{ $lastPage }}</a>
                    </li>
                @endif

                {{-- Next Page --}}
                <li class="page-item {{ $currentPage == $lastPage ? 'disabled' : '' }}">
                    <a class="page-link ajax-page-link" href="#" data-page="{{ $currentPage + 1 }}" aria-label="Next" title="Next Page">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>

                {{-- Last Page --}}
                <li class="page-item {{ $currentPage == $lastPage ? 'disabled' : '' }}">
                    <a class="page-link ajax-page-link" href="#" data-page="{{ $lastPage }}" aria-label="Last" title="Last Page">
                        <i class="bi bi-chevron-double-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    @endif
</div>
