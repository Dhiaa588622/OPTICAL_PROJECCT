@props([
    'icon' => 'grid',
    'title',
    'description',
    'action',
    'href',
    'count' => null,
    'status' => null,
])

<article class="module-card">
    <div class="module-card__top">
        <div class="module-card__icon"><x-icon :name="$icon" /></div>
        @if ($count !== null || $status)
            <span class="module-card__meta">{{ $status ?: $count }}</span>
        @endif
    </div>
    <div class="module-card__body">
        <h3>{{ $title }}</h3>
        <p>{{ $description }}</p>
    </div>
    <a class="button button-ghost" href="{{ $href }}">
        <span>{{ $action }}</span>
        <x-icon name="arrow" />
    </a>
</article>
