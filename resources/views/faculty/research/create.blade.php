@extends('layouts.app')

@section('title', __('Register research'))

@section('navbar-context')
    {{ __('Faculty') }}
@endsection

@section('content')
    <x-page-header
        :title="__('Register research')"
        :subtitle="__('Choose how you want to register your research. A draft is created only after you confirm below.')"
        :breadcrumb="[
            ['label' => __('My Research'), 'url' => route('research.index')],
            ['label' => __('Register research')],
        ]"
    />

    <div class="kmsar-card" style="max-width:640px;">
        <div class="kmsar-card-body" style="display:grid;gap:16px;">
            <div>
                <h2 class="kmsar-card-title">{{ __('New research') }}</h2>
                <p class="kmsar-body mt-1" style="font-size:0.875rem;color:var(--color-text-secondary);">
                    {{ __('Register a new research project that is starting from proposal or early planning.') }}
                </p>
                <form method="POST" action="{{ route('research.begin') }}" style="margin-top:12px;">
                    @csrf
                    <input type="hidden" name="registration_type" value="new">
                    <x-button type="submit" variant="primary">{{ __('Register new research') }}</x-button>
                </form>
            </div>

            <div style="border-top:1px solid var(--color-border);padding-top:16px;">
                <h2 class="kmsar-card-title">{{ __('Existing research') }}</h2>
                <p class="kmsar-body mt-1" style="font-size:0.875rem;color:var(--color-text-secondary);">
                    {{ __('Register research that is already ongoing or completed before KMSAR onboarding.') }}
                </p>
                <form method="POST" action="{{ route('research.begin') }}" style="margin-top:12px;">
                    @csrf
                    <input type="hidden" name="registration_type" value="existing">
                    <x-button type="submit" variant="outline">{{ __('Register existing research') }}</x-button>
                </form>
            </div>
        </div>
    </div>
@endsection
