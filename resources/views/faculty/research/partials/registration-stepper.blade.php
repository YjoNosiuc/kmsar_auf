{{--
  Alpine: mobile toggle for horizontal step list.
  Locked steps are non-clickable until previous steps are complete.
  @var int $currentStep 1–3
  @var \App\Models\Research|null $research
  @var bool $step1Complete
  @var bool $step2Complete
--}}
@php
    $step1Complete = (bool) ($step1Complete ?? false);
    $step2Complete = (bool) ($step2Complete ?? false);
    $urls = [
        1 => $research ? route('research.wizard.details', $research) : null,
        2 => $research && $step1Complete ? route('research.wizard.authors', $research) : null,
        3 => $research && $step1Complete && $step2Complete ? route('research.wizard.documents', $research) : null,
    ];
    $labels = [1 => __('Details'), 2 => __('Authors'), 3 => __('Documents')];
    $lockTitles = [
        2 => __('Complete Step 1 first'),
        3 => ! $step1Complete ? __('Complete Step 1 first') : __('Complete Step 2 first'),
    ];
    $stepCount = 3;
@endphp

<div
    class="mb-6"
    x-data="{ current: {{ (int) $currentStep }}, mobileOpen: false }"
>
    <div class="flex items-center justify-between gap-2 md:hidden mb-3">
        <button
            type="button"
            class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm"
            @click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen"
            aria-controls="kmsar-reg-stepper-nav"
        >
            {{ __('Steps') }} (<span x-text="current"></span>/{{ $stepCount }})
        </button>
    </div>

    <nav
        id="kmsar-reg-stepper-nav"
        class="kmsar-stepper flex-wrap overflow-x-auto pb-1"
        :class="{ 'hidden md:flex': !mobileOpen, 'flex': mobileOpen }"
        aria-label="{{ __('Research registration steps') }}"
    >
        @foreach (range(1, $stepCount) as $step)
            @php
                $completed = ($step === 1 && $step1Complete) || ($step === 2 && $step2Complete);
                $state = $step === $currentStep ? 'active' : ($completed || $step < $currentStep ? 'done' : 'pending');
                $href = $urls[$step];
            @endphp
            @if ($step > 1)
                <div
                    class="kmsar-step-connector {{ ($step === 2 && $step1Complete) || ($step === 3 && $step2Complete) ? 'kmsar-step-connector--done' : '' }}"
                    aria-hidden="true"
                ></div>
            @endif
            @if ($href)
                <a
                    href="{{ $href }}"
                    class="kmsar-step kmsar-step--{{ $state }} no-underline text-inherit min-w-[5.5rem]"
                    @if ($step === $currentStep) aria-current="step" @endif
                >
                    <span class="kmsar-step-num">{{ $step }}</span>
                    <span class="kmsar-step-label">{{ $labels[$step] }}</span>
                </a>
            @else
                <span
                    class="kmsar-step kmsar-step--pending locked min-w-[5.5rem]"
                    title="{{ $lockTitles[$step] ?? __('Complete the previous step first') }}"
                    aria-disabled="true"
                >
                    <span class="kmsar-step-num" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="14" height="14" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </span>
                    <span class="kmsar-step-label">{{ $labels[$step] }}</span>
                </span>
            @endif
        @endforeach
    </nav>
</div>
