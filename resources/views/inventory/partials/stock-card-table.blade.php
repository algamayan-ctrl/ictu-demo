@php
    $stockQty = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $stockColumnWidths = [12, 22, 9, 8, 23, 13, 13];
@endphp
<table class="sao-stock-card-table">
    <colgroup>@foreach($stockColumnWidths as $width)<col style="width:{{ $width }}%">@endforeach</colgroup>
    <thead>
        <tr>
            <th rowspan="2" scope="col">Date</th>
            <th rowspan="2" scope="col">Reference</th>
            <th scope="col"><em>Receipt</em></th>
            <th colspan="2" scope="colgroup"><em>Issue</em></th>
            <th scope="col"><em>Balance</em></th>
            <th rowspan="2" scope="col">No. of Days to consume</th>
        </tr>
        <tr><th scope="col">Qty.</th><th scope="col">Qty.</th><th scope="col">Office</th><th scope="col">Qty.</th></tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr data-stock-card-row>
                <td>{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('m-d-Y') : '' }}</td>
                <td title="{{ $row['remarks'] }}">{{ $row['reference'] }}</td>
                <td class="stock-number">{{ $stockQty($row['receipt']) }}</td>
                <td class="stock-number">{{ $stockQty($row['issue']) }}</td>
                <td>{{ $row['issue'] !== null ? $row['office'] : '' }}</td>
                <td class="stock-number stock-balance">{{ $stockQty($row['balance']) }}</td>
                <td class="stock-consumption"></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">{{ ($card['filtered'] ?? false) ? 'No stock entries match these filters.' : 'No stock entries have been recorded for this item.' }}</td></tr>
        @endforelse
    </tbody>
</table>
