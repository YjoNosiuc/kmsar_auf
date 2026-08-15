<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Unavailable — KMSAR</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #F1F5F9;
            color: #0F172A;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .top {
            background: #1E3A8A;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .top-brand {
            color: #D4AF37;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: 1px;
        }
        .top-system {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
        }
        .body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            padding: 40px 32px;
            text-align: center;
        }
        .icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #EEF2FF;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1E3A8A;
        }
        .icon svg { width: 26px; height: 26px; }
        .code { font-size: 56px; font-weight: 700; line-height: 1; color: #64748B; }
        h1 { font-size: 22px; font-weight: 600; margin: 14px 0 12px; }
        p { color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 auto 28px; max-width: 440px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 20px;
            border-radius: 8px;
            border: 1.5px solid transparent;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: #1E3A8A; color: #FFFFFF; }
        .btn-primary:hover { background: #1E40AF; }
        .foot {
            text-align: center;
            padding: 20px;
            color: #94A3B8;
            font-size: 12px;
        }
        @media (max-width: 480px) {
            .card { padding: 28px 20px; }
            .actions .btn { width: 100%; }
            .top-system { display: none; }
        }
    </style>
</head>
<body>
    <div class="top">
        <span class="top-brand">KMSAR</span>
        <span class="top-system">Knowledge Management System for Academic Research</span>
    </div>

    <main class="body">
        <div class="card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75 3.75 2.25 7.5 4.5v1.409l4.26 4.26" />
                </svg>
            </div>
            <div class="code">503</div>
            <h1>System Unavailable</h1>
            <p>KMSAR is temporarily unavailable for maintenance. Please try again in a few minutes.</p>
            <div class="actions">
                <button type="button" class="btn btn-primary" onclick="window.location.reload()">Try Again</button>
            </div>
        </div>
    </main>

    <div class="foot">Angeles University Foundation · OVPRI · KMSAR System</div>
</body>
</html>
