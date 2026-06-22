<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="theme-color" content="#071427">
        <link rel="manifest" href="/manifest.webmanifest">
        <title>{{ __('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-shell">
            <aside class="auth-aside"><a class="landing-brand" href="{{ route('landing') }}"><x-brand-logo /><span>{{ app(\App\Support\DocumentSettings::class)->all()['company_name'] }}</span></a><h1>{{ __('auth.welcome') }}</h1><p>{{ __('auth.subtitle') }}</p><strong>{{ __('auth.secure') }}</strong></aside>
            <section class="auth-main"><div class="auth-toolbar"><a href="{{ route('landing') }}">{{ __('auth.back_home') }}</a><a class="language-switch" href="{{ request()->fullUrlWithQuery(['lang' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}">{{ app()->getLocale() === 'ar' ? __('language.english') : __('language.arabic') }}</a></div><div class="auth-card">{{ $slot }}</div></section>
        </main>
    </body>
</html>
