<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journals', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->after('source_id')
                ->constrained('accounting_journals')->restrictOnDelete();
            $table->unique('reversal_of_id');
        });

        Schema::create('accounting_monthly_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_debit', 14, 2)->default(0);
            $table->decimal('total_credit', 14, 2)->default(0);
            $table->string('status')->default('closed')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'year', 'month'], 'accounting_monthly_period_unique');
        });

        Schema::create('offline_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module')->index();
            $table->string('form_key');
            $table->json('payload');
            $table->string('status')->default('draft')->index();
            $table->string('idempotency_key')->unique();
            $table->dateTime('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'module', 'status']);
        });

        $this->createImmutabilityTriggers();
    }

    public function down(): void
    {
        $this->dropImmutabilityTriggers();
        Schema::dropIfExists('offline_drafts');
        Schema::dropIfExists('accounting_monthly_closings');

        Schema::table('accounting_journals', function (Blueprint $table): void {
            $table->dropUnique(['reversal_of_id']);
            $table->dropConstrainedForeignId('reversal_of_id');
        });
    }

    private function createImmutabilityTriggers(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $triggers = [
                "CREATE TRIGGER prevent_posted_invoice_update BEFORE UPDATE ON sales_invoices WHEN OLD.status = 'posted' BEGIN SELECT RAISE(ABORT, 'Posted invoices are immutable'); END",
                "CREATE TRIGGER prevent_posted_invoice_delete BEFORE DELETE ON sales_invoices WHEN OLD.status = 'posted' BEGIN SELECT RAISE(ABORT, 'Posted invoices are immutable'); END",
                "CREATE TRIGGER prevent_posted_invoice_item_update BEFORE UPDATE ON sales_invoice_items WHEN EXISTS (SELECT 1 FROM sales_invoices WHERE id = OLD.sales_invoice_id AND status = 'posted') BEGIN SELECT RAISE(ABORT, 'Posted invoice lines are immutable'); END",
                "CREATE TRIGGER prevent_posted_invoice_item_delete BEFORE DELETE ON sales_invoice_items WHEN EXISTS (SELECT 1 FROM sales_invoices WHERE id = OLD.sales_invoice_id AND status = 'posted') BEGIN SELECT RAISE(ABORT, 'Posted invoice lines are immutable'); END",
                "CREATE TRIGGER prevent_posted_payment_update BEFORE UPDATE ON sales_payments WHEN OLD.status = 'posted' BEGIN SELECT RAISE(ABORT, 'Posted payments are immutable'); END",
                "CREATE TRIGGER prevent_posted_payment_delete BEFORE DELETE ON sales_payments WHEN OLD.status = 'posted' BEGIN SELECT RAISE(ABORT, 'Posted payments are immutable'); END",
                "CREATE TRIGGER prevent_posted_journal_update BEFORE UPDATE ON accounting_journals WHEN OLD.status IN ('posted', 'reversed') AND NOT (OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.company_id IS OLD.company_id AND NEW.branch_id IS OLD.branch_id AND NEW.journal_number IS OLD.journal_number AND NEW.journal_date IS OLD.journal_date AND NEW.source_type IS OLD.source_type AND NEW.source_id IS OLD.source_id AND NEW.reversal_of_id IS OLD.reversal_of_id AND NEW.description IS OLD.description AND NEW.total_debit IS OLD.total_debit AND NEW.total_credit IS OLD.total_credit AND NEW.posted_at IS OLD.posted_at AND NEW.created_by IS OLD.created_by) BEGIN SELECT RAISE(ABORT, 'Posted journals are immutable'); END",
                "CREATE TRIGGER prevent_posted_journal_delete BEFORE DELETE ON accounting_journals WHEN OLD.status IN ('posted', 'reversed') BEGIN SELECT RAISE(ABORT, 'Posted journals are immutable'); END",
                "CREATE TRIGGER prevent_posted_journal_line_update BEFORE UPDATE ON accounting_journal_lines WHEN EXISTS (SELECT 1 FROM accounting_journals WHERE id = OLD.accounting_journal_id AND status IN ('posted', 'reversed')) BEGIN SELECT RAISE(ABORT, 'Posted journal lines are immutable'); END",
                "CREATE TRIGGER prevent_posted_journal_line_delete BEFORE DELETE ON accounting_journal_lines WHEN EXISTS (SELECT 1 FROM accounting_journals WHERE id = OLD.accounting_journal_id AND status IN ('posted', 'reversed')) BEGIN SELECT RAISE(ABORT, 'Posted journal lines are immutable'); END",
            ];
        } elseif ($driver === 'mysql') {
            $triggers = [
                "CREATE TRIGGER prevent_posted_invoice_update BEFORE UPDATE ON sales_invoices FOR EACH ROW BEGIN IF OLD.status = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted invoices are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_invoice_delete BEFORE DELETE ON sales_invoices FOR EACH ROW BEGIN IF OLD.status = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted invoices are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_invoice_item_update BEFORE UPDATE ON sales_invoice_items FOR EACH ROW BEGIN IF (SELECT status FROM sales_invoices WHERE id = OLD.sales_invoice_id) = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted invoice lines are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_invoice_item_delete BEFORE DELETE ON sales_invoice_items FOR EACH ROW BEGIN IF (SELECT status FROM sales_invoices WHERE id = OLD.sales_invoice_id) = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted invoice lines are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_payment_update BEFORE UPDATE ON sales_payments FOR EACH ROW BEGIN IF OLD.status = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted payments are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_payment_delete BEFORE DELETE ON sales_payments FOR EACH ROW BEGIN IF OLD.status = 'posted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted payments are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_journal_update BEFORE UPDATE ON accounting_journals FOR EACH ROW BEGIN IF OLD.status IN ('posted', 'reversed') AND NOT (OLD.status = 'posted' AND NEW.status = 'reversed' AND NEW.company_id <=> OLD.company_id AND NEW.branch_id <=> OLD.branch_id AND NEW.journal_number = OLD.journal_number AND NEW.journal_date = OLD.journal_date AND NEW.source_type <=> OLD.source_type AND NEW.source_id <=> OLD.source_id AND NEW.reversal_of_id <=> OLD.reversal_of_id AND NEW.description <=> OLD.description AND NEW.total_debit = OLD.total_debit AND NEW.total_credit = OLD.total_credit AND NEW.posted_at <=> OLD.posted_at AND NEW.created_by <=> OLD.created_by) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journals are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_journal_delete BEFORE DELETE ON accounting_journals FOR EACH ROW BEGIN IF OLD.status IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journals are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_journal_line_update BEFORE UPDATE ON accounting_journal_lines FOR EACH ROW BEGIN IF (SELECT status FROM accounting_journals WHERE id = OLD.accounting_journal_id) IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal lines are immutable'; END IF; END",
                "CREATE TRIGGER prevent_posted_journal_line_delete BEFORE DELETE ON accounting_journal_lines FOR EACH ROW BEGIN IF (SELECT status FROM accounting_journals WHERE id = OLD.accounting_journal_id) IN ('posted', 'reversed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posted journal lines are immutable'; END IF; END",
            ];
        } else {
            return;
        }

        foreach ($triggers as $trigger) {
            DB::unprepared($trigger);
        }
    }

    private function dropImmutabilityTriggers(): void
    {
        foreach ([
            'prevent_posted_invoice_update', 'prevent_posted_invoice_delete',
            'prevent_posted_invoice_item_update', 'prevent_posted_invoice_item_delete',
            'prevent_posted_payment_update', 'prevent_posted_payment_delete',
            'prevent_posted_journal_update', 'prevent_posted_journal_delete',
            'prevent_posted_journal_line_update', 'prevent_posted_journal_line_delete',
        ] as $trigger) {
            DB::statement('DROP TRIGGER IF EXISTS '.$trigger);
        }
    }
};
