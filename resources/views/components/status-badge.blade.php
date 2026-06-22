@props(['status' => null])

@php
    $rawStatus = (string) ($status ?: 'open');
    $statusKey = 'status.'.str_replace('-', '_', $rawStatus);
    $label = __($statusKey);
    if ($label === $statusKey) {
        $label = \Illuminate\Support\Str::of($rawStatus)->replace(['_', '-'], ' ')->title();
    }
    $tone = match ($rawStatus) {
        'paid', 'completed', 'delivered', 'ready_for_pickup', 'sent', 'active', 'posted' => 'success',
        'failed', 'cancelled', 'no_show', 'overdue', 'inactive' => 'danger',
        'partial', 'pending', 'draft', 'scheduled', 'open', 'in_lab', 'confirmed' => 'warning',
        default => 'neutral',
    };
@endphp

<span class="status-badge status-badge-{{ $tone }}">{{ $label }}</span>
