<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.accountability.partials.official-styles')
</head>
<body>
<section class="official-document">
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
        <h1>Property Compliance Notice</h1>
        <div class="document-meta">
            <strong>Case No.</strong> {{ $incident->incident_no }}
            <span class="meta-separator">•</span>
            <strong>Date Issued</strong> {{ $decisionDate }}
        </div>
    </div>

    <div class="section-title">Borrower and Case Information</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Borrower</span><span class="field-value">{{ $incident->borrower?->full_name ?: '—' }}</span></td>
            <td><span class="field-label">Office / College / Unit</span><span class="field-value">{{ $officeUnit ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Request No.</span><span class="field-value">{{ $requestNo ?: '—' }}</span></td>
            <td><span class="field-label">Custody No.</span><span class="field-value">{{ $custodyNo ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Finding / Case Type</span><span class="field-value">{{ str($incident->incident_type)->replace('_', ' ')->title() }}</span></td>
            <td><span class="field-label">Case Status</span><span class="field-value">Compliance Required</span></td>
        </tr>
    </table>

    <div class="section-title">Affected Property</div>
    <table class="document-table">
        <thead><tr>
            <th style="width:42%">Item</th>
            <th style="width:12%" class="center">Qty</th>
            <th style="width:20%">Finding</th>
            <th style="width:26%">Recorded Disposition</th>
        </tr></thead>
        <tbody>
        @foreach($incident->lines as $line)
            <tr>
                <td>{{ $line->custodyLine?->requestItem?->description_snapshot ?: 'Inventory item' }}</td>
                <td class="center">{{ $line->quantity + 0 }}</td>
                <td>{{ str($line->observed_condition)->replace('_', ' ')->title() }}</td>
                <td>{{ str($line->disposition_state)->replace('_', ' ')->title() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="section-title">Required Compliance</div>
    <p class="decision-copy">{{ $decisionRemarks }}</p>

    <table class="signature-table" role="presentation">
        <tr>
            <td colspan="2" style="width: 100%; padding-left: 25%; padding-right: 25%;">
                <div class="signature-label">Confirmed By — SPMU Head</div>
                <div class="signature-space">{!! $headSignatureHtml ?: "" !!}</div>
                <div class="signature-name">{{ $headName }}</div>
                <div class="signature-role">{{ $headDesignation }}</div>
                <div class="signature-date">{{ $decisionDate }}</div>
            </td>
        </tr>
    </table>

    <div class="document-footer">
        <table role="presentation"><tr>
            <td>SPMU accountability document.</td>
            <td>{{ $incident->incident_no }}</td>
        </tr></table>
    </div>
</section>
</body>
</html>
