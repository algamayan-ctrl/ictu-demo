@extends('layouts.app', ['title' => 'Completed Laundry'])
@section('content')
{{-- This is the Completed Laundry Records UI for SPMU Action Officer. --}}
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

    @include('laundry.partials.record-filters', ['completed' => true, 'records' => $jobs, 'filterAction' => route('laundry.completed')])
    @if($jobs->total() === 0)
        <section class="card completed-laundry-empty" aria-labelledby="completed-laundry-empty-title">
            <div class="completed-laundry-empty-content">
                @include('laundry.partials.completed-empty-illustration')
                <h2 id="completed-laundry-empty-title">{{ request()->filled('completed_q') || request('completed_outcome', 'ALL') !== 'ALL' ? 'No completed cases match your filters.' : 'No completed laundry cases yet.' }}</h2>
                <p>{{ request()->filled('completed_q') || request('completed_outcome', 'ALL') !== 'ALL' ? 'Try another search or clear the filters.' : 'Completed laundry cases will appear here after processing is finalized.' }}</p>
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

            <x-record-table-footer :records="$jobs" label="Completed laundry pages" />
        </section>
    @endif

    <div class="completed-laundry-archive-note"><x-icon name="information" size="21" /><p>Completed cases are archived for record keeping and inventory management.</p></div>
</div>


@endsection
