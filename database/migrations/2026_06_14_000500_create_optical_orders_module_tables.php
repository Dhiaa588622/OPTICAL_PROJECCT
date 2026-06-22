<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optical_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_prescription_id')->nullable();
            $table->foreignId('sales_customer_id')->nullable();
            $table->foreignId('sales_invoice_id')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lab_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('frame_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('remake_of_order_id')->nullable();
            $table->string('order_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('priority')->default('normal')->index();
            $table->date('order_date')->index();
            $table->date('expected_delivery_date')->nullable()->index();
            $table->dateTime('lab_sent_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('lens_type')->nullable();
            $table->string('lens_material')->nullable();
            $table->string('lens_index')->nullable();
            $table->string('coating')->nullable();
            $table->string('tint')->nullable();
            $table->boolean('custom_lens_order')->default(false);
            $table->json('prescription_snapshot')->nullable();
            $table->json('frame_snapshot')->nullable();
            $table->text('fitting_notes')->nullable();
            $table->text('special_instructions')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('remake_reason')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('deposit_required', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2)->default(0);
            $table->string('payment_status')->default('unpaid')->index();
            $table->boolean('frame_stock_reserved')->default(false);
            $table->boolean('stock_reduced')->default(false);
            $table->boolean('allow_unpaid_delivery')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('patient_prescription_id', 'opt_orders_rx_fk')->references('id')->on('patient_prescriptions')->nullOnDelete();
            $table->foreign('sales_customer_id', 'opt_orders_customer_fk')->references('id')->on('sales_customers')->nullOnDelete();
            $table->foreign('sales_invoice_id', 'opt_orders_invoice_fk')->references('id')->on('sales_invoices')->nullOnDelete();
            $table->foreign('remake_of_order_id', 'opt_orders_remake_fk')->references('id')->on('optical_orders')->nullOnDelete();
            $table->index(['branch_id', 'status'], 'opt_orders_branch_status_idx');
            $table->index(['patient_id', 'order_date'], 'opt_orders_patient_date_idx');
        });

        Schema::create('optical_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('optical_order_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable();
            $table->foreignId('lab_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('item_type')->default('frame')->index();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->decimal('issued_quantity', 12, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->boolean('is_custom')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('optical_order_id', 'opt_order_items_order_fk')->references('id')->on('optical_orders')->cascadeOnDelete();
            $table->foreign('inventory_location_id', 'opt_order_items_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
        });

        Schema::create('optical_order_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('optical_order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status')->index();
            $table->string('event_title');
            $table->text('notes')->nullable();
            $table->dateTime('event_at')->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('optical_order_id', 'opt_order_status_order_fk')->references('id')->on('optical_orders')->cascadeOnDelete();
        });

        Schema::create('optical_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('optical_order_id');
            $table->foreignId('sales_payment_id')->nullable();
            $table->string('payment_number')->unique();
            $table->string('payment_method')->index();
            $table->string('direction')->default('in')->index();
            $table->decimal('amount', 14, 2);
            $table->dateTime('paid_at')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('optical_order_id', 'opt_order_payments_order_fk')->references('id')->on('optical_orders')->cascadeOnDelete();
            $table->foreign('sales_payment_id', 'opt_order_payments_sales_fk')->references('id')->on('sales_payments')->nullOnDelete();
        });

        Schema::create('optical_order_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('optical_order_id');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number')->unique();
            $table->string('document_type')->default('other')->index();
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('optical_order_id', 'opt_order_documents_order_fk')->references('id')->on('optical_orders')->cascadeOnDelete();
        });

        Schema::create('optical_order_lab_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('optical_order_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('incident_number')->unique();
            $table->string('incident_type')->default('remake')->index();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('responsibility')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('stock_written_off')->default(false);
            $table->dateTime('reported_at')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('optical_order_id', 'opt_order_lab_incidents_order_fk')->references('id')->on('optical_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optical_order_lab_incidents');
        Schema::dropIfExists('optical_order_documents');
        Schema::dropIfExists('optical_order_payments');
        Schema::dropIfExists('optical_order_status_events');
        Schema::dropIfExists('optical_order_items');
        Schema::dropIfExists('optical_orders');
    }
};
