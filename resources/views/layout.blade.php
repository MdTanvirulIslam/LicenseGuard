<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'License Setup')</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            padding: 24px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 8px 24px rgba(0,0,0,.06);
            padding: 32px;
            width: 100%;
            max-width: 480px;
        }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p.subtitle { margin: 0 0 24px; color: #64748b; font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=password], input[type=url] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
        }
        input:focus { outline: 2px solid #6366f1; outline-offset: 1px; border-color: #6366f1; }
        button {
            width: 100%;
            padding: 11px 12px;
            border: none;
            border-radius: 8px;
            background: #4f46e5;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #4338ca; }
        button.secondary { background: #e2e8f0; color: #334155; }
        button.secondary:hover { background: #cbd5e1; }
        .status { padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
        .status.valid, .status.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .status.invalid, .status.failure { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .status.bypassed { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 12px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
        .errors ul { margin: 0; padding-left: 18px; }
        .kv { font-size: 13px; color: #475569; margin: 4px 0; }
        .kv strong { color: #1e293b; }
        .actions { display: flex; gap: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>
