@extends('layouts.app', ['title' => 'Completed Laundry'])
@section('content')
@include('laundry.partials.completed-styles')

<div class="completed-laundry" data-completed-laundry>
    <section class="page-heading completed-laundry-heading">
        <div>
            <p class="eyebrow">Laundry Operations</p>
            <h1>Completed Laundry</h1>
            <p>Review completed laundry cases and their final disposition.</p>
        </div>
        @include('laundry.partials.completed-back-link')
    </section>

    @include('laundry.partials.record-filters', ['completed' => true, 'records' => $jobs, 'showPageSize' => false, 'filterAction' => route('laundry.completed')])
    @if($jobs->total() === 0)
        <section class="card completed-laundry-empty" aria-labelledby="completed-laundry-empty-title">
            <div class="completed-laundry-empty-content">
                @include('laundry.partials.completed-empty-illustration')
                <h2 id="completed-laundry-empty-title">{{ request()->filled('completed_q') || request('completed_outcome', 'ALL') !== 'ALL' ? 'No completed cases match your filters.' : 'No completed laundry cases yet.' }}</h2>
                <p>Completed laundry cases will appear here after processing is finalized.</p>
                @include('laundry.partials.completed-back-link')
            </div>
        </section>
    @else


        <section class="card completed-laundry-card" aria-labelledby="completed-laundry-cases-title">
            <div class="completed-laundry-cases-heading">
                <h2 id="completed-laundry-cases-title">Completed Laundry Cases</h2>
                <p data-completed-count data-total="{{ $jobs->total() }}" data-first="{{ $jobs->firstItem() ?? 0 }}" data-last="{{ $jobs->lastItem() ?? 0 }}" data-paginated="{{ $jobs->hasPages() ? 'true' : 'false' }}" role="status" aria-live="polite">Showing {{ $jobs->firstItem() ?? 0 }} to {{ $jobs->lastItem() ?? 0 }} of {{ $jobs->total() }} completed cases</p>
            </div>

            <div class="completed-laundry-table-wrap">
                <table class="completed-laundry-table">
                    <thead><tr><th scope="col">Case ID</th><th scope="col">Borrower</th><th scope="col">Items</th><th scope="col">Completed Date</th><th scope="col">Return Result</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                        @foreach($jobs as $job)
                            @include('laundry.partials.completed-case-row')
                        @endforeach
                    </tbody>
                </table>

            </div>

            <div class="completed-laundry-footer completed-laundry-footer-universal">
                <div class="completed-laundry-pagination-meta">
                    <span>Showing {{ $jobs->firstItem() ?? 0 }} to {{ $jobs->lastItem() ?? 0 }} of {{ $jobs->total() }} completed cases</span>
                    <form method="get" action="{{ route('laundry.completed') }}">
                        @foreach(request()->except(['page', 'per_page', 'completed_page', 'completed_per_page']) as $name => $value)
                            @if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                        @endforeach
                        <label class="sr-only" for="completed-laundry-archive-per-page">Completed laundry cases per page</label>
                        <select id="completed-laundry-archive-per-page" name="per_page" onchange="this.form.submit()">
                            @foreach(array_values(array_unique([7, 15, 30, 50, $perPage])) as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} items per page</option>@endforeach
                        </select>
                    </form>
                </div>
                @if($jobs->hasPages()){{ $jobs->onEachSide(1)->links('laundry.partials.completed-pagination') }}@endif
            </div>
        </section>
    @endif

    <div class="completed-laundry-archive-note"><x-icon name="information" size="21" /><p>Completed cases are archived for record keeping and inventory management.</p></div>
</div>


<style>
.completed-laundry-footer-universal { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:14px 0 4px; }
.completed-laundry-pagination-meta { display:inline-flex; align-items:center; flex-wrap:wrap; gap:10px; color:var(--text-muted); font-size:11px; }
.completed-laundry-pagination-meta form { margin:0; }
.completed-laundry-pagination-meta select { min-height:32px; padding:4px 28px 4px 9px; font-size:11px; }
</style>
@endsection
