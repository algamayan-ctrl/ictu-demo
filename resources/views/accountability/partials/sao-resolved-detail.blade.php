{{--
    SAO-only expanded resolved record. Summary columns already contain the
    reference, case type, outcome, assessed amount and resolution date.
    This read-only view shows supporting evidence without repeating them.
    $case, $incident, $billing, $payment, $fromLaundry, $lateReturnNotice,
    and $row come from resolved-history.blade.php.
--}}
@php
    $saoIncidentDisposition = $incident
        ? match (true) {
            $incident->findingWasCleared() => 'Finding Cleared',
            $incident->accountabilityWasConfirmed() => 'Accountability Confirmed',
            default => null,
        }
        : null;
    $saoIncidentDecisionReason = $saoIncidentDisposition && $incident
        ? $incident->headDecisionReason()
        : null;
    $saoRelatedSanction = $row['related_sanction'] ?? null;
    $saoBillingDocuments = ($billing?->documents ?? collect())
        ->whereNotIn('status', ['SUPERSEDED', 'INVALIDATED', 'EXPIRED']);
    $saoHasDocuments = $lateReturnNotice
        || $saoBillingDocuments->isNotEmpty()
        || $payment?->evidence_file_id
        || $incident?->rslddp_attachment_file_id;
@endphp
<div class="sao-resolved-detail">
    @if($incident)
        <section class="sao-resolved-detail__section" aria-label="Property accountability finding">
            <h3>Property Finding</h3>
            <dl class="sao-resolved-detail__facts">
                <div>
                    <dt>Incident No.</dt>
                    <dd>{{ $incident->incident_no ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Finding</dt>
                    <dd>{{ str($incident->incident_type)->replace('_', ' ')->title() }}</dd>
                </div>
                <div>
                    <dt>Custody No.</dt>
                    <dd>{{ $incident->custody?->custody_no ?: '—' }}</dd>
                </div>
                @if($incident->reported_at)
                    <div>
                        <dt>Reported On</dt>
                        <dd>{{ $incident->reported_at->format('d M Y') }}</dd>
                    </div>
                @endif
                @if($saoIncidentDisposition)
                    <div>
                        <dt>SPMU Head Decision</dt>
                        <dd>
                            {{ $saoIncidentDisposition }}
                            @if($incident->head_decided_at)
                                <small>{{ $incident->head_decided_at->format('d M Y') }}</small>
                            @endif
                        </dd>
                    </div>
                    @if($saoIncidentDecisionReason)
                        <div>
                            <dt>Recorded Reason</dt>
                            <dd>{{ $saoIncidentDecisionReason }}</dd>
                        </div>
                    @endif
                @else
                    <div>
                        <dt>Incident Final Status</dt>
                        <dd><x-status-badge :status="$incident->status" /></dd>
                    </div>
                @endif
                @if($saoRelatedSanction)
                    <div>
                        <dt>Administrative Sanction</dt>
                        <dd>
                            Offense {{ $saoRelatedSanction->offense_no }} · {{ $saoRelatedSanction->sanction_label }}
                            @if($saoRelatedSanction->confirmed_at)
                                <small>Recorded {{ $saoRelatedSanction->confirmed_at->format('d M Y') }}</small>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    @if($case)
        <section class="sao-resolved-detail__section" aria-label="Return assessment">
            <h3>Return Assessment</h3>
            <dl class="sao-resolved-detail__facts">
                <div>
                    <dt>Expected Return</dt>
                    <dd>{{ optional($case->grace_expires_at)->format('d M Y') ?: '—' }}</dd>
                </div>
                @if($case->actual_return_date)
                    <div>
                        <dt>{{ $fromLaundry ? 'Laundry Received' : 'Actual Return' }}</dt>
                        <dd>{{ optional($case->actual_return_date)->format('d M Y') }}</dd>
                    </div>
                @endif
                @if($case->late_days !== null)
                    <div>
                        <dt>Late Days</dt>
                        <dd>{{ $case->late_days }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    @if($case?->ao_confirmed_at || $billing || $payment)
        <section class="sao-resolved-detail__section" aria-label="Approval and payment details">
            <h3>Approval and Payment</h3>
            <dl class="sao-resolved-detail__facts sao-resolved-detail__facts--approval-payment">
                @if($case?->ao_confirmed_at)
                    <div>
                        <dt>AO Confirmation</dt>
                        <dd>
                            {{ $case->confirmedBy?->full_name ?? 'SPMU Action Officer' }}
                            <small>{{ $case->ao_confirmed_at->format('d M Y, h:i A') }}</small>
                        </dd>
                    </div>
                @endif
                @if($billing)
                    <div>
                        <dt>Approved By / Billing Issued By</dt>
                        <dd>
                            {{ $billing->responsibleSpmuUser?->full_name ?? 'SPMU Head/Admin' }}
                            @if($billing->issued_at)
                                <small>{{ $billing->issued_at->format('d M Y, h:i A') }}</small>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Billing Reference</dt>
                        <dd>{{ $billing->billing_no ?: '—' }}</dd>
                    </div>
                @endif
                @if($payment)
                    <div>
                        <dt>Official Receipt No.</dt>
                        <dd>{{ $payment->official_receipt_no ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Receipt Date</dt>
                        <dd>{{ optional($payment->receipt_date)->format('d M Y') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Amount Paid</dt>
                        <dd>PHP {{ number_format((float) $payment->amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt>Verified By</dt>
                        <dd>
                            {{ $payment->verifiedBy?->full_name ?? 'SPMU Action Officer' }}
                            @if($payment->verified_at)
                                <small>{{ $payment->verified_at->format('d M Y, h:i A') }}</small>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    @if($saoHasDocuments)
        <section class="sao-resolved-detail__section" aria-label="Supporting documents">
            <h3>Supporting Documents</h3>
            <div class="sao-resolved-detail__documents">
                @if($lateReturnNotice)
                    <a href="{{ route('documents.preview', $lateReturnNotice) }}">
                        <x-icon name="external-link" size="14" />
                        <span>Late Return Notice</span>
                    </a>
                @endif
                @foreach($saoBillingDocuments as $document)
                    <a href="{{ route('documents.preview', $document) }}"
                       @if($document->document_no) title="{{ $document->document_no }}" @endif>
                        <x-icon name="external-link" size="14" />
                        <span>{{ str($document->document_type)->replace('_', ' ')->title() }}</span>
                    </a>
                @endforeach
                @if($payment?->evidence_file_id)
                    <a href="{{ route('files.preview', $payment->evidence_file_id, false) }}">
                        <x-icon name="external-link" size="14" />
                        <span>Payment Evidence</span>
                    </a>
                @endif
                @if($incident?->rslddp_attachment_file_id)
                    <a href="{{ route('files.preview', $incident->rslddp_attachment_file_id) }}">
                        <x-icon name="external-link" size="14" />
                        <span>Accomplished RSLDDP</span>
                    </a>
                @endif
            </div>
        </section>
    @endif
</div>
