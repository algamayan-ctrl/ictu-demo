<section class="content-area sao-release-browser" data-sao-release-browser>
    <div class="sao-release-toolbar">
        <label>Search
            <span class="search-input-shell">
                <span class="search-input-icon" aria-hidden="true"><x-icon name="search" size="17" /></span>
                <input type="search" id="operational-search" aria-label="Search release records" placeholder="Search borrower, request no., custody no., or event…" autocomplete="off">
            </span>
        </label>
        <label>Status
            <select id="operational-status" aria-label="Release status">
                <option value="all">All statuses</option>
                @foreach($releaseFilterStatuses as $filterStatus)<option value="{{ $filterStatus['key'] }}">{{ $filterStatus['label'] }}</option>@endforeach
            </select>
        </label>
        <label>Sort
            <select id="operational-sort" aria-label="Sort release records"><option value="newest">Newest first</option><option value="oldest">Oldest first</option></select>
        </label>
    </div>

    <article class="card sao-release-records">
        <div class="table-wrap sao-release-table-wrap" data-release-table-wrap @if($custodies->isEmpty()) hidden @endif>
            <table class="sao-release-table">
                <colgroup><col style="width:27%"><col style="width:19%"><col style="width:17%"><col style="width:11%"><col style="width:14%"><col style="width:12%"></colgroup>
                <thead><tr><th scope="col">Borrower / Request</th><th scope="col">Borrowing Dates</th><th scope="col">Pickup</th><th scope="col">Preparation</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead>
                <tbody id="operational-filter-list">
                    @foreach($custodies as $custody)
                        @php
                            $version = $custody->request?->currentVersion;
                            $scheduleDate = $version?->schedule_date ?: $version?->needed_from;
                            $originalReturnDate = $custody->original_due_at ?: $version?->return_date ?: $version?->return_due_at ?: $custody->due_at;
                            $returnDate = $custody->due_at ?: $originalReturnDate;
                            $returnDateAdjusted = $originalReturnDate && $returnDate && ! $originalReturnDate->isSameDay($returnDate);
                            $releaseStatus = \App\Support\ActionOfficerStatusPresentation::releaseStatus($custody->workflowStatus());
                        @endphp
                        <tr data-operational-record data-status="{{ $releaseStatus['key'] }}" data-created="{{ $custody->updated_at?->timestamp ?? 0 }}" data-id="{{ $custody->id }}" data-search="{{ trim(($custody->borrower?->full_name ?? '').' '.($custody->request?->request_no ?? '').' '.$custody->custody_no.' '.($version?->purpose_event ?? '')) }}">
                            <td><strong>{{ $custody->borrower?->full_name ?: 'Not recorded' }}</strong><small>{{ $custody->request?->request_no ?: $custody->custody_no }}</small></td>
                            <td><span>{{ $scheduleDate?->format('d M Y') ?: '—' }}</span><small>{{ $returnDateAdjusted ? 'Effective Return' : 'Return' }}: {{ $returnDate?->format('d M Y') ?: '—' }}</small>@if($returnDateAdjusted)<small>Adjusted from {{ $originalReturnDate->format('d M Y') }}</small>@endif</td>
                            <td>{{ $custody->scheduled_release_at?->format('d M Y, g:i A') ?: 'Not scheduled' }}@if(!$custody->pickup_scheduled_at)<small>Schedule review required</small>@endif</td>
                            <td>{{ $custody->prepared_at ? 'Confirmed' : 'Pending' }}</td>
                            <td><x-status-badge :status="$releaseStatus['key']" :label="$releaseStatus['label']" /></td>
                            <td><a class="button secondary small ui-pressable" href="{{ route('custody.release.show', $custody) }}">View Record</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="sao-release-empty" id="operational-filter-empty" @if($custodies->isNotEmpty()) hidden @endif>
            <span class="sao-release-empty-icon" aria-hidden="true"><x-icon name="box" size="30" /></span>
            <h2 data-release-empty-title>{{ $custodies->isEmpty() ? 'No transactions waiting for release.' : 'No matching release records.' }}</h2>
            <p data-release-empty-copy>{{ $custodies->isEmpty() ? 'Approved transactions appear here until physical release is confirmed.' : 'Try another search term or status.' }}</p>
        </div>

        <footer class="sao-release-footer" data-release-pagination @if($custodies->isEmpty()) hidden @endif>
            <p class="meta" data-release-range role="status" aria-live="polite">{{ $custodies->count() }} {{ \Illuminate\Support\Str::plural('record', $custodies->count()) }}</p>
            <div class="sao-release-pagination">
                <select data-release-page-size aria-label="Release records per page">@foreach([10, 30, 50, 100] as $size)<option value="{{ $size }}">{{ $size }} records per page</option>@endforeach</select>
                <nav aria-label="Release record pages">
                    <button type="button" class="button secondary small" data-release-previous disabled>Previous</button>
                    <span class="meta" data-release-page>Page 1 of 1</span>
                    <button type="button" class="button secondary small" data-release-next disabled>Next</button>
                </nav>
            </div>
        </footer>
    </article>
</section>
@include('custody.partials.sao-release-list-styles')
@include('custody.partials.sao-release-list-interactions')
