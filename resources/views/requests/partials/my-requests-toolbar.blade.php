<section class="record-browser-toolbar mr-toolbar" aria-label="Borrowing request controls">
    <label class="record-browser-search">
        Search

        <span class="search-input-shell">
            <span class="search-input-icon" aria-hidden="true">
                <x-icon name="search" size="17" />
            </span>

            <input
                id="request-search"
                type="search"
                placeholder="Search request no., purpose, or item..."
                autocomplete="off"
            >
        </span>
    </label>

    <label>
        Status

        <select id="request-status-filter">
            <option value="all">All statuses</option>
            <option value="DRAFT">Draft</option>
            <option value="FOR_REVIEW">For Review</option>
            <option value="NEEDS_REVISION">Needs Revision</option>
            <option value="PICKUP_SCHEDULED">Pickup Scheduled</option>
            <option value="FOR_ISSUANCE">For Issuance</option>
            <option value="READY_FOR_PICKUP">Ready for Pickup</option>
            <option value="MISSED_PICKUP">Missed Pickup</option>
            <option value="ON_CUSTODY">On Custody</option>
            <option value="OVERDUE">Overdue</option>
            <option value="COMPLETED">Completed</option>
            <option value="CANCELLED">Cancelled</option>
            <option value="REJECTED">Rejected</option>
        </select>
    </label>

    <label>
        Sort

        <select id="request-sort" aria-label="Sort requests">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
        </select>
    </label>
</section>
