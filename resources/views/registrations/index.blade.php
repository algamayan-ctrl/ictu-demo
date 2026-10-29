@extends('layouts.app', ['title' => 'Borrower registrations'])
@section('content')
@include('administration.users.partials.ui-styles')
<style>
.registration-records .ictu-users-table { min-width: 850px; table-layout: auto; }
.registration-records .ictu-users-table td { height: auto; padding: 9px 15px; }
.registration-records .registration-applicant strong, .registration-records .registration-applicant span, .registration-records .registration-affiliation span { display: block; }
.registration-records .registration-applicant strong { color: var(--heading); font-size: 13px; }
.registration-records .registration-applicant span, .registration-records .registration-affiliation span { margin-top: 3px; color: var(--text-muted); font-size: 12px; }
.registration-records .registration-review-cell { width: 70px; text-align: center; }
.registration-records .registration-pagination { padding: 16px 24px; }
.registration-records .registration-pagination .pagination { margin: 0; }
#registration-records-area { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
#registration-records-area > .ictu-users-filter-card { flex: 0 0 auto; }
#registration-results { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 320px; }
#registration-results > .ictu-users-list-toolbar { flex: 0 0 auto; }
#registration-results > .ictu-users-table-scroll { flex: 0 0 auto; }
#registration-results > .registration-pagination { flex: 0 0 auto; margin-top: auto; border-top: 1px solid var(--border); }
#registration-results:has(.registration-empty-row) > .ictu-users-table-scroll { display: flex; flex: 1 1 auto; }
#registration-results:has(.registration-empty-row) .ictu-users-table { height: 100%; }
#registration-results:has(.registration-empty-row) thead { height: 40px; }
#registration-results .registration-empty-row td { text-align: center; vertical-align: middle; }
#registration-records-area .ictu-users-filter-grid { grid-template-columns: minmax(260px, 1fr) minmax(180px, 240px) minmax(180px, 240px); }
#registration-results[aria-busy="true"] { opacity: .65; pointer-events: none; }
@media (max-width: 1100px) { #registration-records-area .ictu-users-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } #registration-records-area .ictu-users-filter-field:first-child { grid-column: 1 / -1; } }
@media (max-width: 640px) { #registration-records-area .ictu-users-filter-grid { grid-template-columns: minmax(0, 1fr); } #registration-records-area .ictu-users-filter-field:first-child { grid-column: auto; } }
</style>
<section class="page-heading"><div><p class="eyebrow">Account verification</p><h1>Borrower registrations</h1><p>Review applicant details and email verification before recording an SAO decision.</p></div></section>
<section id="registration-records-area" class="content-area ictu-users-area registration-records" aria-label="Borrower registration records">
    <form class="ictu-users-filter-card" method="get" action="{{ route('registrations.index') }}" data-registration-filters>
        <div class="ictu-users-filter-grid">
            <div class="ictu-users-filter-field"><label for="registration-search">Search applicants</label><div class="ictu-users-search-control"><x-icon name="search" size="18" /><input type="search" id="registration-search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="255" placeholder="Name, email, employee no. or company" autocomplete="off"></div></div>
            <div class="ictu-users-filter-field"><label for="registration-filter-type">Account type</label><select id="registration-filter-type" name="type"><option value="">All account types</option>@foreach(App\Enums\RegistrationType::cases() as $type)<option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
            <div class="ictu-users-filter-field"><label for="registration-filter-status">Registration status</label><select id="registration-filter-status" name="status"><option value="">All statuses</option>@foreach(App\Enums\RegistrationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst(strtolower($status->value)) }}</option>@endforeach</select></div>
        </div>
    </form>
    <div class="ictu-users-list-card" id="registration-results" aria-live="polite" aria-busy="false">
        <div class="ictu-users-list-toolbar">
            <div class="ictu-users-list-title"><x-icon name="users" size="22" /><h2>Registration records</h2><span class="ictu-users-total-pill">{{ $registrations->total() }}</span></div>
            <span class="ictu-users-pagination-meta">
                @if($registrations->count())
                    {{ $registrations->firstItem() }}–{{ $registrations->lastItem() }} of {{ $registrations->total() }}
                @else
                    No records
                @endif
            </span>
        </div>
        <div class="ictu-users-table-scroll" role="region" aria-label="Registration records table" tabindex="0">
            <table class="ictu-users-table"><thead><tr><th scope="col">Applicant</th><th scope="col">Account type</th><th scope="col">Affiliation</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col">Review</th></tr></thead><tbody>
            @forelse($registrations as $registration)
                @php($submittedAt = $registration->submitted_for_review_at ?? $registration->created_at)
                <tr>
                    <td class="registration-applicant"><strong>{{ $registration->user->full_name }}</strong><span>{{ $registration->user->email }}</span></td>
                    <td>{{ $registration->registration_type->label() }}</td>
                    <td class="registration-affiliation">@if($registration->registration_type === App\Enums\RegistrationType::External){{ $registration->company_name }}@else{{ $registration->user->organizationalUnit?->unit_name ?: 'No assignment' }}<span>{{ $registration->user->employee_no }}</span>@endif</td>
                    <td>{{ $submittedAt->format('M j, Y') }}<div>{{ $submittedAt->format('g:i A') }}</div></td>
                    <td><div class="ictu-user-status-stack"><x-status-badge :status="$registration->status->value" />@if(!$registration->user->hasVerifiedInstitutionalEmail())<x-status-badge status="EMAIL_PENDING" label="OTP verification pending" />@else<x-status-badge status="EMAIL_VERIFIED" label="Email verified" />@endif</div></td>
                    <td class="registration-review-cell"><a class="ictu-user-icon-button" href="{{ route('registrations.show', $registration) }}" title="Review {{ $registration->user->full_name }}" aria-label="Review {{ $registration->user->full_name }}"><x-icon name="file-search" size="18" /></a></td>
                </tr>
            @empty
                <tr class="registration-empty-row"><td colspan="6">No borrower registrations match these filters.</td></tr>
            @endforelse
            </tbody></table>
        </div>
        @if($registrations->hasPages())
            <div class="registration-pagination">{{ $registrations->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
</section>
@include('registrations.partials.filter-interactions')
@endsection
