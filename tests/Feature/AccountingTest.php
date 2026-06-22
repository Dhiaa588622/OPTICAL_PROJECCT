<?php

namespace Tests\Feature;

use App\Support\AccountingPoster;
use App\Support\AccountingReportService;
use App\Support\AccountingService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $branchId;

    private int $cashAccountId;

    private int $revenueAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        $this->companyId = DB::table('companies')->insertGetId(['name' => 'Test Optical', 'currency' => 'SAR', 'created_at' => $now, 'updated_at' => $now]);
        $this->branchId = DB::table('branches')->insertGetId(['company_id' => $this->companyId, 'code' => 'MAIN', 'name' => 'Main', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->cashAccountId = $this->account('1000', 'Cash', 'asset', 'debit');
        $this->revenueAccountId = $this->account('4000', 'Revenue', 'revenue', 'credit');
    }

    public function test_balanced_voucher_posts_atomically(): void
    {
        $id = app(AccountingService::class)->createPosted($this->header(), $this->balancedLines());

        $this->assertDatabaseHas('accounting_journals', ['id' => $id, 'status' => 'posted', 'total_debit' => 125, 'total_credit' => 125]);
        $this->assertDatabaseCount('accounting_journal_lines', 2);
    }

    public function test_unbalanced_voucher_is_rejected_without_partial_rows(): void
    {
        try {
            app(AccountingService::class)->createPosted($this->header(), [
                ['account_id' => $this->cashAccountId, 'debit' => 125, 'credit' => 0],
                ['account_id' => $this->revenueAccountId, 'debit' => 0, 'credit' => 100],
            ]);
            $this->fail('Expected an unbalanced voucher exception.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('unbalanced', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseCount('accounting_journals', 0);
        $this->assertDatabaseCount('accounting_journal_lines', 0);
    }

    public function test_posted_journal_is_immutable_and_can_only_be_corrected_by_reversal(): void
    {
        $service = app(AccountingService::class);
        $id = $service->createPosted($this->header(), $this->balancedLines());

        try {
            DB::table('accounting_journals')->where('id', $id)->update(['description' => 'Changed']);
            $this->fail('Expected the database immutability trigger to reject the update.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        try {
            DB::table('accounting_journals')->where('id', $id)->update(['status' => 'reversed', 'description' => 'Changed']);
            $this->fail('Expected the database trigger to reject a disguised edit.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $reversalId = $service->reverse($id, now()->toDateString(), 'Correction');
        $this->assertDatabaseHas('accounting_journals', ['id' => $id, 'status' => 'reversed']);
        $this->assertDatabaseHas('accounting_journals', ['id' => $reversalId, 'reversal_of_id' => $id, 'total_debit' => 125, 'total_credit' => 125]);
    }

    public function test_posted_invoice_and_payment_are_database_immutable(): void
    {
        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'invoice_number' => 'INV-LOCKED',
            'status' => 'posted', 'invoice_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $paymentId = DB::table('sales_payments')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'sales_invoice_id' => $invoiceId,
            'payment_number' => 'PAY-LOCKED', 'payment_method' => 'cash', 'amount' => 50, 'status' => 'posted',
            'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['sales_invoices', $invoiceId], ['sales_payments', $paymentId]] as [$table, $id]) {
            try {
                DB::table($table)->where('id', $id)->update(['notes' => 'Direct edit']);
                $this->fail('Expected posted record immutability trigger to reject the update.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_monthly_close_locks_future_posting(): void
    {
        $service = app(AccountingService::class);
        $service->closeMonthly($this->companyId, $this->branchId, (int) now()->format('Y'), (int) now()->format('m'), null, null);

        $this->expectException(DomainException::class);
        $service->createPosted($this->header(), $this->balancedLines());
    }

    public function test_draft_can_be_edited_but_posted_journal_cannot(): void
    {
        $service = app(AccountingService::class);
        $draftId = $service->createDraft($this->header(), $this->balancedLines());
        $service->updateDraft($draftId, [...$this->header(), 'description' => 'Edited draft'], [
            ['account_id' => $this->cashAccountId, 'debit' => 175, 'credit' => 0],
            ['account_id' => $this->revenueAccountId, 'debit' => 0, 'credit' => 175],
        ]);

        $this->assertDatabaseHas('accounting_journals', ['id' => $draftId, 'status' => 'draft', 'description' => 'Edited draft', 'total_debit' => 175, 'total_credit' => 175]);
        $service->postDraft($draftId);

        $this->expectException(DomainException::class);
        $service->updateDraft($draftId, $this->header(), $this->balancedLines());
    }

    public function test_trial_balance_profit_and_loss_and_balance_sheet_come_from_balanced_ledger(): void
    {
        $expenseAccount = $this->account('6000', 'Expense', 'expense', 'debit');
        $service = app(AccountingService::class);
        $service->createPosted($this->header(), [
            ['account_id' => $this->cashAccountId, 'debit' => 200, 'credit' => 0],
            ['account_id' => $this->revenueAccountId, 'debit' => 0, 'credit' => 200],
        ]);
        $service->createPosted([...$this->header(), 'description' => 'Expense'], [
            ['account_id' => $expenseAccount, 'debit' => 50, 'credit' => 0],
            ['account_id' => $this->cashAccountId, 'debit' => 0, 'credit' => 50],
        ]);

        $reports = app(AccountingReportService::class);
        $trialBalance = $reports->trialBalance($this->branchId);
        $this->assertSame(200.0, round((float) $trialBalance->sum('debit_balance'), 2));
        $this->assertSame(200.0, round((float) $trialBalance->sum('credit_balance'), 2));
        $this->assertSame(150.0, (float) $reports->profitAndLoss($this->branchId)->where('type', 'net_income')->first()->amount);

        $balanceSheet = $reports->balanceSheet($this->branchId);
        $assets = $balanceSheet->where('type', 'asset')->sum('amount');
        $equity = $balanceSheet->where('type', 'equity')->sum('amount');
        $this->assertSame(round((float) $assets, 2), round((float) $equity, 2));
    }

    public function test_sales_invoice_posts_revenue_tax_and_cost_of_goods_sold(): void
    {
        $receivable = $this->account('1100', 'Receivable', 'asset', 'debit');
        $inventory = $this->account('1200', 'Inventory', 'asset', 'debit');
        $vat = $this->account('2100', 'VAT payable', 'liability', 'credit');
        $cogs = $this->account('5000', 'Cost of goods sold', 'expense', 'debit');
        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId, 'invoice_number' => 'INV-COGS', 'status' => 'paid',
            'invoice_date' => now()->toDateString(), 'subtotal' => 100, 'tax_total' => 15, 'grand_total' => 115, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => $invoiceId, 'description' => 'Frame', 'quantity' => 1, 'stock_quantity' => 1,
            'unit_price' => 100, 'cost_price' => 30, 'tax_rate' => 15, 'tax_amount' => 15, 'line_total' => 115,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $journalId = app(AccountingPoster::class)->postSalesInvoice($invoiceId);

        $this->assertDatabaseHas('accounting_journals', ['id' => $journalId, 'total_debit' => 145, 'total_credit' => 145]);
        $this->assertDatabaseHas('accounting_journal_lines', ['accounting_journal_id' => $journalId, 'accounting_account_id' => $receivable, 'debit' => 115]);
        $this->assertDatabaseHas('accounting_journal_lines', ['accounting_journal_id' => $journalId, 'accounting_account_id' => $vat, 'credit' => 15]);
        $this->assertDatabaseHas('accounting_journal_lines', ['accounting_journal_id' => $journalId, 'accounting_account_id' => $cogs, 'debit' => 30]);
        $this->assertDatabaseHas('accounting_journal_lines', ['accounting_journal_id' => $journalId, 'accounting_account_id' => $inventory, 'credit' => 30]);
    }

    private function account(string $code, string $name, string $type, string $normalBalance): int
    {
        return DB::table('accounting_accounts')->insertGetId([
            'company_id' => $this->companyId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function header(): array
    {
        return ['company_id' => $this->companyId, 'branch_id' => $this->branchId, 'journal_date' => now()->toDateString(), 'description' => 'Test voucher'];
    }

    private function balancedLines(): array
    {
        return [
            ['account_id' => $this->cashAccountId, 'debit' => 125, 'credit' => 0],
            ['account_id' => $this->revenueAccountId, 'debit' => 0, 'credit' => 125],
        ];
    }
}
