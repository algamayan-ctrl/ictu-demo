@extends('layouts.app', ['title' => 'User Accounts'])
@section('content')
@php
    $classificationOptions = $users->map(fn ($user) => $user->access_classification?->label())->filter()->unique()->sort()->values();
    $statusOptions = $users->map(fn ($user) => $user->account_status?->value ?? 'ACTIVE')->filter()->unique()->sort()->values();
@endphp

<section class="page-heading ictu-users-heading" aria-labelledby="ictu-institutional-heading">
    <div>
        <p class="eyebrow">ICTU identity administration</p>
        <h1 id="ictu-institutional-heading">Institutional user accounts</h1>
        <p>Manage official CSPC identities, organizational assignments, access, and account status.</p>
    </div>
    <a class="button primary ictu-register-button" href="{{ route('administration.users.create') }}">
        Register account
    </a>
</section>

<section class="content-area ictu-users-area" aria-label="User administration directory">
    @if($verificationFilter ?? false)
        <x-filter-context-banner
            label="Pending Verification"
            :count="$users->count()"
            :clear-url="route('administration.users.index')"
        />
    @elseif($statusFilter)
        <x-filter-context-banner
            :label="$statusFilter === 'active' ? 'Active Accounts' : 'Inactive Accounts'"
            :count="$users->count()"
            :clear-url="route('administration.users.index')"
        />
    @endif
    <div class="card ictu-users-filter-card" aria-label="User account filters">
        <div class="ictu-users-filter-grid">
            <div class="ictu-users-filter-field ictu-users-filter-field-search">
                <label for="ictu-user-search">Search</label>
                <div class="ictu-users-search-control">
                    <x-icon name="search" size="21" />
                    <input id="ictu-user-search" type="search" placeholder="Search name, employee no., email, office..." autocomplete="off">
                </div>
            </div>

            <div class="ictu-users-filter-field">
                <label for="ictu-user-classification-filter">Role</label>
                <select id="ictu-user-classification-filter">
                    <option value="ALL">All roles</option>
                    @foreach($classificationOptions as $classificationOption)
                        <option value="{{ $classificationOption }}">{{ $classificationOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ictu-users-filter-field">
                <label for="ictu-user-status-filter">Status</label>
                <select id="ictu-user-status-filter">
                    <option value="ALL">All statuses</option>
                    @foreach($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}">{{ ucwords(strtolower(str_replace('_', ' ', $statusOption))) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ictu-users-filter-field">
                <label for="ictu-user-restriction-filter">Borrowing</label>
                <div class="ictu-users-borrowing-actions">
                    <select id="ictu-user-restriction-filter">
                        <option value="ALL">All</option>
                        <option value="CLEAR">Not restricted</option>
                        <option value="RESTRICTED">Restricted</option>
                    </select>
                    <button class="button secondary ictu-users-reset" type="button" id="ictu-user-reset" title="Clear all filters">
                        <x-icon name="cycle" size="18" />
                        <span>Reset</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="card ictu-users-list-card" aria-labelledby="ictu-users-heading">
        <header class="ictu-users-list-toolbar">
            <div class="ictu-users-list-title">
                <h2 id="ictu-users-heading">User Accounts</h2>
                <span class="ictu-users-total-pill" id="ictu-user-result-count" aria-live="polite">{{ $users->count() }} total user{{ $users->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="ictu-users-pagination-meta" aria-label="User account pagination">
                <label class="visually-hidden" for="ictu-user-page-size">Users per page</label>
                <select id="ictu-user-page-size" aria-label="Users per page">
                    <option value="7" selected>7 users per page</option>
                    <option value="14">14 users per page</option>
                    <option value="28">28 users per page</option>
                </select>
                <span class="ictu-users-pagination-divider" aria-hidden="true"></span>
                <span id="ictu-user-page-indicator" aria-live="polite">{{ $users->count() ? '1–'.min(7, $users->count()).' of '.$users->count() : '0–0 of 0' }}</span>
                <div class="ictu-users-pagination-buttons">
                    <button type="button" class="ictu-users-page-button icon-button" id="ictu-user-prev" aria-label="Previous page" disabled>
                        <x-icon name="chevron-right" size="17" class="is-rotated" />
                    </button>
                    <button type="button" class="ictu-users-page-button icon-button" id="ictu-user-next" aria-label="Next page">
                        <x-icon name="chevron-right" size="17" />
                    </button>
                </div>
            </div>
        </header>

        <div class="ictu-users-table-scroll" role="region" aria-label="User accounts table" tabindex="0">
            <table class="ictu-users-table">
                <colgroup><col class="ictu-col-user"><col class="ictu-col-number"><col class="ictu-col-unit"><col class="ictu-col-role"><col class="ictu-col-status"><col class="ictu-col-action"></colgroup>
                <thead>
                    <tr><th scope="col">User</th><th scope="col">Employee No.</th><th scope="col">Office / College / Unit</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col" class="ictu-users-action-heading">Action</th></tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    @php
                        $classificationLabel = $user->access_classification?->label() ?? 'Not assigned';
                        $accountStatus = $user->account_status?->value ?? 'ACTIVE';
                        $isOwnAccount = auth()->id() === $user->id;
                        $canDeactivate = $accountStatus === 'ACTIVE' && ! $isOwnAccount;
                        $isRestricted = $user->activeRestrictions()->exists();
                        $personnelType = match ($user->employment_type?->value) {
                            'FACULTY' => 'Faculty', 'EMPLOYEE' => 'Employee', 'STAFF' => 'Staff', default => null,
                        };
                        $employmentStatus = match ($user->employment_status ?? null) {
                            'FULL_TIME' => 'Full-time', 'PART_TIME' => 'Part-time', default => null,
                        };
                        $nameParts = collect(preg_split('/\s+/', trim((string) $user->full_name)) ?: [])->filter()->values();
                        $initials = $nameParts->isEmpty() ? '' : mb_strtoupper(
                            mb_substr((string) $nameParts->first(), 0, 1)
                            .($nameParts->count() > 1 ? mb_substr((string) $nameParts->last(), 0, 1) : '')
                        );
                        $searchText = mb_strtolower(implode(' ', array_filter([
                            $user->full_name, $user->designation, $user->employee_no, $user->email,
                            $user->organizationalUnit?->unit_name, $user->organizationalUnit?->divisionLabel(),
                            $classificationLabel, $personnelType, $employmentStatus,
                        ])));
                    @endphp
                    <tr data-ictu-user-row data-user-search="{{ $searchText }}" data-user-classification="{{ $classificationLabel }}" data-user-status="{{ $accountStatus }}" data-user-restriction="{{ $isRestricted ? 'RESTRICTED' : 'CLEAR' }}">
                        <td>
                            <div class="ictu-user-identity-wrap">
                                <span class="ictu-user-avatar" aria-hidden="true">{{ $initials ?: 'U' }}</span>
                                <div class="ictu-user-identity-copy">
                                    <strong>{{ $user->full_name }}</strong>
                                    <span>{{ $user->designation ?: 'No designation recorded' }}</span>
                                    <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                                </div>
                            </div>
                        </td>
                        <td class="ictu-user-number-cell"><strong>{{ $user->employee_no ?: 'Not recorded' }}</strong></td>
                        <td class="ictu-user-unit-cell">
                            <strong>{{ $user->organizationalUnit?->unit_name ?: 'No assignment' }}</strong>
                            @if($user->organizationalUnit?->divisionLabel())<span>{{ $user->organizationalUnit->divisionLabel() }}</span>@endif
                        </td>
                        <td class="ictu-user-access-cell">
                            <strong>{{ $classificationLabel }}</strong>
                            @if($personnelType || $employmentStatus)<span>{{ collect([$personnelType, $employmentStatus])->filter()->join(' • ') }}</span>@endif
                        </td>
                        <td><div class="ictu-user-status-stack">
                            <x-status-badge :status="$user->account_status ?? 'ACTIVE'" />
                            @if($user->hasVerifiedInstitutionalEmail())
                                <x-status-badge status="EMAIL_VERIFIED" label="Email verified" />
                            @else
                                <x-status-badge status="EMAIL_PENDING" label="OTP pending" />
                            @endif
                            @if($isRestricted)
                                <x-status-badge status="BORROWING_RESTRICTED" />
                            @else
                                <x-status-badge status="NOT_APPLICABLE" label="Not restricted" />
                            @endif
                        </div></td>
                        <td class="ictu-user-action-cell">
                            <div class="ictu-user-action-inline">
                                <a class="ictu-user-icon-button" href="{{ route('administration.users.edit', $user) }}"
                                   title="Manage {{ $user->full_name }}" aria-label="Manage {{ $user->full_name }}">
                                    <x-icon name="edit" size="18" />
                                </a>
                                <form method="post" action="{{ route('administration.users.deactivate', $user) }}"
                                      data-ictu-deactivate-form data-user-name="{{ $user->full_name }}" data-preserve-submit-label="true">
                                    @csrf
                                    @method('PATCH')
                                    <button class="ictu-user-icon-button ictu-user-deactivate-button" type="submit"
                                            @disabled(! $canDeactivate)
                                            title="{{ $isOwnAccount ? 'You cannot deactivate your own account' : ($accountStatus !== 'ACTIVE' ? 'Account is already inactive or suspended' : 'Deactivate '.$user->full_name) }}"
                                            aria-label="Deactivate {{ $user->full_name }}">
                                        <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="1.8"
                                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <path d="M18.36 6.64a9 9 0 1 1-12.73 0" /><path d="M12 2v10" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="ictu-users-empty-directory"><td colspan="6">No institutional accounts have been registered.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ictu-users-no-results empty-state" id="ictu-user-no-results" hidden>No accounts match the selected filters.</div>
    </section>
</section>

@include('administration.users.partials.ui-styles')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('ictu-user-search');
    const classification = document.getElementById('ictu-user-classification-filter');
    const status = document.getElementById('ictu-user-status-filter');
    const restriction = document.getElementById('ictu-user-restriction-filter');
    const reset = document.getElementById('ictu-user-reset');
    const resultCount = document.getElementById('ictu-user-result-count');
    const pageIndicator = document.getElementById('ictu-user-page-indicator');
    const pageSizeControl = document.getElementById('ictu-user-page-size');
    const prevPage = document.getElementById('ictu-user-prev');
    const nextPage = document.getElementById('ictu-user-next');
    const noResults = document.getElementById('ictu-user-no-results');
    const tableScroll = document.querySelector('.ictu-users-table-scroll');
    const rows = Array.from(document.querySelectorAll('[data-ictu-user-row]'));
    let pageSize = Number(pageSizeControl?.value || 7);
    let currentPage = 1;
    let filteredRows = [...rows];

    const renderPage = () => {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.max(1, Math.min(currentPage, totalPages));
        const start = total === 0 ? 0 : (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        rows.forEach(row => { row.hidden = true; });
        filteredRows.slice(start, end).forEach(row => { row.hidden = false; });
        if (resultCount) resultCount.textContent = `${total} total user${total === 1 ? '' : 's'}`;
        if (pageIndicator) pageIndicator.textContent = total ? `${start + 1}–${end} of ${total}` : '0–0 of 0';
        if (prevPage) prevPage.disabled = currentPage <= 1 || total === 0;
        if (nextPage) nextPage.disabled = currentPage >= totalPages || total === 0;
        if (noResults) noResults.hidden = total !== 0 || rows.length === 0;
        tableScroll?.scrollTo({ top: 0, behavior: 'auto' });
    };
    const applyFilters = () => {
        const query = (search?.value || '').trim().toLocaleLowerCase();
        filteredRows = rows.filter(row =>
            (!query || (row.dataset.userSearch || '').includes(query)) &&
            (classification?.value === 'ALL' || row.dataset.userClassification === classification?.value) &&
            (status?.value === 'ALL' || row.dataset.userStatus === status?.value) &&
            (restriction?.value === 'ALL' || row.dataset.userRestriction === restriction?.value)
        );
        currentPage = 1;
        renderPage();
    };
    [search, classification, status, restriction].forEach(control => control?.addEventListener(control === search ? 'input' : 'change', applyFilters));
    reset?.addEventListener('click', () => {
        if (search) search.value = '';
        if (classification) classification.value = 'ALL';
        if (status) status.value = 'ALL';
        if (restriction) restriction.value = 'ALL';
        applyFilters();
        search?.focus();
    });
    pageSizeControl?.addEventListener('change', () => {
        pageSize = Number(pageSizeControl.value) || 7;
        currentPage = 1;
        renderPage();
    });
    prevPage?.addEventListener('click', () => { if (currentPage > 1) { currentPage -= 1; renderPage(); } });
    nextPage?.addEventListener('click', () => {
        if (currentPage < Math.ceil(filteredRows.length / pageSize)) { currentPage += 1; renderPage(); }
    });
    // The confirmation happens at click time, before the application-wide
    // duplicate-submit handler locks state-changing forms on submit.
    document.querySelectorAll('[data-ictu-deactivate-form] button:not(:disabled)').forEach(button => {
        button.addEventListener('click', event => {
            const form = button.closest('form');
            const name = form?.dataset.userName || 'this account';
            if (!window.confirm(`Deactivate ${name}? Portal access will be disabled, but all records will be retained. Reactivate through Manage User.`)) {
                event.preventDefault();
            }
        });
    });
    applyFilters();
});
</script>
@endsection
