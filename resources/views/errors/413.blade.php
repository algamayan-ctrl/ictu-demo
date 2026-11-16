<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload too large | {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; padding: 24px; background: #f4f7fb; color: #18324a; font: 16px/1.6 system-ui, sans-serif; }
        main { max-width: 36rem; margin: 12vh auto; }
        h1 { margin: 0 0 12px; font-size: 26px; line-height: 1.3; }
        p { margin: 12px 0; }
        a { color: #1262b8; }
        @media (prefers-color-scheme: dark) { body { background: #15202e; color: #e4edf7; } a { color: #8fc5ff; } }
    </style>
</head>
<body>
    <main>
        <h1>Upload too large</h1>
        <p>{{ $message }}</p>
        <p>Use your browser's Back button to review the form and choose files within the displayed limit. You may need to select the files again.</p>
        <p><a href="{{ route('requests.create', [], false) }}">Open borrowing request form</a></p>
    </main>
</body>
</html>
