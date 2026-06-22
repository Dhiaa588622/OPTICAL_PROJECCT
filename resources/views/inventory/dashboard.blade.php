@extends('layouts.erp', ['activeNav' => 'inventory'])

@section('title', __('nav.inventory'))

@php
    $currency = config('erp.module.currency', 'SAR');
    $money = fn ($amount) => $currency.' '.number_format((float) $amount, 2);
    $filters = array_filter(['branch_id' => $branchId ?? null, 'q' => $query ?? null, 'type' => $type ?? null]);
    $inventoryCards = [
        ['icon' => 'box', 'key' => 'products', 'page' => 'products', 'count' => $metrics['active_products'] ?? 0],
        ['icon' => 'inventory', 'key' => 'stock', 'page' => 'stock-movements', 'count' => $metrics['total_products'] ?? 0],
        ['icon' => 'orders', 'key' => 'purchase_orders', 'page' => 'purchase-orders', 'count' => $metrics['open_pos'] ?? 0],
        ['icon' => 'truck', 'key' => 'receiving', 'page' => 'goods-receiving', 'count' => $goodsReceipts->count() ?? 0],
        ['icon' => 'truck', 'key' => 'transfers', 'page' => 'stock-transfers', 'count' => $metrics['open_transfers'] ?? 0],
        ['icon' => 'configuration', 'key' => 'adjustments', 'page' => 'stock-movements', 'count' => null],
        ['icon' => 'reports', 'key' => 'low_stock', 'page' => 'low-stock-alerts', 'count' => $metrics['low_stock'] ?? 0],
        ['icon' => 'reports', 'key' => 'reports', 'page' => 'reports', 'count' => null],
    ];
@endphp

