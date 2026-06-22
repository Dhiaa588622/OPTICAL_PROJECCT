@props([
    'title' => null,
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <div class="empty-state__icon"><x-icon name="search" /></div>
    <strong>{{ $title ?: __('common.no_data') }}</strong>
    <p>{{ $message ?: __('common.empty_hint') }}</p>
</div>
