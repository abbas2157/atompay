{{-- Paginator in the v2 design (.pill), so it matches the filter controls. --}}
@if ($paginator->hasPages())
    <nav class="pills" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="pill is-locked" aria-disabled="true">Previous</span>
        @else
            <a class="pill" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pill is-locked" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pill on" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pill" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pill" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="pill is-locked" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
