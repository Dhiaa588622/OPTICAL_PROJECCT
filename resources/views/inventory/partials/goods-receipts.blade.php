<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.goods_receipts') }}</h2>
        <span class="pill tone-green">{{ $goodsReceipts->count() }} {{ __('common.posted') }}</span>
    </div>
    <div class="table">
        @forelse ($goodsReceipts as $receipt)
            <div class="row po-row">
                <span class="strong">{{ $receipt->receipt_number }}</span>
                <div>
                    <span>{{ $receipt->supplier_name }}</span>
                    <span class="sub">{{ $receipt->branch_name }}</span>
                </div>
                <span class="status tone-green">{{ $receipt->status }}</span>
                <span>{{ $receipt->received_on }}</span>
                <span class="sub">{{ $receipt->supplier_invoice_number ?: __('inventory.no_supplier_invoice') }}</span>
                <a class="pill tone-blue" href="{{ route('documents.purchase-invoices.show', ['receipt' => $receipt->id, 'lang' => app()->getLocale()]) }}">{{ __('common.print') }}</a>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_goods_receipts') }}</div>
        @endforelse
    </div>
</section>
