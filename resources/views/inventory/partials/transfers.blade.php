<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.stock_transfers') }}</h2>
        <span class="pill tone-amber">{{ $transfers->count() }} {{ __('common.transfers') }}</span>
    </div>
    <div class="table">
        @forelse ($transfers as $transfer)
            <div class="row po-row">
                <span class="strong">{{ $transfer->transfer_number }}</span>
                <div>
                    <span>{{ $transfer->from_branch_name }} {{ __('common.to') }} {{ $transfer->to_branch_name }}</span>
                    <span class="sub">{{ __('common.shipped') }} {{ $transfer->shipped_at ?: __('common.not_yet') }}</span>
                </div>
                <span class="status tone-amber">{{ str_replace('_', ' ', $transfer->status) }}</span>
                <span>{{ $transfer->received_at ?: __('status.pending') }}</span>
                <span class="sub">{{ __('inventory.branch_stock_movement') }}</span>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_stock_transfers') }}</div>
        @endforelse
    </div>
</section>
