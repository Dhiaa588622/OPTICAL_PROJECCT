<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.purchase_orders') }}</h2>
        <span class="pill tone-blue">{{ trans_choice('common.records', $purchaseOrders->count(), ['count' => $purchaseOrders->count()]) }}</span>
    </div>
    <div class="table">
        @forelse ($purchaseOrders as $po)
            <div class="row po-row">
                <span class="strong">{{ $po->po_number }}</span>
                <div>
                    <span>{{ $po->supplier_name }}</span>
                    <span class="sub">{{ $po->branch_name ?? __('common.all_branches') }}</span>
                </div>
                <span class="status tone-blue">{{ str_replace('_', ' ', $po->status) }}</span>
                <span>{{ $po->expected_on ?: '-' }}</span>
                <span class="strong">SAR {{ number_format($po->grand_total, 2) }}</span>
                <a class="pill tone-blue" href="{{ route('documents.purchase-orders.show', ['order' => $po->id, 'lang' => app()->getLocale()]) }}">{{ __('common.print') }}</a>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_purchase_orders') }}</div>
        @endforelse
    </div>
</section>
