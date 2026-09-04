@php
    try {
        $loginUrl = route('login', ['expired' => 1]);
    } catch (\Throwable $e) {
        $loginUrl = url('/login?expired=1');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ $loginUrl }}">
    <title>{{ __('Redirecting…') }} — KMSAR</title>
    <script>window.location.replace(@json($loginUrl));</script>
</head>
<body>
    <p>{{ __('Your session expired. Redirecting to sign in…') }}</p>
    <p><a href="{{ $loginUrl }}">{{ __('Continue to sign in') }}</a></p>
</body>
</html>
