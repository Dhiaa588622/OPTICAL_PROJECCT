<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.low_stock_alerts') }}</h2>
        <span class="pill tone-red">{{ $lowStockAlerts->count() }} {{ __('common.items') }}</span>
    </div>
    <div class="list">
        @forelse ($lowStockAlerts as $alert)
            <div class="list-row">
                <div>
                    <span class="strong">{{ $alert->name }}</span>
                    <span class="sub">{{ $alert->sku }} {{ __('common.at') }} {{ $alert->branch_name }} / {{ __('inventory.reorder') }} {{ number_format($alert->reorder_point, 3) }}</span>
                </div>
                <span class="pill tone-red">{{ number_format($alert->available_stock, 3) }} {{ __('common.available') }}</span>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_low_stock') }}</div>
        @endforelse
    </div>
</section>
