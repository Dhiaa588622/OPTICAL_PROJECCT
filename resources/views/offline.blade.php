<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#071427"><title>{{ __('offline.page_title') }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="offline-shell"><main class="offline-card"><span class="brand-mark">O</span><h1>{{ __('offline.page_title') }}</h1><p>{{ __('offline.page_message') }}</p><a class="button" href="{{ route('landing') }}">{{ __('auth.back_home') }}</a></main></body>
</html>
