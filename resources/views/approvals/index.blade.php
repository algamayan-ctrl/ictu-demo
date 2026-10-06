@extends('layouts.app', ['title' => $mode === 'ACTION_OFFICER_VERIFICATION' ? 'For Verification' : 'For Approval'])

@section('content')
@include('approvals.partials.queue-styles')

<div class="approval-queue" data-approval-queue>
    <section class="page-heading approval-queue-heading">
        <div>
            <p class="eyebrow">{{ $mode === 'ACTION_OFFICER_VERIFICATION' ? 'SPMU Action Officer' : 'SPMU Head decision' }}</p>

            <h1>{{ $mode === 'ACTION_OFFICER_VERIFICATION' ? 'Requests for Verification' : 'Requests for Approval' }}</h1>

            <p>
                {{ $mode === 'ACTION_OFFICER_VERIFICATION'
                    ? 'Verify documents and request details before routing to the SPMU Head. Verification is not approval.'
                    : 'Review requests awaiting final SPMU Head decision.' }}
            </p>
        </div>

        <a
            class="button secondary ui-pressable approval-queue-records"
            href="{{ route('requests.index') }}"
        >
            <span>View Request Records</span>
        </a>
    </section>

    @if($mode === 'ACTION_OFFICER_VERIFICATION' || $requests->isNotEmpty())
<div class="approval-queue-filters {{ $mode === 'ACTION_OFFICER_VERIFICATION' ? 'is-verification record-browser-toolbar' : '' }}">
            <label class="{{ $mode === 'ACTION_OFFICER_VERIFICATION' ? 'record-browser-search' : '' }}">
                Search
                <span class="search-input-shell">
                    <span class="search-input-icon" aria-hidden="true"><x-icon name="search" size="17" /></span>
                    <input
                        id="approval-queue-search"
                        type="search"
                        placeholder="Search request no., borrower, event, or item..."
                        autocomplete="off"
                    >
                </span>
            </label>

            @if($mode === 'ACTION_OFFICER_VERIFICATION')
                <label>
                    Status
                    <select id="approval-queue-status">
                        <option value="">All statuses</option>
                        <option value="review">For review</option>
                        <option value="attention">Needs attention</option>
                    </select>
                </label>
            @endif

            <label>
                Sort
                <select id="approval-queue-sort">
                    @if($mode === 'ACTION_OFFICER_VERIFICATION')
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    @else
                        <option value="oldest">Oldest submitted</option>
                        <option value="newest">Newest submitted</option>
                    @endif
                </select>
            </label>
        </div>
    @endif

    @if($requests->isEmpty())
        @include('approvals.partials.queue-empty', [
            'emptyId' => null,
            'emptyHidden' => false,
            'emptyVariant' => null,
            'emptyTitle' => $mode === 'ACTION_OFFICER_VERIFICATION'
                ? 'No requests waiting for verification.'
                : 'No requests waiting for final approval.',
            'emptyMessage' => $mode === 'ACTION_OFFICER_VERIFICATION'
                ? 'New borrower submissions will appear here first.'
                : 'On-campus submissions and Action Officer-verified off-campus requests will appear here.',
        ])
    @else

        <section class="card approval-queue-card" aria-labelledby="approval-queue-title">
            <h2 class="approval-queue-card-heading" id="approval-queue-title">
                {{ $mode === 'ACTION_OFFICER_VERIFICATION' ? 'Pending Verifications' : 'Pending Head Decisions' }}
            </h2>

            <div class="approval-queue-table-wrap">
                <table class="approval-queue-table">
                    <thead>
                        <tr>
                            <th scope="col">Request No.</th>
                            <th scope="col">Borrower</th>
                            <th scope="col">Event / Purpose</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>

                    <tbody id="approval-queue-body">
                        @foreach($requests as $request)
                            @include('approvals.partials.queue-row')
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('approvals.partials.queue-empty', [
                'emptyId' => 'approval-queue-no-results',
                'emptyHidden' => true,
                'emptyVariant' => 'no-results',
                'emptyTitle' => 'No matching requests.',
                'emptyMessage' => 'Try another request number, borrower, event, or item.',
            ])

            <div class="approval-queue-footer" id="approval-queue-footer">
                <p id="approval-queue-summary" role="status" aria-live="polite">
                    Showing {{ min(5, $requests->count()) }} of {{ $requests->count() }} requests
                </p>

                <div
                    class="approval-queue-pagination"
                    id="approval-queue-pagination"
                    role="group"
                    aria-label="Pending approvals pages"
                ></div>
            </div>
        </section>
    @endif
</div>

@include('approvals.partials.queue-interactions')
@endsection

<!-- SPMU APPROVAL NOTICE ALIGNMENT START -->
<style>
/*
 * Keep the approval success notice on the same horizontal grid
 * as the page heading, action button, and approval cards.
 */
.spmu-approval-success-notice {
    box-sizing: border-box !important;
    width: 100% !important;
    max-width: none !important;

    margin: 0 0 20px !important;
    padding: 14px 18px !important;

    display: flex !important;
    align-items: center !important;
    gap: 12px !important;

    line-height: 1.45 !important;
}

.spmu-approval-success-notice > :first-child {
    flex: 0 0 auto !important;
}

.spmu-approval-success-notice .spmu-approval-notice-text {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    margin: 0 !important;
}

.spmu-approval-success-notice .spmu-approval-notice-text,
.spmu-approval-success-notice .spmu-approval-notice-text p {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/*
 * Avoid a visually higher icon when the message wraps onto two lines.
 */
.spmu-approval-success-notice svg {
    display: block !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const expectedText =
        'Verified request E-signed and approved by the authorized SPMU Head signatory';

    /*
     * Locate the success notice by its message rather than depending
     * on one specific historical alert class.
     */
    const candidates = Array.from(
        document.querySelectorAll(
            '[role="alert"], .alert, .notice, .flash-message, .success-message, .message, .banner'
        )
    );

    const notice = candidates.find((element) =>
        (element.textContent || '').includes(expectedText)
    );

    if (!notice) {
        return;
    }

    notice.classList.add(
        'spmu-approval-success-notice'
    );

    /*
     * Wrap text separately from the status icon so both remain
     * vertically aligned even when the message occupies two lines.
     */
    const children = Array.from(notice.childNodes);

    const iconNode = Array.from(notice.children).find((child) =>
        child.matches?.(
            'svg, .icon, .status-icon, .alert-icon, [class*="icon"]'
        )
    );

    const textNodes = children.filter((node) =>
        node !== iconNode &&
        !(
            node.nodeType === Node.TEXT_NODE &&
            !node.textContent.trim()
        )
    );

    if (
        textNodes.length &&
        !notice.querySelector(':scope > .spmu-approval-notice-text')
    ) {
        const textWrapper =
            document.createElement('div');

        textWrapper.className =
            'spmu-approval-notice-text';

        textNodes.forEach((node) => {
            textWrapper.appendChild(node);
        });

        notice.appendChild(textWrapper);
    }
});
</script>
<!-- SPMU APPROVAL NOTICE ALIGNMENT END -->
