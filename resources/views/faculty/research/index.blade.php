@extends('layouts.app')

@section('title', __('My research'))

@section('navbar-context')
    {{ __('Faculty') }}
@endsection

@section('content')
    <x-page-header
        :title="__('My research')"
        :subtitle="__('Registered research records you own or co-author')"
        :breadcrumb="[
            ['label' => __('My Research')],
        ]"
    >
        <x-button variant="primary" href="{{ route('research.create') }}">{{ __('Register new') }}</x-button>
    </x-page-header>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-6" />
    @endif

    @php
        $expectedLabels = [
            'publication' => __('Publication'),
            'patent' => __('Patent'),
            'policy_brief' => __('Policy brief'),
            'other' => __('Other'),
        ];
        $borderByStage = static fn (string $stage): string => match ($stage) {
            'draft' => '#94A3B8',
            'dean_review' => '#D4AF37',
            'ovpri_review' => '#2563EB',
            'approved' => '#059669',
            'rejected' => '#DC2626',
            default => '#94A3B8',
        };

        $researchProgressBadgeStatus = static function (string $status): string {
            return match ($status) {
                'proposal', 'ongoing', 'patent_submitted' => 'pending',
                'published_scopus', 'published_non_indexed', 'patent_granted', 'completed_unpublished' => 'approved',
                'presented_internal', 'presented_external' => 'info',
                default => 'info',
            };
        };

        $approvalStageBadgeStatus = static function (string $stage): string {
            return match ($stage) {
                'draft' => 'draft',
                'dean_review', 'ovpri_review' => 'pending',
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'info',
            };
        };
    @endphp

    <div class="mb-2">
        <h2 class="kmsar-h3" style="margin:0 0 4px 0;">{{ __('Submissions') }}</h2>
        <p class="kmsar-body" style="margin:0;font-size:13px;color:var(--color-text-muted);">{{ __('Click a card to open the full record.') }}</p>
    </div>

    @forelse ($research as $item)
        @php
            $statusLabel = str_replace('_', ' ', $item->status);
            $stageLabel = str_replace('_', ' ', $item->approval_stage);
            $leftBorder = $borderByStage($item->approval_stage);
        @endphp
        <div
            style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px 20px;margin-bottom:10px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;border-left:4px solid {{ $leftBorder }};"
        >
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:6px;">
                    <span style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:11px;font-weight:600;color:#D4AF37;letter-spacing:.06em;">{{ $item->reference_number }}</span>
                    @if ((int) $item->primary_author_id !== (int) auth()->id())
                        <x-badge status="info">{{ __('Co-author') }}</x-badge>
                    @endif
                    <x-badge :status="$researchProgressBadgeStatus($item->status)">{{ ucwords($statusLabel) }}</x-badge>
                    <x-badge :status="$approvalStageBadgeStatus($item->approval_stage)">{{ ucwords($stageLabel) }}</x-badge>
                </div>
                <div style="font-size:15px;font-weight:600;color:#0F172A;line-height:1.4;margin-bottom:6px;">{{ $item->title }}</div>
                <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#475569;">
                    <span>{{ ucwords(str_replace('_', ' ', $item->research_classification)) }}</span>
                    <span>{{ $item->start_date?->format('M Y') ?? '—' }}</span>
                    <span>{{ collect($item->expectedOutputKeys())->map(fn ($o) => $expectedLabels[$o] ?? ucwords(str_replace('_', ' ', (string) $o)))->implode(', ') ?: '—' }}</span>
                </div>
            </div>
            <div style="flex-shrink:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <a
                    href="{{ route('research.show', $item) }}"
                    style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1E3A8A;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;"
                    aria-label="{{ __('View research') }}"
                >{{ __('View') }} →</a>
                @if ($item->approval_stage === 'draft')
                    <form method="POST"
                          action="{{ route('research.destroy', $item) }}"
                          onsubmit="return confirm('Are you sure you want to delete this research? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="kmsar-btn kmsar-btn--danger-outline kmsar-btn--sm">
                            Delete
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:48px 24px;text-align:center;max-width:520px;margin:0 auto;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:48px;height:48px;margin:0 auto;color:#94A3B8;" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
            </svg>
            <p style="margin:16px 0 0;font-size:15px;font-weight:600;color:#0F172A;">{{ __('No research records yet') }}</p>
            <p style="margin:8px 0 0;font-size:13px;color:#64748B;line-height:1.5;">{{ __('Start the registration wizard to create your first submission.') }}</p>
            <div style="margin-top:20px;">
                <x-button variant="primary" href="{{ route('research.create') }}">{{ __('Register new research') }}</x-button>
            </div>
        </div>
    @endforelse

    @if ($research instanceof \Illuminate\Contracts\Pagination\Paginator && $research->hasPages())
        <div class="mt-6 flex justify-end">
            {{ $research->links() }}
        </div>
    @endif
@endsection
