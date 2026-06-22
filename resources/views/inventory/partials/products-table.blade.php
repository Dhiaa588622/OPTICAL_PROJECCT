<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.product_list') }}</h2>
        <span class="pill tone-teal">{{ $products->count() }} {{ __('common.shown') }}</span>
    </div>
    <div class="table">
        @forelse ($products as $product)
            <div class="row product-row">
                <div>
                    <span class="strong">{{ $product->name }}</span>
                    <span class="sub">{{ $product->sku }} / {{ $product->barcode ?: __('inventory.no_barcode') }}</span>
                </div>
                <span>{{ $product->category_name ?? $product->type }}</span>
                <span>{{ $product->brand ?: '-' }}</span>
                <span>
                    <span class="strong">{{ number_format($product->qty_on_hand - $product->qty_reserved, 3) }}</span>
                    <span class="sub">{{ number_format($product->qty_on_hand, 3) }} {{ __('inventory.on_hand') }}, {{ number_format($product->qty_reserved, 3) }} {{ __('inventory.reserved') }}</span>
                </span>
                <span class="strong">SAR {{ number_format($product->retail_price, 2) }}</span>
                <a class="link-button secondary" href="{{ route('inventory.app', ['page' => 'product-details', 'product_id' => $product->id] + $queryParams) }}">{{ __('common.open') }}</a>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_matching_products') }}</div>
        @endforelse
    </div>
</section>
