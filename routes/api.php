<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OpticalOrderController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\SalesPosController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::middleware(['web', 'auth'])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('erp/dashboard', [ErpController::class, 'dashboardApi'])->name('erp.dashboard');
    Route::get('erp/search', [ErpController::class, 'searchApi'])->name('erp.search');
    Route::get('erp/workflows', [ErpController::class, 'workflowApi'])->name('erp.workflows');
    Route::get('erp/reports/{report}', [ErpController::class, 'reportApi'])
        ->where('report', 'sales-today|appointments-today|pending-orders|ready-pickup|low-stock|unpaid-invoices|whatsapp-unread|inventory-valuation|accounting-journals|trial-balance|general-ledger|profit-and-loss|balance-sheet')
        ->name('erp.reports');
    Route::get('configuration', [ErpController::class, 'configurationApi'])->name('configuration.groups');
    Route::get('configuration/{group}', [ConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('configuration/{group}', [ConfigurationController::class, 'store'])->name('configuration.store');
    Route::patch('configuration/{group}/{id}', [ConfigurationController::class, 'update'])->whereNumber('id')->name('configuration.update');
    Route::delete('configuration/{group}/{id}', [ConfigurationController::class, 'destroy'])->whereNumber('id')->name('configuration.destroy');

    Route::get('admin/{resource}', [AdminController::class, 'index'])
        ->where('resource', 'users|roles|permissions|branches|companies|settings|audit-logs')
        ->name('admin.index');
    Route::post('admin/{resource}', [AdminController::class, 'store'])
        ->where('resource', 'users|roles|branches|settings')
        ->name('admin.store');
    Route::get('admin/{resource}/{id}', [AdminController::class, 'show'])
        ->where('resource', 'users|roles|permissions|branches|companies|settings|audit-logs')
        ->whereNumber('id')
        ->name('admin.show');
    Route::patch('admin/{resource}/{id}', [AdminController::class, 'update'])
        ->where('resource', 'users|roles|branches|settings')
        ->whereNumber('id')
        ->name('admin.update');

    Route::get('whatsapp/meta', [WhatsAppController::class, 'meta'])->name('whatsapp.meta');
    Route::get('whatsapp/dashboard', [WhatsAppController::class, 'dashboardApi'])->name('whatsapp.dashboard');
    Route::get('whatsapp/conversations', [WhatsAppController::class, 'conversationsApi'])->name('whatsapp.conversations');
    Route::get('whatsapp/conversations/{conversation}', [WhatsAppController::class, 'showApi'])->whereNumber('conversation')->name('whatsapp.conversations.show');
    Route::get('whatsapp/logs', [WhatsAppController::class, 'logsApi'])->name('whatsapp.logs');
    Route::get('whatsapp/reports/{report}', [WhatsAppController::class, 'reportApi'])
        ->where('report', 'total-messages-sent|failed-messages|appointment-reminders-sent|order-ready-messages-sent|response-rate|conversations-by-staff|unresolved-conversations|opt-out')
        ->name('whatsapp.reports');
    Route::post('whatsapp/messages', [WhatsAppController::class, 'sendMessage'])->name('whatsapp.messages.send');
    Route::post('whatsapp/conversations', [WhatsAppController::class, 'updateConversation'])->name('whatsapp.conversations.update');
    Route::post('whatsapp/templates', [WhatsAppController::class, 'saveTemplate'])->name('whatsapp.templates.save');
    Route::post('whatsapp/automations', [WhatsAppController::class, 'saveAutomation'])->name('whatsapp.automations.save');
    Route::post('whatsapp/automation-trigger', [WhatsAppController::class, 'automationTrigger'])->name('whatsapp.automation.trigger');
    Route::post('whatsapp/consent', [WhatsAppController::class, 'updateConsent'])->name('whatsapp.consent.update');
    Route::post('whatsapp/settings', [WhatsAppController::class, 'saveSettings'])->name('whatsapp.settings.save');
    Route::post('whatsapp/retry', [WhatsAppController::class, 'retryFailed'])->name('whatsapp.messages.retry');

    Route::get('appointments/meta', [AppointmentController::class, 'meta'])->name('appointments.meta');
    Route::get('appointments/dashboard', [AppointmentController::class, 'dashboardApi'])->name('appointments.dashboard');
    Route::get('appointments/search', [AppointmentController::class, 'appointmentsApi'])->name('appointments.search');
    Route::get('appointments/calendar', [AppointmentController::class, 'calendarApi'])->name('appointments.calendar');
    Route::get('appointments/reports/{report}', [AppointmentController::class, 'reportApi'])
        ->where('report', 'appointments-by-day|appointments-by-branch|appointments-by-optometrist|no-show|cancelled|follow-up|waiting-time')
        ->name('appointments.reports');
    Route::get('appointments/{appointment}', [AppointmentController::class, 'showApi'])->whereNumber('appointment')->name('appointments.show');
    Route::post('appointments', [AppointmentController::class, 'storeAppointment'])->name('appointments.store');
    Route::post('appointments/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
    Route::post('appointments/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('appointments/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('appointments/reminders', [AppointmentController::class, 'sendReminder'])->name('appointments.reminders.send');
    Route::post('appointments/patient-reply', [AppointmentController::class, 'patientReply'])->name('appointments.patient-reply');

    Route::get('optical-orders/meta', [OpticalOrderController::class, 'meta'])->name('optical-orders.meta');
    Route::get('optical-orders/dashboard', [OpticalOrderController::class, 'dashboardApi'])->name('optical-orders.dashboard');
    Route::get('optical-orders/search', [OpticalOrderController::class, 'ordersApi'])->name('optical-orders.search');
    Route::get('optical-orders/reports/{report}', [OpticalOrderController::class, 'reportApi'])
        ->where('report', 'pending-optical-orders|orders-by-status|orders-by-lab|delayed-orders|remake-orders|ready-for-pickup|orders-by-branch|orders-by-salesperson')
        ->name('optical-orders.reports');
    Route::get('optical-orders/{order}', [OpticalOrderController::class, 'showApi'])->whereNumber('order')->name('optical-orders.show');
    Route::post('optical-orders', [OpticalOrderController::class, 'storeOrder'])->name('optical-orders.store');
    Route::post('optical-orders/status', [OpticalOrderController::class, 'updateStatus'])->name('optical-orders.status');
    Route::post('optical-orders/payments', [OpticalOrderController::class, 'collectPayment'])->name('optical-orders.payments.store');
    Route::post('optical-orders/documents', [OpticalOrderController::class, 'storeDocument'])->name('optical-orders.documents.store');

    Route::get('patients/meta', [PatientController::class, 'meta'])->name('patients.meta');
    Route::get('patients/dashboard', [PatientController::class, 'dashboardApi'])->name('patients.dashboard');
    Route::get('patients/search', [PatientController::class, 'patientsApi'])->name('patients.search');
    Route::get('patients/reports/{report}', [PatientController::class, 'reportApi'])
        ->where('report', 'new-patients|active-patients|patient-visit-history|prescription-history|exams-by-optometrist|follow-up')
        ->name('patients.reports');
    Route::get('patients/{patient}', [PatientController::class, 'showApi'])->whereNumber('patient')->name('patients.show');
    Route::get('patients/{patient}/timeline', [PatientController::class, 'timelineApi'])->whereNumber('patient')->name('patients.timeline');
    Route::post('patients', [PatientController::class, 'storePatient'])->name('patients.store');
    Route::post('patients/{patient}/update', [PatientController::class, 'updatePatient'])->whereNumber('patient')->name('patients.update');
    Route::post('patients/exams', [PatientController::class, 'storeExam'])->name('patients.exams.store');
    Route::post('patients/prescriptions', [PatientController::class, 'storePrescription'])->name('patients.prescriptions.store');
    Route::post('patients/prescriptions/{prescription}/update', [PatientController::class, 'updatePrescription'])->whereNumber('prescription')->name('patients.prescriptions.update');
    Route::post('patients/prescriptions/{prescription}/lock', [PatientController::class, 'lockPrescription'])->whereNumber('prescription')->name('patients.prescriptions.lock');
    Route::post('patients/documents', [PatientController::class, 'storeDocument'])->name('patients.documents.store');
    Route::post('patients/timeline-notes', [PatientController::class, 'storeTimelineNote'])->name('patients.timeline.store');

    Route::get('sales-pos/meta', [SalesPosController::class, 'meta'])->name('sales-pos.meta');
    Route::get('sales-pos/dashboard', [SalesPosController::class, 'dashboardApi'])->name('sales-pos.dashboard');
    Route::get('sales-pos/products', [SalesPosController::class, 'productsApi'])->name('sales-pos.products');
    Route::get('sales-pos/{resource}', [SalesPosController::class, 'documentsApi'])
        ->where('resource', 'invoices|quotations|sales-orders|payments|returns|cash-sessions')
        ->name('sales-pos.documents');
    Route::post('sales-pos/checkout', [SalesPosController::class, 'checkout'])->name('sales-pos.checkout');
    Route::post('sales-pos/quotations', [SalesPosController::class, 'createQuotation'])->name('sales-pos.quotations.store');
    Route::patch('sales-pos/quotations/{quotation}', [SalesPosController::class, 'updateQuotation'])->whereNumber('quotation')->name('sales-pos.quotations.update');
    Route::post('sales-pos/orders', [SalesPosController::class, 'createSalesOrder'])->name('sales-pos.orders.store');
    Route::patch('sales-pos/orders/{order}', [SalesPosController::class, 'updateSalesOrder'])->whereNumber('order')->name('sales-pos.orders.update');
    Route::post('sales-pos/returns', [SalesPosController::class, 'processReturn'])->name('sales-pos.returns.store');
    Route::post('sales-pos/cashier-closing', [SalesPosController::class, 'closeCashier'])->name('sales-pos.cashier.close');
    Route::get('sales-pos/reports/{report}', [SalesPosController::class, 'reportApi'])
        ->where('report', 'daily-sales|sales-by-cashier|sales-by-branch|sales-by-product|sales-by-category|sales-by-brand|gross-profit|returns|payment-methods')
        ->name('sales-pos.reports');

    Route::get('inventory/meta', function () {
        return response()->json([
            'module' => config('inventory.module'),
            'product_types' => config('inventory.product_types'),
            'tax_types' => config('inventory.tax_types'),
            'stock_statuses' => config('inventory.stock_statuses'),
            'movement_types' => config('inventory.movement_types'),
            'roles' => config('inventory.roles'),
            'permissions' => config('inventory.permissions'),
            'reports' => config('inventory.reports'),
            'integration_rules' => config('inventory.integration_rules'),
        ]);
    })->name('inventory.meta');

    Route::get('inventory/dashboard', function (Request $request) {
        if (! Schema::hasTable('products')) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $branchId = $request->integer('branch_id');
        $stockValue = DB::table('inventory_stock_levels')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->sum(DB::raw('qty_on_hand * average_cost'));
        $retailValue = DB::table('inventory_stock_levels')
            ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
            ->when($branchId, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))
            ->sum(DB::raw('inventory_stock_levels.qty_on_hand * products.retail_price'));

        return response()->json([
            'branch_id' => $branchId ?: null,
            'business_date' => now()->toDateString(),
            'metrics' => [
                'total_products' => DB::table('products')->count(),
                'active_products' => DB::table('products')->where('is_active', true)->count(),
                'stock_value_cost' => round($stockValue, 2),
                'stock_value_retail' => round($retailValue, 2),
                'low_stock_items' => DB::table('inventory_stock_levels')
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->whereRaw('(qty_on_hand - qty_reserved) <= reorder_point')
                    ->count(),
                'expiring_items' => DB::table('inventory_batches')
                    ->whereNotNull('expires_on')
                    ->whereDate('expires_on', '<=', now()->addDays(60)->toDateString())
                    ->count(),
                'pending_purchase_orders' => DB::table('inventory_purchase_orders')->whereIn('status', ['draft', 'approved', 'partial_received'])->count(),
                'open_transfers' => DB::table('inventory_transfers')->whereIn('status', ['draft', 'approved', 'in_transit'])->count(),
            ],
            'stock_mix' => DB::table('products')
                ->leftJoin('inventory_stock_levels', 'inventory_stock_levels.product_id', '=', 'products.id')
                ->selectRaw('products.type, count(distinct products.id) as items, coalesce(sum(inventory_stock_levels.qty_on_hand * inventory_stock_levels.average_cost), 0) as value')
                ->groupBy('products.type')
                ->orderBy('products.type')
                ->get(),
        ]);
    })->name('inventory.dashboard');

    Route::get('inventory/search', function (Request $request) {
        $q = trim((string) $request->query('q', ''));
        $results = DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->select('products.id', 'products.name', 'products.sku', 'products.barcode', 'products.brand', 'products.type', 'product_categories.name as category')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('products.name', 'like', "%{$q}%")
                        ->orWhere('products.sku', 'like', "%{$q}%")
                        ->orWhere('products.barcode', 'like', "%{$q}%")
                        ->orWhere('products.brand', 'like', "%{$q}%")
                        ->orWhere('product_categories.name', 'like', "%{$q}%");
                });
            })
            ->when($request->query('type'), fn ($query, $type) => $query->where('products.type', $type))
            ->limit(25)
            ->get();

        return response()->json([
            'query' => $q,
            'searchable_fields' => ['product_name', 'sku', 'barcode', 'brand', 'category'],
            'filters' => [
                'branch' => $request->query('branch'),
                'category' => $request->query('category'),
                'type' => $request->query('type'),
                'stock_status' => $request->query('stock_status'),
            ],
            'results' => $results,
        ]);
    })->name('inventory.search');

    $resources = [
        'product-categories' => ['category-tree', 'product-type-grouping'],
        'suppliers' => ['supplier-profile', 'purchase-history', 'active-status'],
        'products' => ['catalog', 'sku', 'barcode', 'pricing', 'tax-type', 'active-status'],
        'frame-details' => ['frame-size', 'bridge-size', 'temple-length', 'material', 'gender-style'],
        'lens-details' => ['lens-type', 'material', 'index', 'coating', 'tint', 'prescription-range'],
        'contact-lens-details' => ['power', 'base-curve', 'diameter', 'expiry', 'lot-batch'],
        'product-barcodes' => ['primary-barcode', 'alternate-barcodes', 'scan-lookup'],
        'locations' => ['branch-location', 'sellable-stock', 'damaged-stock', 'transit-stock'],
        'batches' => ['lot-tracking', 'expiry-tracking', 'supplier-source'],
        'serials' => ['serial-tracking', 'status-history'],
        'stock-levels' => ['on-hand', 'reserved', 'available', 'minimum-level', 'reorder-point'],
        'stock-movements' => ['history', 'source-document', 'balance-after'],
        'purchase-orders' => ['supplier-order', 'approval', 'partial-receiving'],
        'goods-receipts' => ['receiving', 'accepted-quantity', 'rejected-quantity', 'batch-creation'],
        'supplier-returns' => ['return-to-supplier', 'condition', 'stock-reduction'],
        'adjustments' => ['stock-adjustment', 'damaged-lost-writeoff', 'audit-log'],
        'transfers' => ['branch-transfer', 'ship', 'receive'],
        'stock-counts' => ['cycle-count', 'variance', 'approval'],
        'reservations' => ['optical-order-reserve', 'release', 'consume'],
        'valuation-snapshots' => ['cost-value', 'retail-value', 'accounting-hook'],
    ];

    $resourceData = function (string $resource) {
        if (! Schema::hasTable('products')) {
            return [];
        }

        return match ($resource) {
            'products' => DB::table('products')
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
                ->select('products.*', 'product_categories.name as category_name', 'suppliers.name as supplier_name')
                ->orderBy('products.name')
                ->limit(100)
                ->get(),
            'stock-levels' => DB::table('inventory_stock_levels')
                ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                ->join('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                ->select('inventory_stock_levels.*', 'products.name as product_name', 'products.sku', 'branches.name as branch_name')
                ->orderBy('products.name')
                ->limit(100)
                ->get(),
            'stock-movements' => DB::table('inventory_stock_movements')
                ->join('products', 'products.id', '=', 'inventory_stock_movements.product_id')
                ->join('branches', 'branches.id', '=', 'inventory_stock_movements.branch_id')
                ->select('inventory_stock_movements.*', 'products.name as product_name', 'products.sku', 'branches.name as branch_name')
                ->orderByDesc('occurred_at')
                ->limit(100)
                ->get(),
            'purchase-orders' => DB::table('inventory_purchase_orders')
                ->join('suppliers', 'suppliers.id', '=', 'inventory_purchase_orders.supplier_id')
                ->select('inventory_purchase_orders.*', 'suppliers.name as supplier_name')
                ->orderByDesc('inventory_purchase_orders.id')
                ->limit(100)
                ->get(),
            'goods-receipts' => DB::table('inventory_goods_receipts')
                ->join('suppliers', 'suppliers.id', '=', 'inventory_goods_receipts.supplier_id')
                ->select('inventory_goods_receipts.*', 'suppliers.name as supplier_name')
                ->orderByDesc('received_on')
                ->limit(100)
                ->get(),
            'suppliers' => DB::table('suppliers')->orderBy('name')->get(),
            'product-categories' => DB::table('product_categories')->orderBy('name')->get(),
            'locations' => DB::table('inventory_locations')
                ->join('branches', 'branches.id', '=', 'inventory_locations.branch_id')
                ->select('inventory_locations.*', 'branches.name as branch_name')
                ->orderBy('branches.name')
                ->orderBy('inventory_locations.name')
                ->get(),
            'batches' => DB::table('inventory_batches')
                ->join('products', 'products.id', '=', 'inventory_batches.product_id')
                ->select('inventory_batches.*', 'products.name as product_name', 'products.sku')
                ->orderBy('expires_on')
                ->get(),
            'transfers' => DB::table('inventory_transfers')->orderByDesc('id')->get(),
            'stock-counts' => DB::table('inventory_stock_counts')->orderByDesc('count_date')->get(),
            default => [],
        };
    };

    foreach ($resources as $resource => $capabilities) {
        Route::get('inventory/'.$resource, [InventoryController::class, 'apiResourceIndex'])
            ->defaults('resource', $resource)
            ->name('inventory.'.$resource.'.index');

        Route::post('inventory/'.$resource, [InventoryController::class, 'apiResourceStore'])
            ->defaults('resource', $resource)
            ->name('inventory.'.$resource.'.store');

        Route::get('inventory/'.$resource.'/{id}', [InventoryController::class, 'apiResourceShow'])
            ->whereNumber('id')
            ->defaults('resource', $resource)
            ->name('inventory.'.$resource.'.show');

        Route::patch('inventory/'.$resource.'/{id}', [InventoryController::class, 'apiResourceUpdate'])
            ->whereNumber('id')
            ->defaults('resource', $resource)
            ->name('inventory.'.$resource.'.update');
    }

    $operations = [
        'scan-barcode' => 'Lookup product, batch, serial, and branch stock by barcode.',
        'reserve-stock' => 'Reserve stock for an optical order.',
        'release-reservation' => 'Release reserved stock back to available stock.',
        'issue-sale' => 'Reduce stock after a confirmed sale.',
        'receive-return' => 'Increase stock after a restockable return.',
        'write-off' => 'Write off damaged or lost stock and create audit trail.',
        'post-adjustment' => 'Approve adjustment and write stock movements.',
        'approve-transfer' => 'Approve stock transfer between branches.',
        'ship-transfer' => 'Move stock into transit from source branch.',
        'receive-transfer' => 'Receive stock into destination branch.',
    ];

    foreach ($operations as $operation => $description) {
        Route::post('inventory/operations/'.$operation, [InventoryController::class, 'apiOperation'])
            ->defaults('operation', $operation)
            ->name('inventory.operations.'.$operation);
    }

    $reports = [
        'current-stock',
        'low-stock',
        'expiring-items',
        'stock-movements',
        'inventory-valuation',
        'fast-moving-products',
        'slow-moving-products',
        'supplier-purchases',
    ];

    foreach ($reports as $report) {
        Route::get('inventory/reports/'.$report, function (Request $request) use ($report) {
            $data = match ($report) {
                'current-stock' => DB::table('inventory_stock_levels')
                    ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                    ->join('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                    ->selectRaw('products.sku, products.name, branches.name as branch, inventory_stock_levels.qty_on_hand, inventory_stock_levels.qty_reserved, (inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) as available_stock, inventory_stock_levels.reorder_point')
                    ->orderBy('products.name')
                    ->get(),
                'low-stock' => DB::table('inventory_stock_levels')
                    ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                    ->join('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                    ->selectRaw('products.sku, products.name, branches.name as branch, inventory_stock_levels.qty_on_hand, inventory_stock_levels.qty_reserved, (inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) as available_stock, inventory_stock_levels.reorder_point')
                    ->whereRaw('(inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) <= inventory_stock_levels.reorder_point')
                    ->orderBy('available_stock')
                    ->get(),
                'expiring-items' => DB::table('inventory_batches')
                    ->join('products', 'products.id', '=', 'inventory_batches.product_id')
                    ->select('products.sku', 'products.name', 'inventory_batches.lot_number', 'inventory_batches.expires_on', 'inventory_batches.initial_quantity')
                    ->whereNotNull('inventory_batches.expires_on')
                    ->orderBy('inventory_batches.expires_on')
                    ->get(),
                'stock-movements' => DB::table('inventory_stock_movements')
                    ->join('products', 'products.id', '=', 'inventory_stock_movements.product_id')
                    ->join('branches', 'branches.id', '=', 'inventory_stock_movements.branch_id')
                    ->select('products.sku', 'products.name', 'branches.name as branch', 'inventory_stock_movements.movement_type', 'inventory_stock_movements.direction', 'inventory_stock_movements.quantity', 'inventory_stock_movements.occurred_at')
                    ->orderByDesc('inventory_stock_movements.occurred_at')
                    ->get(),
                'inventory-valuation' => DB::table('inventory_stock_levels')
                    ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                    ->join('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                    ->selectRaw('products.sku, products.name, branches.name as branch, inventory_stock_levels.qty_on_hand, inventory_stock_levels.average_cost, (inventory_stock_levels.qty_on_hand * inventory_stock_levels.average_cost) as cost_value, (inventory_stock_levels.qty_on_hand * products.retail_price) as retail_value')
                    ->orderByDesc('cost_value')
                    ->get(),
                'supplier-purchases' => DB::table('inventory_purchase_orders')
                    ->join('suppliers', 'suppliers.id', '=', 'inventory_purchase_orders.supplier_id')
                    ->select('inventory_purchase_orders.po_number', 'suppliers.name as supplier', 'inventory_purchase_orders.status', 'inventory_purchase_orders.ordered_on', 'inventory_purchase_orders.grand_total')
                    ->orderByDesc('inventory_purchase_orders.ordered_on')
                    ->get(),
                default => [],
            };

            return response()->json([
                'report' => $report,
                'filters' => [
                    'branch' => $request->query('branch'),
                    'category' => $request->query('category'),
                    'supplier' => $request->query('supplier'),
                    'date_from' => $request->query('date_from'),
                    'date_to' => $request->query('date_to'),
                ],
                'data' => $data,
            ]);
        })->name('inventory.reports.'.$report);
    }
});
