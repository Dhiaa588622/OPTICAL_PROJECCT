<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('normal_balance')->default('debit')->index();
            $table->boolean('is_cash')->default(false)->index();
            $table->boolean('is_bank')->default(false)->index();
            $table->boolean('is_system')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'type']);
        });

        Schema::create('accounting_journals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('journal_number')->unique();
            $table->date('journal_date')->index();
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('status')->default('posted')->index();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 14, 2)->default(0);
            $table->decimal('total_credit', 14, 2)->default(0);
            $table->dateTime('posted_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['company_id', 'journal_date']);
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_journal_id')->constrained('accounting_journals')->cascadeOnDelete();
            $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('party_type')->nullable()->index();
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->timestamps();
            $table->index(['accounting_account_id', 'source_type']);
        });

        Schema::create('accounting_daily_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('closing_date')->index();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('cash_sales', 14, 2)->default(0);
            $table->decimal('card_sales', 14, 2)->default(0);
            $table->decimal('bank_transfer_sales', 14, 2)->default(0);
            $table->decimal('mobile_wallet_sales', 14, 2)->default(0);
            $table->decimal('payment_link_sales', 14, 2)->default(0);
            $table->decimal('refunds', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('actual_cash', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('status')->default('open')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'closing_date']);
        });

        Schema::create('accounting_integration_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key')->unique();
            $table->foreignId('debit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_integration_mappings');
        Schema::dropIfExists('accounting_daily_closings');
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('accounting_accounts');
    }
};
