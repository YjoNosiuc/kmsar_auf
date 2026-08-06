@extends('layouts.app')

@section('title', __('Access Denied'))

@section('navbar-context')
    {{ __('Access Denied') }}
@endsection

@section('content')
    @php
        $dashboardUrl = '/';
        $dashboardLabel = __('Go to Home');

        if (auth()->check()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();
            $dashboardUrl = match (true) {
                $user->hasRole('super_admin') => route('admin.dashboard'),
                $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => route('ovpri.dashboard'),
                $user->hasAnyRole(['college_dean', 'unit_head']) => route('dean.dashboard'),
                $user->hasAnyRole(['faculty', 'co_author']) => route('research.index'),
                default => url('/'),
            };
            $dashboardLabel = match (true) {
                $user->hasRole('super_admin') => __('Go to Admin Dashboard'),
                $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']) => __('Go to OVPRI Dashboard'),
                $user->hasAnyRole(['college_dean', 'unit_head']) => __('Go to Dean Dashboard'),
                $user->hasAnyRole(['faculty', 'co_author']) => __('Go to My Research'),
                default => __('Go to Home'),
            };
        } else {
            $dashboardUrl = route('login');
            $dashboardLabel = __('Sign in');
        }

        $backUrl = url()->previous();
        if ($backUrl === url()->current() || $backUrl === '') {
            $backUrl = $dashboardUrl;
        }
    @endphp

    <div style="text-align:center;padding:4rem 2rem;">
        <div style="font-size:4rem;font-weight:700;color:#1E3A8A;">403</div>
        <h1 style="font-size:1.5rem;font-weight:600;color:#0F172A;margin:1rem 0;">{{ __('Access Denied') }}</h1>
        <p style="color:#64748B;margin-bottom:2rem;max-width:36rem;margin-left:auto;margin-right:auto;line-height:1.6;">
            {{ __('You do not have permission to view this page. This research may not be in the correct stage for your role.') }}
        </p>
        <a href="{{ $backUrl }}" style="display:inline-block;padding:10px 24px;background:#1E3A8A;color:#fff;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;margin:0 4px 8px;">← {{ __('Go Back') }}</a>
        <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:10px 24px;background:#D4AF37;color:#fff;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;margin:0 4px 8px;">{{ $dashboardLabel }}</a>
    </div>
@endsection
