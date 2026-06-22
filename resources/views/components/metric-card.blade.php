@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',
])

<article class="metric-card tone-{{ $tone }}">
    <span>{{ $label }}</span>
    <strong>{{ $value }}</strong>
    @if ($hint)
        <small>{{ $hint }}</small>
    @endif
</article>
