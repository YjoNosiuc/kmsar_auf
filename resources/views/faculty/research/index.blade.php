@extends('layouts.app')

@section('title', __('My research'))

@section('navbar-context')
    {{ __('Faculty') }}
@endsection

@section('content')
    @php
        use App\Support\ResearchStatus;

        $borderByStatus = static fn (string $status): string => match ($status) {
            ResearchStatus::DRAFT => '#94A3B8',
            ResearchStatus::INITIAL_DEAN_REVIEW, ResearchStatus::FINAL_DEAN_REVIEW => '#D4AF37',
            ResearchStatus::INITIAL_OVPRI_REVIEW, ResearchStatus::FINAL_OVPRI_REVIEW => '#2563EB',
            ResearchStatus::RESEARCH_REGISTERED, ResearchStatus::RESEARCH_ACCEPTED => '#059669',
            ResearchStatus::INITIAL_REJECTED, ResearchStatus::FINAL_REJECTED => '#DC2626',
            ResearchStatus::RESEARCH_COMPLETED => '#2563EB',
            default => '#94A3B8',
        };

        $statusBadgeVariant = static function (string $status): string {
            return match ($status) {
                ResearchStatus::DRAFT => 'draft',
                ResearchStatus::INITIAL_DEAN_REVIEW, ResearchStatus::FINAL_DEAN_REVIEW => 'pending',
                ResearchStatus::INITIAL_OVPRI_REVIEW, ResearchStatus::FINAL_OVPRI_REVIEW => 'info',
                ResearchStatus::RESEARCH_REGISTERED, ResearchStatus::RESEARCH_ACCEPTED => 'approved',
                ResearchStatus::INITIAL_REJECTED, ResearchStatus::FINAL_REJECTED => 'rejected',
                ResearchStatus::RESEARCH_COMPLETED => 'info',
                default => 'info',
            };
        };

        $statusOptions = ResearchStatus::facultyFilterOptions();

        $expectedLabels = [
            'publication' => __('Publication'),
            'patent' => __('Patent'),
            'policy_brief' => __('Policy brief'),
            'other' => __('Other'),
        ];
    @endphp

    <x-page-header
        :title="__('My research')"
        :subtitle="__('Registered research records you own or co-author')"
        :breadcrumb="[
            ['label' => __('My Research')],
        ]"
    >
        @unless(auth()->user()->hasRole('viewer'))
            <form method="POST" action="{{ route('research.begin') }}" style="display:inline;">
                @csrf
                <input type="hidden" name="registration_type" value="new">
                <x-button type="submit" variant="primary">{{ __('Register new research') }}</x-button>
            </form>
            <form method="POST" action="{{ route('research.begin') }}" style="display:inline;">
                @csrf
                <input type="hidden" name="registration_type" value="existing">
                <x-button type="submit" variant="outline">{{ __('Register existing research') }}</x-button>
            </form>
        @endunless
    </x-page-header>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-6" />
    @endif

    @php
        $activeFilters = $filters ?? ['search' => '', 'status' => ''];
        $hasActiveFilters = filled($activeFilters['search'] ?? '')
            || filled($activeFilters['status'] ?? '');
    @endphp

    <div>
        <form method="GET" action="{{ route('research.index') }}" class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="kmsar-form-group" style="margin:0;flex:1 1 220px;min-width:200px;">
                <label class="kmsar-form-label" for="faculty-research-search">{{ __('Search') }}</label>
                <input
                    id="faculty-research-search"
                    type="search"
                    name="search"
                    class="kmsar-input"
                    placeholder="{{ __('Search by title or reference…') }}"
                    value="{{ $activeFilters['search'] ?? '' }}"
                    autocomplete="off"
                >
            </div>
            <div class="kmsar-form-group" style="margin:0;flex:0 1 260px;min-width:200px;">
                <label class="kmsar-form-label" for="faculty-research-status">{{ __('Workflow status') }}</label>
                <select id="faculty-research-status" name="status" class="kmsar-select" onchange="this.form.submit()">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($activeFilters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--sm">{{ __('Apply') }}</button>
                @if ($hasActiveFilters)
                    <a href="{{ route('research.index') }}" class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>

        <div class="mb-2">
            <h2 class="kmsar-h3" style="margin:0 0 4px 0;">{{ __('Submissions') }}</h2>
            <p class="kmsar-body" style="margin:0;font-size:13px;color:var(--color-text-muted);">{{ __('Click a card to open the full record.') }}</p>
        </div>

        @forelse ($research as $item)
            @php
                $statusLabel = ResearchStatus::label($item->status);
                $leftBorder = $borderByStatus((string) $item->status);
            @endphp
            <div
                class="kmsar-research-card"
                style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px 20px;margin-bottom:10px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;border-left:4px solid {{ $leftBorder }};"
            >
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:6px;">
                        <span style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:11px;font-weight:600;color:#D4AF37;letter-spacing:.06em;">{{ $item->reference_number }}</span>
                        @if ((int) $item->primary_author_id !== (int) auth()->id())
                            <x-badge status="info">{{ __('Co-author') }}</x-badge>
                        @endif
                        <x-badge :status="$statusBadgeVariant((string) $item->status)">{{ $statusLabel }}</x-badge>
                    </div>
                    <div style="font-size:15px;font-weight:600;color:#0F172A;line-height:1.4;margin-bottom:6px;">{{ $item->title }}</div>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#475569;">
                        <span>{{ ucwords(str_replace('_', ' ', $item->research_classification)) }}</span>
                        <span>{{ $item->start_date?->format('M Y') ?? '—' }}</span>
                        <span>{{ collect($item->expectedOutputKeys())->map(fn ($o) => $expectedLabels[$o] ?? ucwords(str_replace('_', ' ', (string) $o)))->implode(', ') ?: '—' }}</span>
                    </div>
                </div>
                <div class="kmsar-research-card-actions" style="flex-shrink:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <a
                        href="{{ route('research.show', $item) }}"
                        style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1E3A8A;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;"
                        aria-label="{{ __('View research') }}"
                    >{{ __('View') }} →</a>
                    @if (ResearchStatus::isPreSubmission((string) $item->status) && (int) $item->primary_author_id === (int) auth()->id())
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
            @if ($hasActiveFilters)
                <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:24px;text-align:center;color:#64748B;font-size:13px;">
                    {{ __('No results found') }}
                </div>
            @else
                <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:48px 24px;text-align:center;max-width:520px;margin:0 auto;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:48px;height:48px;margin:0 auto;color:#94A3B8;" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    <p style="margin:16px 0 0;font-size:15px;font-weight:600;color:#0F172A;">{{ __('No research records yet') }}</p>
                    <p style="margin:8px 0 0;font-size:13px;color:#64748B;line-height:1.5;">{{ __('Start the registration wizard to create your first submission.') }}</p>
                    @unless(auth()->user()->hasRole('viewer'))
                        <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
                            <form method="POST" action="{{ route('research.begin') }}" style="display:inline;">
                                @csrf
                                <input type="hidden" name="registration_type" value="new">
                                <x-button type="submit" variant="primary">{{ __('Register new research') }}</x-button>
                            </form>
                            <form method="POST" action="{{ route('research.begin') }}" style="display:inline;">
                                @csrf
                                <input type="hidden" name="registration_type" value="existing">
                                <x-button type="submit" variant="outline">{{ __('Register existing research') }}</x-button>
                            </form>
                        </div>
                    @endunless
                </div>
            @endif
        @endforelse
    </div>

    @if ($research instanceof \Illuminate\Contracts\Pagination\Paginator && $research->hasPages())
        <div class="mt-6 flex justify-end">
            {{ $research->links() }}
        </div>
    @endif
@endsection
