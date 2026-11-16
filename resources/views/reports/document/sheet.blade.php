@php
    // This is the shared formal report sheet for preview and printing.
    $options = $options ?? [];
@endphp

<article class="doc-sheet">
    @if($options['header'] ?? true)
        @include('reports.document.header')
    @endif

    @include('reports.document.title')

    @if($options['metadata'] ?? true)
        @include('reports.document.metadata')
    @endif

    @if($options['summary'] ?? true)
        @include('reports.document.summary')
    @endif

    <div class="doc-table-scroll">
        @include('reports.document.table')
    </div>

    @include('reports.document.signatories')

    @if($options['footer'] ?? true)
        @include('reports.document.footer')
    @endif
</article>
