<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_frame_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('frame_size')->nullable();
            $table->string('bridge_size')->nullable();
            $table->string('temple_length')->nullable();
            $table->string('material')->nullable();
            $table->string('gender_style')->nullable();
            $table->string('rim_type')->nullable();
            $table->string('shape')->nullable();
            $table->timestamps();
            $table->unique('product_id', 'inv_frame_product_unique');
        });

        Schema::create('inventory_lens_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('lens_type')->nullable();
            $table->string('material')->nullable();
            $table->string('lens_index')->nullable();
            $table->string('coating')->nullable();
            $table->string('tint')->nullable();
            $table->decimal('sphere_min', 5, 2)->nullable();
            $table->decimal('sphere_max', 5, 2)->nullable();
            $table->decimal('cylinder_min', 5, 2)->nullable();
            $table->decimal('cylinder_max', 5, 2)->nullable();
            $table->boolean('is_custom_order')->default(false);
            $table->json('prescription_range')->nullable();
            $table->timestamps();
            $table->unique('product_id', 'inv_lens_product_unique');
        });

        Schema::create('inventory_contact_lens_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('power', 5, 2)->nullable();
            $table->decimal('base_curve', 4, 2)->nullable();
            $table->decimal('diameter', 4, 2)->nullable();
            $table->string('wear_schedule')->nullable();
            $table->unsignedSmallInteger('pack_size')->nullable();
            $table->timestamps();
            $table->unique('product_id', 'inv_contact_product_unique');
        });

        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('stock_room')->index();
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code'], 'inv_locations_branch_code_unique');
        });

        Schema::create('inventory_product_barcodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('barcode_type')->default('EAN13');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_number')->nullable()->index();
            $table->string('batch_number')->nullable()->index();
            $table->date('expires_on')->nullable()->index();
            $table->date('received_on')->nullable();
            $table->decimal('initial_quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('manufacturer_barcode')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'expires_on'], 'inv_batches_product_expiry_idx');
        });

        Schema::create('inventory_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->foreignId('inventory_location_id')->nullable();
            $table->string('serial_number')->unique();
            $table->string('status')->default('available')->index();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->foreign('inventory_batch_id', 'inv_serials_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
            $table->foreign('inventory_location_id', 'inv_serials_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->index(['source_type', 'source_id'], 'inv_serials_source_idx');
        });

        Schema::create('inventory_stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->decimal('qty_on_hand', 12, 3)->default(0);
            $table->decimal('qty_reserved', 12, 3)->default(0);
            $table->decimal('minimum_stock_level', 12, 3)->default(0);
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->decimal('average_cost', 14, 2)->default(0);
            $table->timestamps();
            $table->foreign('inventory_location_id', 'inv_stock_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('inventory_batch_id', 'inv_stock_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
            $table->unique(['product_id', 'branch_id', 'inventory_location_id', 'inventory_batch_id'], 'inv_stock_level_unique');
            $table->index(['branch_id', 'qty_on_hand'], 'inv_stock_branch_qty_idx');
        });

        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->foreignId('inventory_serial_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type')->index();
            $table->string('direction')->index();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('balance_after', 12, 3)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            $table->foreign('inventory_location_id', 'inv_mov_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('inventory_batch_id', 'inv_mov_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
            $table->foreign('inventory_serial_id', 'inv_mov_serial_fk')->references('id')->on('inventory_serials')->nullOnDelete();
            $table->index(['reference_type', 'reference_id'], 'inv_mov_reference_idx');
        });

        Schema::create('inventory_purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('po_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->date('ordered_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_purchase_order_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('ordered_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->date('expected_on')->nullable();
            $table->timestamps();
            $table->foreign('inventory_purchase_order_id', 'inv_po_lines_po_fk')->references('id')->on('inventory_purchase_orders')->cascadeOnDelete();
        });

        Schema::create('inventory_goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_purchase_order_id')->nullable();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('supplier_invoice_number')->nullable();
            $table->date('received_on')->index();
            $table->string('status')->default('draft')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('inventory_purchase_order_id', 'inv_gr_po_fk')->references('id')->on('inventory_purchase_orders')->nullOnDelete();
        });

        Schema::create('inventory_goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_goods_receipt_id');
            $table->foreignId('inventory_purchase_order_line_id')->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->decimal('received_quantity', 12, 3);
            $table->decimal('accepted_quantity', 12, 3)->default(0);
            $table->decimal('rejected_quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('lot_number')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('inventory_goods_receipt_id', 'inv_gr_lines_receipt_fk')->references('id')->on('inventory_goods_receipts')->cascadeOnDelete();
            $table->foreign('inventory_purchase_order_line_id', 'inv_gr_lines_po_line_fk')->references('id')->on('inventory_purchase_order_lines')->nullOnDelete();
            $table->foreign('inventory_batch_id', 'inv_gr_lines_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
        });

        Schema::create('inventory_supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('return_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('reason')->nullable();
            $table->date('return_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_supplier_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_supplier_return_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('condition')->nullable();
            $table->timestamps();
            $table->foreign('inventory_supplier_return_id', 'inv_sr_lines_return_fk')->references('id')->on('inventory_supplier_returns')->cascadeOnDelete();
            $table->foreign('inventory_batch_id', 'inv_sr_lines_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('adjustment_number')->unique();
            $table->string('reason')->index();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_adjustment_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->decimal('system_quantity', 12, 3)->default(0);
            $table->decimal('adjusted_quantity', 12, 3)->default(0);
            $table->decimal('variance_quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('inventory_adjustment_id', 'inv_adj_lines_adjustment_fk')->references('id')->on('inventory_adjustments')->cascadeOnDelete();
            $table->foreign('inventory_location_id', 'inv_adj_lines_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('inventory_batch_id', 'inv_adj_lines_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
        });

        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable();
            $table->foreignId('to_location_id')->nullable();
            $table->string('transfer_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('from_location_id', 'inv_transfer_from_loc_fk')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('to_location_id', 'inv_transfer_to_loc_fk')->references('id')->on('inventory_locations')->nullOnDelete();
        });

        Schema::create('inventory_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_transfer_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->decimal('requested_quantity', 12, 3);
            $table->decimal('shipped_quantity', 12, 3)->default(0);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->timestamps();
            $table->foreign('inventory_transfer_id', 'inv_transfer_lines_transfer_fk')->references('id')->on('inventory_transfers')->cascadeOnDelete();
            $table->foreign('inventory_batch_id', 'inv_transfer_lines_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
        });

        Schema::create('inventory_stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable();
            $table->string('count_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->date('count_date')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('inventory_location_id', 'inv_count_location_fk')->references('id')->on('inventory_locations')->nullOnDelete();
        });

        Schema::create('inventory_stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_stock_count_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->decimal('system_quantity', 12, 3)->default(0);
            $table->decimal('counted_quantity', 12, 3)->default(0);
            $table->decimal('variance_quantity', 12, 3)->default(0);
            $table->string('count_status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('inventory_stock_count_id', 'inv_count_lines_count_fk')->references('id')->on('inventory_stock_counts')->cascadeOnDelete();
            $table->foreign('inventory_batch_id', 'inv_count_lines_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->decimal('quantity', 12, 3);
            $table->string('status')->default('active')->index();
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
            $table->foreign('inventory_batch_id', 'inv_reservations_batch_fk')->references('id')->on('inventory_batches')->nullOnDelete();
            $table->index(['source_type', 'source_id'], 'inv_reservations_source_idx');
        });

        Schema::create('inventory_valuation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('snapshot_date')->index();
            $table->decimal('total_quantity', 14, 3)->default(0);
            $table->decimal('total_cost_value', 14, 2)->default(0);
            $table->decimal('total_retail_value', 14, 2)->default(0);
            $table->json('summary_by_category')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_valuation_snapshots');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_stock_count_lines');
        Schema::dropIfExists('inventory_stock_counts');
        Schema::dropIfExists('inventory_transfer_lines');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_supplier_return_lines');
        Schema::dropIfExists('inventory_supplier_returns');
        Schema::dropIfExists('inventory_goods_receipt_lines');
        Schema::dropIfExists('inventory_goods_receipts');
        Schema::dropIfExists('inventory_purchase_order_lines');
        Schema::dropIfExists('inventory_purchase_orders');
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_stock_levels');
        Schema::dropIfExists('inventory_serials');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('inventory_product_barcodes');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_contact_lens_details');
        Schema::dropIfExists('inventory_lens_details');
        Schema::dropIfExists('inventory_frame_details');
    }
};
