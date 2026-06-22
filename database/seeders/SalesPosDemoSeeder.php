<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SalesPosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchIds = DB::table('branches')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $productIds = DB::table('products')->pluck('id', 'sku')->map(fn ($id) => (int) $id)->all();

        if (! $companyId || $branchIds === [] || $productIds === []) {
            return;
        }

        $cashierId = $this->user('pos.cashier@example.com', 'POS Cashier', $now);
        $managerId = $this->user('store.manager@example.com', 'Store Manager', $now);
        $customerIds = $this->customers($companyId, $now);

        $this->permissionsAndRoles($companyId, $now);
        $this->promotions($companyId, $now);
        $this->cashSessions($branchIds, $cashierId, $now);
        $this->quotation($companyId, $branchIds, $productIds, $customerIds, $managerId, $now);
        $this->salesOrder($companyId, $branchIds, $productIds, $customerIds, $managerId, $now);
        $this->invoice($companyId, $branchIds, $productIds, $customerIds, $cashierId, $now);
        $this->partialInvoice($companyId, $branchIds, $productIds, $customerIds, $cashierId, $now);
        $this->returnAndCredit($companyId, $branchIds, $cashierId, $now);
        $this->recalculateOpenSession();
    }

    private function user(string $email, string $name, $now): int
    {
        $id = DB::table('users')->where('email', $email)->value('id');
        if ($id) {
            return (int) $id;
        }

        return DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $now,
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function customers(int $companyId, $now): array
    {
        $rows = [
            ['Sara Ahmed', '+966500111222', 'sara@example.test'],
            ['Omar Khaled', '+966500333444', 'omar@example.test'],
            ['Walk-in Customer', null, null],
        ];

        foreach ($rows as [$name, $phone, $email]) {
            DB::table('sales_customers')->updateOrInsert(
                ['company_id' => $companyId, 'name' => $name],
                [
                    'phone' => $phone,
                    'email' => $email,
                    'whatsapp_opt_in' => $name !== 'Walk-in Customer',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        return DB::table('sales_customers')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
    }

    private function permissionsAndRoles(int $companyId, $now): void
    {
        foreach (config('sales_pos.permissions') as $permission) {
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

        foreach (config('sales_pos.roles') as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'company_id' => $companyId,
                    'name' => $role['name'],
                    'description' => 'Sales & POS module role',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function promotions(int $companyId, $now): void
    {
        DB::table('sales_promotions')->updateOrInsert(
            ['code' => 'FRAME-LENS-10'],
            [
                'company_id' => $companyId,
                'name' => 'Frame + lens package discount',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'starts_on' => now()->subDays(10)->toDateString(),
                'ends_on' => now()->addDays(20)->toDateString(),
                'is_active' => true,
                'applies_to' => json_encode(['types' => ['frame', 'lens']]),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function cashSessions(array $branchIds, int $cashierId, $now): void
    {
        DB::table('sales_cash_sessions')->updateOrInsert(
            ['session_number' => 'CSH-DEMO-OPEN'],
            [
                'branch_id' => $branchIds['MAIN'],
                'cashier_id' => $cashierId,
                'opened_at' => now()->startOfDay()->addHours(8),
                'opening_cash' => 500,
                'expected_cash' => 500,
                'status' => 'open',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('sales_cash_sessions')->updateOrInsert(
            ['session_number' => 'CSH-DEMO-CLOSED'],
            [
                'branch_id' => $branchIds['MALL'] ?? $branchIds['MAIN'],
                'cashier_id' => $cashierId,
                'opened_at' => now()->subDay()->startOfDay()->addHours(9),
                'closed_at' => now()->subDay()->endOfDay()->subHours(2),
                'opening_cash' => 300,
                'total_cash_sales' => 920,
                'total_card_sales' => 1280,
                'total_refunds' => 120,
                'expected_cash' => 1100,
                'actual_cash' => 1095,
                'difference' => -5,
                'status' => 'closed',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function quotation(int $companyId, array $branchIds, array $productIds, array $customerIds, int $managerId, $now): void
    {
        if (DB::table('sales_quotations')->where('quotation_number', 'QUO-DEMO-1001')->exists()) {
            return;
        }

        $lines = [
            $this->line($productIds['FRM-RAY-2140-BLK'], 1, 420, 'percent', 10, 'frame_lens_package'),
            $this->line($productIds['LEN-PROG-150'], 2, 480, 'percent', 10, 'frame_lens_package'),
        ];
        $totals = $this->totals($lines);

        $quoteId = DB::table('sales_quotations')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $customerIds['Sara Ahmed'],
            'quotation_number' => 'QUO-DEMO-1001',
            'status' => 'sent',
            'quoted_on' => now()->toDateString(),
            'expires_on' => now()->addDays(14)->toDateString(),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'created_by' => $managerId,
            'notes' => 'Progressive frame and lens package.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($lines as $line) {
            DB::table('sales_quotation_items')->insert([
                'sales_quotation_id' => $quoteId,
                ...$line,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function salesOrder(int $companyId, array $branchIds, array $productIds, array $customerIds, int $managerId, $now): void
    {
        if (DB::table('sales_orders')->where('order_number', 'SO-DEMO-1001')->exists()) {
            return;
        }

        $line = $this->line($productIds['LEN-CR39-200'], 1, 160, 'none', 0, 'prescription_order');
        $totals = $this->totals([$line]);
        $orderId = DB::table('sales_orders')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $customerIds['Omar Khaled'],
            'order_number' => 'SO-DEMO-1001',
            'status' => 'reserved',
            'order_type' => 'prescription_order',
            'ordered_on' => now()->toDateString(),
            'pickup_due_on' => now()->addDays(3)->toDateString(),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'deposit_required' => 80,
            'deposit_paid' => 80,
            'balance_due' => $totals['grand_total'] - 80,
            'stock_reserved' => true,
            'created_by' => $managerId,
            'notes' => 'Prescription lens order with pickup balance.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sales_order_items')->insert([
            'sales_order_id' => $orderId,
            ...$line,
            'reserved_quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->reserveStock($productIds['LEN-CR39-200'], $branchIds['MAIN'], $orderId, 1, $now);

        $sessionId = (int) DB::table('sales_cash_sessions')->where('session_number', 'CSH-DEMO-OPEN')->value('id');
        DB::table('sales_payments')->insert([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $customerIds['Omar Khaled'],
            'sales_order_id' => $orderId,
            'sales_cash_session_id' => $sessionId,
            'payment_number' => 'DEP-DEMO-1001',
            'payment_method' => 'cash',
            'direction' => 'in',
            'amount' => 80,
            'status' => 'posted',
            'paid_at' => now()->subHours(2),
            'received_by' => $managerId,
            'notes' => 'Deposit for prescription order.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function invoice(int $companyId, array $branchIds, array $productIds, array $customerIds, int $cashierId, $now): void
    {
        if (DB::table('sales_invoices')->where('invoice_number', 'INV-DEMO-1001')->exists()) {
            return;
        }

        $lines = [
            $this->line($productIds['SUN-POL-330'], 1, 360, 'fixed', 20, 'ready_product'),
            $this->line($productIds['ACC-CASE-HARD'], 1, 35, 'none', 0, 'ready_product'),
        ];
        $totals = $this->totals($lines);
        $sessionId = (int) DB::table('sales_cash_sessions')->where('session_number', 'CSH-DEMO-OPEN')->value('id');

        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $customerIds['Walk-in Customer'],
            'cash_session_id' => $sessionId,
            'cashier_id' => $cashierId,
            'invoice_number' => 'INV-DEMO-1001',
            'invoice_type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
            'sale_mode' => 'ready_product',
            'pickup_status' => 'not_required',
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'paid_total' => $totals['grand_total'],
            'balance_due' => 0,
            'gross_profit' => $totals['gross_profit'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($lines as $line) {
            $locationId = $this->sellableLocation($branchIds['MAIN']);
            DB::table('sales_invoice_items')->insert([
                'sales_invoice_id' => $invoiceId,
                'inventory_location_id' => $locationId,
                ...$line,
                'stock_quantity' => $line['quantity'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->issueStock($line['product_id'], $branchIds['MAIN'], $locationId, $invoiceId, (float) $line['quantity'], (float) $line['cost_price'], $now);
        }

        foreach ([['cash', 150], ['card', $totals['grand_total'] - 150]] as [$method, $amount]) {
            $paymentId = DB::table('sales_payments')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchIds['MAIN'],
                'customer_id' => $customerIds['Walk-in Customer'],
                'sales_invoice_id' => $invoiceId,
                'sales_cash_session_id' => $sessionId,
                'payment_number' => 'PAY-DEMO-'.$method,
                'payment_method' => $method,
                'direction' => 'in',
                'amount' => $amount,
                'status' => 'posted',
                'paid_at' => now()->subHour(),
                'received_by' => $cashierId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('sales_receipts')->insert([
                'sales_invoice_id' => $invoiceId,
                'sales_payment_id' => $paymentId,
                'receipt_number' => 'REC-DEMO-'.$method,
                'receipt_type' => 'payment',
                'printed_at' => now()->subMinutes(50),
                'payload' => json_encode(['invoice' => 'INV-DEMO-1001', 'method' => $method]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function partialInvoice(int $companyId, array $branchIds, array $productIds, array $customerIds, int $cashierId, $now): void
    {
        if (DB::table('sales_invoices')->where('invoice_number', 'INV-DEMO-1002')->exists()) {
            return;
        }

        $line = $this->line($productIds['FRM-RAY-2140-BLK'], 1, 420, 'none', 0, 'pickup_balance');
        $totals = $this->totals([$line]);
        $sessionId = (int) DB::table('sales_cash_sessions')->where('session_number', 'CSH-DEMO-OPEN')->value('id');

        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $customerIds['Sara Ahmed'],
            'cash_session_id' => $sessionId,
            'cashier_id' => $cashierId,
            'invoice_number' => 'INV-DEMO-1002',
            'invoice_type' => 'invoice',
            'status' => 'partial',
            'invoice_date' => now()->toDateString(),
            'sale_mode' => 'pickup_balance',
            'pickup_status' => 'ready',
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'paid_total' => 200,
            'balance_due' => $totals['grand_total'] - 200,
            'gross_profit' => $totals['gross_profit'],
            'notes' => 'Deposit paid, remaining due at pickup.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $locationId = $this->sellableLocation($branchIds['MAIN']);
        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => $invoiceId,
            'inventory_location_id' => $locationId,
            ...$line,
            'stock_quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->issueStock($line['product_id'], $branchIds['MAIN'], $locationId, $invoiceId, 1, (float) $line['cost_price'], $now);

        DB::table('sales_payments')->insert([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $customerIds['Sara Ahmed'],
            'sales_invoice_id' => $invoiceId,
            'sales_cash_session_id' => $sessionId,
            'payment_number' => 'PAY-DEMO-1002',
            'payment_method' => 'payment_link',
            'direction' => 'in',
            'amount' => 200,
            'status' => 'posted',
            'paid_at' => now()->subMinutes(25),
            'received_by' => $cashierId,
            'reference' => 'LINK-4922',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function returnAndCredit(int $companyId, array $branchIds, int $cashierId, $now): void
    {
        if (DB::table('sales_returns')->where('return_number', 'RET-DEMO-1001')->exists()) {
            return;
        }

        $invoice = DB::table('sales_invoices')->where('invoice_number', 'INV-DEMO-1001')->first();
        if (! $invoice) {
            return;
        }

        $item = DB::table('sales_invoice_items')->where('sales_invoice_id', $invoice->id)->orderBy('id')->first();
        if (! $item) {
            return;
        }

        $sessionId = (int) DB::table('sales_cash_sessions')->where('session_number', 'CSH-DEMO-OPEN')->value('id');
        $subtotal = (float) $item->unit_price;
        $tax = round($subtotal * (float) $item->tax_rate / 100, 2);
        $total = round($subtotal + $tax, 2);
        $returnId = DB::table('sales_returns')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'original_invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'cash_session_id' => $sessionId,
            'return_number' => 'RET-DEMO-1001',
            'status' => 'posted',
            'return_date' => now()->toDateString(),
            'reason' => 'Exchange for different model',
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'refund_total' => $total,
            'restock_total' => $total,
            'created_by' => $cashierId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sales_return_items')->insert([
            'sales_return_id' => $returnId,
            'original_invoice_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => 1,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'tax_amount' => $tax,
            'line_total' => $total,
            'restock_action' => 'restock',
            'condition' => 'Resellable',
            'inventory_location_id' => $item->inventory_location_id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->returnStock((int) $item->product_id, $branchIds['MAIN'], (int) $item->inventory_location_id, $returnId, 1, $now);

        $creditId = DB::table('sales_credit_notes')->insertGetId([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $invoice->customer_id,
            'sales_return_id' => $returnId,
            'original_invoice_id' => $invoice->id,
            'credit_note_number' => 'CRN-DEMO-1001',
            'status' => 'open',
            'credit_date' => now()->toDateString(),
            'amount' => $total,
            'remaining_amount' => $total,
            'reason' => 'Exchange credit',
            'created_by' => $cashierId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sales_credit_note_items')->insert([
            'sales_credit_note_id' => $creditId,
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_price' => $item->unit_price,
            'tax_amount' => $tax,
            'line_total' => $total,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sales_payments')->insert([
            'company_id' => $companyId,
            'branch_id' => $branchIds['MAIN'],
            'customer_id' => $invoice->customer_id,
            'sales_invoice_id' => $invoice->id,
            'sales_cash_session_id' => $sessionId,
            'payment_number' => 'REF-DEMO-1001',
            'payment_method' => 'credit_note',
            'direction' => 'refund',
            'amount' => $total,
            'status' => 'posted',
            'paid_at' => now()->subMinutes(10),
            'received_by' => $cashierId,
            'notes' => 'Credit note issued.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    }

    private function line(int $productId, float $quantity, float $unitPrice, string $discountType, float $discountValue, string $itemType): array
    {
        $product = DB::table('products')->where('id', $productId)->first();
        $subtotal = round($quantity * $unitPrice, 2);
        $discountAmount = match ($discountType) {
            'percent' => round($subtotal * min($discountValue, 100) / 100, 2),
            'fixed' => min($subtotal, $discountValue),
            default => 0,
        };
        $taxBase = $subtotal - $discountAmount;
        $tax = round($taxBase * (float) $product->tax_rate / 100, 2);

        return [
            'product_id' => $productId,
            'item_type' => $itemType,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $product->cost_price,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'tax_rate' => $product->tax_rate,
            'tax_amount' => $tax,
            'line_total' => round($taxBase + $tax, 2),
            'optical_package_key' => $itemType === 'frame_lens_package' ? 'PKG-DEMO-1' : null,
            'prescription_snapshot' => $itemType !== 'ready_product' ? json_encode(['od' => '-1.25', 'os' => '-1.00', 'add' => '+1.50']) : null,
            'is_custom_order' => $itemType === 'prescription_order',
        ];
    }

    private function totals(array $lines): array
    {
        return [
            'subtotal' => array_sum(array_map(fn ($line) => (float) $line['quantity'] * (float) $line['unit_price'], $lines)),
            'discount_total' => array_sum(array_column($lines, 'discount_amount')),
            'tax_total' => array_sum(array_column($lines, 'tax_amount')),
            'grand_total' => array_sum(array_column($lines, 'line_total')),
            'gross_profit' => array_sum(array_map(fn ($line) => ((float) $line['line_total'] - (float) $line['tax_amount']) - ((float) $line['cost_price'] * (float) $line['quantity']), $lines)),
        ];
    }

    private function sellableLocation(int $branchId): ?int
    {
        return DB::table('inventory_locations')
            ->where('branch_id', $branchId)
            ->where('is_sellable', true)
            ->orderBy('id')
            ->value('id');
    }

    private function issueStock(int $productId, int $branchId, ?int $locationId, int $invoiceId, float $quantity, float $cost, $now): void
    {
        $stock = DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->when($locationId, fn ($query) => $query->where('inventory_location_id', $locationId))
            ->first();

        if (! $stock) {
            return;
        }

        $balance = max(0, (float) $stock->qty_on_hand - $quantity);
        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_on_hand' => $balance,
            'updated_at' => $now,
        ]);

        DB::table('inventory_stock_movements')->insert([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'inventory_location_id' => $locationId,
            'movement_type' => 'sale_issue',
            'direction' => 'out',
            'quantity' => $quantity,
            'unit_cost' => $cost,
            'balance_after' => $balance,
            'reference_type' => 'sales_invoice',
            'reference_id' => $invoiceId,
            'reason' => 'Demo POS invoice',
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function returnStock(int $productId, int $branchId, ?int $locationId, int $returnId, float $quantity, $now): void
    {
        $stock = DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->when($locationId, fn ($query) => $query->where('inventory_location_id', $locationId))
            ->first();

        if (! $stock) {
            return;
        }

        $balance = (float) $stock->qty_on_hand + $quantity;
        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_on_hand' => $balance,
            'updated_at' => $now,
        ]);

        DB::table('inventory_stock_movements')->insert([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'inventory_location_id' => $locationId,
            'movement_type' => 'sale_return',
            'direction' => 'in',
            'quantity' => $quantity,
            'unit_cost' => 0,
            'balance_after' => $balance,
            'reference_type' => 'sales_return',
            'reference_id' => $returnId,
            'reason' => 'Demo customer return',
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function reserveStock(int $productId, int $branchId, int $orderId, float $quantity, $now): void
    {
        $stock = DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->first();

        if ($stock) {
            DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                'qty_reserved' => (float) $stock->qty_reserved + $quantity,
                'updated_at' => $now,
            ]);
        }

        DB::table('inventory_reservations')->insert([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'source_type' => 'sales_order',
            'source_id' => $orderId,
            'quantity' => $quantity,
            'status' => 'active',
            'reserved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function recalculateOpenSession(): void
    {
        $session = DB::table('sales_cash_sessions')->where('session_number', 'CSH-DEMO-OPEN')->first();
        if (! $session) {
            return;
        }

        $cash = (float) DB::table('sales_payments')->where('sales_cash_session_id', $session->id)->where('direction', 'in')->where('payment_method', 'cash')->sum('amount');
        $card = (float) DB::table('sales_payments')->where('sales_cash_session_id', $session->id)->where('direction', 'in')->where('payment_method', 'card')->sum('amount');
        $link = (float) DB::table('sales_payments')->where('sales_cash_session_id', $session->id)->where('direction', 'in')->where('payment_method', 'payment_link')->sum('amount');
        $refunds = (float) DB::table('sales_payments')->where('sales_cash_session_id', $session->id)->where('direction', 'refund')->sum('amount');

        DB::table('sales_cash_sessions')->where('id', $session->id)->update([
            'total_cash_sales' => $cash,
            'total_card_sales' => $card,
            'total_payment_link_sales' => $link,
            'total_refunds' => $refunds,
            'expected_cash' => (float) $session->opening_cash + $cash,
            'updated_at' => now(),
        ]);
    }
}
