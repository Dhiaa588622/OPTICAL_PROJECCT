<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#071427">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ __('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-shell">
    <header class="landing-nav">
        <a class="landing-brand" href="{{ route('landing') }}"><x-brand-logo /><span>{{ app(\App\Support\DocumentSettings::class)->all()['company_name'] }}</span></a>
        <div class="landing-actions">
            <a class="language-switch" href="{{ request()->fullUrlWithQuery(['lang' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}">{{ app()->getLocale() === 'ar' ? __('language.english') : __('language.arabic') }}</a>
            <a class="button" href="{{ auth()->check() ? auth()->user()->homeUrl() : route('login') }}">{{ auth()->check() ? __('Dashboard') : __('landing.sign_in') }}</a>
        </div>
    </header>
    <main>
        <section class="landing-hero">
            <p class="eyebrow">{{ __('app.tagline') }}</p>
            <h1>{{ __('landing.title') }}</h1>
            <p>{{ __('landing.subtitle') }}</p>
            <div><a class="button" href="{{ auth()->check() ? auth()->user()->homeUrl() : route('login') }}">{{ __('landing.sign_in') }}</a></div>
        </section>
        <section class="landing-features">
            @foreach(['accounting', 'bilingual', 'offline'] as $feature)
                <article class="landing-feature"><h2>{{ __('landing.feature.'.$feature) }}</h2><p>{{ __('landing.feature.'.$feature.'_hint') }}</p></article>
            @endforeach
        </section>
    </main>
</body>
</html>
