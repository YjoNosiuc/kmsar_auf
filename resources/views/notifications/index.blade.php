@extends('layouts.app')
@section('title', 'Notifications')
@section('navbar-context', 'Notifications')

@section('content')
<div class="kmsar-page-header">
    <div>
        <h1 class="kmsar-h2">Notifications</h1>
        <p class="kmsar-body">
            Your research activity updates
        </p>
    </div>
</div>

<x-card>
    @forelse($notifications as $notif)
        <a href="{{ $notif->data['action_url'] ?? '#' }}"
           style="display:flex;
                  align-items:flex-start;
                  gap:1rem;
                  padding:1rem var(--space-5);
                  border-bottom:1px solid var(--color-border);
                  text-decoration:none;
                  background:{{ $notif->read_at 
                    ? 'transparent' 
                    : 'var(--color-gold-muted)' }};
                  border-left:3px solid {{ $notif->read_at 
                    ? 'transparent' 
                    : 'var(--color-gold)' }};">

            {{-- Icon dot --}}
            <div style="width:0.5rem;height:0.5rem;
                        border-radius:50%;
                        margin-top:0.4rem;
                        flex-shrink:0;
                        background:{{ $notif->read_at 
                          ? 'var(--color-border)' 
                          : 'var(--color-gold)' }};">
            </div>

            <div style="flex:1;">
                <p style="font-size:var(--text-sm);
                          font-weight:{{ $notif->read_at 
                            ? '400' : '600' }};
                          color:var(--color-text-primary);
                          margin-bottom:2px;">
                    {{ $notif->data['message'] ?? '' }}
                </p>
                <p style="font-size:var(--text-xs);
                          color:var(--color-text-muted);">
                    {{ $notif->created_at->format('M d, Y h:i A') }}
                    · {{ $notif->created_at->diffForHumans() }}
                </p>
            </div>

            @if(!$notif->read_at)
                <span class="kmsar-badge kmsar-badge--gold">
                    New
                </span>
            @endif
        </a>
    @empty
        <div style="padding:3rem;
                    text-align:center;
                    color:var(--color-text-muted);">
            <p style="font-size:var(--text-base);">
                No notifications yet.
            </p>
        </div>
    @endforelse
</x-card>

@if($notifications->hasPages())
    <div style="margin-top:1rem;
                display:flex;
                justify-content:flex-end;">
        {{ $notifications->links() }}
    </div>
@endif
@endsection
