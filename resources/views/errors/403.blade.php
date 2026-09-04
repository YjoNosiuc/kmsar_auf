@php
    use App\Support\FriendlyExceptionResponse;

    $redirectUrl = url('/login');

    try {
        if (auth()->check()) {
            $redirectUrl = FriendlyExceptionResponse::homeUrlFor(auth()->user());
        } else {
            $redirectUrl = route('login');
        }
    } catch (\Throwable $e) {
        $redirectUrl = url('/login');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ $redirectUrl }}">
    <title>{{ __('Redirecting…') }} — KMSAR</title>
    <script>window.location.replace(@json($redirectUrl));</script>
</head>
<body>
    <p>{{ __('You do not have access to that page. Redirecting…') }}</p>
    <p><a href="{{ $redirectUrl }}">{{ __('Continue') }}</a></p>
</body>
</html>
