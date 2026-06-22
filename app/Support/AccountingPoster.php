<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingPoster
{
    public function postSalesInvoice(int $invoiceId): ?int
    {
        if (! $this->ready()) {
            return null;
        }

        $invoice = DB::table('sales_invoices')->where('id', $invoiceId)->first();
        if (! $invoice) {
            return null;
        }

        $grandTotal = round((float) $invoice->grand_total, 2);
        if ($grandTotal <= 0) {
            return null;
        }

        $tax = round((float) $invoice->tax_total, 2);
        $revenue = round($grandTotal - $tax, 2);
        $lines = [
            $this->line($this->account('1100'), 'Customer invoice '.$invoice->invoice_number, $grandTotal, 0, 'sales_customer', $invoice->customer_id),
            $this->line($this->account('4000'), 'Sales revenue '.$invoice->invoice_number, 0, $revenue, 'sales_customer', $invoice->customer_id),
        ];

        if ($tax > 0) {
            $lines[] = $this->line($this->account('2100'), 'VAT '.$invoice->invoice_number, 0, $tax, 'sales_customer', $invoice->customer_id);
        }

        $cost = round((float) DB::table('sales_invoice_items')
            ->where('sales_invoice_id', $invoiceId)
            ->sum(DB::raw('stock_quantity * cost_price')), 2);
        if ($cost > 0) {
            $lines[] = $this->line($this->account('5000'), 'Cost of goods sold '.$invoice->invoice_number, $cost, 0, 'sales_customer', $invoice->customer_id);
            $lines[] = $this->line($this->account('1200'), 'Inventory issued '.$invoice->invoice_number, 0, $cost, 'sales_customer', $invoice->customer_id);
        }

        return $this->journal(
            $invoice->company_id,
            $invoice->branch_id,
            'sales_invoice',
            $invoice->id,
            $invoice->invoice_date,
            'Sales invoice '.$invoice->invoice_number,
            $lines,
        );
    }

    public function postSalesPayment(int $paymentId): ?int
    {
        if (! $this->ready()) {
            return null;
        }

        $payment = DB::table('sales_payments')->where('id', $paymentId)->first();
        if (! $payment || $payment->status !== 'posted') {
            return null;
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $cashAccount = $this->cashAccount((string) $payment->payment_method);
        if ($payment->direction === 'refund') {
            $lines = [
                $this->line($this->account('1100'), 'Refund clears customer balance '.$payment->payment_number, $amount, 0, 'sales_customer', $payment->customer_id),
                $this->line($cashAccount, 'Refund paid '.$payment->payment_number, 0, $amount, 'sales_customer', $payment->customer_id),
            ];
        } else {
            $lines = [
                $this->line($cashAccount, 'Payment '.$payment->payment_number, $amount, 0, 'sales_customer', $payment->customer_id),
                $this->line($this->account('1100'), 'Customer balance payment '.$payment->payment_number, 0, $amount, 'sales_customer', $payment->customer_id),
            ];
        }

        return $this->journal(
            $payment->company_id,
            $payment->branch_id,
            'sales_payment',
            $payment->id,
            substr((string) $payment->paid_at, 0, 10),
            ($payment->direction === 'refund' ? 'Refund paid ' : 'Payment received ').$payment->payment_number,
            $lines,
        );
    }

    public function postSalesReturn(int $returnId): ?int
    {
        if (! $this->ready()) {
            return null;
        }

        $return = DB::table('sales_returns')->where('id', $returnId)->first();
        if (! $return) {
            return null;
        }

        $refund = round((float) $return->refund_total, 2);
        if ($refund <= 0) {
            return null;
        }

        $subtotal = round((float) $return->subtotal, 2);
        $tax = round((float) $return->tax_total, 2);
        $lines = [
            $this->line($this->account('4000'), 'Sales return '.$return->return_number, $subtotal, 0, 'sales_customer', $return->customer_id),
        ];

        if ($tax > 0) {
            $lines[] = $this->line($this->account('2100'), 'VAT reversed '.$return->return_number, $tax, 0, 'sales_customer', $return->customer_id);
        }

        $lines[] = $this->line($this->account('1100'), 'Credit note '.$return->return_number, 0, $refund, 'sales_customer', $return->customer_id);

        $restockedCost = round((float) DB::table('sales_return_items')
            ->join('sales_invoice_items', 'sales_invoice_items.id', '=', 'sales_return_items.original_invoice_item_id')
            ->where('sales_return_items.sales_return_id', $returnId)
            ->where('sales_return_items.restock_action', 'restock')
            ->sum(DB::raw('sales_return_items.quantity * sales_invoice_items.cost_price')), 2);
        if ($restockedCost > 0) {
            $lines[] = $this->line($this->account('1200'), 'Inventory returned '.$return->return_number, $restockedCost, 0, 'sales_customer', $return->customer_id);
            $lines[] = $this->line($this->account('5000'), 'Cost of goods sold reversed '.$return->return_number, 0, $restockedCost, 'sales_customer', $return->customer_id);
        }

        return $this->journal(
            $return->company_id,
            $return->branch_id,
            'sales_return',
            $return->id,
            $return->return_date,
            'Sales return '.$return->return_number,
            $lines,
        );
    }

    public function postGoodsReceipt(int $receiptId): ?int
    {
        if (! $this->ready()) {
            return null;
        }

        $receipt = DB::table('inventory_goods_receipts')->where('id', $receiptId)->first();
        if (! $receipt) {
            return null;
        }

        $amount = round((float) DB::table('inventory_goods_receipt_lines')
            ->where('inventory_goods_receipt_id', $receiptId)
            ->sum(DB::raw('accepted_quantity * unit_cost')), 2);

        if ($amount <= 0) {
            return null;
        }

        $companyId = (int) DB::table('branches')->where('id', $receipt->branch_id)->value('company_id');
        $lines = [
            $this->line($this->account('1200'), 'Goods received '.$receipt->receipt_number, $amount, 0, 'supplier', $receipt->supplier_id),
            $this->line($this->account('2000'), 'Supplier payable '.$receipt->receipt_number, 0, $amount, 'supplier', $receipt->supplier_id),
        ];

        return $this->journal(
            $companyId,
            $receipt->branch_id,
            'inventory_goods_receipt',
            $receipt->id,
            $receipt->received_on,
            'Goods receipt '.$receipt->receipt_number,
            $lines,
        );
    }

    public function postInventoryAdjustment(int $adjustmentId): ?int
    {
        if (! $this->ready()) {
            return null;
        }

        $adjustment = DB::table('inventory_adjustments')->where('id', $adjustmentId)->first();
        if (! $adjustment) {
            return null;
        }

        $lines = DB::table('inventory_adjustment_lines')
            ->where('inventory_adjustment_id', $adjustmentId)
            ->get();
        $loss = round((float) $lines
            ->filter(fn ($line) => (float) $line->variance_quantity < 0)
            ->sum(fn ($line) => abs((float) $line->variance_quantity) * (float) $line->unit_cost), 2);
        $gain = round((float) $lines
            ->filter(fn ($line) => (float) $line->variance_quantity > 0)
            ->sum(fn ($line) => (float) $line->variance_quantity * (float) $line->unit_cost), 2);
        if ($loss <= 0 && $gain <= 0) {
            return null;
        }

        $companyId = (int) DB::table('branches')->where('id', $adjustment->branch_id)->value('company_id');
        $journalLines = [];
        if ($loss > 0) {
            $journalLines = [
                $this->line($this->account('6100'), 'Inventory write-off '.$adjustment->adjustment_number, $loss, 0),
                $this->line($this->account('1200'), 'Inventory reduced '.$adjustment->adjustment_number, 0, $loss),
            ];
        }
        if ($gain > 0) {
            $journalLines[] = $this->line($this->account('1200'), 'Inventory increased '.$adjustment->adjustment_number, $gain, 0);
            $journalLines[] = $this->line($this->account('6100'), 'Inventory adjustment gain '.$adjustment->adjustment_number, 0, $gain);
        }

        return $this->journal(
            $companyId,
            $adjustment->branch_id,
            'inventory_adjustment',
            $adjustment->id,
            now()->toDateString(),
            'Inventory adjustment '.$adjustment->adjustment_number,
            $journalLines,
        );
    }

    private function ready(): bool
    {
        return collect(['accounting_accounts', 'accounting_journals', 'accounting_journal_lines'])
            ->every(fn (string $table) => Schema::hasTable($table));
    }

    private function cashAccount(string $method): int
    {
        return match ($method) {
            'card', 'bank_transfer' => $this->account('1010'),
            'mobile_wallet' => $this->account('1020'),
            'payment_link' => $this->account('1030'),
            default => $this->account('1000'),
        };
    }

    private function account(string $code): int
    {
        return (int) DB::table('accounting_accounts')->where('code', $code)->value('id');
    }

    private function line(int $accountId, string $description, float $debit, float $credit, ?string $partyType = null, mixed $partyId = null): array
    {
        return [
            'account_id' => $accountId,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'party_type' => $partyType,
            'party_id' => $partyId,
        ];
    }

    private function journal(mixed $companyId, mixed $branchId, string $sourceType, mixed $sourceId, string $date, string $description, array $lines): ?int
    {
        if (! $sourceId) {
            return null;
        }

        return app(AccountingService::class)->createPosted([
            'company_id' => $companyId ?: null,
            'branch_id' => $branchId ?: null,
            'journal_date' => $date,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'created_by' => auth()->id(),
        ], $lines);
    }
}
