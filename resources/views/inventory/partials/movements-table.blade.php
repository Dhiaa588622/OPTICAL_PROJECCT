<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.stock_movements') }}</h2>
        <span class="pill tone-blue">{{ __('inventory.ledger_history') }}</span>
    </div>
    <div class="table">
        @forelse ($movements as $movement)
            <div class="row movement-row">
                <div>
                    <span class="strong">{{ \Illuminate\Support\Carbon::parse($movement->occurred_at)->format('M d, H:i') }}</span>
                    <span class="sub">{{ $movement->branch_name }}</span>
                </div>
                <div>
                    <span class="strong">{{ $movement->product_name }}</span>
                    <span class="sub">{{ $movement->sku }} / {{ str_replace('_', ' ', $movement->movement_type) }}</span>
                </div>
                <span class="status {{ $movement->direction === 'in' ? 'tone-green' : 'tone-amber' }}">{{ $movement->direction }}</span>
                <span class="strong">{{ number_format($movement->quantity, 3) }}</span>
                <span class="sub">{{ $movement->reference_type ?: __('common.manual') }}</span>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_stock_movements') }}</div>
        @endforelse
    </div>
</section>
