<?php

namespace App\Http\Controllers;

use App\Support\AccountingPoster;
use App\Support\SetupOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const PAGES = [
        'dashboard' => 'Dashboard',
        'products' => 'Products',
        'product-create' => 'Create Product',
        'product-details' => 'Product Details',
        'stock-movements' => 'Stock Movements',
        'purchase-orders' => 'Purchase Orders',
        'goods-receiving' => 'Goods Receiving',
        'stock-transfers' => 'Stock Transfers',
        'stock-counts' => 'Stock Counts',
        'low-stock-alerts' => 'Low Stock Alerts',
        'reports' => 'Reports',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'dashboard';

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        if (! Schema::hasTable('products')) {
            return view('inventory.dashboard', [
                'page' => 'setup',
                'pages' => self::PAGES,
                'databaseReady' => false,
            ]);
        }

        return view('inventory.dashboard', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => true,
            ...$this->inventoryData($request),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'product_category_id' => ['required', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'tax_type' => ['required', 'string', 'max:255'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'frame_size' => ['nullable', 'string', 'max:255'],
            'bridge_size' => ['nullable', 'string', 'max:255'],
            'temple_length' => ['nullable', 'string', 'max:255'],
            'gender_style' => ['nullable', 'string', 'max:255'],
            'lens_type' => ['nullable', 'string', 'max:255'],
            'lens_index' => ['nullable', 'string', 'max:255'],
            'coating' => ['nullable', 'string', 'max:255'],
            'tint' => ['nullable', 'string', 'max:255'],
            'power' => ['nullable', 'numeric'],
            'base_curve' => ['nullable', 'numeric'],
            'diameter' => ['nullable', 'numeric'],
        ]);

        $companyId = (int) DB::table('companies')->value('id');
        $branchId = (int) DB::table('branches')->orderBy('id')->value('id');

        DB::transaction(function () use ($validated, $companyId, $branchId): void {
            $now = now();
            $productId = DB::table('products')->insertGetId([
                'company_id' => $companyId,
                'product_category_id' => $validated['product_category_id'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'brand' => $validated['brand'] ?? null,
                'model' => $validated['model'] ?? null,
                'color' => $validated['color'] ?? null,
                'size' => $validated['size'] ?? null,
                'material' => $validated['material'] ?? null,
                'cost_price' => $validated['cost_price'],
                'retail_price' => $validated['retail_price'],
                'tax_type' => $validated['tax_type'],
                'tax_rate' => $validated['tax_rate'] ?? 15,
                'default_minimum_stock_level' => $validated['minimum_stock_level'] ?? 0,
                'default_reorder_point' => $validated['reorder_point'] ?? 0,
                'track_batches' => in_array($validated['type'], ['contact_lens', 'cleaning_solution', 'consumable'], true),
                'track_serials' => false,
                'track_expiry' => in_array($validated['type'], ['contact_lens', 'cleaning_solution', 'consumable'], true),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! empty($validated['barcode'])) {
                DB::table('inventory_product_barcodes')->insert([
                    'product_id' => $productId,
                    'barcode' => $validated['barcode'],
                    'barcode_type' => 'EAN13',
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (in_array($validated['type'], ['frame', 'sunglasses'], true)) {
                DB::table('inventory_frame_details')->insert([
                    'product_id' => $productId,
                    'frame_size' => $validated['frame_size'] ?? $validated['size'] ?? null,
                    'bridge_size' => $validated['bridge_size'] ?? null,
                    'temple_length' => $validated['temple_length'] ?? null,
                    'material' => $validated['material'] ?? null,
                    'gender_style' => $validated['gender_style'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($validated['type'] === 'lens') {
                DB::table('inventory_lens_details')->insert([
                    'product_id' => $productId,
                    'lab_supplier_id' => $validated['supplier_id'] ?? null,
                    'lens_type' => $validated['lens_type'] ?? null,
                    'material' => $validated['material'] ?? null,
                    'lens_index' => $validated['lens_index'] ?? null,
                    'coating' => $validated['coating'] ?? null,
                    'tint' => $validated['tint'] ?? null,
                    'is_custom_order' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($validated['type'] === 'contact_lens') {
                DB::table('inventory_contact_lens_details')->insert([
                    'product_id' => $productId,
                    'power' => $validated['power'] ?? null,
                    'base_curve' => $validated['base_curve'] ?? null,
                    'diameter' => $validated['diameter'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $openingStock = (float) ($validated['opening_stock'] ?? 0);
            if ($openingStock > 0) {
                $locationId = DB::table('inventory_locations')
                    ->where('branch_id', $branchId)
                    ->where('is_sellable', true)
                    ->value('id');

                DB::table('inventory_stock_levels')->insert([
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'inventory_location_id' => $locationId,
                    'qty_on_hand' => $openingStock,
                    'qty_reserved' => 0,
                    'minimum_stock_level' => $validated['minimum_stock_level'] ?? 0,
                    'reorder_point' => $validated['reorder_point'] ?? 0,
                    'average_cost' => $validated['cost_price'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('inventory_stock_movements')->insert([
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'inventory_location_id' => $locationId,
                    'movement_type' => 'stock_adjustment',
                    'direction' => 'in',
                    'quantity' => $openingStock,
                    'unit_cost' => $validated['cost_price'],
                    'balance_after' => $openingStock,
                    'reference_type' => 'opening_stock',
                    'reason' => 'Opening stock',
                    'occurred_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return redirect()->route('inventory.app', ['page' => 'products'])
            ->with('status', __('Product created and stock updated.'));
    }

    public function adjustStock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'quantity_change' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $allowNegative = $request->user()->hasRole('erp-admin') || $request->user()->hasPermission('inventory.negative_stock');

        DB::transaction(function () use ($validated, $allowNegative): void {
            $now = now();
            $locationId = DB::table('inventory_locations')
                ->where('branch_id', $validated['branch_id'])
                ->where('is_sellable', true)
                ->value('id');

            $stock = DB::table('inventory_stock_levels')
                ->where('product_id', $validated['product_id'])
                ->where('branch_id', $validated['branch_id'])
                ->where('inventory_location_id', $locationId)
                ->first();

            if (! $stock) {
                $product = DB::table('products')->where('id', $validated['product_id'])->first();
                $stockId = DB::table('inventory_stock_levels')->insertGetId([
                    'product_id' => $validated['product_id'],
                    'branch_id' => $validated['branch_id'],
                    'inventory_location_id' => $locationId,
                    'qty_on_hand' => 0,
                    'qty_reserved' => 0,
                    'minimum_stock_level' => $product->default_minimum_stock_level ?? 0,
                    'reorder_point' => $product->default_reorder_point ?? 0,
                    'average_cost' => $product->cost_price ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $stock = DB::table('inventory_stock_levels')->where('id', $stockId)->first();
            }

            $newBalance = (float) $stock->qty_on_hand + (float) $validated['quantity_change'];
            if ($newBalance < -0.0001 && ! $allowNegative) {
                throw ValidationException::withMessages(['quantity_change' => 'This adjustment would create negative stock.']);
            }
            DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                'qty_on_hand' => $newBalance,
                'updated_at' => $now,
            ]);

            $adjustmentId = DB::table('inventory_adjustments')->insertGetId([
                'branch_id' => $validated['branch_id'],
                'adjustment_number' => 'ADJ-'.Str::upper(Str::random(6)),
                'reason' => $validated['reason'],
                'status' => 'posted',
                'notes' => $validated['notes'] ?? null,
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_adjustment_lines')->insert([
                'inventory_adjustment_id' => $adjustmentId,
                'product_id' => $validated['product_id'],
                'inventory_location_id' => $locationId,
                'system_quantity' => $stock->qty_on_hand,
                'adjusted_quantity' => $newBalance,
                'variance_quantity' => $validated['quantity_change'],
                'unit_cost' => $stock->average_cost,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_stock_movements')->insert([
                'product_id' => $validated['product_id'],
                'branch_id' => $validated['branch_id'],
                'inventory_location_id' => $locationId,
                'movement_type' => in_array($validated['reason'], ['damaged_write_off', 'lost_write_off'], true)
                    ? $validated['reason']
                    : 'stock_adjustment',
                'direction' => ((float) $validated['quantity_change'] > 0) ? 'in' : 'out',
                'quantity' => abs((float) $validated['quantity_change']),
                'unit_cost' => $stock->average_cost,
                'balance_after' => $newBalance,
                'reference_type' => 'inventory_adjustment',
                'reference_id' => $adjustmentId,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('audit_logs')->insert([
                'branch_id' => $validated['branch_id'],
                'action' => 'inventory.stock.adjusted',
                'auditable_type' => 'inventory_adjustment',
                'auditable_id' => $adjustmentId,
                'before_values' => json_encode(['qty_on_hand' => $stock->qty_on_hand]),
                'after_values' => json_encode(['qty_on_hand' => $newBalance]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            app(AccountingPoster::class)->postInventoryAdjustment($adjustmentId);
        });

        return redirect()->route('inventory.app', ['page' => 'stock-movements'])
            ->with('status', __('Stock adjustment posted.'));
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $this->createPurchaseOrderRecord($request);

        return redirect()->route('inventory.app', ['page' => 'purchase-orders'])
            ->with('status', __('messages.purchase_order_created'));
    }

    public function receiveGoods(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'lot_number' => ['nullable', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($validated): void {
            $now = now();
            $locationId = DB::table('inventory_locations')
                ->where('branch_id', $validated['branch_id'])
                ->where('is_sellable', true)
                ->value('id');

            $receiptId = DB::table('inventory_goods_receipts')->insertGetId([
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'receipt_number' => 'GR-'.Str::upper(Str::random(6)),
                'received_on' => $now->toDateString(),
                'status' => 'posted',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $batchId = DB::table('inventory_batches')->insertGetId([
                'product_id' => $validated['product_id'],
                'supplier_id' => $validated['supplier_id'],
                'lot_number' => $validated['lot_number'] ?? null,
                'batch_number' => $validated['lot_number'] ?? null,
                'expires_on' => $validated['expires_on'] ?? null,
                'received_on' => $now->toDateString(),
                'initial_quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_goods_receipt_lines')->insert([
                'inventory_goods_receipt_id' => $receiptId,
                'product_id' => $validated['product_id'],
                'inventory_batch_id' => $batchId,
                'received_quantity' => $validated['quantity'],
                'accepted_quantity' => $validated['quantity'],
                'rejected_quantity' => 0,
                'unit_cost' => $validated['unit_cost'],
                'lot_number' => $validated['lot_number'] ?? null,
                'expires_on' => $validated['expires_on'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stock = DB::table('inventory_stock_levels')
                ->where('product_id', $validated['product_id'])
                ->where('branch_id', $validated['branch_id'])
                ->where('inventory_location_id', $locationId)
                ->first();

            $balance = (float) ($stock->qty_on_hand ?? 0) + (float) $validated['quantity'];

            if ($stock) {
                DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                    'qty_on_hand' => $balance,
                    'average_cost' => $validated['unit_cost'],
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('inventory_stock_levels')->insert([
                    'product_id' => $validated['product_id'],
                    'branch_id' => $validated['branch_id'],
                    'inventory_location_id' => $locationId,
                    'qty_on_hand' => $validated['quantity'],
                    'qty_reserved' => 0,
                    'average_cost' => $validated['unit_cost'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('inventory_stock_movements')->insert([
                'product_id' => $validated['product_id'],
                'branch_id' => $validated['branch_id'],
                'inventory_location_id' => $locationId,
                'inventory_batch_id' => $batchId,
                'movement_type' => 'purchase_receipt',
                'direction' => 'in',
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'],
                'balance_after' => $balance,
                'reference_type' => 'inventory_goods_receipt',
                'reference_id' => $receiptId,
                'reason' => 'Goods received',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            app(AccountingPoster::class)->postGoodsReceipt($receiptId);
        });

        return redirect()->route('inventory.app', ['page' => 'goods-receiving'])
            ->with('status', __('Goods receipt posted and stock increased.'));
    }

    public function apiResourceIndex(Request $request, string $resource)
    {
        $this->assertResource($resource);
        $query = $this->resourceQuery($resource, $request);

        return response()->json([
            'resource' => $resource,
            'capabilities' => $this->resourceCapabilities($resource),
            ...$this->paginateQuery($query, $request),
            'filters' => $request->only(['q', 'branch_id', 'type', 'category_id', 'supplier_id', 'status']),
        ]);
    }

    public function apiResourceShow(int $id, string $resource)
    {
        $this->assertResource($resource);
        $table = $this->resourceTable($resource);
        $data = DB::table($table)->where('id', $id)->first();
        if (! $data) {
            abort(404);
        }

        if ($resource === 'products') {
            $data->stock = DB::table('inventory_stock_levels')
                ->leftJoin('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                ->select('inventory_stock_levels.*', 'branches.name as branch_name')
                ->where('product_id', $id)
                ->get();
            $data->barcodes = DB::table('inventory_product_barcodes')->where('product_id', $id)->get();
            $data->frame_detail = DB::table('inventory_frame_details')->where('product_id', $id)->first();
            $data->lens_detail = DB::table('inventory_lens_details')->where('product_id', $id)->first();
            $data->contact_lens_detail = DB::table('inventory_contact_lens_details')->where('product_id', $id)->first();
        }

        return response()->json([
            'resource' => $resource,
            'id' => $id,
            'data' => $data,
        ]);
    }

    public function apiResourceStore(Request $request, string $resource)
    {
        $this->assertResource($resource);

        $id = match ($resource) {
            'products' => $this->createProductRecord($request),
            'product-categories' => $this->createCategoryRecord($request),
            'suppliers' => $this->createSupplierRecord($request),
            'locations' => $this->createLocationRecord($request),
            'purchase-orders' => $this->createPurchaseOrderRecord($request),
            'goods-receipts' => $this->createGoodsReceiptRecord($request),
            'adjustments' => $this->createAdjustmentRecord($request),
            'transfers' => $this->createTransferRecord($request),
            'stock-counts' => $this->createStockCountRecord($request),
            'reservations' => $this->reserveStockFromRequest($request),
            'supplier-returns' => $this->createSupplierReturnRecord($request),
            default => throw ValidationException::withMessages(['resource' => 'This resource is read-only or is managed through a parent record.']),
        };

        return response()->json([
            'resource' => $resource,
            'status' => 'created',
            'id' => $id,
            'data' => DB::table($this->resourceTable($resource))->where('id', $id)->first(),
        ], 201);
    }

    public function apiResourceUpdate(Request $request, int $id, string $resource)
    {
        $this->assertResource($resource);

        $updated = match ($resource) {
            'products' => $this->updateProductRecord($request, $id),
            'product-categories' => $this->updateSimpleRecord($request, $resource, $id, ['name', 'type', 'parent_id']),
            'suppliers' => $this->updateSimpleRecord($request, $resource, $id, ['name', 'contact_person', 'phone', 'email', 'address', 'tax_number', 'is_active']),
            'locations' => $this->updateSimpleRecord($request, $resource, $id, ['code', 'name', 'type', 'is_sellable', 'is_active']),
            'purchase-orders' => $this->updatePurchaseOrderRecord($request, $id),
            'transfers' => $this->updateTransferRecord($request, $id),
            'stock-counts' => $this->approveStockCountRecord($request, $id),
            default => $this->updateSimpleRecord($request, $resource, $id, ['status', 'notes']),
        };

        return response()->json([
            'resource' => $resource,
            'status' => 'updated',
            'id' => $id,
            'data' => $updated,
        ]);
    }

    public function apiOperation(Request $request, string $operation)
    {
        $result = match ($operation) {
            'scan-barcode' => $this->scanBarcode($request),
            'reserve-stock' => ['reservation_id' => $this->reserveStockFromRequest($request)],
            'release-reservation' => $this->releaseReservationFromRequest($request),
            'issue-sale' => $this->issueStockFromRequest($request, 'sale_issue'),
            'receive-return' => $this->receiveReturnFromRequest($request),
            'write-off' => ['adjustment_id' => $this->createAdjustmentRecord($request->merge(['reason' => $request->input('reason', 'damaged_write_off')]))],
            'post-adjustment' => ['adjustment_id' => $this->createAdjustmentRecord($request)],
            'approve-transfer' => $this->transferAction($request, 'approved'),
            'ship-transfer' => $this->shipTransfer($request),
            'receive-transfer' => $this->receiveTransfer($request),
            default => abort(404),
        };

        return response()->json([
            'operation' => $operation,
            'status' => 'posted',
            'result' => $result,
        ]);
    }

    private function resourceQuery(string $resource, Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $branchId = $request->integer('branch_id');

        return match ($resource) {
            'products' => DB::table('products')
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
                ->leftJoin('inventory_stock_levels', 'inventory_stock_levels.product_id', '=', 'products.id')
                ->selectRaw('products.id, products.sku, products.barcode, products.type, products.name, products.brand, products.model, products.color, products.size, products.cost_price, products.retail_price, products.is_active, product_categories.name as category_name, suppliers.name as supplier_name, coalesce(sum(inventory_stock_levels.qty_on_hand), 0) as qty_on_hand, coalesce(sum(inventory_stock_levels.qty_reserved), 0) as qty_reserved')
                ->when($branchId, fn ($query) => $query->where(function ($inner) use ($branchId): void {
                    $inner->where('inventory_stock_levels.branch_id', $branchId)->orWhereNull('inventory_stock_levels.branch_id');
                }))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q): void {
                    $inner->where('products.name', 'like', "%{$q}%")
                        ->orWhere('products.sku', 'like', "%{$q}%")
                        ->orWhere('products.barcode', 'like', "%{$q}%")
                        ->orWhere('products.brand', 'like', "%{$q}%")
                        ->orWhere('product_categories.name', 'like', "%{$q}%");
                }))
                ->when($request->query('type'), fn ($query, $type) => $query->where('products.type', $type))
                ->when($request->query('category_id'), fn ($query, $category) => $query->where('products.product_category_id', $category))
                ->when($request->query('supplier_id'), fn ($query, $supplier) => $query->where('products.supplier_id', $supplier))
                ->groupBy('products.id', 'products.sku', 'products.barcode', 'products.type', 'products.name', 'products.brand', 'products.model', 'products.color', 'products.size', 'products.cost_price', 'products.retail_price', 'products.is_active', 'product_categories.name', 'suppliers.name')
                ->orderBy('products.name'),
            'stock-levels' => DB::table('inventory_stock_levels')
                ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
                ->join('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
                ->selectRaw('inventory_stock_levels.*, products.sku, products.name as product_name, branches.name as branch_name, (qty_on_hand - qty_reserved) as available_stock')
                ->when($branchId, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))
                ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q): void {
                    $inner->where('products.name', 'like', "%{$q}%")->orWhere('products.sku', 'like', "%{$q}%");
                }))
                ->orderBy('products.name'),
            'stock-movements' => DB::table('inventory_stock_movements')
                ->join('products', 'products.id', '=', 'inventory_stock_movements.product_id')
                ->join('branches', 'branches.id', '=', 'inventory_stock_movements.branch_id')
                ->select('inventory_stock_movements.*', 'products.sku', 'products.name as product_name', 'branches.name as branch_name')
                ->when($branchId, fn ($query) => $query->where('inventory_stock_movements.branch_id', $branchId))
                ->orderByDesc('inventory_stock_movements.occurred_at'),
            'purchase-orders' => DB::table('inventory_purchase_orders')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'inventory_purchase_orders.supplier_id')
                ->leftJoin('branches', 'branches.id', '=', 'inventory_purchase_orders.branch_id')
                ->select('inventory_purchase_orders.*', 'suppliers.name as supplier_name', 'branches.name as branch_name')
                ->when($branchId, fn ($query) => $query->where('inventory_purchase_orders.branch_id', $branchId))
                ->when($request->query('status'), fn ($query, $status) => $query->where('inventory_purchase_orders.status', $status))
                ->orderByDesc('inventory_purchase_orders.id'),
            'goods-receipts' => DB::table('inventory_goods_receipts')
                ->join('suppliers', 'suppliers.id', '=', 'inventory_goods_receipts.supplier_id')
                ->join('branches', 'branches.id', '=', 'inventory_goods_receipts.branch_id')
                ->select('inventory_goods_receipts.*', 'suppliers.name as supplier_name', 'branches.name as branch_name')
                ->when($branchId, fn ($query) => $query->where('inventory_goods_receipts.branch_id', $branchId))
                ->orderByDesc('inventory_goods_receipts.received_on'),
            'transfers' => DB::table('inventory_transfers')
                ->join('branches as from_branch', 'from_branch.id', '=', 'inventory_transfers.from_branch_id')
                ->join('branches as to_branch', 'to_branch.id', '=', 'inventory_transfers.to_branch_id')
                ->select('inventory_transfers.*', 'from_branch.name as from_branch_name', 'to_branch.name as to_branch_name')
                ->when($request->query('status'), fn ($query, $status) => $query->where('inventory_transfers.status', $status))
                ->orderByDesc('inventory_transfers.id'),
            'stock-counts' => DB::table('inventory_stock_counts')
                ->join('branches', 'branches.id', '=', 'inventory_stock_counts.branch_id')
                ->select('inventory_stock_counts.*', 'branches.name as branch_name')
                ->when($branchId, fn ($query) => $query->where('inventory_stock_counts.branch_id', $branchId))
                ->orderByDesc('inventory_stock_counts.count_date'),
            default => DB::table($this->resourceTable($resource))->orderByDesc('id'),
        };
    }

    private function paginateQuery($query, Request $request): array
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $countQuery = clone $query;
        $total = $countQuery->groups
            ? DB::query()->fromSub($countQuery, 'resource_count')->count()
            : $countQuery->count();
        $data = (clone $query)->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    private function createProductRecord(Request $request): int
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'tax_type' => ['nullable', 'string', 'max:255'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $companyId = $this->companyId();
            $branchId = (int) ($validated['branch_id'] ?? DB::table('branches')->orderBy('id')->value('id'));
            $productId = DB::table('products')->insertGetId([
                'company_id' => $companyId,
                'product_category_id' => $validated['product_category_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'sku' => $validated['sku'] ?? $this->nextCode('SKU', 'products', 'sku'),
                'barcode' => $validated['barcode'] ?? null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'brand' => $validated['brand'] ?? null,
                'model' => $validated['model'] ?? null,
                'color' => $validated['color'] ?? null,
                'size' => $validated['size'] ?? null,
                'material' => $validated['material'] ?? null,
                'cost_price' => $validated['cost_price'] ?? 0,
                'retail_price' => $validated['retail_price'] ?? 0,
                'tax_type' => $validated['tax_type'] ?? 'standard',
                'tax_rate' => $validated['tax_rate'] ?? 15,
                'default_minimum_stock_level' => (float) $request->input('minimum_stock_level', 0),
                'default_reorder_point' => (float) $request->input('reorder_point', 0),
                'track_batches' => $request->boolean('track_batches', in_array($validated['type'], ['contact_lens', 'cleaning_solution', 'consumable'], true)),
                'track_serials' => $request->boolean('track_serials'),
                'track_expiry' => $request->boolean('track_expiry', in_array($validated['type'], ['contact_lens', 'cleaning_solution', 'consumable'], true)),
                'is_active' => $request->boolean('is_active', true),
                'attributes' => $request->filled('attributes') ? json_encode($request->input('attributes')) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! empty($validated['barcode'])) {
                DB::table('inventory_product_barcodes')->insert([
                    'product_id' => $productId,
                    'barcode' => $validated['barcode'],
                    'barcode_type' => 'EAN13',
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ((float) ($validated['opening_stock'] ?? 0) > 0 && $branchId) {
                $this->increaseStock($productId, $branchId, (float) $validated['opening_stock'], (float) ($validated['cost_price'] ?? 0), 'opening_stock', $productId, 'Opening stock', $now);
            }

            $this->auditAction('inventory.product.created', 'product', $productId, $branchId ?: null, null, ['name' => $validated['name']], $now);

            return $productId;
        });
    }

    private function updateProductRecord(Request $request, int $id): object
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (! $product) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($id)],
            'type' => ['sometimes', 'required', Rule::in(app(SetupOptions::class)->keys('product_types'))],
            'product_category_id' => ['sometimes', 'required', 'integer', 'exists:product_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'retail_price' => ['sometimes', 'numeric', 'min:0'],
            'tax_type' => ['sometimes', Rule::in(app(SetupOptions::class)->keys('tax_settings'))],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $request->boolean('is_active');
        }
        $data['updated_at'] = now();
        DB::table('products')->where('id', $id)->update($data);
        $this->auditAction('inventory.product.updated', 'product', $id, null, (array) $product, $data, now());

        return DB::table('products')->where('id', $id)->first();
    }

    private function createCategoryRecord(Request $request): int
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        return DB::table('product_categories')->insertGetId([
            'company_id' => $this->companyId(),
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSupplierRecord(Request $request): int
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tax_number' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::table('suppliers')->insertGetId([
            'company_id' => $this->companyId(),
            ...$validated,
            'opening_balance' => (float) $request->input('opening_balance', 0),
            'is_active' => $request->boolean('is_active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLocationRecord(Request $request): int
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::table('inventory_locations')->insertGetId([
            'branch_id' => $validated['branch_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'stock_room',
            'is_sellable' => $request->boolean('is_sellable', true),
            'is_active' => $request->boolean('is_active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPurchaseOrderRecord(Request $request): int
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'expected_on' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.ordered_quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated, $request): int {
            $now = now();
            $subtotal = 0;
            $tax = 0;
            foreach ($validated['lines'] as $line) {
                $lineSubtotal = (float) $line['ordered_quantity'] * (float) $line['unit_cost'];
                $subtotal += $lineSubtotal;
                $tax += $lineSubtotal * ((float) ($line['tax_rate'] ?? 0) / 100);
            }

            $poId = DB::table('inventory_purchase_orders')->insertGetId([
                'company_id' => $this->companyId(),
                'branch_id' => $validated['branch_id'] ?? null,
                'supplier_id' => $validated['supplier_id'],
                'po_number' => $this->nextCode('PO', 'inventory_purchase_orders', 'po_number'),
                'status' => $request->input('status', 'approved'),
                'ordered_on' => $request->input('ordered_on', $now->toDateString()),
                'expected_on' => $validated['expected_on'] ?? null,
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($tax, 2),
                'grand_total' => round($subtotal + $tax, 2),
                'notes' => $request->input('notes'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($validated['lines'] as $line) {
                $lineSubtotal = (float) $line['ordered_quantity'] * (float) $line['unit_cost'];
                $lineTax = $lineSubtotal * ((float) ($line['tax_rate'] ?? 0) / 100);
                DB::table('inventory_purchase_order_lines')->insert([
                    'inventory_purchase_order_id' => $poId,
                    'product_id' => $line['product_id'],
                    'ordered_quantity' => $line['ordered_quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'line_total' => round($lineSubtotal + $lineTax, 2),
                    'expected_on' => $validated['expected_on'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $poId;
        });
    }

    private function createGoodsReceiptRecord(Request $request): int
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'inventory_purchase_order_id' => ['nullable', 'integer'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'array'],
            'product_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = $validated['lines'] ?? [[
            'product_id' => $validated['product_id'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'unit_cost' => $validated['unit_cost'] ?? null,
            'lot_number' => $request->input('lot_number'),
            'expires_on' => $request->input('expires_on'),
        ]];

        $allowNegative = $request->user()->hasRole('erp-admin') || $request->user()->hasPermission('inventory.negative_stock');

        return DB::transaction(function () use ($validated, $lines): int {
            $now = now();
            $receiptId = DB::table('inventory_goods_receipts')->insertGetId([
                'inventory_purchase_order_id' => $validated['inventory_purchase_order_id'] ?? null,
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'receipt_number' => $this->nextCode('GR', 'inventory_goods_receipts', 'receipt_number'),
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'received_on' => $now->toDateString(),
                'status' => 'posted',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $rawLine) {
                if (empty($rawLine['product_id']) || (float) ($rawLine['quantity'] ?? 0) <= 0) {
                    throw ValidationException::withMessages(['lines' => 'Each receipt line needs a product and quantity.']);
                }
                $batchId = DB::table('inventory_batches')->insertGetId([
                    'product_id' => $rawLine['product_id'],
                    'supplier_id' => $validated['supplier_id'],
                    'lot_number' => $rawLine['lot_number'] ?? null,
                    'batch_number' => $rawLine['batch_number'] ?? ($rawLine['lot_number'] ?? null),
                    'expires_on' => $rawLine['expires_on'] ?? null,
                    'received_on' => $now->toDateString(),
                    'initial_quantity' => $rawLine['quantity'],
                    'unit_cost' => $rawLine['unit_cost'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('inventory_goods_receipt_lines')->insert([
                    'inventory_goods_receipt_id' => $receiptId,
                    'product_id' => $rawLine['product_id'],
                    'inventory_batch_id' => $batchId,
                    'received_quantity' => $rawLine['quantity'],
                    'accepted_quantity' => $rawLine['accepted_quantity'] ?? $rawLine['quantity'],
                    'rejected_quantity' => $rawLine['rejected_quantity'] ?? 0,
                    'unit_cost' => $rawLine['unit_cost'] ?? 0,
                    'lot_number' => $rawLine['lot_number'] ?? null,
                    'batch_number' => $rawLine['batch_number'] ?? null,
                    'expires_on' => $rawLine['expires_on'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->increaseStock((int) $rawLine['product_id'], (int) $validated['branch_id'], (float) ($rawLine['accepted_quantity'] ?? $rawLine['quantity']), (float) ($rawLine['unit_cost'] ?? 0), 'inventory_goods_receipt', $receiptId, 'Goods received', $now, $batchId);
            }

            app(AccountingPoster::class)->postGoodsReceipt($receiptId);

            return $receiptId;
        });
    }

    private function createAdjustmentRecord(Request $request): int
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['nullable', 'array'],
            'product_id' => ['nullable', 'integer'],
            'quantity_change' => ['nullable', 'numeric', 'not_in:0'],
        ]);

        $lines = $validated['lines'] ?? [[
            'product_id' => $validated['product_id'] ?? null,
            'quantity_change' => $validated['quantity_change'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]];

        return DB::transaction(function () use ($validated, $lines): int {
            $now = now();
            $adjustmentId = DB::table('inventory_adjustments')->insertGetId([
                'branch_id' => $validated['branch_id'],
                'adjustment_number' => $this->nextCode('ADJ', 'inventory_adjustments', 'adjustment_number'),
                'reason' => $validated['reason'] ?? 'stock_adjustment',
                'status' => 'posted',
                'approved_at' => $now,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $change = (float) ($line['quantity_change'] ?? $line['variance_quantity'] ?? 0);
                if ($productId <= 0 || abs($change) <= 0.0001) {
                    throw ValidationException::withMessages(['lines' => 'Each adjustment line needs a product and non-zero quantity change.']);
                }
                $stock = $this->ensureStockRow($productId, (int) $validated['branch_id']);
                $newBalance = (float) $stock->qty_on_hand + $change;
                if ($newBalance < -0.0001 && ! $allowNegative) {
                    throw ValidationException::withMessages(['lines' => 'An adjustment line would create negative stock.']);
                }
                DB::table('inventory_stock_levels')->where('id', $stock->id)->update(['qty_on_hand' => $newBalance, 'updated_at' => $now]);
                DB::table('inventory_adjustment_lines')->insert([
                    'inventory_adjustment_id' => $adjustmentId,
                    'product_id' => $productId,
                    'inventory_location_id' => $stock->inventory_location_id,
                    'system_quantity' => $stock->qty_on_hand,
                    'adjusted_quantity' => $newBalance,
                    'variance_quantity' => $change,
                    'unit_cost' => $stock->average_cost,
                    'notes' => $line['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->movement($productId, (int) $validated['branch_id'], $stock->inventory_location_id, $change > 0 ? 'stock_adjustment' : ($validated['reason'] ?? 'stock_adjustment'), $change > 0 ? 'in' : 'out', abs($change), (float) $stock->average_cost, $newBalance, 'inventory_adjustment', $adjustmentId, $validated['reason'] ?? 'stock_adjustment', $now);
            }

            app(AccountingPoster::class)->postInventoryAdjustment($adjustmentId);
            $this->auditAction('inventory.stock.adjusted', 'inventory_adjustment', $adjustmentId, (int) $validated['branch_id'], null, $validated, $now);

            return $adjustmentId;
        });
    }

    private function createTransferRecord(Request $request): int
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'integer'],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        return DB::transaction(function () use ($validated, $request): int {
            $now = now();
            $transferId = DB::table('inventory_transfers')->insertGetId([
                'from_branch_id' => $validated['from_branch_id'],
                'to_branch_id' => $validated['to_branch_id'],
                'from_location_id' => $this->sellableLocationId((int) $validated['from_branch_id']),
                'to_location_id' => $this->sellableLocationId((int) $validated['to_branch_id']),
                'transfer_number' => $this->nextCode('TRF', 'inventory_transfers', 'transfer_number'),
                'status' => $request->input('status', 'approved'),
                'notes' => $request->input('notes'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($validated['lines'] as $line) {
                DB::table('inventory_transfer_lines')->insert([
                    'inventory_transfer_id' => $transferId,
                    'product_id' => $line['product_id'],
                    'requested_quantity' => $line['quantity'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $transferId;
        });
    }

    private function createStockCountRecord(Request $request): int
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'lines' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($validated, $request): int {
            $now = now();
            $countId = DB::table('inventory_stock_counts')->insertGetId([
                'branch_id' => $validated['branch_id'],
                'inventory_location_id' => $request->integer('inventory_location_id') ?: $this->sellableLocationId((int) $validated['branch_id']),
                'count_number' => $this->nextCode('CNT', 'inventory_stock_counts', 'count_number'),
                'status' => 'draft',
                'count_date' => $request->input('count_date', $now->toDateString()),
                'notes' => $request->input('notes'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (($validated['lines'] ?? []) as $line) {
                $stock = $this->ensureStockRow((int) $line['product_id'], (int) $validated['branch_id']);
                $counted = (float) ($line['counted_quantity'] ?? $stock->qty_on_hand);
                DB::table('inventory_stock_count_lines')->insert([
                    'inventory_stock_count_id' => $countId,
                    'product_id' => $line['product_id'],
                    'system_quantity' => $stock->qty_on_hand,
                    'counted_quantity' => $counted,
                    'variance_quantity' => $counted - (float) $stock->qty_on_hand,
                    'count_status' => 'counted',
                    'notes' => $line['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $countId;
        });
    }

    private function createSupplierReturnRecord(Request $request): int
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        return DB::transaction(function () use ($validated, $request): int {
            $now = now();
            $returnId = DB::table('inventory_supplier_returns')->insertGetId([
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'return_number' => $this->nextCode('SRT', 'inventory_supplier_returns', 'return_number'),
                'status' => 'posted',
                'reason' => $request->input('reason', 'supplier_return'),
                'return_on' => $now->toDateString(),
                'notes' => $request->input('notes'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($validated['lines'] as $line) {
                $stock = $this->ensureStockRow((int) $line['product_id'], (int) $validated['branch_id']);
                $quantity = (float) $line['quantity'];
                if ($quantity > (float) $stock->qty_on_hand + 0.0001) {
                    throw ValidationException::withMessages(['lines' => 'Supplier return quantity exceeds branch stock.']);
                }
                DB::table('inventory_supplier_return_lines')->insert([
                    'inventory_supplier_return_id' => $returnId,
                    'product_id' => $line['product_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $stock->average_cost,
                    'condition' => $line['condition'] ?? 'return_to_supplier',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $newBalance = (float) $stock->qty_on_hand - $quantity;
                DB::table('inventory_stock_levels')->where('id', $stock->id)->update(['qty_on_hand' => $newBalance, 'updated_at' => $now]);
                $this->movement((int) $line['product_id'], (int) $validated['branch_id'], $stock->inventory_location_id, 'supplier_return', 'out', $quantity, (float) $stock->average_cost, $newBalance, 'inventory_supplier_return', $returnId, 'Supplier return', $now);
            }

            return $returnId;
        });
    }

    private function updateSimpleRecord(Request $request, string $resource, int $id, array $allowed): object
    {
        $table = $this->resourceTable($resource);
        $before = DB::table($table)->where('id', $id)->first();
        if (! $before) {
            abort(404);
        }
        $data = array_intersect_key($request->all(), array_flip($allowed));
        if ($data === []) {
            throw ValidationException::withMessages(['resource' => 'No editable fields were provided.']);
        }
        $data['updated_at'] = now();
        DB::table($table)->where('id', $id)->update($data);
        $after = DB::table($table)->where('id', $id)->first();
        $this->auditAction('inventory.'.$resource.'.updated', $table, $id, $after->branch_id ?? null, (array) $before, (array) $after, $data['updated_at']);

        return $after;
    }

    private function updatePurchaseOrderRecord(Request $request, int $id): object
    {
        return $this->updateSimpleRecord($request, 'purchase-orders', $id, ['status', 'expected_on', 'notes', 'approved_by', 'approved_at']);
    }

    private function updateTransferRecord(Request $request, int $id): object
    {
        $status = $request->input('status');
        if (in_array($status, ['approved', 'in_transit', 'received'], true)) {
            $request->merge(['transfer_id' => $id]);
            match ($status) {
                'approved' => $this->transferAction($request, 'approved'),
                'in_transit' => $this->shipTransfer($request),
                'received' => $this->receiveTransfer($request),
            };
        }

        return DB::table('inventory_transfers')->where('id', $id)->first();
    }

    private function approveStockCountRecord(Request $request, int $id): object
    {
        $count = DB::table('inventory_stock_counts')->where('id', $id)->first();
        if (! $count) {
            abort(404);
        }
        if ($request->input('status') !== 'approved') {
            return $this->updateSimpleRecord($request, 'stock-counts', $id, ['status', 'notes']);
        }

        DB::transaction(function () use ($count): void {
            $now = now();
            $lines = DB::table('inventory_stock_count_lines')->where('inventory_stock_count_id', $count->id)->get();
            foreach ($lines as $line) {
                if (abs((float) $line->variance_quantity) > 0.0001) {
                    $request = new Request([
                        'branch_id' => $count->branch_id,
                        'reason' => 'stock_count_variance',
                        'lines' => [[
                            'product_id' => $line->product_id,
                            'quantity_change' => $line->variance_quantity,
                            'notes' => 'Stock count '.$count->count_number,
                        ]],
                    ]);
                    $this->createAdjustmentRecord($request);
                }
            }
            DB::table('inventory_stock_counts')->where('id', $count->id)->update([
                'status' => 'approved',
                'approved_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return DB::table('inventory_stock_counts')->where('id', $id)->first();
    }

    private function scanBarcode(Request $request): array
    {
        $barcode = trim((string) $request->input('barcode', $request->query('barcode', $request->query('q', ''))));
        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'Barcode is required.']);
        }

        $product = DB::table('products')
            ->leftJoin('inventory_product_barcodes', 'inventory_product_barcodes.product_id', '=', 'products.id')
            ->select('products.*')
            ->where('products.barcode', $barcode)
            ->orWhere('inventory_product_barcodes.barcode', $barcode)
            ->first();

        if (! $product) {
            throw ValidationException::withMessages(['barcode' => 'No product found for this barcode.']);
        }

        return [
            'product' => $product,
            'stock' => DB::table('inventory_stock_levels')->where('product_id', $product->id)->get(),
        ];
    }

    private function reserveStockFromRequest(Request $request): int
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'source_type' => ['required', 'string', 'max:255'],
            'source_id' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($validated): int {
            $now = now();
            $stock = $this->ensureStockRow((int) $validated['product_id'], (int) $validated['branch_id']);
            $available = (float) $stock->qty_on_hand - (float) $stock->qty_reserved;
            if ($available + 0.0001 < (float) $validated['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Not enough available stock to reserve.']);
            }
            DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                'qty_reserved' => (float) $stock->qty_reserved + (float) $validated['quantity'],
                'updated_at' => $now,
            ]);

            $reservationId = DB::table('inventory_reservations')->insertGetId([
                'product_id' => $validated['product_id'],
                'branch_id' => $validated['branch_id'],
                'source_type' => $validated['source_type'],
                'source_id' => $validated['source_id'],
                'quantity' => $validated['quantity'],
                'status' => 'active',
                'reserved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->auditAction('inventory.stock.reserved', 'inventory_reservation', $reservationId, (int) $validated['branch_id'], null, $validated, $now);

            return $reservationId;
        });
    }

    private function releaseReservationFromRequest(Request $request): array
    {
        $reservationId = $request->integer('reservation_id');
        $reservation = $reservationId
            ? DB::table('inventory_reservations')->where('id', $reservationId)->first()
            : DB::table('inventory_reservations')
                ->where('source_type', $request->input('source_type'))
                ->where('source_id', $request->integer('source_id'))
                ->where('status', 'active')
                ->first();
        if (! $reservation) {
            throw ValidationException::withMessages(['reservation_id' => 'Active reservation was not found.']);
        }

        DB::transaction(function () use ($reservation): void {
            $now = now();
            $stock = $this->ensureStockRow((int) $reservation->product_id, (int) $reservation->branch_id);
            DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                'qty_reserved' => max(0, (float) $stock->qty_reserved - (float) $reservation->quantity),
                'updated_at' => $now,
            ]);
            DB::table('inventory_reservations')->where('id', $reservation->id)->update([
                'status' => 'released',
                'released_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return ['reservation_id' => $reservation->id, 'released_quantity' => (float) $reservation->quantity];
    }

    private function issueStockFromRequest(Request $request, string $movementType): array
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($validated, $movementType): array {
            $now = now();
            $stock = $this->ensureStockRow((int) $validated['product_id'], (int) $validated['branch_id']);
            $available = (float) $stock->qty_on_hand - (float) $stock->qty_reserved;
            if ($available + 0.0001 < (float) $validated['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Not enough available stock to issue.']);
            }
            $newBalance = (float) $stock->qty_on_hand - (float) $validated['quantity'];
            DB::table('inventory_stock_levels')->where('id', $stock->id)->update(['qty_on_hand' => $newBalance, 'updated_at' => $now]);
            $movementId = $this->movement((int) $validated['product_id'], (int) $validated['branch_id'], $stock->inventory_location_id, $movementType, 'out', (float) $validated['quantity'], (float) $stock->average_cost, $newBalance, $validated['reference_type'] ?? $movementType, $validated['reference_id'] ?? null, $movementType, $now);

            return ['movement_id' => $movementId, 'balance_after' => $newBalance];
        });
    }

    private function receiveReturnFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
        ]);

        $movementId = $this->increaseStock((int) $validated['product_id'], (int) $validated['branch_id'], (float) $validated['quantity'], (float) ($validated['unit_cost'] ?? 0), $validated['reference_type'] ?? 'sales_return', $validated['reference_id'] ?? null, 'Returned stock', now());

        return ['movement_id' => $movementId];
    }

    private function transferAction(Request $request, string $status): array
    {
        $id = $request->integer('transfer_id');
        $transfer = DB::table('inventory_transfers')->where('id', $id)->first();
        if (! $transfer) {
            throw ValidationException::withMessages(['transfer_id' => 'Transfer was not found.']);
        }
        DB::table('inventory_transfers')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);

        return ['transfer_id' => $id, 'status' => $status];
    }

    private function shipTransfer(Request $request): array
    {
        $id = $request->integer('transfer_id');
        $transfer = DB::table('inventory_transfers')->where('id', $id)->first();
        if (! $transfer) {
            throw ValidationException::withMessages(['transfer_id' => 'Transfer was not found.']);
        }

        DB::transaction(function () use ($transfer): void {
            $now = now();
            $lines = DB::table('inventory_transfer_lines')->where('inventory_transfer_id', $transfer->id)->get();
            foreach ($lines as $line) {
                $stock = $this->ensureStockRow((int) $line->product_id, (int) $transfer->from_branch_id);
                $available = (float) $stock->qty_on_hand - (float) $stock->qty_reserved;
                $quantity = (float) $line->requested_quantity;
                if ($quantity > $available + 0.0001) {
                    throw ValidationException::withMessages(['transfer_id' => 'Transfer cannot ship because branch stock is insufficient.']);
                }
                $newBalance = (float) $stock->qty_on_hand - $quantity;
                DB::table('inventory_stock_levels')->where('id', $stock->id)->update(['qty_on_hand' => $newBalance, 'updated_at' => $now]);
                DB::table('inventory_transfer_lines')->where('id', $line->id)->update(['shipped_quantity' => $quantity, 'updated_at' => $now]);
                $this->movement((int) $line->product_id, (int) $transfer->from_branch_id, $stock->inventory_location_id, 'transfer_ship', 'out', $quantity, (float) $stock->average_cost, $newBalance, 'inventory_transfer', $transfer->id, 'Stock transfer shipped', $now);
            }
            DB::table('inventory_transfers')->where('id', $transfer->id)->update(['status' => 'in_transit', 'shipped_at' => $now, 'updated_at' => $now]);
        });

        return ['transfer_id' => $id, 'status' => 'in_transit'];
    }

    private function receiveTransfer(Request $request): array
    {
        $id = $request->integer('transfer_id');
        $transfer = DB::table('inventory_transfers')->where('id', $id)->first();
        if (! $transfer) {
            throw ValidationException::withMessages(['transfer_id' => 'Transfer was not found.']);
        }
        if ($transfer->status !== 'in_transit') {
            throw ValidationException::withMessages(['transfer_id' => 'Only an in-transit transfer can be received.']);
        }

        DB::transaction(function () use ($transfer): void {
            $now = now();
            $lines = DB::table('inventory_transfer_lines')->where('inventory_transfer_id', $transfer->id)->get();
            foreach ($lines as $line) {
                $quantity = (float) $line->shipped_quantity;
                $movementId = $this->increaseStock((int) $line->product_id, (int) $transfer->to_branch_id, $quantity, 0, 'inventory_transfer', $transfer->id, 'Stock transfer received', $now);
                DB::table('inventory_transfer_lines')->where('id', $line->id)->update(['received_quantity' => $quantity, 'updated_at' => $now]);
            }
            DB::table('inventory_transfers')->where('id', $transfer->id)->update(['status' => 'received', 'received_at' => $now, 'updated_at' => $now]);
        });

        return ['transfer_id' => $id, 'status' => 'received'];
    }

    private function increaseStock(int $productId, int $branchId, float $quantity, float $unitCost, string $referenceType, mixed $referenceId, string $reason, $now, ?int $batchId = null): int
    {
        $stock = $this->ensureStockRow($productId, $branchId);
        $newBalance = (float) $stock->qty_on_hand + $quantity;
        $averageCost = $unitCost > 0 ? $unitCost : (float) $stock->average_cost;
        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_on_hand' => $newBalance,
            'average_cost' => $averageCost,
            'updated_at' => $now,
        ]);

        $movementType = match ($referenceType) {
            'opening_stock' => 'opening_stock',
            'sales_return' => 'sale_return',
            'inventory_transfer' => 'transfer_receive',
            default => 'purchase_receipt',
        };

        return $this->movement($productId, $branchId, $stock->inventory_location_id, $movementType, 'in', $quantity, $averageCost, $newBalance, $referenceType, $referenceId, $reason, $now, $batchId);
    }

    private function movement(int $productId, int $branchId, mixed $locationId, string $movementType, string $direction, float $quantity, float $unitCost, float $balanceAfter, ?string $referenceType, mixed $referenceId, ?string $reason, $now, ?int $batchId = null): int
    {
        return DB::table('inventory_stock_movements')->insertGetId([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'inventory_location_id' => $locationId,
            'inventory_batch_id' => $batchId,
            'movement_type' => $movementType,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureStockRow(int $productId, int $branchId): object
    {
        $locationId = $this->sellableLocationId($branchId);
        $stock = DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('inventory_location_id', $locationId)
            ->first();

        if ($stock) {
            return $stock;
        }

        $product = DB::table('products')->where('id', $productId)->first();
        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'Product was not found.']);
        }

        $stockId = DB::table('inventory_stock_levels')->insertGetId([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'inventory_location_id' => $locationId,
            'qty_on_hand' => 0,
            'qty_reserved' => 0,
            'minimum_stock_level' => $product->default_minimum_stock_level ?? 0,
            'reorder_point' => $product->default_reorder_point ?? 0,
            'average_cost' => $product->cost_price ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('inventory_stock_levels')->where('id', $stockId)->first();
    }

    private function sellableLocationId(int $branchId): ?int
    {
        $locationId = DB::table('inventory_locations')->where('branch_id', $branchId)->where('is_sellable', true)->value('id');
        if ($locationId) {
            return (int) $locationId;
        }

        return DB::table('inventory_locations')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'SELL-'.$branchId,
            'name' => 'Sellable Stock',
            'type' => 'showroom',
            'is_sellable' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function companyId(): int
    {
        return (int) DB::table('companies')->orderBy('id')->value('id');
    }

    private function nextCode(string $prefix, string $table, string $column): string
    {
        do {
            $code = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (DB::table($table)->where($column, $code)->exists());

        return $code;
    }

    private function auditAction(string $action, string $type, int $id, ?int $branchId, ?array $before, ?array $after, $now): void
    {
        DB::table('audit_logs')->insert([
            'branch_id' => $branchId,
            'action' => $action,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'before_values' => $before ? json_encode($before) : null,
            'after_values' => $after ? json_encode($after) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function assertResource(string $resource): void
    {
        if (! array_key_exists($resource, $this->resourceTables())) {
            abort(404);
        }
    }

    private function resourceTable(string $resource): string
    {
        return $this->resourceTables()[$resource] ?? abort(404);
    }

    private function resourceTables(): array
    {
        return [
            'product-categories' => 'product_categories',
            'suppliers' => 'suppliers',
            'products' => 'products',
            'frame-details' => 'inventory_frame_details',
            'lens-details' => 'inventory_lens_details',
            'contact-lens-details' => 'inventory_contact_lens_details',
            'product-barcodes' => 'inventory_product_barcodes',
            'locations' => 'inventory_locations',
            'batches' => 'inventory_batches',
            'serials' => 'inventory_serials',
            'stock-levels' => 'inventory_stock_levels',
            'stock-movements' => 'inventory_stock_movements',
            'purchase-orders' => 'inventory_purchase_orders',
            'goods-receipts' => 'inventory_goods_receipts',
            'supplier-returns' => 'inventory_supplier_returns',
            'adjustments' => 'inventory_adjustments',
            'transfers' => 'inventory_transfers',
            'stock-counts' => 'inventory_stock_counts',
            'reservations' => 'inventory_reservations',
            'valuation-snapshots' => 'inventory_valuation_snapshots',
        ];
    }

    private function resourceCapabilities(string $resource): array
    {
        return [
            'search' => in_array($resource, ['products', 'stock-levels'], true),
            'filter' => ['branch_id', 'type', 'category_id', 'supplier_id', 'status'],
            'pagination' => true,
            'actions' => match ($resource) {
                'products', 'suppliers', 'product-categories', 'locations' => ['create', 'update'],
                'purchase-orders' => ['create', 'approve', 'receive'],
                'goods-receipts' => ['create'],
                'adjustments' => ['post'],
                'transfers' => ['create', 'approve', 'ship', 'receive'],
                'stock-counts' => ['create', 'approve'],
                'reservations' => ['create', 'release'],
                default => ['read'],
            },
        ];
    }

    private function inventoryData(Request $request): array
    {
        $branches = DB::table('branches')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch_id') ?: ($branches->first()->id ?? 0));
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');

        return [
            'productTypes' => app(SetupOptions::class)->options('product_types'),
            'taxTypes' => app(SetupOptions::class)->options('tax_settings'),
            'brands' => app(SetupOptions::class)->options('brands'),
            'frameTypes' => app(SetupOptions::class)->options('frame_types'),
            'lensTypes' => app(SetupOptions::class)->options('lens_types'),
            'lensMaterials' => app(SetupOptions::class)->options('lens_materials'),
            'lensCoatings' => app(SetupOptions::class)->options('lens_coatings'),
            'lensTints' => app(SetupOptions::class)->options('lens_tints'),
            'branches' => $branches,
            'branchId' => $branchId,
            'query' => $query,
            'type' => $type,
            'metrics' => $this->metrics($branchId),
            'products' => $this->products($branchId, $query, $type),
            'selectedProduct' => $this->selectedProduct($branchId, $request->integer('product_id')),
            'categories' => DB::table('product_categories')->orderBy('name')->get(),
            'suppliers' => DB::table('suppliers')->where('is_active', true)->orderBy('name')->get(),
            'lowStockAlerts' => $this->lowStockAlerts($branchId),
            'movements' => $this->movements($branchId),
            'purchaseOrders' => $this->purchaseOrders($branchId),
            'goodsReceipts' => $this->goodsReceipts($branchId),
            'transfers' => $this->transfers(),
            'stockCounts' => $this->stockCounts($branchId),
            'reports' => config('inventory.reports'),
        ];
    }

    private function metrics(int $branchId): array
    {
        $stockScope = DB::table('inventory_stock_levels')
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId));

        $stockValue = (clone $stockScope)->sum(DB::raw('qty_on_hand * average_cost'));
        $retailValue = DB::table('inventory_stock_levels')
            ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
            ->when($branchId > 0, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))
            ->sum(DB::raw('inventory_stock_levels.qty_on_hand * products.retail_price'));

        return [
            'total_products' => DB::table('products')->count(),
            'active_products' => DB::table('products')->where('is_active', true)->count(),
            'stock_value' => $stockValue,
            'retail_value' => $retailValue,
            'low_stock' => DB::table('inventory_stock_levels')
                ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
                ->whereRaw('(qty_on_hand - qty_reserved) <= reorder_point')
                ->count(),
            'expiring' => DB::table('inventory_batches')
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<=', now()->addDays(60)->toDateString())
                ->count(),
            'open_pos' => DB::table('inventory_purchase_orders')->whereIn('status', ['draft', 'approved', 'partial_received'])->count(),
            'open_transfers' => DB::table('inventory_transfers')->whereIn('status', ['draft', 'approved', 'in_transit'])->count(),
        ];
    }

    private function products(int $branchId, string $query, string $type)
    {
        $stock = DB::table('inventory_stock_levels')
            ->selectRaw('product_id, sum(qty_on_hand) as qty_on_hand, sum(qty_reserved) as qty_reserved, max(reorder_point) as reorder_point')
            ->when($branchId > 0, fn ($builder) => $builder->where('branch_id', $branchId))
            ->groupBy('product_id');

        return DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->select([
                'products.*',
                'product_categories.name as category_name',
                'suppliers.name as supplier_name',
                DB::raw('coalesce(stock.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('coalesce(stock.qty_reserved, 0) as qty_reserved'),
                DB::raw('coalesce(stock.reorder_point, products.default_reorder_point, 0) as reorder_point'),
            ])
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('products.name', 'like', "%{$query}%")
                        ->orWhere('products.sku', 'like', "%{$query}%")
                        ->orWhere('products.barcode', 'like', "%{$query}%")
                        ->orWhere('products.brand', 'like', "%{$query}%")
                        ->orWhere('product_categories.name', 'like', "%{$query}%");
                });
            })
            ->when($type !== '', fn ($builder) => $builder->where('products.type', $type))
            ->orderBy('products.name')
            ->limit(80)
            ->get();
    }

    private function selectedProduct(int $branchId, int $productId): ?object
    {
        $product = $this->products($branchId, '', '')
            ->first(fn ($item) => (int) $item->id === $productId);

        if (! $product) {
            $product = $this->products($branchId, '', '')->first();
        }

        if (! $product) {
            return null;
        }

        $product->frame_detail = DB::table('inventory_frame_details')->where('product_id', $product->id)->first();
        $product->lens_detail = DB::table('inventory_lens_details')->where('product_id', $product->id)->first();
        $product->contact_lens_detail = DB::table('inventory_contact_lens_details')->where('product_id', $product->id)->first();
        $product->batches = DB::table('inventory_batches')->where('product_id', $product->id)->orderBy('expires_on')->get();

        return $product;
    }

    private function lowStockAlerts(int $branchId)
    {
        return DB::table('inventory_stock_levels')
            ->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
            ->join('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
            ->select([
                'products.name',
                'products.sku',
                'branches.name as branch_name',
                'inventory_stock_levels.qty_on_hand',
                'inventory_stock_levels.qty_reserved',
                'inventory_stock_levels.reorder_point',
                DB::raw('(inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) as available_stock'),
            ])
            ->when($branchId > 0, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))
            ->whereRaw('(inventory_stock_levels.qty_on_hand - inventory_stock_levels.qty_reserved) <= inventory_stock_levels.reorder_point')
            ->orderBy('available_stock')
            ->limit(20)
            ->get();
    }

    private function movements(int $branchId)
    {
        return DB::table('inventory_stock_movements')
            ->join('products', 'products.id', '=', 'inventory_stock_movements.product_id')
            ->join('branches', 'branches.id', '=', 'inventory_stock_movements.branch_id')
            ->select('inventory_stock_movements.*', 'products.name as product_name', 'products.sku', 'branches.name as branch_name')
            ->when($branchId > 0, fn ($query) => $query->where('inventory_stock_movements.branch_id', $branchId))
            ->orderByDesc('occurred_at')
            ->limit(40)
            ->get();
    }

    private function purchaseOrders(int $branchId)
    {
        return DB::table('inventory_purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'inventory_purchase_orders.supplier_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_purchase_orders.branch_id')
            ->select('inventory_purchase_orders.*', 'suppliers.name as supplier_name', 'branches.name as branch_name')
            ->when($branchId > 0, fn ($query) => $query->where(function ($inner) use ($branchId): void {
                $inner->where('inventory_purchase_orders.branch_id', $branchId)
                    ->orWhereNull('inventory_purchase_orders.branch_id');
            }))
            ->orderByDesc('inventory_purchase_orders.id')
            ->limit(20)
            ->get();
    }

    private function goodsReceipts(int $branchId)
    {
        return DB::table('inventory_goods_receipts')
            ->join('suppliers', 'suppliers.id', '=', 'inventory_goods_receipts.supplier_id')
            ->join('branches', 'branches.id', '=', 'inventory_goods_receipts.branch_id')
            ->select('inventory_goods_receipts.*', 'suppliers.name as supplier_name', 'branches.name as branch_name')
            ->when($branchId > 0, fn ($query) => $query->where('inventory_goods_receipts.branch_id', $branchId))
            ->orderByDesc('received_on')
            ->limit(20)
            ->get();
    }

    private function transfers()
    {
        return DB::table('inventory_transfers')
            ->join('branches as from_branch', 'from_branch.id', '=', 'inventory_transfers.from_branch_id')
            ->join('branches as to_branch', 'to_branch.id', '=', 'inventory_transfers.to_branch_id')
            ->select('inventory_transfers.*', 'from_branch.name as from_branch_name', 'to_branch.name as to_branch_name')
            ->orderByDesc('inventory_transfers.id')
            ->limit(20)
            ->get();
    }

    private function stockCounts(int $branchId)
    {
        return DB::table('inventory_stock_counts')
            ->join('branches', 'branches.id', '=', 'inventory_stock_counts.branch_id')
            ->leftJoin('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock_counts.inventory_location_id')
            ->select('inventory_stock_counts.*', 'branches.name as branch_name', 'inventory_locations.name as location_name')
            ->when($branchId > 0, fn ($query) => $query->where('inventory_stock_counts.branch_id', $branchId))
            ->orderByDesc('count_date')
            ->limit(20)
            ->get();
    }
}
