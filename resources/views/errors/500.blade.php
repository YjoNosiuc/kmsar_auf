@php
    $isLoggedIn = false;
    $backUrl = url()->previous() !== url()->current() ? url()->previous() : url('/');
    $dashboardUrl = url('/');
    $loginUrl = url('/login');

    try {
        $loginUrl = route('login');

        if (auth()->check()) {
            $isLoggedIn = true;
            $user = auth()->user();
            $dashboardUrl = match (true) {
                $user->hasRole('super_admin') => route('admin.dashboard'),
                $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => route('ovpri.dashboard'),
                $user->hasAnyRole(['college_dean', 'unit_head']) => route('dean.dashboard'),
                $user->hasAnyRole(['faculty', 'co_author']) => route('research.index'),
                default => url('/'),
            };
        }
    } catch (\Throwable $e) {
        $isLoggedIn = false;
        $dashboardUrl = url('/');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something Went Wrong — KMSAR</title>
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
        .code { font-size: 56px; font-weight: 700; line-height: 1; color: #DC2626; }
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
        .btn-outline { background: #FFFFFF; color: #1E3A8A; border-color: #CBD5E1; }
        .btn-outline:hover { background: #F8FAFC; }
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <div class="code">500</div>
            <h1>Something Went Wrong</h1>
            <p>An unexpected error occurred. Please try again. If the problem continues, please contact your system administrator.</p>
            <div class="actions">
                @if ($isLoggedIn)
                    <a class="btn btn-outline" href="{{ $backUrl }}">&larr; Go Back</a>
                    <a class="btn btn-primary" href="{{ $dashboardUrl }}">Go to Dashboard</a>
                @else
                    <button type="button" class="btn btn-outline" onclick="window.location.reload()">Try Again</button>
                    <a class="btn btn-primary" href="{{ $loginUrl }}">Login</a>
                @endif
            </div>
        </div>
    </main>

    <div class="foot">Angeles University Foundation · OVPRI · KMSAR System</div>
</body>
</html>
