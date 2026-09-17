@if ($paginator->hasPages())
    <nav class="pagination-wrapper" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pagination-info text-muted text-sm">
            {{ __('عرض') }} <span class="font-bold">{{ $paginator->firstItem() }}</span>
            {{ __('إلى') }} <span class="font-bold">{{ $paginator->lastItem() }}</span>
            {{ __('من أصل') }} <span class="font-bold">{{ $paginator->total() }}</span> {{ __('نتيجة') }}
        </div>

        <ul class="pagination-links">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">&laquo; {{ __('السابق') }}</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; {{ __('السابق') }}</a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('التالي') }} &raquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">{{ __('التالي') }} &raquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
