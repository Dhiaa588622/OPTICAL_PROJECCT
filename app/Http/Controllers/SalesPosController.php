<?php

namespace App\Http\Controllers;

use App\Support\AccountingPoster;
use App\Support\SetupOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesPosController extends Controller
{
    private const PAGES = [
        'dashboard' => 'Sales Dashboard',
        'pos' => 'POS Checkout',
        'invoices' => 'Invoices',
        'quotations' => 'Quotations',
        'sales-orders' => 'Sales Orders',
        'returns' => 'Returns & Exchanges',
        'payments' => 'Payments',
        'cashier-closing' => 'Cashier Closing',
        'print' => 'Print View',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'dashboard';

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        if (! Schema::hasTable('sales_invoices') || ! Schema::hasTable('products')) {
            return view('sales.pos', [
                'page' => 'setup',
                'pages' => self::PAGES,
                'databaseReady' => false,
            ]);
        }

        return view('sales.pos', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => true,
            ...$this->salesData($request),
        ]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'cashier_id' => ['nullable', 'integer'],
            'cash_session_id' => ['nullable', 'integer'],
            'sales_order_id' => ['nullable', 'integer'],
            'sale_mode' => ['nullable', 'string', 'max:255'],
            'pickup_status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $invoiceId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $companyId = $this->companyId();
            $branchId = (int) $validated['branch_id'];
            $cashierId = $validated['cashier_id'] ?? null;
            $customerId = $this->resolveCustomer($request, $companyId, $now);
            $allowUnavailable = $request->boolean('allow_unavailable')
                && ($request->user()->hasRole('erp-admin') || $request->user()->hasPermission('sales.pos.override_stock'));
            $salesOrderId = $validated['sales_order_id'] ?? null;
            $lines = $this->preparedLines((array) $request->input('items', []), $branchId, true, $allowUnavailable);
            $totals = $this->lineTotals($lines);
            $payments = $this->preparedPayments((array) $request->input('payments', []));
            $paidTotal = round(array_sum(array_column($payments, 'amount')), 2);
            $balanceDue = max(0, round($totals['grand_total'] - $paidTotal, 2));
            $status = $balanceDue <= 0.009 ? 'paid' : ($paidTotal > 0 ? 'partial' : 'posted');
            $sessionId = $this->ensureCashSession(
                $branchId,
                $cashierId ? (int) $cashierId : null,
                $validated['cash_session_id'] ?? null,
                $now,
            );

            $invoiceId = DB::table('sales_invoices')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'sales_order_id' => $salesOrderId,
                'cash_session_id' => $sessionId,
                'cashier_id' => $cashierId,
                'invoice_number' => $this->nextNumber('INV', 'sales_invoices', 'invoice_number'),
                'invoice_type' => 'invoice',
                'status' => $status,
                'invoice_date' => $now->toDateString(),
                'sale_mode' => $validated['sale_mode'] ?? 'ready_product',
                'pickup_status' => $validated['pickup_status'] ?? 'not_required',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'paid_total' => $paidTotal,
                'balance_due' => $balanceDue,
                'gross_profit' => $totals['gross_profit'],
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                $invoiceItemId = DB::table('sales_invoice_items')->insertGetId([
                    'sales_invoice_id' => $invoiceId,
                    'product_id' => $line['product_id'],
                    'inventory_location_id' => $line['location_id'],
                    'item_type' => $line['item_type'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'stock_quantity' => $line['stock_quantity'],
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['cost_price'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'optical_package_key' => $line['optical_package_key'],
                    'prescription_snapshot' => $line['prescription_snapshot'],
                    'is_custom_order' => $line['is_custom_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($line['stock_quantity'] > 0) {
                    $this->postSaleIssue($line, $branchId, $invoiceId, $invoiceItemId, $now);
                }
            }

            $paymentIds = [];
            foreach ($payments as $payment) {
                $paymentId = DB::table('sales_payments')->insertGetId([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'sales_invoice_id' => $invoiceId,
                    'sales_order_id' => $salesOrderId,
                    'sales_cash_session_id' => $sessionId,
                    'payment_number' => $this->nextNumber('PAY', 'sales_payments', 'payment_number'),
                    'payment_method' => $payment['method'],
                    'direction' => 'in',
                    'amount' => $payment['amount'],
                    'status' => 'posted',
                    'paid_at' => $now,
                    'reference' => $payment['reference'],
                    'received_by' => $cashierId,
                    'notes' => $payment['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('sales_receipts')->insert([
                    'sales_invoice_id' => $invoiceId,
                    'sales_payment_id' => $paymentId,
                    'receipt_number' => $this->nextNumber('REC', 'sales_receipts', 'receipt_number'),
                    'receipt_type' => 'payment',
                    'payload' => json_encode(['method' => $payment['method'], 'amount' => $payment['amount']]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $paymentIds[] = $paymentId;
            }

            if ($salesOrderId) {
                $this->consumeOrderReservation((int) $salesOrderId, $now);
            }

            $this->recalculateCashSession($sessionId);
            $this->audit('sales.invoice.posted', 'sales_invoice', $invoiceId, $branchId, null, [
                'grand_total' => $totals['grand_total'],
                'paid_total' => $paidTotal,
            ], $now);
            app(AccountingPoster::class)->postSalesInvoice($invoiceId);
            foreach ($paymentIds as $paymentId) {
                app(AccountingPoster::class)->postSalesPayment($paymentId);
            }

            return $invoiceId;
        });

        return $this->respond($request, [
            'status' => 'posted',
            'invoice_id' => $invoiceId,
            'invoice' => DB::table('sales_invoices')->where('id', $invoiceId)->first(),
        ], 'print', 'Invoice posted, stock reduced, and payment recorded.');
    }

    public function createQuotation(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'created_by' => ['nullable', 'integer'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $quotationId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $companyId = $this->companyId();
            $branchId = (int) $validated['branch_id'];
            $customerId = $this->resolveCustomer($request, $companyId, $now);
            $lines = $this->preparedLines((array) $request->input('items', []), $branchId, false, true);
            $totals = $this->lineTotals($lines);

            $quotationId = DB::table('sales_quotations')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'quotation_number' => $this->nextNumber('QUO', 'sales_quotations', 'quotation_number'),
                'status' => 'sent',
                'quoted_on' => $now->toDateString(),
                'expires_on' => $validated['expires_on'] ?? $now->copy()->addDays(14)->toDateString(),
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'created_by' => $validated['created_by'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                DB::table('sales_quotation_items')->insert([
                    'sales_quotation_id' => $quotationId,
                    'product_id' => $line['product_id'],
                    'item_type' => $line['item_type'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['cost_price'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'optical_package_key' => $line['optical_package_key'],
                    'prescription_snapshot' => $line['prescription_snapshot'],
                    'is_custom_order' => $line['is_custom_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->audit('sales.quotation.created', 'sales_quotation', $quotationId, $branchId, null, [
                'grand_total' => $totals['grand_total'],
            ], $now);

            return $quotationId;
        });

        return $this->respond($request, [
            'status' => 'created',
            'quotation_id' => $quotationId,
        ], 'quotations', 'Quotation created. Inventory was not changed.');
    }

    public function createSalesOrder(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'created_by' => ['nullable', 'integer'],
            'order_type' => ['nullable', 'string', 'max:255'],
            'pickup_due_on' => ['nullable', 'date'],
            'deposit_required' => ['nullable', 'numeric', 'min:0'],
            'deposit_paid' => ['nullable', 'numeric', 'min:0'],
            'reserve_stock' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $orderId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $companyId = $this->companyId();
            $branchId = (int) $validated['branch_id'];
            $customerId = $this->resolveCustomer($request, $companyId, $now);
            $reserveStock = $request->boolean('reserve_stock');
            $lines = $this->preparedLines((array) $request->input('items', []), $branchId, $reserveStock, false);
            $totals = $this->lineTotals($lines);
            $depositPaid = (float) ($validated['deposit_paid'] ?? 0);
            $balanceDue = max(0, round($totals['grand_total'] - $depositPaid, 2));

            $orderId = DB::table('sales_orders')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'order_number' => $this->nextNumber('SO', 'sales_orders', 'order_number'),
                'status' => $reserveStock ? 'reserved' : 'draft',
                'order_type' => $validated['order_type'] ?? 'prescription_order',
                'ordered_on' => $now->toDateString(),
                'pickup_due_on' => $validated['pickup_due_on'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'deposit_required' => $validated['deposit_required'] ?? 0,
                'deposit_paid' => $depositPaid,
                'balance_due' => $balanceDue,
                'stock_reserved' => $reserveStock,
                'created_by' => $validated['created_by'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                DB::table('sales_order_items')->insert([
                    'sales_order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'item_type' => $line['item_type'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'reserved_quantity' => $reserveStock ? $line['stock_quantity'] : 0,
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['cost_price'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'optical_package_key' => $line['optical_package_key'],
                    'prescription_snapshot' => $line['prescription_snapshot'],
                    'is_custom_order' => $line['is_custom_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($reserveStock && $line['stock_quantity'] > 0) {
                    $this->reserveStock($line, $branchId, $orderId, $now);
                }
            }

            if ($depositPaid > 0) {
                $sessionId = $this->ensureCashSession($branchId, $validated['created_by'] ?? null, null, $now);
                $refundPaymentId = DB::table('sales_payments')->insertGetId([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'sales_order_id' => $orderId,
                    'sales_cash_session_id' => $sessionId,
                    'payment_number' => $this->nextNumber('DEP', 'sales_payments', 'payment_number'),
                    'payment_method' => 'cash',
                    'direction' => 'in',
                    'amount' => $depositPaid,
                    'status' => 'posted',
                    'paid_at' => $now,
                    'received_by' => $validated['created_by'] ?? null,
                    'notes' => 'Sales order deposit',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->recalculateCashSession($sessionId);
            }

            $this->audit('sales.order.created', 'sales_order', $orderId, $branchId, null, [
                'stock_reserved' => $reserveStock,
                'grand_total' => $totals['grand_total'],
            ], $now);

            return $orderId;
        });

        return $this->respond($request, [
            'status' => 'created',
            'sales_order_id' => $orderId,
        ], 'sales-orders', 'Sales order created and inventory reservation applied when requested.');
    }

    public function updateQuotation(Request $request, int $quotation)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:sales_customers,id'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
        ]);

        DB::transaction(function () use ($validated, $quotation): void {
            $document = DB::table('sales_quotations')->where('id', $quotation)->lockForUpdate()->first();
            if (! $document || $document->status !== 'draft') {
                throw ValidationException::withMessages(['quotation' => 'Only draft quotations can be edited.']);
            }
            $lines = $this->preparedLines($validated['items'], (int) $validated['branch_id'], false, false);
            $totals = $this->lineTotals($lines);
            $now = now();
            DB::table('sales_quotations')->where('id', $quotation)->update([
                'branch_id' => $validated['branch_id'],
                'customer_id' => $validated['customer_id'] ?? null,
                'expires_on' => $validated['expires_on'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => $now,
            ]);
            $this->replaceDraftDocumentLines('quotation', $quotation, $lines, $now);
            $this->audit('sales.quotation.updated', 'sales_quotation', $quotation, (int) $validated['branch_id'], (array) $document, ['grand_total' => $totals['grand_total']], $now);
        });

        return $this->respond($request, ['status' => 'updated', 'quotation_id' => $quotation], 'quotations', 'Draft quotation updated.');
    }

    public function updateSalesOrder(Request $request, int $order)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:sales_customers,id'],
            'order_type' => ['required', Rule::in(app(SetupOptions::class)->keys('sale_modes'))],
            'pickup_due_on' => ['nullable', 'date'],
            'deposit_required' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
        ]);

        DB::transaction(function () use ($validated, $order): void {
            $document = DB::table('sales_orders')->where('id', $order)->lockForUpdate()->first();
            if (! $document || $document->status !== 'draft' || $document->stock_reserved) {
                throw ValidationException::withMessages(['order' => 'Only unreserved draft sales orders can be edited.']);
            }
            $lines = $this->preparedLines($validated['items'], (int) $validated['branch_id'], false, false);
            $totals = $this->lineTotals($lines);
            $depositRequired = min((float) ($validated['deposit_required'] ?? 0), $totals['grand_total']);
            $now = now();
            DB::table('sales_orders')->where('id', $order)->update([
                'branch_id' => $validated['branch_id'],
                'customer_id' => $validated['customer_id'] ?? null,
                'order_type' => $validated['order_type'],
                'pickup_due_on' => $validated['pickup_due_on'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'deposit_required' => $depositRequired,
                'balance_due' => max(0, round($totals['grand_total'] - (float) $document->deposit_paid, 2)),
                'notes' => $validated['notes'] ?? null,
                'updated_at' => $now,
            ]);
            $this->replaceDraftDocumentLines('order', $order, $lines, $now);
            $this->audit('sales.order.updated', 'sales_order', $order, (int) $validated['branch_id'], (array) $document, ['grand_total' => $totals['grand_total']], $now);
        });

        return $this->respond($request, ['status' => 'updated', 'sales_order_id' => $order], 'sales-orders', 'Draft sales order updated.');
    }

    public function processReturn(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'cashier_id' => ['nullable', 'integer'],
            'cash_session_id' => ['nullable', 'integer'],
            'original_invoice_id' => ['required', 'integer'],
            'refund_method' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $companyId = $this->companyId();
            $branchId = (int) $validated['branch_id'];
            $invoice = DB::table('sales_invoices')->where('id', $validated['original_invoice_id'])->first();
            if (! $invoice) {
                throw ValidationException::withMessages(['original_invoice_id' => 'Original invoice was not found.']);
            }

            $returnLines = $this->preparedReturnLines((array) $request->input('return_items', []), $branchId);
            $subtotal = round(array_sum(array_column($returnLines, 'subtotal')), 2);
            $taxTotal = round(array_sum(array_column($returnLines, 'tax_amount')), 2);
            $refundTotal = round(array_sum(array_column($returnLines, 'line_total')), 2);
            $restockTotal = round(array_sum(array_map(fn ($line) => $line['restock_action'] === 'restock' ? $line['line_total'] : 0, $returnLines)), 2);
            $sessionId = $this->ensureCashSession(
                $branchId,
                $validated['cashier_id'] ?? null,
                $validated['cash_session_id'] ?? null,
                $now,
            );

            $returnId = DB::table('sales_returns')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'original_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'cash_session_id' => $sessionId,
                'return_number' => $this->nextNumber('RET', 'sales_returns', 'return_number'),
                'status' => 'posted',
                'return_date' => $now->toDateString(),
                'reason' => $validated['reason'] ?? 'Customer return',
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'refund_total' => $refundTotal,
                'restock_total' => $restockTotal,
                'created_by' => $validated['cashier_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($returnLines as $line) {
                DB::table('sales_return_items')->insert([
                    'sales_return_id' => $returnId,
                    'original_invoice_item_id' => $line['invoice_item_id'],
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'restock_action' => $line['restock_action'],
                    'condition' => $line['condition'],
                    'inventory_location_id' => $line['location_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($line['restock_action'] === 'restock' && $line['product_id']) {
                    $this->postSaleReturn($line, $branchId, $returnId, $now);
                }
            }

            $creditNoteId = DB::table('sales_credit_notes')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $invoice->customer_id,
                'sales_return_id' => $returnId,
                'original_invoice_id' => $invoice->id,
                'credit_note_number' => $this->nextNumber('CRN', 'sales_credit_notes', 'credit_note_number'),
                'status' => 'open',
                'credit_date' => $now->toDateString(),
                'amount' => $refundTotal,
                'remaining_amount' => $refundTotal,
                'reason' => $validated['reason'] ?? 'Customer return',
                'created_by' => $validated['cashier_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($returnLines as $line) {
                DB::table('sales_credit_note_items')->insert([
                    'sales_credit_note_id' => $creditNoteId,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $refundPaymentId = null;
            if ($refundTotal > 0 && ($validated['refund_method'] ?? '') !== 'credit_note') {
                $refundPaymentId = DB::table('sales_payments')->insertGetId([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => $invoice->customer_id,
                    'sales_invoice_id' => $invoice->id,
                    'sales_cash_session_id' => $sessionId,
                    'payment_number' => $this->nextNumber('REF', 'sales_payments', 'payment_number'),
                    'payment_method' => $validated['refund_method'] ?? 'cash',
                    'direction' => 'refund',
                    'amount' => $refundTotal,
                    'status' => 'posted',
                    'paid_at' => $now,
                    'received_by' => $validated['cashier_id'] ?? null,
                    'notes' => 'Refund for return '.$returnId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($refundPaymentId) {
                app(AccountingPoster::class)->postSalesPayment($refundPaymentId);
            }

            $this->recalculateCashSession($sessionId);
            $this->audit('sales.return.posted', 'sales_return', $returnId, $branchId, null, [
                'refund_total' => $refundTotal,
                'credit_note_id' => $creditNoteId,
            ], $now);
            app(AccountingPoster::class)->postSalesReturn($returnId);

            return $returnId;
        });

        return $this->respond($request, [
            'status' => 'posted',
            'return_id' => $returnId,
        ], 'returns', 'Return posted, credit note created, and restockable inventory increased.');
    }

    public function closeCashier(Request $request)
    {
        $validated = $request->validate([
            'cash_session_id' => ['required', 'integer'],
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = DB::transaction(function () use ($validated) {
            $now = now();
            $this->recalculateCashSession((int) $validated['cash_session_id']);
            $session = DB::table('sales_cash_sessions')->where('id', $validated['cash_session_id'])->first();
            if (! $session || $session->status !== 'open') {
                throw ValidationException::withMessages(['cash_session_id' => 'Only an open cashier session can be closed.']);
            }

            $difference = round((float) $validated['actual_cash'] - (float) $session->expected_cash, 2);
            DB::table('sales_cash_sessions')->where('id', $session->id)->update([
                'closed_at' => $now,
                'actual_cash' => $validated['actual_cash'],
                'difference' => $difference,
                'status' => 'closed',
                'notes' => $validated['notes'] ?? null,
                'updated_at' => $now,
            ]);

            $this->audit('sales.cashier.closed', 'sales_cash_session', $session->id, (int) $session->branch_id, [
                'status' => 'open',
            ], [
                'status' => 'closed',
                'actual_cash' => $validated['actual_cash'],
                'difference' => $difference,
            ], $now);

            return DB::table('sales_cash_sessions')->where('id', $session->id)->first();
        });

        return $this->respond($request, [
            'status' => 'closed',
            'cash_session' => $session,
        ], 'cashier-closing', 'Cashier session closed and daily close report updated.');
    }

    public function meta()
    {
        return response()->json([
            'module' => config('sales_pos.module'),
            'document_types' => app(SetupOptions::class)->options('sales_document_types'),
            'payment_methods' => app(SetupOptions::class)->options('payment_methods'),
            'roles' => config('sales_pos.roles'),
            'permissions' => config('sales_pos.permissions'),
            'reports' => config('sales_pos.reports'),
            'integration_rules' => config('sales_pos.integration_rules'),
            'shortcuts' => config('sales_pos.shortcuts'),
        ]);
    }

    public function dashboardApi(Request $request)
    {
        if (! Schema::hasTable('sales_invoices')) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $branchId = $request->integer('branch_id') ?: null;

        return response()->json([
            'business_date' => now()->toDateString(),
            'branch_id' => $branchId,
            'metrics' => $this->metrics($branchId),
            'payment_mix' => $this->paymentMix($branchId),
            'recent_invoices' => $this->invoices($branchId, 10),
            'inventory_connection' => [
                'sale_reduces_stock' => true,
                'return_increases_stock' => true,
                'quotation_affects_stock' => false,
                'sales_order_can_reserve_stock' => true,
            ],
        ]);
    }

    public function productsApi(Request $request)
    {
        return response()->json([
            'query' => trim((string) $request->query('q', '')),
            'branch_id' => $request->integer('branch_id') ?: null,
            'searchable_fields' => ['product_name', 'sku', 'barcode', 'brand', 'category'],
            'data' => $this->productCatalog($request->integer('branch_id'), trim((string) $request->query('q', ''))),
        ]);
    }

    public function documentsApi(Request $request, string $resource)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $data = match ($resource) {
            'invoices' => $this->invoices($branchId, 100),
            'quotations' => $this->quotations($branchId),
            'sales-orders' => $this->salesOrders($branchId),
            'payments' => $this->payments($branchId),
            'returns' => $this->returns($branchId),
            'cash-sessions' => $this->cashSessions($branchId),
            default => abort(404),
        };

        return response()->json([
            'resource' => $resource,
            'branch_id' => $branchId,
            'data' => $data,
        ]);
    }

    public function reportApi(Request $request, string $report)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $dateFrom = $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $data = match ($report) {
            'daily-sales' => DB::table('sales_invoices')
                ->join('branches', 'branches.id', '=', 'sales_invoices.branch_id')
                ->selectRaw('sales_invoices.invoice_date, branches.name as branch, count(*) as invoices, sum(grand_total) as revenue, sum(gross_profit) as gross_profit')
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->whereBetween('sales_invoices.invoice_date', [$dateFrom, $dateTo])
                ->groupBy('sales_invoices.invoice_date', 'branches.name')
                ->orderByDesc('sales_invoices.invoice_date')
                ->get(),
            'sales-by-cashier' => DB::table('sales_invoices')
                ->leftJoin('users', 'users.id', '=', 'sales_invoices.cashier_id')
                ->selectRaw('coalesce(users.name, "Unassigned") as cashier, count(*) as invoices, sum(grand_total) as revenue')
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->whereBetween('sales_invoices.invoice_date', [$dateFrom, $dateTo])
                ->groupBy('users.name')
                ->orderByDesc('revenue')
                ->get(),
            'sales-by-branch' => DB::table('sales_invoices')
                ->join('branches', 'branches.id', '=', 'sales_invoices.branch_id')
                ->selectRaw('branches.name as branch, count(*) as invoices, sum(grand_total) as revenue, sum(gross_profit) as gross_profit')
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->whereBetween('sales_invoices.invoice_date', [$dateFrom, $dateTo])
                ->groupBy('branches.name')
                ->orderByDesc('revenue')
                ->get(),
            'sales-by-product' => DB::table('sales_invoice_items')
                ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
                ->leftJoin('products', 'products.id', '=', 'sales_invoice_items.product_id')
                ->selectRaw('products.sku, sales_invoice_items.description, sum(sales_invoice_items.quantity) as quantity, sum(sales_invoice_items.line_total) as revenue')
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->whereBetween('sales_invoices.invoice_date', [$dateFrom, $dateTo])
                ->groupBy('products.sku', 'sales_invoice_items.description')
                ->orderByDesc('revenue')
                ->get(),
            'sales-by-category' => DB::table('sales_invoice_items')
                ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
                ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->selectRaw('coalesce(product_categories.name, "Uncategorized") as category, sum(sales_invoice_items.quantity) as quantity, sum(sales_invoice_items.line_total) as revenue')
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->whereBetween('sales_invoices.invoice_date', [$dateFrom, $dateTo])
                ->groupBy('product_categories.name')
                ->orderByDesc('revenue')
                ->get(),
            'sales-by-brand' => DB::table('sales_invoice_items')
                ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
                ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
                ->selectRaw('coalesce(products.brand, "No brand") as brand, sum(sales_invoice_items.quantity) as quantity, sum(sales_invoice_items.line_total) as revenue')
                ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
                ->whereBetween('sales_invoices.invoice_date', [$dateFrom, $dateTo])
                ->groupBy('products.brand')
                ->orderByDesc('revenue')
                ->get(),
            'gross-profit' => DB::table('sales_invoices')
                ->selectRaw('invoice_date, invoice_number, grand_total, gross_profit, round((gross_profit / nullif(grand_total - tax_total, 0)) * 100, 2) as gross_margin_percent')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereBetween('invoice_date', [$dateFrom, $dateTo])
                ->orderByDesc('invoice_date')
                ->get(),
            'returns' => $this->returns($branchId),
            'payment-methods' => $this->paymentMix($branchId),
            default => abort(404),
        };

        return response()->json([
            'report' => $report,
            'filters' => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'data' => $data,
        ]);
    }

    private function salesData(Request $request): array
    {
        $branches = DB::table('branches')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch_id') ?: ($branches->first()->id ?? 0));
        $query = trim((string) $request->query('q', ''));
        $invoiceId = $request->integer('invoice_id');

        return [
            'branches' => $branches,
            'branchId' => $branchId,
            'query' => $query,
            'customers' => DB::table('sales_customers')->orderBy('name')->get(),
            'cashiers' => DB::table('users')->orderBy('name')->get(),
            'productTypes' => app(SetupOptions::class)->options('product_types'),
            'paymentMethods' => app(SetupOptions::class)->options('payment_methods'),
            'saleModes' => app(SetupOptions::class)->options('sale_modes'),
            'reports' => config('sales_pos.reports'),
            'shortcuts' => config('sales_pos.shortcuts'),
            'metrics' => $this->metrics($branchId),
            'products' => $this->productCatalog($branchId, $query),
            'productsForJs' => $this->productCatalog($branchId, $query)->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'brand' => $product->brand,
                'category' => $product->category_name,
                'type' => $product->type,
                'retail_price' => (float) $product->retail_price,
                'cost_price' => (float) $product->cost_price,
                'tax_rate' => (float) $product->tax_rate,
                'available_stock' => (float) $product->available_stock,
            ])->values(),
            'invoices' => $this->invoices($branchId, 40),
            'quotations' => $this->quotations($branchId),
            'salesOrders' => $this->salesOrders($branchId),
            'payments' => $this->payments($branchId),
            'returns' => $this->returns($branchId),
            'cashSessions' => $this->cashSessions($branchId),
            'openSession' => $this->openCashSession($branchId),
            'paymentMix' => $this->paymentMix($branchId),
            'returnableItems' => $this->returnableItems($branchId),
            'printInvoice' => $this->printInvoice($invoiceId, $branchId),
        ];
    }

    private function metrics(?int $branchId): array
    {
        $today = now()->toDateString();

        $invoiceScope = DB::table('sales_invoices')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('invoice_date', $today);

        return [
            'daily_sales' => (clone $invoiceScope)->count(),
            'daily_revenue' => (clone $invoiceScope)->sum('grand_total'),
            'daily_paid' => (clone $invoiceScope)->sum('paid_total'),
            'pending_balance' => DB::table('sales_invoices')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('balance_due'),
            'pending_orders' => DB::table('sales_orders')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereIn('status', ['draft', 'reserved', 'partially_invoiced'])
                ->count(),
            'open_quotations' => DB::table('sales_quotations')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereIn('status', ['draft', 'sent'])
                ->count(),
            'returns_today' => DB::table('sales_returns')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereDate('return_date', $today)
                ->sum('refund_total'),
            'gross_profit' => (clone $invoiceScope)->sum('gross_profit'),
            'low_stock' => DB::table('inventory_stock_levels')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereRaw('(qty_on_hand - qty_reserved) <= reorder_point')
                ->count(),
        ];
    }

    private function productCatalog(?int $branchId, string $query = '')
    {
        $stock = DB::table('inventory_stock_levels')
            ->selectRaw('product_id, sum(qty_on_hand) as qty_on_hand, sum(qty_reserved) as qty_reserved')
            ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId))
            ->groupBy('product_id');

        return DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->select([
                'products.id',
                'products.sku',
                'products.barcode',
                'products.type',
                'products.name',
                'products.brand',
                'products.model',
                'products.color',
                'products.cost_price',
                'products.retail_price',
                'products.tax_rate',
                'product_categories.name as category_name',
                DB::raw('coalesce(stock.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('coalesce(stock.qty_reserved, 0) as qty_reserved'),
                DB::raw('(coalesce(stock.qty_on_hand, 0) - coalesce(stock.qty_reserved, 0)) as available_stock'),
            ])
            ->where('products.is_active', true)
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('products.name', 'like', "%{$query}%")
                        ->orWhere('products.sku', 'like', "%{$query}%")
                        ->orWhere('products.barcode', 'like', "%{$query}%")
                        ->orWhere('products.brand', 'like', "%{$query}%")
                        ->orWhere('product_categories.name', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('available_stock')
            ->orderBy('products.name')
            ->limit(80)
            ->get();
    }

    private function invoices(?int $branchId, int $limit)
    {
        return DB::table('sales_invoices')
            ->join('branches', 'branches.id', '=', 'sales_invoices.branch_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_invoices.cashier_id')
            ->select('sales_invoices.*', 'branches.name as branch_name', 'sales_customers.name as customer_name', 'users.name as cashier_name')
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->orderByDesc('sales_invoices.id')
            ->limit($limit)
            ->get();
    }

    private function quotations(?int $branchId)
    {
        return DB::table('sales_quotations')
            ->join('branches', 'branches.id', '=', 'sales_quotations.branch_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_quotations.customer_id')
            ->select('sales_quotations.*', 'branches.name as branch_name', 'sales_customers.name as customer_name')
            ->when($branchId, fn ($query) => $query->where('sales_quotations.branch_id', $branchId))
            ->orderByDesc('sales_quotations.id')
            ->limit(30)
            ->get();
    }

    private function salesOrders(?int $branchId)
    {
        return DB::table('sales_orders')
            ->join('branches', 'branches.id', '=', 'sales_orders.branch_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_orders.customer_id')
            ->select('sales_orders.*', 'branches.name as branch_name', 'sales_customers.name as customer_name')
            ->when($branchId, fn ($query) => $query->where('sales_orders.branch_id', $branchId))
            ->orderByDesc('sales_orders.id')
            ->limit(30)
            ->get();
    }

    private function payments(?int $branchId)
    {
        return DB::table('sales_payments')
            ->join('branches', 'branches.id', '=', 'sales_payments.branch_id')
            ->leftJoin('sales_invoices', 'sales_invoices.id', '=', 'sales_payments.sales_invoice_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_payments.customer_id')
            ->select('sales_payments.*', 'branches.name as branch_name', 'sales_invoices.invoice_number', 'sales_customers.name as customer_name')
            ->when($branchId, fn ($query) => $query->where('sales_payments.branch_id', $branchId))
            ->orderByDesc('sales_payments.paid_at')
            ->limit(60)
            ->get();
    }

    private function returns(?int $branchId)
    {
        return DB::table('sales_returns')
            ->join('branches', 'branches.id', '=', 'sales_returns.branch_id')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_returns.original_invoice_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_returns.customer_id')
            ->select('sales_returns.*', 'branches.name as branch_name', 'sales_invoices.invoice_number', 'sales_customers.name as customer_name')
            ->when($branchId, fn ($query) => $query->where('sales_returns.branch_id', $branchId))
            ->orderByDesc('sales_returns.id')
            ->limit(30)
            ->get();
    }

    private function cashSessions(?int $branchId)
    {
        return DB::table('sales_cash_sessions')
            ->join('branches', 'branches.id', '=', 'sales_cash_sessions.branch_id')
            ->leftJoin('users', 'users.id', '=', 'sales_cash_sessions.cashier_id')
            ->select('sales_cash_sessions.*', 'branches.name as branch_name', 'users.name as cashier_name')
            ->when($branchId, fn ($query) => $query->where('sales_cash_sessions.branch_id', $branchId))
            ->orderByDesc('sales_cash_sessions.id')
            ->limit(20)
            ->get();
    }

    private function openCashSession(int $branchId): ?object
    {
        return DB::table('sales_cash_sessions')
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();
    }

    private function paymentMix(?int $branchId)
    {
        return DB::table('sales_payments')
            ->selectRaw('payment_method, direction, sum(amount) as total, count(*) as count')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('paid_at', now()->toDateString())
            ->groupBy('payment_method', 'direction')
            ->orderByDesc('total')
            ->get();
    }

    private function returnableItems(?int $branchId)
    {
        return DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->leftJoin('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->select([
                'sales_invoice_items.*',
                'sales_invoices.invoice_number',
                'sales_invoices.branch_id',
                'products.sku',
                DB::raw('(sales_invoice_items.quantity - sales_invoice_items.returned_quantity) as returnable_quantity'),
            ])
            ->when($branchId, fn ($query) => $query->where('sales_invoices.branch_id', $branchId))
            ->whereRaw('(sales_invoice_items.quantity - sales_invoice_items.returned_quantity) > 0')
            ->orderByDesc('sales_invoice_items.id')
            ->limit(80)
            ->get();
    }

    private function printInvoice(int $invoiceId, int $branchId): ?object
    {
        $invoice = DB::table('sales_invoices')
            ->join('branches', 'branches.id', '=', 'sales_invoices.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'sales_invoices.company_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_invoices.cashier_id')
            ->select('sales_invoices.*', 'branches.name as branch_name', 'branches.phone as branch_phone', 'branches.address as branch_address', 'companies.name as company_name', 'companies.tax_number', 'sales_customers.name as customer_name', 'sales_customers.phone as customer_phone', 'users.name as cashier_name')
            ->when($invoiceId > 0, fn ($query) => $query->where('sales_invoices.id', $invoiceId))
            ->when($invoiceId <= 0, fn ($query) => $query->where('sales_invoices.branch_id', $branchId)->orderByDesc('sales_invoices.id'))
            ->first();

        if (! $invoice) {
            return null;
        }

        $invoice->items = DB::table('sales_invoice_items')
            ->leftJoin('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->select('sales_invoice_items.*', 'products.sku')
            ->where('sales_invoice_items.sales_invoice_id', $invoice->id)
            ->get();
        $invoice->payments = DB::table('sales_payments')
            ->where('sales_invoice_id', $invoice->id)
            ->orderBy('paid_at')
            ->get();

        return $invoice;
    }

    private function preparedLines(array $items, int $branchId, bool $checkStock, bool $allowUnavailable): array
    {
        $lines = [];

        foreach ($items as $raw) {
            $productId = (int) ($raw['product_id'] ?? 0);
            $quantity = (float) ($raw['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $product = DB::table('products')
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->select('products.*', 'product_categories.name as category_name')
                ->where('products.id', $productId)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages(['items' => 'One selected product does not exist.']);
            }

            $isCustomOrder = filter_var($raw['is_custom_order'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $stockQuantity = $isCustomOrder ? 0 : $quantity;
            $stock = $this->stockRow($productId, $branchId);
            $available = (float) ($stock?->qty_on_hand ?? 0) - (float) ($stock?->qty_reserved ?? 0);

            if ($checkStock && $stockQuantity > $available && ! $allowUnavailable) {
                throw ValidationException::withMessages([
                    'items' => $product->name.' has only '.number_format($available, 3).' available in this branch.',
                ]);
            }

            $unitPrice = (float) ($raw['unit_price'] ?? 0);
            if ($unitPrice <= 0) {
                $unitPrice = (float) $product->retail_price;
            }

            $requestedDiscountType = $raw['discount_type'] ?? 'none';
            $discountType = in_array($requestedDiscountType, ['none', 'percent', 'fixed'], true)
                ? $requestedDiscountType
                : 'none';
            $discountValue = max(0, (float) ($raw['discount_value'] ?? 0));
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $discountAmount = match ($discountType) {
                'percent' => round(min($lineSubtotal, $lineSubtotal * min($discountValue, 100) / 100), 2),
                'fixed' => round(min($lineSubtotal, $discountValue), 2),
                default => 0,
            };
            $taxBase = max(0, $lineSubtotal - $discountAmount);
            $taxRate = (float) $product->tax_rate;
            $taxAmount = round($taxBase * $taxRate / 100, 2);
            $lineTotal = round($taxBase + $taxAmount, 2);

            $lines[] = [
                'product_id' => $productId,
                'location_id' => $stock?->inventory_location_id ?: $this->sellableLocationId($branchId),
                'stock_level_id' => $stock?->id,
                'item_type' => $raw['item_type'] ?? 'ready_product',
                'description' => $raw['description'] ?? $product->name,
                'quantity' => $quantity,
                'stock_quantity' => $stockQuantity,
                'unit_price' => $unitPrice,
                'cost_price' => (float) $product->cost_price,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'subtotal' => $lineSubtotal,
                'gross_profit' => round($taxBase - ((float) $product->cost_price * $quantity), 2),
                'optical_package_key' => $raw['optical_package_key'] ?? null,
                'prescription_snapshot' => ! empty($raw['prescription_snapshot']) ? json_encode($raw['prescription_snapshot']) : null,
                'is_custom_order' => $isCustomOrder,
                'allow_negative_stock' => $allowUnavailable,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one product line.']);
        }

        return $lines;
    }

    private function preparedReturnLines(array $items, int $branchId): array
    {
        $lines = [];

        foreach ($items as $raw) {
            $invoiceItemId = (int) ($raw['invoice_item_id'] ?? 0);
            $quantity = (float) ($raw['quantity'] ?? 0);
            if ($invoiceItemId <= 0 || $quantity <= 0) {
                continue;
            }

            $item = DB::table('sales_invoice_items')->where('id', $invoiceItemId)->first();
            if (! $item) {
                throw ValidationException::withMessages(['return_items' => 'Invoice item was not found.']);
            }

            $returnable = (float) $item->quantity - (float) $item->returned_quantity;
            if ($quantity > $returnable) {
                throw ValidationException::withMessages(['return_items' => $item->description.' has only '.number_format($returnable, 3).' returnable.']);
            }

            $subtotal = round($quantity * (float) $item->unit_price, 2);
            $taxAmount = round($subtotal * (float) $item->tax_rate / 100, 2);
            $lineTotal = round($subtotal + $taxAmount, 2);
            $stock = $this->stockRow((int) $item->product_id, $branchId);

            $lines[] = [
                'invoice_item_id' => $invoiceItemId,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'subtotal' => $subtotal,
                'restock_action' => in_array(($raw['restock_action'] ?? 'restock'), ['restock', 'damaged', 'write_off'], true) ? $raw['restock_action'] : 'restock',
                'condition' => $raw['condition'] ?? null,
                'location_id' => $stock?->inventory_location_id ?: $this->sellableLocationId($branchId),
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['return_items' => 'Choose at least one return item.']);
        }

        return $lines;
    }

    private function lineTotals(array $lines): array
    {
        return [
            'subtotal' => round(array_sum(array_column($lines, 'subtotal')), 2),
            'discount_total' => round(array_sum(array_column($lines, 'discount_amount')), 2),
            'tax_total' => round(array_sum(array_column($lines, 'tax_amount')), 2),
            'grand_total' => round(array_sum(array_column($lines, 'line_total')), 2),
            'gross_profit' => round(array_sum(array_column($lines, 'gross_profit')), 2),
        ];
    }

    private function replaceDraftDocumentLines(string $type, int $documentId, array $lines, $now): void
    {
        $table = $type === 'quotation' ? 'sales_quotation_items' : 'sales_order_items';
        $foreignKey = $type === 'quotation' ? 'sales_quotation_id' : 'sales_order_id';
        DB::table($table)->where($foreignKey, $documentId)->delete();

        foreach ($lines as $line) {
            $data = [
                $foreignKey => $documentId,
                'product_id' => $line['product_id'],
                'item_type' => $line['item_type'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'cost_price' => $line['cost_price'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => $line['discount_amount'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'optical_package_key' => $line['optical_package_key'],
                'prescription_snapshot' => $line['prescription_snapshot'],
                'is_custom_order' => $line['is_custom_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($type === 'order') {
                $data['reserved_quantity'] = 0;
            }
            DB::table($table)->insert($data);
        }
    }

    private function preparedPayments(array $payments): array
    {
        $prepared = [];
        $validMethods = app(SetupOptions::class)->keys('payment_methods');

        foreach ($payments as $key => $raw) {
            $method = $raw['method'] ?? $key;
            $amount = (float) ($raw['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            if (! in_array($method, $validMethods, true)) {
                throw ValidationException::withMessages(['payments' => 'Payment method '.$method.' is not supported.']);
            }

            $prepared[] = [
                'method' => $method,
                'amount' => round($amount, 2),
                'reference' => $raw['reference'] ?? null,
                'notes' => $raw['notes'] ?? null,
            ];
        }

        return $prepared;
    }

    private function postSaleIssue(array $line, int $branchId, int $invoiceId, int $invoiceItemId, $now): void
    {
        $stock = $line['stock_level_id']
            ? DB::table('inventory_stock_levels')->where('id', $line['stock_level_id'])->lockForUpdate()->first()
            : null;
        if (! $stock) {
            $stockId = DB::table('inventory_stock_levels')->insertGetId([
                'product_id' => $line['product_id'],
                'branch_id' => $branchId,
                'inventory_location_id' => $line['location_id'],
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
                'average_cost' => $line['cost_price'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stock = DB::table('inventory_stock_levels')->where('id', $stockId)->lockForUpdate()->first();
        }

        $available = (float) $stock->qty_on_hand - (float) $stock->qty_reserved;
        if ((float) $line['stock_quantity'] > $available + 0.0001 && ! $line['allow_negative_stock']) {
            throw ValidationException::withMessages(['items' => $line['description'].' no longer has enough available branch stock.']);
        }
        $newOnHand = (float) $stock->qty_on_hand - (float) $line['stock_quantity'];
        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_on_hand' => $newOnHand,
            'updated_at' => $now,
        ]);

        DB::table('inventory_stock_movements')->insert([
            'product_id' => $line['product_id'],
            'branch_id' => $branchId,
            'inventory_location_id' => $line['location_id'],
            'movement_type' => 'sale_issue',
            'direction' => 'out',
            'quantity' => $line['stock_quantity'],
            'unit_cost' => $line['cost_price'],
            'balance_after' => $newOnHand,
            'reference_type' => 'sales_invoice',
            'reference_id' => $invoiceId,
            'reason' => 'POS sale invoice item '.$invoiceItemId,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function postSaleReturn(array $line, int $branchId, int $returnId, $now): void
    {
        $stock = $this->stockRow((int) $line['product_id'], $branchId);
        $locationId = $line['location_id'] ?: $this->sellableLocationId($branchId);

        if (! $stock) {
            $stockId = DB::table('inventory_stock_levels')->insertGetId([
                'product_id' => $line['product_id'],
                'branch_id' => $branchId,
                'inventory_location_id' => $locationId,
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
                'average_cost' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stock = DB::table('inventory_stock_levels')->where('id', $stockId)->first();
        }

        $newOnHand = (float) $stock->qty_on_hand + (float) $line['quantity'];
        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_on_hand' => $newOnHand,
            'updated_at' => $now,
        ]);

        DB::table('inventory_stock_movements')->insert([
            'product_id' => $line['product_id'],
            'branch_id' => $branchId,
            'inventory_location_id' => $locationId,
            'movement_type' => 'sale_return',
            'direction' => 'in',
            'quantity' => $line['quantity'],
            'unit_cost' => 0,
            'balance_after' => $newOnHand,
            'reference_type' => 'sales_return',
            'reference_id' => $returnId,
            'reason' => 'Customer return',
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function reserveStock(array $line, int $branchId, int $orderId, $now): void
    {
        $stock = DB::table('inventory_stock_levels')->where('id', $line['stock_level_id'])->first();
        if (! $stock) {
            throw ValidationException::withMessages(['items' => 'No sellable stock row exists for '.$line['description'].'.']);
        }

        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_reserved' => (float) $stock->qty_reserved + (float) $line['stock_quantity'],
            'updated_at' => $now,
        ]);

        DB::table('inventory_reservations')->insert([
            'product_id' => $line['product_id'],
            'branch_id' => $branchId,
            'source_type' => 'sales_order',
            'source_id' => $orderId,
            'quantity' => $line['stock_quantity'],
            'status' => 'active',
            'reserved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('inventory_stock_movements')->insert([
            'product_id' => $line['product_id'],
            'branch_id' => $branchId,
            'inventory_location_id' => $line['location_id'],
            'movement_type' => 'optical_order_reserve',
            'direction' => 'out',
            'quantity' => $line['stock_quantity'],
            'unit_cost' => $line['cost_price'],
            'balance_after' => $stock->qty_on_hand,
            'reference_type' => 'sales_order',
            'reference_id' => $orderId,
            'reason' => 'Sales order stock reservation',
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function consumeOrderReservation(int $orderId, $now): void
    {
        $reservations = DB::table('inventory_reservations')
            ->where('source_type', 'sales_order')
            ->where('source_id', $orderId)
            ->where('status', 'active')
            ->get();

        foreach ($reservations as $reservation) {
            $stock = $this->stockRow((int) $reservation->product_id, (int) $reservation->branch_id);
            if ($stock) {
                DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                    'qty_reserved' => max(0, (float) $stock->qty_reserved - (float) $reservation->quantity),
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('inventory_reservations')
            ->where('source_type', 'sales_order')
            ->where('source_id', $orderId)
            ->where('status', 'active')
            ->update([
                'status' => 'consumed',
                'consumed_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('sales_orders')->where('id', $orderId)->update([
            'status' => 'completed',
            'stock_reserved' => false,
            'balance_due' => 0,
            'updated_at' => $now,
        ]);
    }

    private function stockRow(int $productId, int $branchId): ?object
    {
        $locationId = $this->sellableLocationId($branchId);

        $stock = DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->when($locationId, fn ($query) => $query->where('inventory_location_id', $locationId))
            ->orderByDesc('qty_on_hand')
            ->first();

        if ($stock) {
            return $stock;
        }

        return DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->orderByDesc('qty_on_hand')
            ->first();
    }

    private function sellableLocationId(int $branchId): ?int
    {
        $locationId = DB::table('inventory_locations')
            ->where('branch_id', $branchId)
            ->where('is_sellable', true)
            ->orderBy('id')
            ->value('id');

        return $locationId ? (int) $locationId : null;
    }

    private function ensureCashSession(int $branchId, ?int $cashierId, ?int $sessionId, $now): int
    {
        if ($sessionId) {
            $session = DB::table('sales_cash_sessions')->where('id', $sessionId)->where('status', 'open')->first();
            if ($session) {
                return (int) $session->id;
            }
        }

        $session = DB::table('sales_cash_sessions')
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->when($cashierId, fn ($query) => $query->where('cashier_id', $cashierId))
            ->orderByDesc('id')
            ->first();

        if ($session) {
            return (int) $session->id;
        }

        return DB::table('sales_cash_sessions')->insertGetId([
            'branch_id' => $branchId,
            'cashier_id' => $cashierId,
            'session_number' => $this->nextNumber('CSH', 'sales_cash_sessions', 'session_number'),
            'opened_at' => $now,
            'opening_cash' => 0,
            'expected_cash' => 0,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function recalculateCashSession(int $sessionId): void
    {
        $session = DB::table('sales_cash_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            return;
        }

        $sales = DB::table('sales_payments')
            ->selectRaw('payment_method, direction, sum(amount) as total')
            ->where('sales_cash_session_id', $sessionId)
            ->where('status', 'posted')
            ->groupBy('payment_method', 'direction')
            ->get();

        $methodTotal = fn (string $method, string $direction) => (float) ($sales
            ->first(fn ($row) => $row->payment_method === $method && $row->direction === $direction)
            ->total ?? 0);
        $refundTotal = (float) $sales->where('direction', 'refund')->sum('total');
        $cashSales = $methodTotal('cash', 'in');
        $cashRefunds = $methodTotal('cash', 'refund');
        $expectedCash = round((float) $session->opening_cash + $cashSales - $cashRefunds, 2);

        DB::table('sales_cash_sessions')->where('id', $sessionId)->update([
            'total_cash_sales' => $cashSales,
            'total_card_sales' => $methodTotal('card', 'in'),
            'total_bank_transfer_sales' => $methodTotal('bank_transfer', 'in'),
            'total_mobile_wallet_sales' => $methodTotal('mobile_wallet', 'in'),
            'total_payment_link_sales' => $methodTotal('payment_link', 'in'),
            'total_refunds' => $refundTotal,
            'expected_cash' => $expectedCash,
            'difference' => $session->actual_cash !== null ? round((float) $session->actual_cash - $expectedCash, 2) : 0,
            'updated_at' => now(),
        ]);
    }

    private function resolveCustomer(Request $request, int $companyId, $now): ?int
    {
        $customerId = $request->integer('customer_id') ?: null;
        if ($customerId) {
            return $customerId;
        }

        $name = trim((string) $request->input('new_customer_name', ''));
        if ($name === '') {
            return null;
        }

        $phone = trim((string) $request->input('new_customer_phone', ''));
        $existing = $phone !== ''
            ? DB::table('sales_customers')->where('company_id', $companyId)->where('phone', $phone)->value('id')
            : null;

        if ($existing) {
            return (int) $existing;
        }

        return DB::table('sales_customers')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'phone' => $phone ?: null,
            'whatsapp_opt_in' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function companyId(): int
    {
        return (int) DB::table('companies')->orderBy('id')->value('id');
    }

    private function nextNumber(string $prefix, string $table, string $column): string
    {
        do {
            $number = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (DB::table($table)->where($column, $number)->exists());

        return $number;
    }

    private function audit(string $action, string $type, int $id, ?int $branchId, ?array $before, ?array $after, $now): void
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

    private function respond(Request $request, array $payload, string $page, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload);
        }

        $params = ['page' => $page];
        if (($payload['invoice_id'] ?? null) && $page === 'print') {
            $params['invoice_id'] = $payload['invoice_id'];
        }

        return redirect()->route('sales.app', $params)->with('status', __($message));
    }
}
