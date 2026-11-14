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
        <h1>{{ $hasBorrowingSuspension ? 'Suspension Notice' : 'Administrative Sanction Notice' }}</h1>
        <div class="document-meta">
            <strong>Date Confirmed</strong> {{ $confirmedDate }}
        </div>
    </div>

    <div class="section-title">Borrower and Transaction Information</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Borrower</span><span class="field-value">{{ $sanction->borrower?->full_name ?: '—' }}</span></td>
            <td><span class="field-label">Office / College / Unit</span><span class="field-value">{{ $officeUnit ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Request No.</span><span class="field-value">{{ $requestNo ?: '—' }}</span></td>
            <td><span class="field-label">Custody No.</span><span class="field-value">{{ $custodyNo ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Academic Period</span><span class="field-value">{{ $academicPeriod }}</span></td>
        </tr>
    </table>

    <div class="section-title">Confirmed Offense Basis</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Applicable Finding(s)</span><span class="field-value">{{ $reasonText }}</span></td>
            <td><span class="field-label">Decision Basis</span><span class="field-value">{{ $sanction->remarks ?: '—' }}</span></td>
        </tr>
    </table>

    <div class="section-title">Administrative Sanction</div>
    <table class="info-grid" role="presentation">
        <tr>
            <td><span class="field-label">Offense Level</span><span class="field-value">{{ $offenseLabel }}</span></td>
            <td><span class="field-label">Sanction</span><span class="field-value emphasis">{{ $sanction->sanction_label }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Effective From</span><span class="field-value">{{ $effectiveFrom }}</span></td>
            <td><span class="field-label">Effective Until</span><span class="field-value">{{ $effectiveTo ?: 'Not applicable' }}</span></td>
        </tr>
    </table>

    @unless($hasBorrowingSuspension)
        <p class="decision-copy">No additional borrowing suspension is imposed by this sanction.</p>
    @endunless
    <p class="decision-copy">Separate unresolved accountability restrictions remain in effect.</p>

    <table class="signature-table" role="presentation">
        <tr>
            <td colspan="2" style="width: 100%; padding-left: 25%; padding-right: 25%;">
                <div class="signature-label">Confirmed By — SPMU Head</div>
                <div class="signature-space">{!! $headSignatureHtml ?: "" !!}</div>
                <div class="signature-name">{{ $headName }}</div>
                <div class="signature-role">{{ $headDesignation }}</div>
                <div class="signature-date">{{ $confirmedDate }}</div>
            </td>
        </tr>
    </table>

    <div class="document-footer">
        <table role="presentation"><tr>
            <td>SPMU administrative accountability record.</td>
            <td>{{ $offenseLabel }}</td>
        </tr></table>
    </div>
</section>
</body>
</html>
