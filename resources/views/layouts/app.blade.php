<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'KMSAR'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/KMSAR.css') }}">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    @stack('scripts-head')

    @stack('styles')
</head>
<body>
    <div
        class="kmsar-app"
        x-data="{ sidebarOpen: false }"
        :class="{ 'sidebar-open': sidebarOpen }"
        @keydown.escape.window="sidebarOpen = false"
    >
        {{-- Mobile sidebar backdrop --}}
        <div
            class="kmsar-sidebar-backdrop"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            id="kmsar-sidebar"
            class="kmsar-sidebar"
            style="background-color: #1E3A8A;"
            aria-label="Main navigation"
        >
            <div class="kmsar-sidebar-brand">
                <div class="kmsar-sidebar-brand-inst">Angeles University Foundation</div>
                <div class="kmsar-sidebar-brand-name">KMSAR</div>
                <div class="kmsar-sidebar-brand-sub">Knowledge Management System for Academic Research</div>
            </div>

            @auth
                <div class="kmsar-sidebar-user" role="group" aria-label="Signed in as {{ auth()->user()->name }}">
                    <span class="kmsar-avatar" aria-hidden="true">
                        @if(auth()->user()->first_name)
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                            {{ strtoupper(substr(auth()->user()->last_name ?? '', 0, 1)) }}
                        @else
                            {{ strtoupper(substr((string) auth()->user()->name, 0, 1)) }}
                        @endif
                    </span>
                    <div class="kmsar-sidebar-user-meta">
                        <div class="kmsar-sidebar-user-name">
                            @if(auth()->user()->first_name)
                                {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                            @else
                                {{ auth()->user()->name }}
                            @endif
                        </div>
                        @php
                            $primaryRole = auth()->user()->roles->first();
                            $roleSlug = $primaryRole?->name;
                            $collegeCode = auth()->user()->college?->code;
                            $kmsarSidebarRoleLabel = match ($roleSlug) {
                                'college_dean' => $collegeCode ? $collegeCode.' Dean' : 'Dean',
                                'unit_head' => $collegeCode ? $collegeCode.' Unit Head' : 'Unit Head',
                                'faculty' => $collegeCode ? $collegeCode.' Faculty' : 'Faculty',
                                'viewer' => 'Viewer',
                                'ovpri_admin' => 'OVPRI Admin',
                                'cdaic_admin' => 'CDAIC Admin',
                                'super_admin' => 'Super Admin',
                                'registrar' => 'Registrar',
                                default => $roleSlug ? str_replace('_', ' ', $roleSlug) : '',
                            };
                        @endphp
                        @if($primaryRole)
                            <span class="kmsar-sidebar-user-role">{{ $kmsarSidebarRoleLabel }}</span>
                        @endif
                    </div>
                </div>
            @endauth

            <nav class="kmsar-sidebar-nav" @click="if (window.innerWidth < 768) sidebarOpen = false">
                @hasSection('sidebar-nav')
                    @yield('sidebar-nav')
                @else
                    @include('layouts.partials.sidebar')
                @endif
            </nav>

            <div class="kmsar-sidebar-footer">
                &copy; {{ date('Y') }} AUF
            </div>
        </aside>

        <div class="kmsar-main-wrapper">
            <header class="kmsar-navbar">
                <div class="kmsar-navbar-left">
                    <button
                        type="button"
                        class="kmsar-navbar-menu-btn"
                        @click="sidebarOpen = !sidebarOpen"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-controls="kmsar-sidebar"
                        aria-label="{{ __('Toggle navigation menu') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" style="width:1.35rem;height:1.35rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div class="kmsar-navbar-context">
                        @yield('navbar-context', 'Dashboard')
                    </div>
                </div>
                <div class="kmsar-navbar-right">
                    @auth
                    @php
                        $unreadNotifications = auth()->user()
                            ->unreadNotifications()
                            ->latest()
                            ->take(10)
                            ->get();
                        $unreadCount = auth()->user()
                            ->unreadNotifications()
                            ->count();
                    @endphp

                    <div style="position: relative;"
                         x-data="{ openNotif: false }">

                        {{-- Bell button --}}
                        <button type="button"
                                class="kmsar-navbar-icon-btn"
                                @click="openNotif = !openNotif"
                                aria-label="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5" stroke="currentColor"
                                 aria-hidden="true"
                                 style="width:1.25rem;height:1.25rem;">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                            </svg>
                            @if($unreadCount > 0)
                                <span class="kmsar-navbar-notif-dot"
                                      style="display:flex;
                                             align-items:center;
                                             justify-content:center;
                                             width:1rem;height:1rem;
                                             font-size:0.5rem;
                                             font-weight:700;
                                             color:var(--color-primary-dark);">
                                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                </span>
                            @endif
                        </button>

                        {{-- Dropdown panel --}}
                        <div x-show="openNotif"
                             @click.outside="openNotif = false"
                             class="kmsar-navbar-notif-panel"
                             style="display:none;
                                    position:absolute;
                                    top:calc(100% + 0.5rem);
                                    right:0;
                                    width:min(22rem, calc(100vw - 1.5rem));
                                    background:var(--color-card);
                                    border:1px solid var(--color-border);
                                    border-radius:var(--radius-lg);
                                    box-shadow:var(--shadow-lg);
                                    z-index:200;
                                    overflow:hidden;">

                            {{-- Header --}}
                            <div style="padding:0.875rem 1rem;
                                        border-bottom:1px solid var(--color-border);
                                        display:flex;
                                        align-items:center;
                                        justify-content:space-between;">
                                <span style="font-size:var(--text-base);
                                             font-weight:600;
                                             color:var(--color-text-primary);">
                                    Notifications
                                </span>
                                @if($unreadCount > 0)
                                    <form method="POST"
                                          action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit"
                                                style="font-size:var(--text-xs);
                                                       color:var(--color-primary);
                                                       background:none;
                                                       border:none;
                                                       cursor:pointer;">
                                            Mark all as read
                                        </button>
                                    </form>
                                @endif
                            </div>

                            {{-- Notification list --}}
                            <div style="max-height:20rem;overflow-y:auto;">
                                @forelse($unreadNotifications as $notif)
                                    <a href="{{ $notif->data['action_url'] ?? '#' }}"
                                       onclick="markRead('{{ $notif->id }}')"
                                       style="display:block;
                                              padding:0.75rem 1rem;
                                              border-bottom:1px solid var(--color-border);
                                              text-decoration:none;
                                              background:{{ $notif->read_at ? 'transparent' : 'var(--color-gold-muted)' }};
                                              border-left:3px solid {{ $notif->read_at ? 'transparent' : 'var(--color-gold)' }};">
                                        <p style="font-size:var(--text-sm);
                                                  font-weight:500;
                                                  color:var(--color-text-primary);
                                                  margin-bottom:2px;">
                                            {{ $notif->data['reference_number'] ?? '' }}
                                        </p>
                                        <p style="font-size:var(--text-xs);
                                                  color:var(--color-text-secondary);
                                                  line-height:1.5;">
                                            {{ $notif->data['message'] ?? '' }}
                                        </p>
                                        <p style="font-size:var(--text-2xs);
                                                  color:var(--color-text-muted);
                                                  margin-top:4px;">
                                            {{ $notif->created_at->diffForHumans() }}
                                        </p>
                                    </a>
                                @empty
                                    <div style="padding:2rem 1rem;
                                                text-align:center;
                                                color:var(--color-text-muted);
                                                font-size:var(--text-sm);">
                                        No new notifications
                                    </div>
                                @endforelse
                            </div>

                            {{-- Footer --}}
                            <div style="padding:0.75rem 1rem;
                                        border-top:1px solid var(--color-border);
                                        text-align:center;">
                                <a href="{{ route('notifications.index') }}"
                                   style="font-size:var(--text-xs);
                                          color:var(--color-primary);">
                                    View all notifications
                                </a>
                            </div>
                        </div>
                    </div>

                    <script>
                    function markRead(id) {
                        fetch('/notifications/' + id + '/read', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            }
                        });
                    }
                    </script>
                    @endauth

                    @yield('navbar-actions')
                </div>
            </header>

            <main class="kmsar-main-content" id="main-content">
                <div class="kmsar-page-container">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('modals')
    @stack('scripts')

    @auth
    {{-- Idle Timeout Warning Modal --}}
    <style>
        #kmsar-idle-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(15, 23, 42, 0.6);
            align-items: center;
            justify-content: center;
        }
        #kmsar-idle-modal.is-open {
            display: flex;
        }
        .kmsar-idle-card {
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .kmsar-idle-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #FEF3C7;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #D97706;
        }
        .kmsar-idle-title {
            font-size: 20px;
            font-weight: 700;
            color: #0F172A;
            margin: 0 0 8px;
        }
        .kmsar-idle-message {
            color: #64748B;
            font-size: 14px;
            margin: 0 0 8px;
            line-height: 1.6;
        }
        #kmsar-idle-countdown {
            font-size: 48px;
            font-weight: 800;
            color: #1E3A8A;
            margin: 12px 0;
            line-height: 1;
        }
        #kmsar-idle-countdown.is-urgent {
            color: #DC2626;
        }
        .kmsar-idle-seconds {
            color: #94A3B8;
            font-size: 12px;
            margin: 0 0 24px;
        }
        .kmsar-idle-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .kmsar-idle-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .kmsar-idle-btn--primary {
            background: #1E3A8A;
            color: #fff;
        }
        .kmsar-idle-btn--primary:hover {
            background: #1E40AF;
        }
        .kmsar-idle-btn--muted {
            background: #F1F5F9;
            color: #64748B;
        }
        .kmsar-idle-btn--muted:hover {
            background: #E2E8F0;
        }
        @media (max-width: 480px) {
            .kmsar-idle-card { padding: 28px 20px; }
            .kmsar-idle-actions { flex-direction: column; }
        }
    </style>

    <div
        id="kmsar-idle-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="kmsar-idle-title"
        aria-hidden="true"
        data-idle-ms="{{ max(1, (int) config('kmsar.idle_timeout_minutes', 2)) * 60 * 1000 }}"
        data-countdown-ms="{{ max(10, (int) config('kmsar.idle_countdown_seconds', 30)) * 1000 }}"
    >
        <div class="kmsar-idle-card">
            <div class="kmsar-idle-icon" aria-hidden="true">
                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <h2 id="kmsar-idle-title" class="kmsar-idle-title">Are you still there?</h2>
            <p class="kmsar-idle-message">
                You've been inactive for a while. For your security, you will be
                automatically logged out in:
            </p>
            <div id="kmsar-idle-countdown">{{ (int) config('kmsar.idle_countdown_seconds', 30) }}</div>
            <p class="kmsar-idle-seconds">seconds</p>
            <div class="kmsar-idle-actions">
                <button type="button" class="kmsar-idle-btn kmsar-idle-btn--primary" onclick="kmsarIdleReset()">
                    Yes, I'm still here
                </button>
                <button type="button" class="kmsar-idle-btn kmsar-idle-btn--muted" onclick="kmsarIdleLogout()">
                    Log out
                </button>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect to login when the session / CSRF token has expired (419).
        (function () {
            const loginExpiredUrl = @json(route('login', ['expired' => 1]));

            function redirectIfExpired(status) {
                if (status === 419) {
                    window.location.href = loginExpiredUrl;
                    return true;
                }
                return false;
            }

            const originalFetch = window.fetch;
            window.fetch = function (...args) {
                return originalFetch.apply(this, args).then(function (response) {
                    redirectIfExpired(response.status);
                    return response;
                });
            };

            const originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function (...args) {
                this.addEventListener('load', function () {
                    redirectIfExpired(this.status);
                });
                return originalOpen.apply(this, args);
            };
        })();

        // Idle timeout — show the warning before logout. Hidden tabs pause so
        // background timer throttling cannot skip the modal and dump the user
        // on /login?expired=1 with no explanation.
        (function () {
            const modal = document.getElementById('kmsar-idle-modal');
            const countEl = document.getElementById('kmsar-idle-countdown');
            const IDLE_MS = parseInt(modal.getAttribute('data-idle-ms') || '120000', 10);
            const COUNTDOWN_MS = parseInt(modal.getAttribute('data-countdown-ms') || '30000', 10);
            const COUNTDOWN_SECS = Math.round(COUNTDOWN_MS / 1000);
            const logoutUrl = @json(route('logout'));
            const pingUrl = @json(route('session.ping'));
            const loginExpiredUrl = @json(route('login', ['expired' => 1]));
            let csrfToken = @json(csrf_token());

            let lastActivity = Date.now();
            let countdownEndsAt = null;
            let isWarningShown = false;
            let isLoggingOut = false;
            let ticking = false;

            function currentCsrf() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return (meta && meta.getAttribute('content')) || csrfToken;
            }

            function applyCsrf(token) {
                if (!token) return;
                csrfToken = token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) {
                    meta.setAttribute('content', token);
                }
            }

            function hideWarning() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                countEl.classList.remove('is-urgent');
                isWarningShown = false;
                countdownEndsAt = null;
            }

            function showWarning() {
                if (isWarningShown) return;
                isWarningShown = true;
                countdownEndsAt = Date.now() + COUNTDOWN_MS;
                countEl.textContent = COUNTDOWN_SECS;
                countEl.classList.remove('is-urgent');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            function paintCountdown() {
                if (!isWarningShown || countdownEndsAt === null) return;
                const remaining = Math.max(0, Math.ceil((countdownEndsAt - Date.now()) / 1000));
                countEl.textContent = remaining;
                if (remaining <= 5) {
                    countEl.classList.add('is-urgent');
                }
                if (remaining <= 0) {
                    kmsarIdleLogout();
                }
            }

            function markActivity() {
                if (isWarningShown || document.hidden) return;
                lastActivity = Date.now();
            }

            function tick() {
                if (ticking || document.hidden || isLoggingOut) return;
                ticking = true;
                try {
                    if (!isWarningShown && (Date.now() - lastActivity) >= IDLE_MS) {
                        showWarning();
                    }
                    paintCountdown();
                } finally {
                    ticking = false;
                }
            }

            function pingSession() {
                return fetch(pingUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': currentCsrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                }).then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                }).then(function (data) {
                    if (data && data.csrf) {
                        applyCsrf(data.csrf);
                    }
                    return data;
                }).catch(function () {
                    return null;
                });
            }

            window.kmsarIdleReset = function (fromOtherTab) {
                hideWarning();
                lastActivity = Date.now();
                pingSession();
                if (!fromOtherTab) {
                    try {
                        localStorage.setItem('kmsar-idle-reset', String(Date.now()));
                    } catch (e) {
                        /* private mode */
                    }
                }
            };

            window.kmsarIdleLogout = function (fromOtherTab) {
                if (isLoggingOut) return;
                isLoggingOut = true;
                hideWarning();
                if (!fromOtherTab) {
                    try {
                        localStorage.setItem('kmsar-auth-logout', String(Date.now()));
                    } catch (e) {
                        /* private mode */
                    }
                }

                fetch(logoutUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': currentCsrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    redirect: 'manual',
                }).finally(function () {
                    window.location.href = loginExpiredUrl;
                });
            };

            ['mousemove', 'mousedown', 'keypress', 'keydown', 'scroll', 'touchstart', 'click']
                .forEach(function (event) {
                    document.addEventListener(event, markActivity, { passive: true });
                });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) return;
                tick();
            });

            window.addEventListener('storage', function (event) {
                if (event.key === 'kmsar-idle-reset') {
                    window.kmsarIdleReset(true);
                }
                if (event.key === 'kmsar-auth-logout') {
                    window.location.href = loginExpiredUrl;
                }
            });

            setInterval(tick, 1000);
            tick();
        })();

        window.kmsarSignOut = function () {
            const form = document.getElementById('kmsar-logout-form');
            if (!form) {
                return;
            }
            const meta = document.querySelector('meta[name="csrf-token"]');
            const tokenInput = form.querySelector('input[name="_token"]');
            if (meta && tokenInput) {
                tokenInput.value = meta.getAttribute('content') || tokenInput.value;
            }
            form.submit();
        };
    </script>
    @endauth
</body>
</html>
