{{--
    Laundry Area receiving days (linen only).
    A date authorized here lets returned linen be received by Laundry
    Personnel on that date. It never opens the SPMU Office and never affects
    equipment. Decisions are never deleted: a revoked date stays listed.
--}}
<section class="content-area special-dates-config" id="laundry-receiving-days">
    <article class="card special-dates-card">
        <header class="special-dates-header">
            <span class="special-dates-header-icon" aria-hidden="true"><x-icon name="linen" size="26" /></span>

            <div>
                <p class="eyebrow">Linen returns only</p>
                <h2>Laundry Area Receiving Days</h2>
                <p class="meta">Authorize a specific date on which Laundry Personnel may receive returned linen even though the SPMU Office is closed. This does not open SPMU for Pickup / Release or equipment Returns, and it approves no penalty. An authorized date counts as a receiving day when linen late days are computed.</p>
            </div>
        </header>

        <form method="post" action="{{ route('policies.laundry-receiving-days.store') }}" class="special-date-form">
            @csrf

            <div class="special-date-fields">
                <label for="laundry-receiving-date">Date
                    <input id="laundry-receiving-date" type="date" name="receiving_date" value="{{ old('receiving_date') }}" required>
                </label>
            </div>

            <label class="special-date-reason" for="laundry-receiving-reason">Reason / justification
                <input id="laundry-receiving-reason" type="text" name="reason" value="{{ old('reason') }}" minlength="5" maxlength="500" required placeholder="e.g., Laundry Area on duty for the Foundation Week linen turnover">
            </label>

            <button class="button secondary ui-pressable special-date-submit" type="submit">
                <x-icon name="plus-circle" size="17" />
                Authorize Laundry Receiving
            </button>
        </form>

        @if($pendingLaundryReceipts->isNotEmpty())
            <section class="special-dates-records" aria-labelledby="laundry-pending-receipts-title" data-laundry-pending-receipts>
                <div class="special-dates-records-header">
                    <h3 id="laundry-pending-receipts-title">
                        Signed Receipts Pending Date Authorization
                        <span class="special-dates-count">{{ $pendingLaundryReceipts->count() }}</span>
                    </h3>
                </div>
                <p class="meta">Each signed Laundry Form below shows a Laundry Received date that is neither an SPMU open return day nor an authorized Laundry receiving day. Open the form, then decide. These are two different decisions: authorizing the date is a calendar decision (the date then counts as a receiving day); accepting the signed date only verifies this one receipt (the date counts as a late day for this transaction alone and stays unauthorized for all others). Both give this receipt the same late days. Neither approves a penalty.</p>

                <div class="table-wrap special-dates-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Custody</th>
                                <th scope="col">Borrower</th>
                                <th scope="col">Laundry Received (signed form)</th>
                                <th scope="col">Expected Return</th>
                                <th scope="col">Signed form</th>
                                <th scope="col">Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingLaundryReceipts as $pendingJob)
                                <tr data-laundry-pending-row>
                                    <td><strong>{{ $pendingJob->custody?->custody_no }}</strong></td>
                                    <td>{{ $pendingJob->custody?->borrower?->full_name }}</td>
                                    <td>
                                        <strong>{{ $pendingJob->worker_received_at->format('d M Y') }}</strong>
                                        <small>{{ $pendingJob->worker_received_at->format('l') }}</small>
                                    </td>
                                    <td>{{ optional($pendingJob->custody?->due_at)->format('d M Y') ?: '—' }}</td>
                                    <td>
                                        @if($pendingJob->latestEvidence?->file)
                                            <a class="button secondary small ui-pressable" href="{{ route('files.preview', $pendingJob->latestEvidence->file) }}">Preview</a>
                                        @else
                                            Not on file
                                        @endif
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('policies.laundry-receiving-days.store') }}">
                                            @csrf
                                            <input type="hidden" name="receiving_date" value="{{ $pendingJob->worker_received_at->toDateString() }}">
                                            <input type="text" name="reason" minlength="5" maxlength="500" required placeholder="Reason for authorizing this date" aria-label="Reason for authorizing {{ $pendingJob->worker_received_at->format('d M Y') }}">
                                            <button class="button secondary small ui-pressable" type="submit">Authorize this date</button>
                                        </form>
                                        <form method="post" action="{{ route('laundry.spmu.accept-receipt-date', $pendingJob) }}">
                                            @csrf
                                            <input type="text" name="review_note" minlength="5" maxlength="500" required placeholder="How the signed date was verified" aria-label="Verification note for {{ $pendingJob->custody?->custody_no }}">
                                            <button class="button secondary small ui-pressable" type="submit">Accept signed date only</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="special-dates-records" aria-labelledby="laundry-receiving-days-title">
            <div class="special-dates-records-header">
                <h3 id="laundry-receiving-days-title">
                    Authorization Record
                    <span class="special-dates-count">{{ $laundryReceivingDays->count() }}</span>
                </h3>
            </div>

            <div class="table-wrap special-dates-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Status</th>
                            <th scope="col">Authorized by</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($laundryReceivingDays as $day)
                            <tr data-laundry-receiving-row>
                                <td>
                                    <strong>{{ $day->receiving_date->format('d M Y') }}</strong>
                                    <small>{{ $day->receiving_date->format('l') }}</small>
                                </td>
                                <td>
                                    @if($day->is_authorized)
                                        <x-status-badge status="OPEN" label="Authorized" />
                                    @else
                                        <x-status-badge status="CLOSED" label="Revoked" />
                                    @endif
                                </td>
                                <td>
                                    {{ $day->authorizedBy?->full_name ?? '—' }}
                                    <small>{{ $day->authorized_at?->format('d M Y, g:i A') }}</small>
                                </td>
                                <td>
                                    {{ $day->reason }}
                                    @unless($day->is_authorized)
                                        <small>Revoked {{ $day->revoked_at?->format('d M Y, g:i A') }} by {{ $day->revokedBy?->full_name ?? '—' }}: {{ $day->revocation_reason }}</small>
                                    @endunless
                                </td>
                                <td>
                                    @if($day->is_authorized)
                                        <form method="post" action="{{ route('policies.laundry-receiving-days.revoke', $day) }}" onsubmit="return confirm('Revoke Laundry receiving for this date? The decision stays on record, and returns already encoded are not changed.')">
                                            @csrf
                                            <input type="text" name="revocation_reason" minlength="5" maxlength="500" required placeholder="Reason for revoking" aria-label="Reason for revoking {{ $day->receiving_date->format('d M Y') }}">
                                            <button class="button secondary small ui-pressable" type="submit">Revoke</button>
                                        </form>
                                    @else
                                        <small>Authorize the date again above to restore it.</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state special-dates-empty">No Laundry receiving date has been authorized.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </article>
</section>
