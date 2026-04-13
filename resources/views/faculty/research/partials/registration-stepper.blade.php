{{--
  Alpine: mobile toggle for horizontal step list; links navigate between wizard routes.
  @var int $currentStep 1–3
  @var \App\Models\Research|null $research
--}}
@php
    $urls = [
        1 => $research ? route('research.wizard.details', $research) : null,
        2 => $research ? route('research.wizard.authors', $research) : null,
        3 => $research ? route('research.wizard.documents', $research) : null,
    ];
    $labels = [1 => __('Details'), 2 => __('Authors'), 3 => __('Documents')];
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
                $state = $step < $currentStep ? 'done' : ($step === $currentStep ? 'active' : 'pending');
                $href = $urls[$step];
            @endphp
            @if ($step > 1)
                <div
                    class="kmsar-step-connector {{ $step <= $currentStep ? 'kmsar-step-connector--done' : '' }}"
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
                <div class="kmsar-step kmsar-step--{{ $state }} opacity-60 cursor-not-allowed min-w-[5.5rem]">
                    <span class="kmsar-step-num">{{ $step }}</span>
                    <span class="kmsar-step-label">{{ $labels[$step] }}</span>
                </div>
            @endif
        @endforeach
    </nav>
</div>
