@php
    $print = $print ?? true;
    $page = $report['configuration']['page'] ?? [];
    $size = in_array($page['size'] ?? 'A4', ['A4', 'LETTER', 'LEGAL'], true) ? $page['size'] : 'A4';
    $orientation = in_array($page['orientation'] ?? 'LANDSCAPE', ['PORTRAIT', 'LANDSCAPE'], true) ? strtolower($page['orientation']) : 'landscape';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} – Official SPMU Form</title>
    <style>
        :root{color-scheme:light}*{box-sizing:border-box}
        body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f3f6fb;color:#192c47;font-size:13px}
        .toolbar{max-width:1280px;margin:20px auto;display:flex;align-items:end;gap:12px;flex-wrap:wrap;padding:0 14px}
        .toolbar a,.toolbar button{border:1px solid #b9c8da;background:#fff;border-radius:7px;padding:9px 13px;color:#164785;text-decoration:none;cursor:pointer;font-weight:600;font-size:12px}
        .toolbar a.primary{background:#164785;color:#fff;border-color:#164785}
        .toolbar label{display:grid;gap:4px;color:#405571;font-weight:600;font-size:12px}
        .toolbar input{padding:8px;border:1px solid #b9c8da;border-radius:7px;font:inherit;background:white}
        .toolbar h1{font-size:18px;margin:0 0 4px}.toolbar .intro{margin-right:auto}
        .note{max-width:1252px;margin:0 auto 12px;padding:11px 14px;border:1px solid #d2dceb;background:#fff;border-radius:8px;font-size:12px}
        .sheet{background:white;color:#111;margin:10px auto 35px;padding:20px 24px;max-width:1252px;box-shadow:0 6px 25px #192c4720}
        .institutional-head{display:table;width:100%;border-bottom:2px solid #174b8d;padding:2px 0 12px}
        .institutional-head>div{display:table-cell;vertical-align:middle}.institutional-head .seal{width:66px}
        .institutional-head img{width:58px;height:58px;object-fit:contain}
        .institutional-head .institution{font-size:12px;line-height:1.55;text-align:center}
        .institutional-head .institution strong{font-size:15px;letter-spacing:.3px}
        .institutional-head .code{text-align:right;width:148px;font-weight:700;font-size:11px}
        .form-title{text-align:center;font-size:15px;margin:18px 0 15px;line-height:1.4}
        .form-table{border-collapse:collapse;width:100%;font-size:10px;table-layout:fixed}
        .form-table th,.form-table td{border:1px solid #444;padding:7px 5px;vertical-align:top;overflow-wrap:anywhere}
        .form-table th{background:#edf2f9;text-align:center;font-size:10px;overflow-wrap:normal;word-break:normal}
        .form-table td{text-align:left}
        .formula{margin:18px 0 0;font-size:11px}.formula .muted{color:#4b5563}
        .closing{page-break-inside:avoid;break-inside:avoid}
        .signatures{display:table;width:100%;margin:28px 0 26px;table-layout:fixed}
        .signatures>div{display:table-cell;width:50%;padding-right:8%;vertical-align:top}
        .signatures .sign-label{font-size:11px;font-weight:600}
        .signatures .ink-line{border-bottom:1px solid #222;height:56px;width:86%}
        .signatures .sign-name{font-size:11px;font-weight:700;padding-top:5px}
        .signatures .designation,.signatures .sign-date{font-size:10px;line-height:1.7}
        .footer{border-top:1px solid #9eaec0;padding-top:7px;font-size:10px;display:table;width:100%}
        .footer>span{display:table-cell;width:33.3%}.footer>span:nth-child(2){text-align:center}.footer>span:nth-child(3){text-align:right}
        @page{size:{{ strtolower($size) }} {{ $orientation }};margin:13mm}
        @media print{body{background:white}.toolbar,.note{display:none!important}.sheet{margin:0;padding:0;max-width:none;box-shadow:none}.form-table thead{display:table-header-group}.closing,.signatures{page-break-inside:avoid}}
    </style>
</head>
<body>
{{-- The generated official document (Print and PDF). Records are reviewed beforehand in the system preview. --}}
@include('reports.official.equipment-sheet')
@if($print)<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
