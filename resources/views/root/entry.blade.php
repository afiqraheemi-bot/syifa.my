<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>SYIFA.my</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 38rem;
            margin: 4rem auto;
            padding: 0 1.5rem;
            color: #0f172a;
            line-height: 1.5;
        }
        h1 {
            margin-bottom: 0.25rem;
            font-size: 1.5rem;
        }
        .status {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .notice {
            margin-top: 2rem;
            padding: 1rem 1.25rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #f8fafc;
        }
        a {
            color: #0f766e;
        }
    </style>
</head>
<body>
    <h1>SYIFA.my</h1>
    <p class="status">Backend running</p>

    @if ($operationsHealthUrl !== null)
        <p><a href="{{ $operationsHealthUrl }}">Operations health</a></p>
    @endif

    <div class="notice">
        Authentication UI is not yet available. Sign in through the existing session API to reach your dashboard.
    </div>
</body>
</html>
