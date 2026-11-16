<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.accountability.partials.official-styles')
</head>
@php
    $isLateReturnBilling = $billing->isLateReturnBilling();
    $statementTitle = $isLateReturnBilling
        ? 'Late Return Billing Statement'
        : (($documentTitle ?? '') === 'Billing Statement / Assessment Notice' ? 'Billing Statement' : ($documentTitle ?? 'Billing Statement'));
@endphp
<body>
<style>
    .billing-statement-document .cashier-copy {
        margin-top: 12px;
        padding: 8px 10px;
        border: 1px solid #222;
    }

    .billing-statement-document .cashier-copy strong {
        display: block;
        margin-bottom: 3px;
        font-size: 8.5px;
        text-transform: uppercase;
    }

    .billing-statement-document .cashier-copy p {
        margin: 0;
        color: #111;
    }

    .billing-statement-document .cashier-copy .receipt-note {
        margin-top: 4px;
        color: #333;
        font-size: 8px;
    }

</style>
<section class="official-document billing-statement-document">
    <table class="accountability-form-header" role="presentation">
        <tr>
            <td class="accountability-form-logo-cell"><img class="accountability-form-logo" src="{{ $logoDataUri }}" alt="CSPC logo"></td>
            <td class="accountability-form-identity">
                <span>Republic of the Philippines</span>
                <strong>CAMARINES SUR POLYTECHNIC COLLEGES</strong>
                <span>Nabua, Camarines Sur</span>
            </td>
        </tr>
    </table>
    <div class="accountability-form-rule"><span>CSPC-F-SPMU-26</span></div>

    <div class="document-title">
        <h1>{{ $statementTitle }}</h1>
        <div class="document-meta">
            <strong>Billing No.</strong> {{ $billing->billing_no }}
            <span class="meta-separator">•</span>
            <strong>Date Issued</strong> {{ $issuedDate }}
            @if($dueDate && ! $isLateReturnBilling)
                <span class="meta-separator">•</span>
                <strong>Due Date</strong> {{ $dueDate }}
            @endif
        </div>
    </div>

    @if($isLateReturnBilling)
        <div class="section-title">Payment Details</div>
        <table class="info-grid" role="presentation">
            <tr>
                <td><span class="field-label">Borrower</span><span class="field-value">{{ $billing->borrower?->full_name ?: '—' }}</span></td>
                <td><span class="field-label">Custody No.</span><span class="field-value">{{ $custodyNo ?: '—' }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="field-label">Office / College / Unit</span><span class="field-value">{{ $officeUnit ?: '—' }}</span></td>
            </tr>
        </table>

        <div class="section-title">Charge</div>
        <table class="document-table">
            <thead>
            <tr>
                <th>Description</th>
                <th style="width:22%" class="numeric">Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse($billing->lines as $line)
                <tr>
                    <td>{{ $line->line_type === 'LATE_RETURN_FEE' ? 'Late Return Fee' : $line->description }}</td>
                    <td class="numeric">PHP {{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No charge detail is available.</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="total-box">
            <table role="presentation">
                <tr><td class="label">Amount Due</td><td class="amount">PHP {{ number_format((float) $billing->total_amount, 2) }}</td></tr>
            </table>
        </div>

        <div class="cashier-copy">
            <strong>For payment at the CSPC Cashier</strong>
            <p>Accept the total amount shown above and issue an Official Receipt to the payer. The borrower must present the receipt to SPMU after payment.</p>
            <p class="receipt-note">This Billing Statement is not an Official Receipt.</p>
        </div>
    @else
    <div class="section-title">Borrower and Transaction Information</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Borrower</span><span class="field-value">{{ $billing->borrower?->full_name ?: '—' }}</span></td>
            <td><span class="field-label">Office / College / Unit</span><span class="field-value">{{ $officeUnit ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Request No.</span><span class="field-value">{{ $requestNo ?: '—' }}</span></td>
            <td><span class="field-label">Custody No.</span><span class="field-value">{{ $custodyNo ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Accountability / Incident Ref.</span><span class="field-value">{{ $incidentNo ?: '—' }}</span></td>
            <td><span class="field-label">Billing Status</span><span class="field-value">{{ str($billing->status)->replace('_', ' ')->title() }}</span></td>
        </tr>
    </table>

    <div class="section-title">Assessment Details</div>
    <table class="document-table">
        <thead>
        <tr>
            <th style="width:20%">Type</th>
            <th style="width:32%">Description</th>
            <th style="width:31%">Assessment Basis</th>
            <th style="width:17%" class="numeric">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach($billing->lines as $line)
            <tr>
                <td>{{ str($line->line_type)->replace('_', ' ')->title() }}</td>
                <td>{{ $line->description }}</td>
                <td>{{ $line->basis ?: 'Approved accountability assessment' }}</td>
                <td class="numeric">PHP {{ number_format((float) $line->amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <table role="presentation">
            <tr><td class="label">Total Amount Due</td><td class="amount">PHP {{ number_format((float) $billing->total_amount, 2) }}</td></tr>
        </table>
    </div>

    <div class="section-title">Payment Instruction</div>
    <p class="decision-copy">
        Pay the stated amount at the CSPC Cashier and present the Official Receipt to SPMU for recording.
        <strong>This statement is not an Official Receipt.</strong>
    </p>

    @if($billing->remarks)
        <div class="section-title">Remarks</div>
        <p class="decision-copy">{{ $billing->remarks }}</p>
    @endif
    @endif

    <table class="signature-table" role="presentation">
        <tr>
            <td colspan="2" style="width: 100%; padding-left: 25%; padding-right: 25%;">
                <div class="signature-label">{{ $isLateReturnBilling ? 'Authorized By — SPMU Head' : 'Confirmed By — SPMU Head' }}</div>
                <div class="signature-space">{!! $headSignatureHtml ?: "" !!}</div>
                <div class="signature-name">{{ $headName }}</div>
                <div class="signature-role">{{ $headDesignation }}</div>
                <div class="signature-date">{{ $headDecisionDate ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="document-footer form-record-footer">
        <table role="presentation"><tr>
            <td>{{ $isLateReturnBilling ? 'SPMU late-return accountability record.' : 'SPMU accountability record.' }}</td>
            <td>{{ $billing->billing_no }}@if($custodyNo ?? null) · {{ $custodyNo }}@endif</td>
        </tr></table>
    </div>
</section>
</body>
</html>
