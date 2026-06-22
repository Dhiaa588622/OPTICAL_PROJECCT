<section class="section">
    <div class="section-header">
        <h2>{{ __('inventory.stock_counts') }}</h2>
        <span class="pill tone-teal">{{ $stockCounts->count() }} {{ __('common.counts') }}</span>
    </div>
    <div class="table">
        @forelse ($stockCounts as $count)
            <div class="row po-row">
                <span class="strong">{{ $count->count_number }}</span>
                <div>
                    <span>{{ $count->branch_name }}</span>
                    <span class="sub">{{ $count->location_name ?: __('common.all_locations') }}</span>
                </div>
                <span class="status tone-teal">{{ str_replace('_', ' ', $count->status) }}</span>
                <span>{{ $count->count_date }}</span>
                <span class="sub">{{ __('inventory.variance_review') }}</span>
            </div>
        @empty
            <div class="empty">{{ __('inventory.no_stock_counts') }}</div>
        @endforelse
    </div>
</section>
