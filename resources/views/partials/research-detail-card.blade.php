@php
    $classificationLabels = [
        'self_funded' => __('Self-funded'),
        'internally_funded' => __('Internally funded'),
        'externally_funded' => __('Externally funded'),
        'thesis' => __('Thesis / dissertation'),
        'collaboration' => __('Collaboration'),
        'other' => __('Other'),
    ];
    $expectedLabels = [
        'publication' => __('Publication'),
        'patent' => __('Patent'),
        'policy_brief' => __('Policy brief'),
        'other' => __('Other'),
    ];
    $registrationLabel = match ($research->registration_type) {
        'new' => __('New Research'),
        'update' => __('Update Existing'),
        default => ucwords(str_replace('_', ' ', (string) $research->registration_type)),
    };
    $classificationLabel = $classificationLabels[$research->research_classification] ?? ucwords(str_replace('_', ' ', (string) $research->research_classification));
    $statusLabel = ucwords(str_replace('_', ' ', (string) $research->status));
    $expectedKeys = $research->expectedOutputKeys();
    $expectedDisplay = collect($expectedKeys)->map(fn ($o) => $expectedLabels[$o] ?? ucwords(str_replace('_', ' ', (string) $o)))->implode(', ');
    if ($expectedDisplay === '') {
        $expectedDisplay = '—';
    }
    if (in_array('other', $expectedKeys, true) && $research->expected_output_other) {
        $expectedDisplay .= ' — '.$research->expected_output_other;
    }
    $progressBadge = match ($research->status) {
        'published_scopus', 'published_non_indexed', 'presented_external', 'presented_internal', 'completed_unpublished' => 'approved',
        'proposal', 'ongoing' => 'pending',
        'patent_submitted', 'patent_granted' => 'info',
        default => 'draft',
    };
    $externalPrimary = $research->researchAuthors->where('is_primary', true)->whereNull('user_id')->first();
@endphp

