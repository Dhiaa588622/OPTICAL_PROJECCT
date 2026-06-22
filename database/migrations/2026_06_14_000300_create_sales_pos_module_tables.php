<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->boolean('whatsapp_opt_in')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'name']);
        });

        Schema::create('sales_cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_number')->unique();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('total_cash_sales', 14, 2)->default(0);
            $table->decimal('total_card_sales', 14, 2)->default(0);
            $table->decimal('total_bank_transfer_sales', 14, 2)->default(0);
            $table->decimal('total_mobile_wallet_sales', 14, 2)->default(0);
            $table->decimal('total_payment_link_sales', 14, 2)->default(0);
            $table->decimal('total_refunds', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('actual_cash', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('status')->default('open')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('sales_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->string('quotation_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->date('quoted_on')->index();
            $table->date('expires_on')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_quotation_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type')->default('ready_product')->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->string('discount_type')->default('none');
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('optical_package_key')->nullable();
            $table->json('prescription_snapshot')->nullable();
            $table->boolean('is_custom_order')->default(false);
            $table->timestamps();
            $table->foreign('sales_quotation_id', 'sales_quote_items_quote_fk')->references('id')->on('sales_quotations')->cascadeOnDelete();
        });

        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->foreignId('source_quotation_id')->nullable();
            $table->string('order_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('order_type')->default('ready_product')->index();
            $table->date('ordered_on')->index();
            $table->date('pickup_due_on')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('deposit_required', 14, 2)->default(0);
            $table->decimal('deposit_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->boolean('stock_reserved')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('source_quotation_id', 'sales_orders_quote_fk')->references('id')->on('sales_quotations')->nullOnDelete();
        });

        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type')->default('ready_product')->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->string('discount_type')->default('none');
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('optical_package_key')->nullable();
            $table->json('prescription_snapshot')->nullable();
            $table->boolean('is_custom_order')->default(false);
            $table->timestamps();
            $table->foreign('sales_order_id', 'sales_order_items_order_fk')->references('id')->on('sales_orders')->cascadeOnDelete();
        });

        Schema::create('sales_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable();
            $table->foreignId('cash_session_id')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('invoice_type')->default('invoice')->index();
            $table->string('status')->default('posted')->index();
            $table->date('invoice_date')->index();
            $table->string('sale_mode')->default('ready_product')->index();
            $table->string('pickup_status')->default('not_required')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->decimal('gross_profit', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('sales_order_id', 'sales_invoices_order_fk')->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreign('cash_session_id', 'sales_invoices_session_fk')->references('id')->on('sales_cash_sessions')->nullOnDelete();
        });

        Schema::create('sales_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_invoice_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable();
            $table->string('item_type')->default('ready_product')->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->decimal('returned_quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->string('discount_type')->default('none');
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('optical_package_key')->nullable();
            $table->json('prescription_snapshot')->nullable();
            $table->boolean('is_custom_order')->default(false);
            $table->timestamps();
            $table->foreign('sales_invoice_id', 'sales_invoice_items_invoice_fk')->references('id')->on('sales_invoices')->cascadeOnDelete();
            $table->foreign('inventory_location_id', 'sales_invoice_items_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
        });

        Schema::create('sales_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->foreignId('sales_invoice_id')->nullable();
            $table->foreignId('sales_order_id')->nullable();
            $table->foreignId('sales_cash_session_id')->nullable();
            $table->string('payment_number')->unique();
            $table->string('payment_method')->index();
            $table->string('direction')->default('in')->index();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('posted')->index();
            $table->dateTime('paid_at')->index();
            $table->string('reference')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('sales_invoice_id', 'sales_payments_invoice_fk')->references('id')->on('sales_invoices')->nullOnDelete();
            $table->foreign('sales_order_id', 'sales_payments_order_fk')->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreign('sales_cash_session_id', 'sales_payments_session_fk')->references('id')->on('sales_cash_sessions')->nullOnDelete();
        });

        Schema::create('sales_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_invoice_id')->nullable();
            $table->foreignId('sales_payment_id')->nullable();
            $table->string('receipt_number')->unique();
            $table->string('receipt_type')->default('payment');
            $table->dateTime('printed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->foreign('sales_invoice_id', 'sales_receipts_invoice_fk')->references('id')->on('sales_invoices')->cascadeOnDelete();
            $table->foreign('sales_payment_id', 'sales_receipts_payment_fk')->references('id')->on('sales_payments')->nullOnDelete();
        });

        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_invoice_id');
            $table->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable();
            $table->string('return_number')->unique();
            $table->string('status')->default('posted')->index();
            $table->date('return_date')->index();
            $table->string('reason')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('refund_total', 14, 2)->default(0);
            $table->decimal('restock_total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('original_invoice_id', 'sales_returns_invoice_fk')->references('id')->on('sales_invoices')->cascadeOnDelete();
            $table->foreign('cash_session_id', 'sales_returns_session_fk')->references('id')->on('sales_cash_sessions')->nullOnDelete();
        });

        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_id');
            $table->foreignId('original_invoice_item_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('restock_action')->default('restock');
            $table->string('condition')->nullable();
            $table->foreignId('inventory_location_id')->nullable();
            $table->timestamps();
            $table->foreign('sales_return_id', 'sales_return_items_return_fk')->references('id')->on('sales_returns')->cascadeOnDelete();
            $table->foreign('original_invoice_item_id', 'sales_return_items_inv_item_fk')->references('id')->on('sales_invoice_items')->nullOnDelete();
            $table->foreign('inventory_location_id', 'sales_return_items_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
        });

        Schema::create('sales_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $table->foreignId('sales_return_id')->nullable();
            $table->foreignId('original_invoice_id')->nullable();
            $table->string('credit_note_number')->unique();
            $table->string('status')->default('open')->index();
            $table->date('credit_date')->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('remaining_amount', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('sales_return_id', 'sales_credit_notes_return_fk')->references('id')->on('sales_returns')->cascadeOnDelete();
            $table->foreign('original_invoice_id', 'sales_credit_notes_invoice_fk')->references('id')->on('sales_invoices')->nullOnDelete();
        });

        Schema::create('sales_credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_credit_note_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
            $table->foreign('sales_credit_note_id', 'sales_credit_items_note_fk')->references('id')->on('sales_credit_notes')->cascadeOnDelete();
        });

        Schema::create('sales_discount_approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->decimal('threshold_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->text('reason')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->index(['document_type', 'document_id'], 'sales_discount_doc_idx');
        });

        Schema::create('sales_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('discount_type')->default('percent');
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applies_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_promotions');
        Schema::dropIfExists('sales_discount_approvals');
        Schema::dropIfExists('sales_credit_note_items');
        Schema::dropIfExists('sales_credit_notes');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('sales_receipts');
        Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_quotation_items');
        Schema::dropIfExists('sales_quotations');
        Schema::dropIfExists('sales_cash_sessions');
        Schema::dropIfExists('sales_customers');
    }
};
