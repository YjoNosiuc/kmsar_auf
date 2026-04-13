@extends('layouts.app')

@section('title', __('All research'))

@section('navbar-context')
    {{ __('OVPRI') }}
@endsection

@section('content')
    <x-page-header
        :title="__('All research')"
        :subtitle="__('Institutional research register (OVPRI)')"
        :breadcrumb="[
            ['label' => __('All research')],
        ]"
    />

    <div class="mt-6">
        {{ $research->links() }}
    </div>
@endsection
