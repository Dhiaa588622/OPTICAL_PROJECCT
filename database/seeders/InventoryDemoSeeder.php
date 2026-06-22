<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $userId = DB::table('users')->where('email', 'inventory@example.com')->value('id');
        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Inventory Manager',
                'email' => 'inventory@example.com',
                'email_verified_at' => $now,
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $companyId = $this->upsertCompany($now);
        $branchIds = $this->upsertBranches($companyId, $now);
        $supplierIds = $this->upsertSuppliers($companyId, $now);
        $categoryIds = $this->upsertCategories($companyId, $now);
        $locationIds = $this->upsertLocations($branchIds, $now);
        $productIds = $this->upsertProducts($companyId, $categoryIds, $supplierIds, $now);

        $this->upsertProductDetails($productIds, $supplierIds, $now);
        $this->upsertBarcodes($productIds, $now);
        $this->upsertStock($productIds, $branchIds, $locationIds, $now);
        $batchIds = $this->upsertBatches($productIds, $supplierIds, $now);
        $this->upsertMovements($productIds, $branchIds, $locationIds, $batchIds, $userId, $now);
        $this->upsertPurchaseOrders($companyId, $branchIds, $supplierIds, $productIds, $now);
        $this->upsertReceipts($branchIds, $supplierIds, $productIds, $batchIds, $now);
        $this->upsertTransfers($branchIds, $locationIds, $productIds, $now);
        $this->upsertCounts($branchIds, $locationIds, $productIds, $now);
        $this->upsertPermissions($companyId, $now);
    }

    private function upsertCompany($now): int
    {
        DB::table('companies')->updateOrInsert(
            ['name' => 'ClearView Optical'],
            [
                'legal_name' => 'ClearView Optical Trading',
                'tax_number' => '300000000000003',
                'currency' => 'SAR',
                'phone' => '+966500000001',
                'email' => 'ops@clearview.test',
                'address' => 'Riyadh, Saudi Arabia',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        return (int) DB::table('companies')->where('name', 'ClearView Optical')->value('id');
    }

    private function upsertBranches(int $companyId, $now): array
    {
        $branches = [
            'MAIN' => ['Main Branch', '+966500000010', 'Olaya, Riyadh'],
            'MALL' => ['Mall Branch', '+966500000011', 'Riyadh Park Mall'],
            'NORTH' => ['North Branch', '+966500000012', 'Anas Ibn Malik Road'],
        ];

        foreach ($branches as $code => [$name, $phone, $address]) {
            DB::table('branches')->updateOrInsert(
                ['code' => $code],
                [
                    'company_id' => $companyId,
                    'name' => $name,
                    'phone' => $phone,
                    'address' => $address,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('branches')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
    }

    private function upsertSuppliers(int $companyId, $now): array
    {
        $suppliers = [
            'Rayline Optical' => ['Ahmed Nasser', '+966511110001'],
            'BlueShield Labs' => ['Noor Salem', '+966511110002'],
            'AquaView Contacts' => ['Sara Fahad', '+966511110003'],
            'Vista Eyewear' => ['Khalid Omar', '+966511110004'],
            'CareClean Supplies' => ['Maha Ali', '+966511110005'],
        ];

        foreach ($suppliers as $name => [$contact, $phone]) {
            DB::table('suppliers')->updateOrInsert(
                ['company_id' => $companyId, 'name' => $name],
                [
                    'contact_person' => $contact,
                    'phone' => $phone,
                    'email' => str($name)->lower()->replace(' ', '.').'@supplier.test',
                    'address' => 'Riyadh',
                    'tax_number' => null,
                    'opening_balance' => 0,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('suppliers')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
    }

    private function upsertCategories(int $companyId, $now): array
    {
        $categories = [
            'Frames' => 'frame',
            'Lenses' => 'lens',
            'Contact lenses' => 'contact_lens',
            'Sunglasses' => 'sunglasses',
            'Accessories' => 'accessory',
            'Cleaning solutions' => 'cleaning_solution',
            'Consumables' => 'consumable',
        ];

        foreach ($categories as $name => $type) {
            DB::table('product_categories')->updateOrInsert(
                ['company_id' => $companyId, 'name' => $name],
                [
                    'type' => $type,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('product_categories')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
    }

    private function upsertLocations(array $branchIds, $now): array
    {
        foreach ($branchIds as $branchId) {
            foreach ([
                'FLOOR' => ['Sales Floor', 'sales_floor', true],
                'STOCK' => ['Stock Room', 'stock_room', true],
                'DAMAGED' => ['Damaged / Quarantine', 'damaged', false],
            ] as $code => [$name, $type, $sellable]) {
                DB::table('inventory_locations')->updateOrInsert(
                    ['branch_id' => $branchId, 'code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'is_sellable' => $sellable,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }

        return DB::table('inventory_locations')
            ->where('is_sellable', true)
            ->pluck('id', 'branch_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function upsertProducts(int $companyId, array $categoryIds, array $supplierIds, $now): array
    {
        $products = [
            ['FRM-RAY-2140-BLK', '628100214001', 'frame', 'Rayline Classic Frame', 'Rayline', '2140', 'Black', '54-18-145', 'Acetate', 145, 420, 'Frames', 'Rayline Optical', 5, 12],
            ['FRM-KID-RED-S', '628100214088', 'frame', 'Kids Flex Frame Red S', 'Rayline', 'K-Flex', 'Red', '46-16-125', 'TR90', 72, 210, 'Frames', 'Rayline Optical', 3, 8],
            ['LEN-CR39-200', '628100392000', 'lens', 'CR-39 Single Vision +2.00', 'BlueShield', 'SV CR-39', 'Clear', 'Standard', 'CR-39', 65, 160, 'Lenses', 'BlueShield Labs', 6, 12],
            ['LEN-PROG-150', '628100392150', 'lens', 'Progressive Lens 1.50 Clear', 'BlueShield', 'Progressive 1.50', 'Clear', 'Standard', 'Resin', 180, 480, 'Lenses', 'BlueShield Labs', 4, 10],
            ['CL-MOIST-150', '628100771504', 'contact_lens', 'Moist Contact Lens -1.50', 'AquaView', 'Moist Monthly', 'Clear', '30 pack', 'Hydrogel', 52, 110, 'Contact lenses', 'AquaView Contacts', 10, 24],
            ['SUN-POL-330', '628100990330', 'sunglasses', 'Polarized Sunglasses 330', 'Vista', 'POL-330', 'Brown', '58-17-140', 'Metal', 150, 360, 'Sunglasses', 'Vista Eyewear', 4, 10],
            ['ACC-CASE-HARD', '628100881110', 'accessory', 'Hard Protective Case', 'ClearView', 'Hard Case', 'Black', 'Universal', 'Shell', 12, 35, 'Accessories', 'CareClean Supplies', 20, 50],
            ['SOL-CLEAN-120', '628100551120', 'cleaning_solution', 'Cleaning Solution 120ml', 'CareClean', 'CC-120', 'Clear', '120ml', 'Liquid', 8, 24, 'Cleaning solutions', 'CareClean Supplies', 12, 30],
            ['CON-WIPES-50', '628100552050', 'consumable', 'Lens Wipes 50 Pack', 'CareClean', 'Wipes 50', 'White', '50 pack', 'Consumable', 9, 29, 'Consumables', 'CareClean Supplies', 25, 60],
        ];

        foreach ($products as [$sku, $barcode, $type, $name, $brand, $model, $color, $size, $material, $cost, $retail, $category, $supplier, $min, $reorder]) {
            DB::table('products')->updateOrInsert(
                ['sku' => $sku],
                [
                    'company_id' => $companyId,
                    'product_category_id' => $categoryIds[$category],
                    'supplier_id' => $supplierIds[$supplier],
                    'barcode' => $barcode,
                    'type' => $type,
                    'name' => $name,
                    'brand' => $brand,
                    'model' => $model,
                    'color' => $color,
                    'size' => $size,
                    'material' => $material,
                    'cost_price' => $cost,
                    'retail_price' => $retail,
                    'tax_type' => 'standard',
                    'tax_rate' => 15,
                    'default_minimum_stock_level' => $min,
                    'default_reorder_point' => $reorder,
                    'track_batches' => in_array($type, ['contact_lens', 'cleaning_solution', 'consumable'], true),
                    'track_serials' => false,
                    'track_expiry' => in_array($type, ['contact_lens', 'cleaning_solution', 'consumable'], true),
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('products')->pluck('id', 'sku')->map(fn ($id) => (int) $id)->all();
    }

    private function upsertProductDetails(array $productIds, array $supplierIds, $now): void
    {
        foreach ([
            'FRM-RAY-2140-BLK' => ['54', '18', '145', 'Acetate', 'Unisex', 'Full rim', 'Wayfarer'],
            'FRM-KID-RED-S' => ['46', '16', '125', 'TR90', 'Kids', 'Full rim', 'Rectangle'],
            'SUN-POL-330' => ['58', '17', '140', 'Metal', 'Unisex', 'Full rim', 'Aviator'],
        ] as $sku => $detail) {
            DB::table('inventory_frame_details')->updateOrInsert(
                ['product_id' => $productIds[$sku]],
                [
                    'frame_size' => $detail[0],
                    'bridge_size' => $detail[1],
                    'temple_length' => $detail[2],
                    'material' => $detail[3],
                    'gender_style' => $detail[4],
                    'rim_type' => $detail[5],
                    'shape' => $detail[6],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        foreach ([
            'LEN-CR39-200' => ['Single vision', 'CR-39', '1.50', 'Scratch resistant', 'Clear', -8, 8, -4, 4],
            'LEN-PROG-150' => ['Progressive', 'Resin', '1.50', 'Blue block', 'Clear', -10, 8, -4, 4],
        ] as $sku => $detail) {
            DB::table('inventory_lens_details')->updateOrInsert(
                ['product_id' => $productIds[$sku]],
                [
                    'lab_supplier_id' => $supplierIds['BlueShield Labs'],
                    'lens_type' => $detail[0],
                    'material' => $detail[1],
                    'lens_index' => $detail[2],
                    'coating' => $detail[3],
                    'tint' => $detail[4],
                    'sphere_min' => $detail[5],
                    'sphere_max' => $detail[6],
                    'cylinder_min' => $detail[7],
                    'cylinder_max' => $detail[8],
                    'is_custom_order' => $sku === 'LEN-PROG-150',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        DB::table('inventory_contact_lens_details')->updateOrInsert(
            ['product_id' => $productIds['CL-MOIST-150']],
            [
                'power' => -1.50,
                'base_curve' => 8.60,
                'diameter' => 14.20,
                'wear_schedule' => 'Monthly',
                'pack_size' => 30,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function upsertBarcodes(array $productIds, $now): void
    {
        foreach (DB::table('products')->select('id', 'barcode')->whereNotNull('barcode')->get() as $product) {
            DB::table('inventory_product_barcodes')->updateOrInsert(
                ['barcode' => $product->barcode],
                [
                    'product_id' => $product->id,
                    'barcode_type' => 'EAN13',
                    'is_primary' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function upsertStock(array $productIds, array $branchIds, array $locationIds, $now): void
    {
        $rows = [
            ['FRM-RAY-2140-BLK', 'MAIN', 18, 3],
            ['FRM-KID-RED-S', 'MAIN', 7, 1],
            ['LEN-CR39-200', 'MAIN', 3, 1],
            ['LEN-PROG-150', 'MAIN', 12, 2],
            ['CL-MOIST-150', 'MAIN', 42, 0],
            ['SUN-POL-330', 'MAIN', 9, 2],
            ['ACC-CASE-HARD', 'MAIN', 95, 0],
            ['SOL-CLEAN-120', 'MAIN', 17, 0],
            ['CON-WIPES-50', 'MAIN', 84, 0],
            ['FRM-RAY-2140-BLK', 'MALL', 12, 1],
            ['LEN-CR39-200', 'MALL', 7, 1],
            ['CL-MOIST-150', 'MALL', 18, 0],
            ['SOL-CLEAN-120', 'NORTH', 5, 0],
            ['FRM-KID-RED-S', 'NORTH', 1, 0],
        ];

        foreach ($rows as [$sku, $branchCode, $onHand, $reserved]) {
            $product = DB::table('products')->where('id', $productIds[$sku])->first();
            DB::table('inventory_stock_levels')->updateOrInsert(
                [
                    'product_id' => $productIds[$sku],
                    'branch_id' => $branchIds[$branchCode],
                    'inventory_location_id' => $locationIds[$branchIds[$branchCode]],
                    'inventory_batch_id' => null,
                ],
                [
                    'qty_on_hand' => $onHand,
                    'qty_reserved' => $reserved,
                    'minimum_stock_level' => $product->default_minimum_stock_level,
                    'reorder_point' => $product->default_reorder_point,
                    'average_cost' => $product->cost_price,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function upsertBatches(array $productIds, array $supplierIds, $now): array
    {
        $rows = [
            ['CL-MOIST-150', 'AquaView Contacts', 'CL2044', 42, 52, now()->addDays(45)->toDateString()],
            ['SOL-CLEAN-120', 'CareClean Supplies', 'CC120-06', 17, 8, now()->addMonths(8)->toDateString()],
            ['CON-WIPES-50', 'CareClean Supplies', 'WP50-06', 84, 9, now()->addMonths(14)->toDateString()],
        ];

        foreach ($rows as [$sku, $supplier, $lot, $qty, $cost, $expiry]) {
            DB::table('inventory_batches')->updateOrInsert(
                ['product_id' => $productIds[$sku], 'lot_number' => $lot],
                [
                    'supplier_id' => $supplierIds[$supplier],
                    'batch_number' => $lot,
                    'expires_on' => $expiry,
                    'received_on' => now()->subDays(20)->toDateString(),
                    'initial_quantity' => $qty,
                    'unit_cost' => $cost,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('inventory_batches')->pluck('id', 'lot_number')->map(fn ($id) => (int) $id)->all();
    }

    private function upsertMovements(array $productIds, array $branchIds, array $locationIds, array $batchIds, int $userId, $now): void
    {
        if (DB::table('inventory_stock_movements')->count() > 0) {
            return;
        }

        $rows = [
            ['FRM-RAY-2140-BLK', 'MAIN', null, 'purchase_receipt', 'in', 12, 145, 18, 'GR-1008', 'Goods received', -7],
            ['LEN-CR39-200', 'MAIN', null, 'optical_order_reserve', 'out', 1, 65, 3, 'OPT-1048', 'Reserved for optical order', -5],
            ['CL-MOIST-150', 'MAIN', 'CL2044', 'stock_transfer_out', 'out', 8, 52, 42, 'TR-203', 'Transfer to Mall Branch', -3],
            ['SOL-CLEAN-120', 'MAIN', 'CC120-06', 'damaged_write_off', 'out', 2, 8, 17, 'ADJ-331', 'Damaged bottle write-off', -1],
            ['ACC-CASE-HARD', 'MAIN', null, 'sale_issue', 'out', 4, 12, 95, 'INV-2201', 'POS sale', -1],
            ['SUN-POL-330', 'MAIN', null, 'sale_return', 'in', 1, 150, 9, 'RET-130', 'Customer exchange return', 0],
        ];

        foreach ($rows as [$sku, $branchCode, $lot, $type, $direction, $qty, $cost, $balance, $ref, $reason, $days]) {
            DB::table('inventory_stock_movements')->insert([
                'product_id' => $productIds[$sku],
                'branch_id' => $branchIds[$branchCode],
                'inventory_location_id' => $locationIds[$branchIds[$branchCode]],
                'inventory_batch_id' => $lot ? ($batchIds[$lot] ?? null) : null,
                'user_id' => $userId,
                'movement_type' => $type,
                'direction' => $direction,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'balance_after' => $balance,
                'reference_type' => $ref,
                'reason' => $reason,
                'occurred_at' => now()->addDays($days),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function upsertPurchaseOrders(int $companyId, array $branchIds, array $supplierIds, array $productIds, $now): void
    {
        $orders = [
            ['PO-INV-1028', 'MAIN', 'BlueShield Labs', 'partial_received', 18400, 'LEN-CR39-200', 120, 65],
            ['PO-INV-1029', 'MAIN', 'AquaView Contacts', 'approved', 12720, 'CL-MOIST-150', 180, 52],
            ['PO-INV-1030', 'MALL', 'Vista Eyewear', 'draft', 9640, 'SUN-POL-330', 40, 150],
        ];

        foreach ($orders as [$po, $branch, $supplier, $status, $total, $sku, $qty, $cost]) {
            DB::table('inventory_purchase_orders')->updateOrInsert(
                ['po_number' => $po],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchIds[$branch],
                    'supplier_id' => $supplierIds[$supplier],
                    'status' => $status,
                    'ordered_on' => now()->subDays(5)->toDateString(),
                    'expected_on' => now()->addDays(4)->toDateString(),
                    'subtotal' => $total,
                    'tax_total' => round($total * 0.15, 2),
                    'grand_total' => round($total * 1.15, 2),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $poId = DB::table('inventory_purchase_orders')->where('po_number', $po)->value('id');
            DB::table('inventory_purchase_order_lines')->updateOrInsert(
                ['inventory_purchase_order_id' => $poId, 'product_id' => $productIds[$sku]],
                [
                    'ordered_quantity' => $qty,
                    'received_quantity' => $status === 'partial_received' ? 60 : 0,
                    'unit_cost' => $cost,
                    'tax_rate' => 15,
                    'line_total' => $qty * $cost,
                    'expected_on' => now()->addDays(4)->toDateString(),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function upsertReceipts(array $branchIds, array $supplierIds, array $productIds, array $batchIds, $now): void
    {
        DB::table('inventory_goods_receipts')->updateOrInsert(
            ['receipt_number' => 'GR-1008'],
            [
                'branch_id' => $branchIds['MAIN'],
                'supplier_id' => $supplierIds['BlueShield Labs'],
                'supplier_invoice_number' => 'SUP-BS-778',
                'received_on' => now()->subDays(2)->toDateString(),
                'status' => 'posted',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $receiptId = DB::table('inventory_goods_receipts')->where('receipt_number', 'GR-1008')->value('id');
        DB::table('inventory_goods_receipt_lines')->updateOrInsert(
            ['inventory_goods_receipt_id' => $receiptId, 'product_id' => $productIds['LEN-CR39-200']],
            [
                'received_quantity' => 63,
                'accepted_quantity' => 60,
                'rejected_quantity' => 3,
                'unit_cost' => 65,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function upsertTransfers(array $branchIds, array $locationIds, array $productIds, $now): void
    {
        DB::table('inventory_transfers')->updateOrInsert(
            ['transfer_number' => 'TR-203'],
            [
                'from_branch_id' => $branchIds['MAIN'],
                'to_branch_id' => $branchIds['MALL'],
                'from_location_id' => $locationIds[$branchIds['MAIN']],
                'to_location_id' => $locationIds[$branchIds['MALL']],
                'status' => 'in_transit',
                'shipped_at' => now()->subHours(3),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $transferId = DB::table('inventory_transfers')->where('transfer_number', 'TR-203')->value('id');
        DB::table('inventory_transfer_lines')->updateOrInsert(
            ['inventory_transfer_id' => $transferId, 'product_id' => $productIds['CL-MOIST-150']],
            [
                'requested_quantity' => 8,
                'shipped_quantity' => 8,
                'received_quantity' => 0,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function upsertCounts(array $branchIds, array $locationIds, array $productIds, $now): void
    {
        DB::table('inventory_stock_counts')->updateOrInsert(
            ['count_number' => 'CNT-018'],
            [
                'branch_id' => $branchIds['MAIN'],
                'inventory_location_id' => $locationIds[$branchIds['MAIN']],
                'status' => 'in_progress',
                'count_date' => now()->toDateString(),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $countId = DB::table('inventory_stock_counts')->where('count_number', 'CNT-018')->value('id');
        foreach ([
            ['FRM-RAY-2140-BLK', 18, 18, 0, 'counted'],
            ['LEN-CR39-200', 3, 2, -1, 'variance'],
            ['ACC-CASE-HARD', 95, 95, 0, 'counted'],
        ] as [$sku, $system, $counted, $variance, $status]) {
            DB::table('inventory_stock_count_lines')->updateOrInsert(
                ['inventory_stock_count_id' => $countId, 'product_id' => $productIds[$sku]],
                [
                    'system_quantity' => $system,
                    'counted_quantity' => $counted,
                    'variance_quantity' => $variance,
                    'count_status' => $status,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function upsertPermissions(int $companyId, $now): void
    {
        foreach (config('inventory.permissions') as $permission) {
            [$module, $area, $action] = array_pad(explode('.', $permission), 3, null);
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission],
                [
                    'module' => $module.'.'.$area,
                    'action' => $action ?: 'access',
                    'name' => str($permission)->replace('.', ' ')->title(),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        foreach (config('inventory.roles') as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'company_id' => $companyId,
                    'name' => $role['name'],
                    'description' => 'Inventory module role',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
