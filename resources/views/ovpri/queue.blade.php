@extends('layouts.app')

@section('title', __('Final approval queue'))

@section('navbar-context')
    {{ __('OVPRI') }}
@endsection

@push('styles')
<style>
    .kmsar-tab-bar {
        display: flex;
        border-bottom: 2px solid #E2E8F0;
        margin-bottom: 24px;
        gap: 0;
    }
    .kmsar-tab-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        font-size: 13px;
        font-weight: 500;
        font-family: inherit;
        color: #94A3B8;
        cursor: pointer;
        transition: color 0.15s, border-color 0.15s;
        white-space: nowrap;
    }
    .kmsar-tab-btn:hover { color: #1E3A8A; }
    .kmsar-tab-btn.active {
        color: #1E3A8A;
        font-weight: 600;
        border-bottom-color: #D4AF37;
    }
    .kmsar-tab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        padding: 1px 7px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.6;
    }
    .kmsar-tab-badge.pending  { background: #FEF3C7; color: #D97706; }
    .kmsar-tab-badge.approved { background: #ECFDF5; color: #059669; }
    .kmsar-tab-badge.returned { background: #FEF2F2; color: #DC2626; }

    .kmsar-tab-panel { display: none; }
    .kmsar-tab-panel.active { display: block; }

    .queue-card {
        background: #fff;
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 10px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        transition: box-shadow 0.15s, border-color 0.15s;
    }
    .queue-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border-color: #CBD5E1;
    }
    .queue-card-ref {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        margin-bottom: 4px;
    }
    .queue-card-title {
        font-size: 15px;
        font-weight: 600;
        color: #0F172A;
        line-height: 1.4;
        margin-bottom: 8px;
    }
    .queue-card-meta {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #475569;
    }
    .queue-card-meta-label { color: #94A3B8; margin-right: 2px; }
    .queue-card-action-primary {
        display: inline-flex;
        align-items: center;
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 600;
        background: #1E3A8A;
        color: #fff;
        border-radius: 8px;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .queue-card-action-secondary {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 500;
        border: 1px solid #CBD5E1;
        color: #475569;
        border-radius: 8px;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .queue-empty {
        text-align: center;
        padding: 60px 20px;
    }
    .queue-empty-icon { font-size: 40px; margin-bottom: 12px; line-height: 1; }
    .queue-empty-title { font-size: 15px; font-weight: 600; color: #0F172A; margin-bottom: 4px; }
    .queue-empty-sub { font-size: 13px; color: #94A3B8; }
    .queue-meta-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 12px;
        color: #94A3B8;
    }
    .queue-meta-row span:first-child { font-size: 13px; color: #475569; }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="kmsar-page-header" style="margin-bottom:24px;">
    <div>
        <nav class="kmsar-breadcrumb" aria-label="Breadcrumb">
            <span class="kmsar-breadcrumb-current">{{ __('Final Approval Queue') }}</span>
        </nav>
        <div style="display:flex;align-items:center;gap:12px;margin-top:4px;flex-wrap:wrap;">
            <h1 class="kmsar-h1" style="margin:0;">{{ __('Final Approval') }}</h1>
            @if ($pending->count() > 0)
                <span style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:600;border-radius:9999px;background:rgba(37,99,235,0.12);color:#1D4ED8;border:1px solid rgba(37,99,235,0.25);">
                    {{ $pending->count() }} {{ __('pending') }}
                </span>
            @endif
        </div>
        <p style="font-size:13px;color:var(--color-text-muted);margin:6px 0 0;">{{ __('Research endorsed by colleges and awaiting university-level decision') }}</p>
    </div>
</div>

@if (session('success'))
    <x-alert type="success" :message="session('success')" class="mb-6" />
@endif

@if ($errors->any())
    <x-alert type="danger" class="mb-6">
        <ul class="list-disc pl-5 space-y-1">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

{{-- Main card --}}
<div class="kmsar-card" style="border-top:3px solid #D4AF37;">
    <div class="kmsar-card-body">

        {{-- Tab bar --}}
        <div class="kmsar-tab-bar" role="tablist">
            <button type="button" role="tab" aria-selected="true"
                class="kmsar-tab-btn active" id="tab-pending"
                onclick="switchTab('pending')">
                {{ __('Pending') }}
                <span class="kmsar-tab-badge pending">{{ $pending->count() }}</span>
            </button>
            <button type="button" role="tab" aria-selected="false"
                class="kmsar-tab-btn" id="tab-approved"
                onclick="switchTab('approved')">
                {{ __('Approved') }}
                <span class="kmsar-tab-badge approved">{{ $approved->count() }}</span>
            </button>
            <button type="button" role="tab" aria-selected="false"
                class="kmsar-tab-btn" id="tab-returned"
                onclick="switchTab('returned')">
                {{ __('Returned / Rejected') }}
                <span class="kmsar-tab-badge returned">{{ $returned->count() }}</span>
            </button>
        </div>

        {{-- Pending tab --}}
        <div class="kmsar-tab-panel active" id="panel-pending" role="tabpanel">
            @if ($pending->isEmpty())
                <div class="queue-empty">
                    <div class="queue-empty-icon">✓</div>
                    <div class="queue-empty-title">{{ __('All caught up!') }}</div>
                    <div class="queue-empty-sub">{{ __('No submissions awaiting final approval.') }}</div>
                </div>
            @else
                <div class="queue-meta-row">
                    <span>{{ __('Showing :count submissions awaiting final approval', ['count' => $pending->count()]) }}</span>
                    <span>{{ __('Sorted by submission date — newest first') }}</span>
                </div>
                @foreach ($pending as $research)
                    <div class="queue-card" style="border-left:4px solid #2563EB;">
                        <div style="flex:1;min-width:0;">
                            <div class="queue-card-ref" style="color:#2563EB;">{{ $research->reference_number }}</div>
                            <div class="queue-card-title">{{ str($research->title)->limit(90) }}</div>
                            <div class="queue-card-meta">
                                <span><span class="queue-card-meta-label">{{ __('Author:') }}</span> {{ $research->primaryAuthor?->name ?? '—' }}</span>
                                <span><span class="queue-card-meta-label">{{ __('College:') }}</span> {{ $research->motherCollege?->code ?? '—' }}</span>
                                <span><span class="queue-card-meta-label">{{ __('Submitted:') }}</span> {{ $research->created_at->format('M d, Y') }}</span>
                                <span><span class="queue-card-meta-label">{{ __('Classification:') }}</span> {{ ucwords(str_replace('_', ' ', $research->research_classification)) }}</span>
                                <span><span class="queue-card-meta-label">{{ __('Status:') }}</span> {{ ucwords(str_replace('_', ' ', $research->status)) }}</span>
                            </div>
                        </div>
                        <a href="{{ route('ovpri.review', $research) }}" class="queue-card-action-primary">{{ __('View') }} →</a>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Approved tab --}}
        <div class="kmsar-tab-panel" id="panel-approved" role="tabpanel">
            @if ($approved->isEmpty())
                <div class="queue-empty">
                    <div class="queue-empty-icon">📋</div>
                    <div class="queue-empty-title">{{ __('No approved submissions yet') }}</div>
                    <div class="queue-empty-sub">{{ __('Research you approve will appear here.') }}</div>
                </div>
            @else
                <div class="queue-meta-row">
                    <span>{{ __(':count approved submissions', ['count' => $approved->count()]) }}</span>
                    <span>{{ __('Sorted by most recently approved') }}</span>
                </div>
                @foreach ($approved as $research)
                    <div class="queue-card" style="border-left:4px solid #059669;">
                        <div style="flex:1;min-width:0;">
                            <div class="queue-card-ref" style="color:#D4AF37;">{{ $research->reference_number }}</div>
                            <div class="queue-card-title">{{ str($research->title)->limit(90) }}</div>
                            <div class="queue-card-meta">
                                <span><span class="queue-card-meta-label">{{ __('Author:') }}</span> {{ $research->primaryAuthor?->name ?? '—' }}</span>
                                <span><span class="queue-card-meta-label">{{ __('College:') }}</span> {{ $research->motherCollege?->code ?? '—' }}</span>
                                <span><span class="queue-card-meta-label">{{ __('Status:') }}</span> {{ ucwords(str_replace('_', ' ', $research->status)) }}</span>
                                <span style="color:#059669;font-weight:600;">✓ {{ __('Approved') }}</span>
                            </div>
                        </div>
                        <a href="{{ route('ovpri.review', $research) }}" class="queue-card-action-secondary">{{ __('View') }}</a>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Returned/Rejected tab --}}
        <div class="kmsar-tab-panel" id="panel-returned" role="tabpanel">
            @if ($returned->isEmpty())
                <div class="queue-empty">
                    <div class="queue-empty-icon">👍</div>
                    <div class="queue-empty-title">{{ __('No returned submissions') }}</div>
                    <div class="queue-empty-sub">{{ __('Research returned or rejected will appear here.') }}</div>
                </div>
            @else
                <div class="queue-meta-row">
                    <span>{{ __(':count returned / rejected submissions', ['count' => $returned->count()]) }}</span>
                </div>
                @foreach ($returned as $research)
                    <div class="queue-card" style="border-left:4px solid #DC2626;">
                        <div style="flex:1;min-width:0;">
                            <div class="queue-card-ref" style="color:#D4AF37;">{{ $research->reference_number }}</div>
                            <div class="queue-card-title">{{ str($research->title)->limit(90) }}</div>
                            <div class="queue-card-meta">
                                <span><span class="queue-card-meta-label">{{ __('Author:') }}</span> {{ $research->primaryAuthor?->name ?? '—' }}</span>
                                <span><span class="queue-card-meta-label">{{ __('College:') }}</span> {{ $research->motherCollege?->code ?? '—' }}</span>
                                <span><span class="queue-card-meta-label">{{ __('Status:') }}</span> {{ ucwords(str_replace('_', ' ', $research->status)) }}</span>
                                @if ($research->approval_stage === 'rejected')
                                    <span style="color:#DC2626;font-weight:600;">✕ {{ __('Rejected') }}</span>
                                @else
                                    <span style="color:#D97706;font-weight:600;">↩ {{ __('Returned for revision') }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('ovpri.review', $research) }}" class="queue-card-action-secondary">{{ __('View') }}</a>
                    </div>
                @endforeach
            @endif
        </div>

    </div>
</div>

@push('scripts')
<script>
function switchTab(name) {
    document.querySelectorAll('.kmsar-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
    });
    document.getElementById('tab-' + name).classList.add('active');
    document.getElementById('tab-' + name).setAttribute('aria-selected', 'true');

    document.querySelectorAll('.kmsar-tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById('panel-' + name).classList.add('active');
}
</script>
@endpush

@endsection