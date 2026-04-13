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
    <div class="kmsar-app">
        <aside class="kmsar-sidebar" style="background-color: #1E3A8A;" aria-label="Main navigation">
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
                    <div>
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
                                'co_author' => $collegeCode ? $collegeCode.' Co-Author' : 'Co-Author',
                                'ovpri_admin' => 'OVPRI Admin',
                                'cdaic_admin' => 'CDAIC Admin',
                                'super_admin' => 'Super Admin',
                                'registrar' => 'Registrar',
                                'viewer' => 'Viewer',
                                default => $roleSlug ? str_replace('_', ' ', $roleSlug) : '',
                            };
                        @endphp
                        @if($primaryRole)
                            <span class="kmsar-sidebar-user-role">{{ $kmsarSidebarRoleLabel }}</span>
                        @endif
                    </div>
                </div>
            @endauth

            <nav class="kmsar-sidebar-nav">
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
                <div class="kmsar-navbar-context">
                    @yield('navbar-context', 'Dashboard')
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
                             style="display:none;
                                    position:absolute;
                                    top:calc(100% + 0.5rem);
                                    right:0;
                                    width:22rem;
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
</body>
</html>
