@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $targetLocale = $isRtl ? 'en' : 'ar';
    $activeNav = $activeNav ?? request()->segment(1, 'erp');
    $activeNav = match ($activeNav) {
        '', 'erp' => request()->route('page') ?: 'dashboard',
        'sales' => 'sales-pos',
        'optical-orders' => 'optical-orders',
        default => $activeNav,
    };
    $navItems = config('erp.navigation');
    $pageTitle = trim($__env->yieldContent('title')) ?: __('app.title');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#071427">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $pageTitle }} - {{ __('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="erp-shell">
    <div class="app-frame">
        <aside class="sidebar" aria-label="{{ __('nav.main_menu') }}">
            <a class="brand-block" href="{{ route('erp.dashboard', ['lang' => $locale]) }}">
                <x-brand-logo />
                <span>
                    <strong>{{ app(\App\Support\DocumentSettings::class)->all(request()->integer('branch_id') ?: null)['company_name'] }}</strong>
                    <small>{{ __('app.tagline') }}</small>
                </span>
            </a>

            <nav class="main-nav">
                @foreach ($navItems as $item)
                    @continue(isset($item['permission']) && ! auth()->user()->hasPermission($item['permission']))
                    @php
                        $page = $item['page'] ?? 'dashboard';
                        $href = $item['route'] === 'erp.dashboard'
                            ? route('erp.dashboard', ['lang' => $locale])
                            : route($item['route'], ['page' => $page, 'lang' => $locale]);
                        $isActive = $activeNav === $page || ($page === 'dashboard' && $activeNav === 'dashboard');
                    @endphp
                    <a class="{{ $isActive ? 'active' : '' }}" href="{{ $href }}">
                        <x-icon :name="$item['icon'] ?? 'grid'" />
                        <span>{{ __($item['label_key']) }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <button class="topbar-icon mobile-menu" type="button" data-menu-toggle aria-label="{{ __('common.menu') }}">
                    <x-icon name="menu" />
                </button>
                <form class="global-search" method="GET" action="{{ route('erp.app', ['page' => 'search']) }}">
                    <x-icon name="search" />
                    <input name="q" value="{{ request('q') }}" placeholder="{{ __('search.placeholder') }}" autocomplete="off">
                    <input type="hidden" name="lang" value="{{ $locale }}">
                    <button type="submit">{{ __('common.search') }}</button>
                </form>

                <div class="topbar-actions">
                    <span class="connection-pill"><span data-connection-status data-online-label="{{ __('offline.online') }}" data-offline-label="{{ __('offline.offline') }}">{{ __('offline.online') }}</span><span class="offline-count" data-offline-count hidden>0</span></span>
                    @isset($branches)
                        <form method="GET" action="{{ url()->current() }}" class="branch-picker">
                            @foreach (request()->except(['branch_id', 'page']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <select name="branch_id" onchange="this.form.submit()" aria-label="{{ __('common.branch') }}">
                                <option value="">{{ __('common.all_branches') }}</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) request('branch_id', $branchId ?? $selectedBranch ?? '') === (string) $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endisset

                    <a class="language-switch" href="{{ request()->fullUrlWithQuery(['lang' => $targetLocale]) }}">
                        {{ $isRtl ? __('language.english') : __('language.arabic') }}
                    </a>

                    <a class="topbar-icon" href="{{ route('erp.app', ['page' => 'whatsapp', 'lang' => $locale]) }}" title="{{ __('common.notifications') }}" aria-label="{{ __('common.notifications') }}">
                        <x-icon name="bell" />
                    </a>

                    <a class="user-profile" href="{{ route('profile.edit', ['lang' => $locale]) }}">
                        <span class="user-avatar"><x-icon name="user" /></span>
                        <span>
                            <strong>{{ auth()->user()?->name ?? __('common.store_user') }}</strong>
                            <small>{{ __('common.profile') }}</small>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" data-clear-private-cache>@csrf<button class="button button-ghost" type="submit">{{ __('Log Out') }}</button></form>
                </div>
            </header>

            <main class="page">
                @if (session('status'))
                    <div class="notice notice-success">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="notice notice-error">
                        <strong>{{ __('common.error') }}</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <div class="loading-overlay" data-loading-overlay>
        <div class="loader"></div>
        <span>{{ __('common.loading') }}</span>
    </div>

    @stack('scripts')
</body>
</html>
