{{-- This is the manual-signature area for the SPMU Action Officer and SPMU Admin. --}}
<style>
    .report-signatories { margin: 18pt 0 0; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; break-inside: avoid; page-break-inside: avoid; }
    .report-signatories-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
    .report-signatories-table td { width: 46%; padding: 0; vertical-align: top; border: 0; overflow-wrap: anywhere; word-wrap: break-word; }
    .report-signatories-table td.report-signatories-gap { width: 8%; }
    .report-signatories-label { margin: 0; font-size: 9pt; }
    .report-signatories-line { height: 30pt; border-bottom: .5pt solid #111; }
    .report-signatories-name { margin: 3pt 0 0; font-weight: bold; text-decoration: underline; line-height: 1.3; }
    .report-signatories-designation { margin: 2pt 0 0; font-size: 9pt; line-height: 1.3; }
</style>
<div class="report-signatories" aria-label="Report signatories">
    <table class="report-signatories-table" role="presentation">
        <tbody>
            <tr>
                @foreach(\App\Reports\ReportSignatories::fromMeta($dataset->meta) as $signatory)
                    @if(! $loop->first)<td class="report-signatories-gap" aria-hidden="true"></td>@endif
                    <td>
                        <p class="report-signatories-label">{{ $signatory['label'] }}</p>
                        <div class="report-signatories-line" aria-hidden="true"></div>
                        <p class="report-signatories-name">{{ $signatory['name'] }}</p>
                        <p class="report-signatories-designation">{{ $signatory['designation'] }}</p>
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