{{-- Tab: Research info — shared by faculty, dean, OVPRI --}}
<div x-show="tab === 'info'" class="kmsar-card kmsar-card--accent-primary" role="tabpanel">
    <div class="kmsar-card-body" style="padding:var(--space-5);">
        <h3 class="kmsar-card-title" style="margin-bottom:var(--space-4);">{{ __('Research details') }}</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div style="grid-column:1/-1;">
                <div class="kmsar-label">{{ __('Title') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);font-weight:500;margin-top:0.25rem;">{{ $research->title }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Primary author') }}</div>
                @if ($externalPrimary)
                    <div style="font-size:14px;font-weight:600;color:#0F172A;margin-top:0.25rem;">{{ $externalPrimary->name }}</div>
                    @if ($externalPrimary->employee_number)
                        <div style="font-size:12px;color:#475569;">{{ $externalPrimary->employee_number }}</div>
                    @endif
                    @if ($externalPrimary->college_text)
                        <div style="font-size:12px;color:#475569;">{{ $externalPrimary->college_text }}</div>
                    @endif
                    @if ($externalPrimary->program)
                        <div style="font-size:12px;color:#475569;">{{ $externalPrimary->program }}</div>
                    @endif
                    @if ($externalPrimary->email)
                        <div style="font-size:12px;color:#94A3B8;">{{ $externalPrimary->email }}</div>
                    @endif
                    <div style="font-size:11px;color:#D97706;margin-top:4px;font-weight:600;">{{ __('Registered by:') }} {{ $research->primaryAuthor?->name }}</div>
                @else
                    <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $research->primaryAuthor?->name ?? '—' }}</div>
                @endif
            </div>
            <div>
                <div class="kmsar-label">{{ __('Mother college') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $research->motherCollege?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Other affiliated college') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $research->otherCollege?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Registration type') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $registrationLabel }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Research classification') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $classificationLabel }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Funding agency') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $research->funding_agency ?: '—' }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Expected output') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $expectedDisplay }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Start date') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $research->start_date?->format('M d, Y') ?? '—' }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Est. completion date') }}</div>
                <div style="font-size:14px;color:var(--color-text-primary);margin-top:0.25rem;">{{ $research->estimated_completion_date?->format('M d, Y') ?? '—' }}</div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Progress status') }}</div>
                <div style="margin-top:0.35rem;">
                    <span class="kmsar-badge kmsar-badge--{{ $progressBadge }} kmsar-badge--square">{{ $statusLabel }}</span>
                </div>
            </div>
            <div>
                <div class="kmsar-label">{{ __('Scopus indexed') }}</div>
                <div style="margin-top:0.35rem;">
                    @if ($research->is_scopus_indexed)
                        <span class="kmsar-badge kmsar-badge--approved kmsar-badge--square">{{ __('Yes') }}</span>
                    @else
                        <span class="kmsar-badge kmsar-badge--draft kmsar-badge--square">{{ __('No') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="border-top: 1px solid var(--color-border);
            padding-top: 1.25rem; margin-top: 1.25rem;">
            <div class="kmsar-label" style="margin-bottom: 0.75rem;">
                {{ __('Authors') }}
            </div>

            {{-- Primary Author --}}
            <div style="display: flex; align-items: center;
                justify-content: space-between;
                padding: 0.75rem 1rem;
                background: var(--color-primary-muted);
                border: 1px solid var(--color-revision-border);
                border-radius: var(--radius-md);
                margin-bottom: 0.5rem;">
                <div>
                    <div style="font-size: var(--text-sm);
                        font-weight: 600;
                        color: var(--color-text-primary);">
                        @if ($research->primaryAuthor)
                            {{ $research->primaryAuthor->first_name ?? '' }}
                            {{ $research->primaryAuthor->last_name ?? '' }}
                            @if (! $research->primaryAuthor->first_name)
                                {{ $research->primaryAuthor->name }}
                            @endif
                        @else
                            —
                        @endif
                    </div>
                    <div style="font-size: var(--text-xs);
                        color: var(--color-text-muted);
                        margin-top: 2px;">
                        {{ $research->primaryAuthor?->college?->code ?? '' }}
                        @if ($research->primaryAuthor?->college?->code) · @endif
                        {{ __('Primary Author') }}
                    </div>
                </div>
                <span class="kmsar-badge kmsar-badge--info">
                    {{ __('Primary') }}
                </span>
            </div>

            {{-- Co-authors --}}
            @if ($research->researchAuthors &&
                $research->researchAuthors->where('is_primary', false)->count() > 0)
                @foreach ($research->researchAuthors->where('is_primary', false) as $author)
                    <div style="display: flex; align-items: center;
                        justify-content: space-between;
                        padding: 0.75rem 1rem;
                        background: var(--color-card);
                        border: 1px solid var(--color-border);
                        border-radius: var(--radius-md);
                        margin-bottom: 0.5rem;">
                        <div>
                            <div style="font-size: var(--text-sm);
                                font-weight: 500;
                                color: var(--color-text-primary);">
                                @if ($author->first_name)
                                    {{ $author->first_name }}
                                    {{ $author->middle_name ? $author->middle_name.' ' : '' }}
                                    {{ $author->last_name }}
                                    {{ $author->suffix ? ', '.$author->suffix : '' }}
                                @else
                                    {{ $author->name ?? '—' }}
                                @endif
                            </div>
                            <div style="font-size: var(--text-xs);
                                color: var(--color-text-muted);
                                margin-top: 2px;">
                                @if ($author->author_type === 'student')
                                    {{ __('Student') }}
                                    @if ($author->college)
                                        · {{ $author->college->code }}
                                    @endif
                                    @if ($author->program)
                                        · {{ $author->program->name ?? $author->program }}
                                    @endif
                                @else
                                    {{ __('Employee / Researcher') }}
                                    @if ($author->college)
                                        · {{ $author->college->code }}
                                    @endif
                                    @if ($author->institution)
                                        · {{ $author->institution }}
                                    @endif
                                @endif
                            </div>
                        </div>
                        <span class="kmsar-badge kmsar-badge--draft">
                            {{ __('Co-author') }}
                        </span>
                    </div>
                @endforeach
            @else
                <p class="kmsar-hint">{{ __('No co-authors added.') }}</p>
            @endif
        </div>

        <div class="kmsar-form-group" style="margin-top: 1.5rem;">
            <div class="kmsar-label" style="margin-bottom: 0.5rem;">
                {{ __('SDG Alignment') }}
            </div>
            @if (! empty($research->sdg_tags) && count($research->sdg_tags) > 0)
                <div class="kmsar-sdg-grid">
                    @foreach ($research->sdg_tags as $sdg)
                        <span class="kmsar-badge kmsar-badge--solid-primary
                            kmsar-badge--square">
                            {{ $sdg }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="kmsar-hint">{{ __('No SDG tags selected.') }}</p>
            @endif
        </div>
    </div>
</div>
