@props(['paginator', 'itemLabel' => 'results', 'ariaLabel' => 'Report pages'])

@once
    <style>
        .report-pagination-wrap { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-top:1rem; padding:0 4px; width:100%; }
        .report-pagination-wrap .results-info { color:#64748b; font-size:13px; }
        .report-pagination-wrap .pagination { display:inline-flex; flex-wrap:wrap; align-items:center; gap:6px; padding-left:0; list-style:none; margin:0; }
        .report-pagination-wrap .page-link { display:inline-flex; align-items:center; justify-content:center; min-width:36px; height:34px; padding:0 10px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#334155; text-decoration:none; font-size:13px; font-weight:500; line-height:1; }
        .report-pagination-wrap .page-item.active .page-link { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
        .report-pagination-wrap .page-item.disabled .page-link { color:#94a3b8; background:#f8fafc; cursor:not-allowed; }
        .report-pagination-wrap a.page-link:hover { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
        @media (max-width:575.98px) { .report-pagination-wrap { justify-content:center; text-align:center; } }
    </style>
@endonce

@if($paginator->count() > 0)
    <div class="report-pagination-wrap">
        <div class="results-info">
            Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}
            of {{ number_format($paginator->total()) }} {{ $itemLabel }}
        </div>

        @if($paginator->hasPages())
            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $pageStart = max(1, $currentPage - 1);
                $pageEnd = min($lastPage, $currentPage + 1);
            @endphp
            <nav aria-label="{{ $ariaLabel }}">
                <ul class="pagination">
                    <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                        @if($paginator->onFirstPage())
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        @else
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&lsaquo;</a>
                        @endif
                    </li>

                    @if($pageStart > 1)
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
                        @if($pageStart > 2)
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        @endif
                    @endif

                    @for($page = $pageStart; $page <= $pageEnd; $page++)
                        <li class="page-item {{ $page === $currentPage ? 'active' : '' }}">
                            @if($page === $currentPage)
                                <span class="page-link" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endfor

                    @if($pageEnd < $lastPage)
                        @if($pageEnd < $lastPage - 1)
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        @endif
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a></li>
                    @endif

                    <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                        @if($paginator->hasMorePages())
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rsaquo;</a>
                        @else
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        @endif
                    </li>
                </ul>
            </nav>
        @endif
    </div>
@endif