@section('content')
    @if (! $databaseReady)
        <section class="page-header"><div><p class="eyebrow">{{ __('nav.inventory') }}</p><h1>{{ __('setup.database_not_ready') }}</h1><p>{{ __('setup.run_migrations') }}</p></div></section>
        <x-empty-state :title="__('setup.title')" :message="__('setup.description')" />
    @else
        <section class="page-header">
            <div>
                <p class="eyebrow">{{ __('nav.inventory') }}</p>
                <h1>{{ __('inventory.page.'.$page) }}</h1>
                <p>{{ __('inventory.subtitle') }}</p>
            </div>
            <a class="button" href="{{ route('inventory.app', ['page' => 'product-create', 'lang' => app()->getLocale()]) }}"><x-icon name="plus" />{{ __('inventory.new_product') }}</a>
        </section>

        @if ($page === 'dashboard')
            <section class="metric-grid">
                <x-metric-card :label="__('inventory.stock_value')" :value="$money($metrics['stock_value'])" :hint="__('inventory.cost_value')" />
                <x-metric-card :label="__('inventory.active_products')" :value="$metrics['active_products']" :hint="__('inventory.skus')" />
                <x-metric-card :label="__('inventory.low_stock')" :value="$metrics['low_stock']" :hint="__('inventory.needs_attention')" />
                <x-metric-card :label="__('inventory.expiring')" :value="$metrics['expiring']" :hint="__('inventory.next_60_days')" />
            </section>

            <section class="section">
                <div class="section-header"><div><h2>{{ __('inventory.workspace') }}</h2><p>{{ __('inventory.workspace_hint') }}</p></div></div>
                <div class="module-grid">
                    @foreach ($inventoryCards as $card)
                        <x-module-card
                            :icon="$card['icon']"
                            :title="__('inventory.card.'.$card['key'].'.title')"
                            :description="__('inventory.card.'.$card['key'].'.description')"
                            :action="__('common.open')"
                            :href="route('inventory.app', ['page' => $card['page'], 'lang' => app()->getLocale()] + $filters)"
                            :count="$card['count']"
                        />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($page === 'products')
            <form class="filterbar" method="GET" action="{{ route('inventory.app', ['page' => 'products']) }}">
                <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                <input name="q" value="{{ $query }}" type="search" placeholder="{{ __('inventory.search_products') }}">
                <select name="type"><option value="">{{ __('inventory.all_types') }}</option>@foreach($productTypes as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select>
                <button class="button button-secondary" type="submit">{{ __('common.search') }}</button>
            </form>
            <section class="table-card">
                <div class="section-header panel"><div><h2>{{ __('inventory.product_list') }}</h2><p>{{ trans_choice('common.records', $products->count(), ['count' => $products->count()]) }}</p></div></div>
                <div class="table-scroll"><table>
                    <thead><tr><th>{{ __('common.product') }}</th><th>{{ __('common.category') }}</th><th>{{ __('common.type') }}</th><th>{{ __('inventory.available') }}</th><th>{{ __('common.price') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                    <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td><a href="{{ route('inventory.app', ['page' => 'product-details', 'product_id' => $product->id, 'lang' => app()->getLocale()]) }}"><strong>{{ $product->name }}</strong></a><br><span class="table-meta">{{ $product->sku }} / {{ $product->barcode }}</span></td>
                            <td>{{ $product->category_name ?: __('common.not_set') }}</td>
                            <td>{{ $productTypes[$product->type] ?? $product->type }}</td>
                            <td>{{ number_format($product->qty_on_hand - $product->qty_reserved, 2) }}</td>
                            <td>{{ $money($product->retail_price) }}</td>
                            <td><x-status-badge :status="$product->is_active ? 'active' : 'inactive'" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state :title="__('inventory.no_products')" :message="__('inventory.no_products_hint')" /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        @endif

        @if ($page === 'product-create')
            <form method="POST" action="{{ route('inventory.products.store') }}" data-wizard data-draft-key="inventory-product-draft">
                @csrf
                <section class="wizard">
                    <aside class="wizard-steps">
                        @foreach(['identity', 'pricing', 'optical', 'review'] as $index => $step)
                            <button class="wizard-step-button {{ $index === 0 ? 'active' : '' }}" type="button" data-wizard-step="{{ $step }}"><span class="wizard-step-number">{{ $index + 1 }}</span><span>{{ __('inventory.step.'.$step) }}</span></button>
                        @endforeach
                    </aside>
                    <div>
                        <div class="wizard-panel active" data-wizard-panel="identity">
                            <div class="wizard-progress"><span data-wizard-progress></span></div>
                            <h2>{{ __('inventory.step.identity') }}</h2>
                            <div class="form-grid">
                                <div class="field"><label class="required">{{ __('common.name') }}</label><input name="name" required value="{{ old('name') }}" placeholder="{{ __('inventory.product_name_placeholder') }}"></div>
                                <div class="field"><label class="required">{{ __('common.sku') }}</label><input name="sku" required value="{{ old('sku') }}" placeholder="FR-1001"></div>
                                <div class="field"><label>{{ __('common.barcode') }}</label><input name="barcode" value="{{ old('barcode') }}" placeholder="{{ __('inventory.scan_or_type') }}"></div>
                                <div class="field"><label class="required">{{ __('common.type') }}</label><select name="type" required>@foreach($productTypes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="field"><label class="required">{{ __('common.category') }}</label><select name="product_category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                                <div class="field"><label>{{ __('common.supplier') }}</label><select name="supplier_id"><option value="">{{ __('common.not_set') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                            </div>
                            <div class="wizard-actions"><a class="button button-ghost" href="{{ route('inventory.app', ['page' => 'dashboard']) }}">{{ __('common.cancel') }}</a><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div>
                        </div>
                        <div class="wizard-panel" data-wizard-panel="pricing">
                            <div class="wizard-progress"><span data-wizard-progress></span></div>
                            <h2>{{ __('inventory.step.pricing') }}</h2>
                            <div class="form-grid">
                                <div class="field"><label class="required">{{ __('inventory.cost_price') }}</label><input name="cost_price" type="number" min="0" step="0.01" required value="{{ old('cost_price', 0) }}"></div>
                                <div class="field"><label class="required">{{ __('inventory.selling_price') }}</label><input name="retail_price" type="number" min="0" step="0.01" required value="{{ old('retail_price', 0) }}"></div>
                                <div class="field"><label class="required">{{ __('configuration.tax_settings') }}</label><select name="tax_type" required>@foreach($taxTypes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="field"><label>{{ __('inventory.tax_rate') }}</label><input name="tax_rate" type="number" min="0" step="0.01" value="{{ old('tax_rate', 15) }}"></div>
                                <div class="field"><label>{{ __('inventory.opening_stock') }}</label><input name="opening_stock" type="number" min="0" step="0.001" value="{{ old('opening_stock', 0) }}"></div>
                                <div class="field"><label>{{ __('inventory.reorder_point') }}</label><input name="reorder_point" type="number" min="0" step="0.001" value="{{ old('reorder_point', 0) }}"></div>
                            </div>
                            <div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div>
                        </div>
                        <div class="wizard-panel" data-wizard-panel="optical">
                            <div class="wizard-progress"><span data-wizard-progress></span></div>
                            <h2>{{ __('inventory.step.optical') }}</h2>
                            <div class="form-grid">
                                <div class="field"><label>{{ __('configuration.brands') }}</label><select name="brand"><option value="">{{ __('common.not_set') }}</option>@foreach($brands as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="field"><label>{{ __('inventory.model') }}</label><input name="model" placeholder="{{ __('inventory.model_placeholder') }}"></div>
                                <div class="field"><label>{{ __('inventory.color') }}</label><input name="color"></div>
                                <div class="field"><label>{{ __('inventory.material') }}</label><select name="material"><option value="">{{ __('common.not_set') }}</option>@foreach($lensMaterials as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="field"><label>{{ __('configuration.lens_types') }}</label><select name="lens_type"><option value="">{{ __('common.not_set') }}</option>@foreach($lensTypes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="field"><label>{{ __('configuration.lens_coatings') }}</label><select name="coating"><option value="">{{ __('common.not_set') }}</option>@foreach($lensCoatings as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                <details class="more-details"><summary>{{ __('common.more_details') }}</summary><div class="form-grid" style="margin-top:12px"><div class="field"><label>{{ __('inventory.frame_size') }}</label><input name="frame_size"></div><div class="field"><label>{{ __('inventory.bridge_size') }}</label><input name="bridge_size"></div><div class="field"><label>{{ __('inventory.temple_length') }}</label><input name="temple_length"></div><div class="field"><label>{{ __('inventory.power') }}</label><input name="power" type="number" step="0.01"></div><div class="field"><label>{{ __('inventory.base_curve') }}</label><input name="base_curve" type="number" step="0.01"></div><div class="field"><label>{{ __('inventory.diameter') }}</label><input name="diameter" type="number" step="0.01"></div></div></details>
                            </div>
                            <div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div>
                        </div>
                        <div class="wizard-panel" data-wizard-panel="review">
                            <div class="wizard-progress"><span data-wizard-progress></span></div>
                            <h2>{{ __('common.review') }}</h2><p>{{ __('inventory.review_product') }}</p>
                            <div class="notice notice-success">{{ __('common.draft_saved') }}</div>
                            <div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="submit">{{ __('inventory.save_product') }}</button></div>
                        </div>
                    </div>
                </section>
            </form>
        @endif

        @if ($page === 'product-details')
            <section class="panel">
                @if($selectedProduct)
                    <div class="section-header"><div><h2>{{ $selectedProduct->name }}</h2><p>{{ $selectedProduct->sku }} / {{ $selectedProduct->barcode }}</p></div><x-status-badge :status="$selectedProduct->is_active ? 'active' : 'inactive'" /></div>
                    <div class="metric-grid"><x-metric-card :label="__('inventory.available')" :value="number_format($selectedProduct->qty_on_hand - $selectedProduct->qty_reserved, 2)" /><x-metric-card :label="__('inventory.cost_price')" :value="$money($selectedProduct->cost_price)" /><x-metric-card :label="__('inventory.selling_price')" :value="$money($selectedProduct->retail_price)" /><x-metric-card :label="__('common.supplier')" :value="$selectedProduct->supplier_name ?: __('common.not_set')" /></div>
                @else<x-empty-state :title="__('inventory.no_products')" :message="__('inventory.no_products_hint')" />@endif
            </section>
        @endif

        @if ($page === 'purchase-orders')
            <section class="panel">
                <div class="section-header"><div><h2>{{ __('inventory.new_purchase_order') }}</h2><p>{{ __('inventory.purchase_order_hint') }}</p></div></div>
                <form class="form-grid" method="POST" action="{{ route('inventory.purchase-orders.store') }}">@csrf
                    <div class="field"><label class="required">{{ __('common.supplier') }}</label><select name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('common.branch') }}</label><select name="branch_id"><option value="">{{ __('common.all_branches') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('inventory.expected_on') }}</label><input name="expected_on" type="date"></div>
                    <div class="field field-wide"><label class="required">{{ __('common.product') }}</label><select name="lines[0][product_id]" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="required">{{ __('common.quantity') }}</label><input name="lines[0][ordered_quantity]" type="number" min="0.001" step="0.001" required></div>
                    <div class="field"><label class="required">{{ __('inventory.unit_cost') }}</label><input name="lines[0][unit_cost]" type="number" min="0" step="0.01" required></div>
                    <div class="field"><label>{{ __('inventory.tax_rate') }}</label><input name="lines[0][tax_rate]" type="number" min="0" step="0.01" value="15"></div>
                    <details class="more-details"><summary>{{ __('common.more_details') }}</summary><div class="field" style="margin-top:12px"><label>{{ __('common.notes') }}</label><textarea name="notes"></textarea></div></details>
                    <div class="form-actions"><button class="button" type="submit">{{ __('inventory.create_purchase_order') }}</button></div>
                </form>
            </section>
            <section class="section table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('common.number') }}</th><th>{{ __('common.supplier') }}</th><th>{{ __('common.branch') }}</th><th>{{ __('common.total') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>@forelse($purchaseOrders as $po)<tr><td>{{ $po->po_number }}</td><td>{{ $po->supplier_name }}</td><td>{{ $po->branch_name ?: __('common.all_branches') }}</td><td>{{ $money($po->grand_total) }}</td><td><x-status-badge :status="$po->status" /></td><td><a class="button button-ghost" href="{{ route('documents.purchase-orders.show', ['order' => $po->id, 'lang' => app()->getLocale()]) }}">{{ __('common.print') }}</a><a class="button button-ghost" href="{{ route('documents.purchase-orders.show', ['order' => $po->id, 'lang' => app()->getLocale(), 'format' => 'pdf']) }}">PDF</a></td></tr>@empty<tr><td colspan="6"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if ($page === 'goods-receiving')
            <form method="POST" action="{{ route('inventory.goods.receive') }}" data-wizard data-draft-key="inventory-receiving-draft">@csrf
                <section class="wizard">
                    <aside class="wizard-steps">@foreach(['product', 'source', 'quantity', 'review'] as $index => $step)<button class="wizard-step-button {{ $index === 0 ? 'active' : '' }}" type="button" data-wizard-step="{{ $step }}"><span class="wizard-step-number">{{ $index + 1 }}</span><span>{{ __('inventory.receiving_step.'.$step) }}</span></button>@endforeach</aside>
                    <div>
                        <div class="wizard-panel active" data-wizard-panel="product"><div class="wizard-progress"><span data-wizard-progress></span></div><h2>{{ __('inventory.choose_product') }}</h2><div class="field"><label class="required">{{ __('common.product') }}</label><select name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }} ({{ number_format($product->qty_on_hand, 2) }})</option>@endforeach</select></div><div class="wizard-actions"><a class="button button-ghost" href="{{ route('inventory.app', ['page' => 'dashboard']) }}">{{ __('common.cancel') }}</a><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div></div>
                        <div class="wizard-panel" data-wizard-panel="source"><div class="wizard-progress"><span data-wizard-progress></span></div><h2>{{ __('inventory.choose_source') }}</h2><div class="form-grid"><div class="field"><label class="required">{{ __('common.supplier') }}</label><select name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div><div class="field"><label class="required">{{ __('common.branch') }}</label><select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div></div><div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div></div>
                        <div class="wizard-panel" data-wizard-panel="quantity"><div class="wizard-progress"><span data-wizard-progress></span></div><h2>{{ __('inventory.enter_received_stock') }}</h2><div class="form-grid"><div class="field"><label class="required">{{ __('common.quantity') }}</label><input name="quantity" type="number" min="0.001" step="0.001" required></div><div class="field"><label class="required">{{ __('inventory.unit_cost') }}</label><input name="unit_cost" type="number" min="0" step="0.01" required></div><div class="field"><label>{{ __('inventory.lot_number') }}</label><input name="lot_number"></div><div class="field"><label>{{ __('inventory.expiry_date') }}</label><input name="expires_on" type="date"></div></div><div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="button" data-wizard-next>{{ __('common.next') }}</button></div></div>
                        <div class="wizard-panel" data-wizard-panel="review"><div class="wizard-progress"><span data-wizard-progress></span></div><h2>{{ __('inventory.review_receipt') }}</h2><p>{{ __('inventory.review_receipt_hint') }}</p><div class="notice notice-success">{{ __('inventory.stock_updates_after_post') }}</div><div class="wizard-actions"><button class="button button-ghost" type="button" data-wizard-back>{{ __('common.back') }}</button><button class="button" type="submit">{{ __('inventory.post_receipt') }}</button></div></div>
                    </div>
                </section>
            </form>
            <section class="section table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('common.number') }}</th><th>{{ __('common.supplier') }}</th><th>{{ __('common.branch') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>@forelse($goodsReceipts as $receipt)<tr><td>{{ $receipt->receipt_number }}</td><td>{{ $receipt->supplier_name }}</td><td>{{ $receipt->branch_name }}</td><td>{{ $receipt->received_on }}</td><td><x-status-badge :status="$receipt->status" /></td><td><a class="button button-ghost" href="{{ route('documents.purchase-invoices.show', ['receipt' => $receipt->id, 'lang' => app()->getLocale()]) }}">{{ __('common.print') }}</a><a class="button button-ghost" href="{{ route('documents.purchase-invoices.show', ['receipt' => $receipt->id, 'lang' => app()->getLocale(), 'format' => 'pdf']) }}">PDF</a></td></tr>@empty<tr><td colspan="6"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if ($page === 'stock-movements')
            <section class="panel"><div class="section-header"><div><h2>{{ __('inventory.adjust_stock') }}</h2><p>{{ __('inventory.adjust_stock_hint') }}</p></div></div><form class="form-grid" method="POST" action="{{ route('inventory.stock.adjust') }}">@csrf<div class="field"><label class="required">{{ __('common.product') }}</label><select name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }}</option>@endforeach</select></div><div class="field"><label class="required">{{ __('common.branch') }}</label><select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div><div class="field"><label class="required">{{ __('inventory.quantity_change') }}</label><input name="quantity_change" type="number" step="0.001" required placeholder="-1 / 5"></div><div class="field"><label class="required">{{ __('common.reason') }}</label><select name="reason"><option value="stock_adjustment">{{ __('inventory.reason.correction') }}</option><option value="damaged_write_off">{{ __('inventory.reason.damaged') }}</option><option value="lost_write_off">{{ __('inventory.reason.lost') }}</option></select></div><details class="more-details"><summary>{{ __('common.more_details') }}</summary><div class="field" style="margin-top:12px"><label>{{ __('common.notes') }}</label><textarea name="notes"></textarea></div></details><div class="form-actions"><button class="button" type="submit">{{ __('inventory.post_adjustment') }}</button></div></form></section>
            <section class="section table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('common.date') }}</th><th>{{ __('common.product') }}</th><th>{{ __('common.type') }}</th><th>{{ __('common.quantity') }}</th><th>{{ __('common.branch') }}</th></tr></thead><tbody>@forelse($movements as $movement)<tr><td>{{ $movement->occurred_at }}</td><td>{{ $movement->product_name }}<br><span class="table-meta">{{ $movement->sku }}</span></td><td>{{ __('status.'.$movement->movement_type) }}</td><td>{{ $movement->direction === 'out' ? '-' : '+' }}{{ number_format($movement->quantity, 2) }}</td><td>{{ $movement->branch_name }}</td></tr>@empty<tr><td colspan="5"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if ($page === 'low-stock-alerts')
            <section class="table-card"><div class="table-scroll"><table><thead><tr><th>{{ __('common.product') }}</th><th>{{ __('common.branch') }}</th><th>{{ __('inventory.available') }}</th><th>{{ __('inventory.reorder_point') }}</th><th>{{ __('common.status') }}</th></tr></thead><tbody>@forelse($lowStockAlerts as $row)<tr><td>{{ $row->name }}<br><span class="table-meta">{{ $row->sku }}</span></td><td>{{ $row->branch_name }}</td><td>{{ number_format($row->available_stock, 2) }}</td><td>{{ number_format($row->reorder_point, 2) }}</td><td><x-status-badge status="low_stock" /></td></tr>@empty<tr><td colspan="5"><x-empty-state /></td></tr>@endforelse</tbody></table></div></section>
        @endif

        @if (in_array($page, ['stock-transfers', 'stock-counts'], true))
            <section class="panel"><x-empty-state :title="__('inventory.page.'.$page)" :message="__('inventory.use_configuration_hint')" /></section>
        @endif

        @if ($page === 'reports')
            <div class="module-grid"><x-module-card icon="reports" :title="__('Stock Report')" :description="__('Printable stock and valuation report')" :action="__('common.print')" :href="route('documents.stock-report', ['branch_id' => $branchId, 'lang' => app()->getLocale()])" />@foreach($reports as $key => $label)<x-module-card icon="reports" :title="__('inventory.report.'.$key)" :description="__('reports.description')" :action="__('common.open')" :href="url('/api/v1/inventory/reports/'.str_replace('_', '-', $key).'?'.http_build_query($filters))" />@endforeach</div>
        @endif
    @endif
@endsection
