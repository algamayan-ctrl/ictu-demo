<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Card — {{ $item->unique_description }}</title>
    <style>
        @page { size: A4 landscape; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172b44; background: #edf1f6; font: 14px Arial, sans-serif; }
        .print-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; max-width: 1120px; margin: 20px auto; padding: 0 16px; }
        .print-toolbar p { margin: 0; font-size: 13px; }
        .print-toolbar button, .print-toolbar a { display: inline-block; padding: 10px 16px; border: 1px solid #b9c9dc; border-radius: 6px; background: white; color: #175c9d; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        .print-toolbar button { background: #1569b0; color: white; border-color: #1569b0; }
        .stock-sheet { max-width: 1120px; margin: 0 auto 28px; padding: 30px; background: white; }
        h1 { margin: 0 0 20px; text-align: center; font-size: 22px; }
        .sao-stock-card-identity { width: 100%; border-collapse: collapse; table-layout: fixed; color: #111; font-size: 11px; }
        .sao-stock-card-identity td { padding: 6px; border: 1px solid #555; vertical-align: top; line-height: 1.4; overflow-wrap: anywhere; }
        .sao-stock-card-identity strong { margin-right: 5px; }
        .sao-stock-card-identity tbody tr:last-child td { border-bottom: 0; }
        .sao-stock-card-table { width: 100%; border-collapse: collapse; table-layout: fixed; color: #111; font-size: 11px; }
        .sao-stock-card-table th, .sao-stock-card-table td { padding: 7px 6px; border: 1px solid #555; vertical-align: top; overflow-wrap: anywhere; }
        .sao-stock-card-table th { text-align: center; background: white; }
        .sao-stock-card-table td:first-child, .sao-stock-card-table td:nth-child(5) { text-align: center; }
        .stock-number { text-align: center; font-variant-numeric: tabular-nums; }
        .stock-balance { font-weight: bold; }
        .stock-note { margin: 13px 0 0; font-size: 10px; line-height: 1.5; }
        .stock-warning { padding: 12px; border: 1px solid #ad6500; color: #754600; font-size: 12px; }
        @media print {
            body { background: white; color: black; }
            .print-toolbar { display: none; }
            .stock-sheet { max-width: none; margin: 0; padding: 0; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <p>Print the stock entries shown in this copy.</p>
        <div><a href="{{ route('inventory.show', ['inventory' => $item->id, 'tab' => 'stock-card']) }}">Back to Inventory</a> <button type="button" id="print-stock-card">Print</button></div>
    </div>
    <main class="stock-sheet">
        <h1>STOCK CARD</h1>
        @if($card['warning'])<p class="stock-warning">{{ $card['warning'] }}</p>@endif
        @if($card['filtered'])<p class="stock-note"><strong>Selected entries:</strong> {{ $card['search'] ? 'Search: '.$card['search'].' · ' : '' }}{{ match($card['movement']) { 'RECEIPT' => 'Received Items', 'ISSUE' => 'Issued Items', default => 'All Entries' } }}. Balances retain the full movement sequence.</p>@endif
        @include('inventory.partials.stock-card-identity')
        @include('inventory.partials.stock-card-table', ['rows' => $card['rows']])
        <p class="stock-note">{{ $card['balance_note'] }} Balances start from the existing inventory figures. Fields without recorded values are left blank; consumption days are not calculated for borrowing transactions. This copy includes all {{ $card['rows']->count() }} matching entries.</p>
        <p class="stock-note">Inventory reference: {{ 'INV-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT) }} · Generated {{ now()->format('m-d-Y, g:i A') }}</p>
    </main>
    <script>document.getElementById('print-stock-card').addEventListener('click', () => window.print());</script>
</body>
</html>
