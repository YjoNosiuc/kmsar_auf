{{--
  Role slugs match database/seeders/RolePermissionSeeder.php.
  Route names match KMSAR_ARCHITECTURE.md route reference (register in routes/web.php).
  College dean / unit head: dean.dashboard (Dashboard), approval.queue, reports.index, dean.research (optional).
  Super admin: admin.dashboard, admin.users.*, admin.colleges.*, reports.index, audit.index (routes/web.php).
--}}
@auth
    @php($u = auth()->user())

    @if($u->hasAnyRole(['faculty', 'co_author']) && (Route::has('research.index') || Route::has('research.create')))
        <div class="kmsar-sidebar-section">Faculty</div>
        @if(Route::has('research.index'))
            <a href="{{ route('research.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('research.*') && ! request()->routeIs('research.create', 'research.wizard.*') ? 'active' : '' }}"
               aria-label="My Research"
               @if(request()->routeIs('research.*') && ! request()->routeIs('research.create', 'research.wizard.*')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v15.128A23.922 23.922 0 0112 18c3.243 0 6.328.612 9 1.718V4.756c-.938-.332-1.948-.512-3-.512-2.25 0-4.485.707-6 2.042zM19.5 4.756v15.128A23.922 23.922 0 0018 18c-1.052 0-2.062.18-3 .512" />
                </svg>
                <span>My Research</span>
            </a>
        @endif
        @if(Route::has('research.create'))
            <a href="{{ route('research.create') }}"
               class="kmsar-nav-item {{ request()->routeIs('research.create', 'research.wizard.*') ? 'active' : '' }}"
               aria-label="Register New Research"
               @if(request()->routeIs('research.create', 'research.wizard.*')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>Register New</span>
            </a>
        @endif
    @endif

    @if($u->hasAnyRole(['college_dean', 'unit_head']) && (Route::has('dean.dashboard') || Route::has('approval.queue') || Route::has('reports.index') || Route::has('dean.research')))
        <div class="kmsar-sidebar-section">College</div>
        @if(Route::has('dean.dashboard'))
            @php($collegeDashboardActive = request()->routeIs('dean.dashboard'))
            <a href="{{ route('dean.dashboard') }}"
               class="kmsar-nav-item {{ $collegeDashboardActive ? 'active' : '' }}"
               aria-label="Dashboard"
               @if($collegeDashboardActive) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <span>Dashboard</span>
            </a>
        @endif
        @if(Route::has('approval.queue'))
            <a href="{{ route('approval.queue') }}"
               class="kmsar-nav-item {{ request()->routeIs('approval.queue', 'approval.review') ? 'active' : '' }}"
               aria-label="Approval Queue"
               @if(request()->routeIs('approval.queue', 'approval.review')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                </svg>
                <span>Approval Queue</span>
            </a>
        @endif
        @if(Route::has('reports.index'))
            <a href="{{ route('reports.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"
               aria-label="Reports"
               @if(request()->routeIs('reports.*')) aria-current="page" @endif>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>Reports</span>
            </a>
        @endif
        @if(Route::has('dean.research'))
            <a href="{{ route('dean.research') }}"
               class="kmsar-nav-item {{ request()->routeIs('dean.research', 'dean.research.*') ? 'active' : '' }}"
               aria-label="All Research"
               @if(request()->routeIs('dean.research', 'dean.research.*')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v15.128A23.922 23.922 0 0112 18c3.243 0 6.328.612 9 1.718V4.756c-.938-.332-1.948-.512-3-.512-2.25 0-4.485.707-6 2.042zM19.5 4.756v15.128A23.922 23.922 0 0018 18c-1.052 0-2.062.18-3 .512" />
                </svg>
                <span>All Research</span>
            </a>
        @endif
    @endif

    @if($u->hasAnyRole(['ovpri_admin', 'cdaic_admin']) && (Route::has('ovpri.dashboard') || Route::has('ovpri.queue') || Route::has('reports.index') || Route::has('ovpri.research')))
        <div class="kmsar-sidebar-section">OVPRI</div>
        @if(Route::has('ovpri.dashboard'))
            <a href="{{ route('ovpri.dashboard') }}"
               class="kmsar-nav-item {{ request()->routeIs('ovpri.dashboard') ? 'active' : '' }}"
               aria-label="Dashboard"
               @if(request()->routeIs('ovpri.dashboard')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008z" />
                </svg>
                <span>Dashboard</span>
            </a>
        @endif
        @if(Route::has('ovpri.queue'))
            <a href="{{ route('ovpri.queue') }}"
               class="kmsar-nav-item {{ request()->routeIs('ovpri.queue', 'ovpri.review') ? 'active' : '' }}"
               aria-label="Final Approval"
               @if(request()->routeIs('ovpri.queue', 'ovpri.review')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.967 3.746 3.746 0 01-3.967 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.967-1.043 3.745 3.745 0 01-1.043-3.967A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.967 3.746 3.746 0 013.967-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.967 1.043 3.746 3.746 0 011.043 3.967A3.745 3.745 0 0121 12z" />
                </svg>
                <span>Approval Queue</span>
            </a>
        @endif
        @if(Route::has('reports.index'))
            <a href="{{ route('reports.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"
               aria-label="Reports"
               @if(request()->routeIs('reports.*')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                <span>Reports</span>
            </a>
        @endif
        @if(Route::has('ovpri.research'))
            <a href="{{ route('ovpri.research') }}"
               class="kmsar-nav-item {{ request()->routeIs('ovpri.research') ? 'active' : '' }}"
               aria-label="All Research"
               @if(request()->routeIs('ovpri.research')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v15.128A23.922 23.922 0 0112 18c3.243 0 6.328.612 9 1.718V4.756c-.938-.332-1.948-.512-3-.512-2.25 0-4.485.707-6 2.042zM19.5 4.756v15.128A23.922 23.922 0 0018 18c-1.052 0-2.062.18-3 .512" />
                </svg>
                <span>All Research</span>
            </a>
        @endif
    @endif

    @if($u->hasRole('super_admin') && (Route::has('admin.dashboard') || Route::has('admin.users.index') || Route::has('admin.colleges.index') || Route::has('reports.index') || Route::has('audit.index')))
        <div class="kmsar-sidebar-section">Administration</div>
        @if(Route::has('admin.dashboard'))
            <a href="{{ route('admin.dashboard') }}"
               class="kmsar-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
               aria-label="Dashboard"
               @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <span>Dashboard</span>
            </a>
        @endif
        @if(Route::has('admin.users.index'))
            <a href="{{ route('admin.users.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
               aria-label="User Management"
               @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.433-2.004M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
                <span>User Management</span>
            </a>
        @endif
        @if(Route::has('admin.colleges.index'))
            <a href="{{ route('admin.colleges.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('admin.colleges.*') ? 'active' : '' }}"
               aria-label="Colleges"
               @if(request()->routeIs('admin.colleges.*')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008zm0 3h.008v.008H17.25v-.008z" />
                </svg>
                <span>Colleges</span>
            </a>
        @endif
        @if(Route::has('reports.index'))
            <a href="{{ route('reports.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"
               aria-label="Reports"
               @if(request()->routeIs('reports.*')) aria-current="page" @endif>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>Reports</span>
            </a>
        @endif
        @if(Route::has('audit.index'))
            <a href="{{ route('audit.index') }}"
               class="kmsar-nav-item {{ request()->routeIs('audit.index') ? 'active' : '' }}"
               aria-label="Audit Logs"
               @if(request()->routeIs('audit.index')) aria-current="page" @endif>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                </svg>
                <span>Audit Logs</span>
            </a>
        @endif
    @endif

    @if(Route::has('profile.edit'))
        <a href="{{ route('profile.edit') }}"
           class="kmsar-nav-item 
                  {{ request()->routeIs('profile.*') 
                     ? 'active' : '' }}"
           aria-label="My Profile"
           @if(request()->routeIs('profile.*')) 
               aria-current="page" 
           @endif>
            <svg xmlns="http://www.w3.org/2000/svg"
                 fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor"
                 aria-hidden="true">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 11-7.5 0 
                         3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 
                         0114.998 0A17.933 17.933 0 0112 21.75c-2.676 
                         0-5.216-.584-7.499-1.632z"/>
            </svg>
            <span>My Profile</span>
        </a>
    @endif

    @if(Route::has('logout'))
        <div class="kmsar-sidebar-signout" style="border-top: 1px solid var(--sidebar-border); margin-top: 0.75rem; padding-top: 0.5rem;">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="kmsar-nav-item w-full text-left" aria-label="Sign out">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    Sign Out
                </button>
            </form>
        </div>
    @endif
@else
    @if(Route::has('login'))
        <div class="kmsar-sidebar-section">Account</div>
        <a href="{{ route('login') }}" class="kmsar-nav-item" aria-label="Log in">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
            </svg>
            <span>Log in</span>
        </a>
    @endif
@endauth
