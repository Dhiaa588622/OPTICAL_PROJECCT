<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OpticalOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('optical_orders')) {
            return;
        }

        $now = now();
        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchIds = DB::table('branches')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $productIds = DB::table('products')->pluck('id', 'sku')->map(fn ($id) => (int) $id)->all();
        $supplierIds = DB::table('suppliers')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
        $patientIds = DB::table('patients')->pluck('id', 'patient_code')->map(fn ($id) => (int) $id)->all();

        if (! $companyId || $branchIds === [] || $productIds === [] || $patientIds === []) {
            return;
        }

        $salespersonId = $this->user('orders@example.com', 'Optical Order Desk', $now);
        $labUserId = $this->user('lab@example.com', 'Lab Coordinator', $now);

        $this->permissionsAndRoles($companyId, $now);

        $orders = [
            [
                'number' => 'OPT-2001',
                'patient' => 'PT-1001',
                'branch' => 'MAIN',
                'status' => 'confirmed',
                'priority' => 'normal',
                'rx' => 'RX-1001',
                'frame' => 'FRM-RAY-2140-BLK',
                'lens' => null,
                'lab' => 'BlueShield Labs',
                'custom' => true,
                'lens_type' => 'progressive',
                'lens_material' => 'resin',
                'lens_index' => '1.60',
                'coating' => 'blue_block',
                'tint' => 'clear',
                'due_days' => 3,
                'deposit' => 350,
                'payment_method' => 'cash',
                'notes' => 'Segment height 18mm. Patient wants thin progressive lenses.',
                'instructions' => 'Blue block coating, urgent check before edging.',
            ],
            [
                'number' => 'OPT-2002',
                'patient' => 'PT-1002',
                'branch' => 'MAIN',
                'status' => 'sent_to_lab',
                'priority' => 'urgent',
                'rx' => 'RX-1002',
                'frame' => 'FRM-RAY-2140-BLK',
                'lens' => 'LEN-PROG-150',
                'lab' => 'BlueShield Labs',
                'custom' => false,
                'lens_type' => 'progressive',
                'lens_material' => 'resin',
                'lens_index' => '1.50',
                'coating' => 'premium_ar',
                'tint' => 'clear',
                'due_days' => 1,
                'deposit' => 600,
                'payment_method' => 'card',
                'notes' => 'Patient requested narrow fitting. Call before pickup.',
                'instructions' => 'Verify corridor and near zone.',
            ],
            [
                'number' => 'OPT-2003',
                'patient' => 'PT-1001',
                'branch' => 'MAIN',
                'status' => 'ready_for_pickup',
                'priority' => 'vip',
                'rx' => 'RX-1001',
                'frame' => 'SUN-POL-330',
                'lens' => null,
                'lab' => 'Vista Eyewear',
                'custom' => true,
                'lens_type' => 'single_vision',
                'lens_material' => 'polycarbonate',
                'lens_index' => '1.59',
                'coating' => 'premium_ar',
                'tint' => 'gray',
                'due_days' => 0,
                'deposit' => 500,
                'payment_method' => 'mobile_wallet',
                'notes' => 'Prescription sunglasses package.',
                'instructions' => 'Gray tint 70 percent, polish edges.',
            ],
            [
                'number' => 'OPT-2004',
                'patient' => 'PT-1002',
                'branch' => 'MAIN',
                'status' => 'remake',
                'priority' => 'warranty',
                'rx' => 'RX-1002',
                'frame' => 'FRM-KID-RED-S',
                'lens' => null,
                'lab' => 'BlueShield Labs',
                'custom' => true,
                'lens_type' => 'bifocal',
                'lens_material' => 'cr39',
                'lens_index' => '1.50',
                'coating' => 'standard_ar',
                'tint' => 'clear',
                'due_days' => -2,
                'deposit' => 0,
                'payment_method' => 'cash',
                'notes' => 'Remake after QC axis mismatch.',
                'instructions' => 'Remake right lens. Verify axis before delivery.',
            ],
        ];

        foreach ($orders as $order) {
            $this->upsertOrder($companyId, $branchIds, $productIds, $supplierIds, $patientIds, $order, $salespersonId, $labUserId, $now);
        }
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

    private function upsertOrder(int $companyId, array $branchIds, array $productIds, array $supplierIds, array $patientIds, array $demo, int $salespersonId, int $labUserId, $now): void
    {
        $branchId = $branchIds[$demo['branch']] ?? reset($branchIds);
        $patientId = $patientIds[$demo['patient']] ?? reset($patientIds);
        $patient = DB::table('patients')->where('id', $patientId)->first();
        $customerId = DB::table('sales_customers')->where('patient_id', $patientId)->value('id');
        $rx = DB::table('patient_prescriptions')->where('prescription_number', $demo['rx'])->first();
        $labId = $supplierIds[$demo['lab']] ?? null;
        $frameId = $demo['frame'] ? ($productIds[$demo['frame']] ?? null) : null;
        $lensId = $demo['lens'] ? ($productIds[$demo['lens']] ?? null) : null;
        $orderDate = now()->subDays(4)->toDateString();
        $expected = now()->addDays($demo['due_days'])->toDateString();
        $lines = $this->lines($branchId, $frameId, $lensId, $demo, $labId);
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $tax = round(array_sum(array_column($lines, 'tax_amount')), 2);
        $grand = round(array_sum(array_column($lines, 'line_total')), 2);
        $deposit = min((float) $demo['deposit'], $grand);
        $outstanding = max(0, round($grand - $deposit, 2));

        if (! $customerId && $patient) {
            $customerId = DB::table('sales_customers')->insertGetId([
                'company_id' => $companyId,
                'patient_id' => $patientId,
                'name' => $patient->full_name,
                'phone' => $patient->phone ?: $patient->whatsapp_number,
                'email' => $patient->email,
                'whatsapp_opt_in' => (bool) $patient->whatsapp_number,
                'notes' => 'Synced from Optical Order demo data.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('optical_orders')->updateOrInsert(
            ['order_number' => $demo['number']],
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'patient_id' => $patientId,
                'patient_prescription_id' => $rx?->id,
                'sales_customer_id' => $customerId,
                'salesperson_id' => $salespersonId,
                'lab_supplier_id' => $labId,
                'frame_product_id' => $frameId,
                'status' => $demo['status'],
                'priority' => $demo['priority'],
                'order_date' => $orderDate,
                'expected_delivery_date' => $expected,
                'lab_sent_at' => in_array($demo['status'], ['sent_to_lab', 'in_lab', 'quality_check', 'ready_for_pickup', 'remake'], true) ? now()->subDays(2) : null,
                'ready_at' => $demo['status'] === 'ready_for_pickup' ? now()->subHours(6) : null,
                'lens_type' => $demo['lens_type'],
                'lens_material' => $demo['lens_material'],
                'lens_index' => $demo['lens_index'],
                'coating' => $demo['coating'],
                'tint' => $demo['tint'],
                'custom_lens_order' => $demo['custom'],
                'prescription_snapshot' => $rx ? json_encode($this->prescriptionSnapshot($rx)) : null,
                'frame_snapshot' => $frameId ? json_encode($this->productSnapshot($frameId, $branchId)) : null,
                'fitting_notes' => $demo['notes'],
                'special_instructions' => $demo['instructions'],
                'remake_reason' => $demo['status'] === 'remake' ? 'QC found axis mismatch; remake required.' : null,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => $tax,
                'grand_total' => $grand,
                'deposit_required' => $deposit,
                'paid_amount' => $deposit,
                'outstanding_amount' => $outstanding,
                'payment_status' => $deposit <= 0 ? 'unpaid' : ($outstanding <= 0 ? 'paid' : 'partial'),
                'frame_stock_reserved' => in_array($demo['status'], ['confirmed', 'sent_to_lab', 'in_lab', 'quality_check', 'ready_for_pickup', 'remake'], true),
                'stock_reduced' => false,
                'allow_unpaid_delivery' => false,
                'created_by' => $salespersonId,
                'confirmed_by' => $salespersonId,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $orderId = (int) DB::table('optical_orders')->where('order_number', $demo['number'])->value('id');
        DB::table('optical_order_items')->where('optical_order_id', $orderId)->delete();
        foreach ($lines as $line) {
            $itemId = DB::table('optical_order_items')->insertGetId([
                'optical_order_id' => $orderId,
                'product_id' => $line['product_id'],
                'inventory_location_id' => $line['location_id'],
                'lab_supplier_id' => $labId,
                'item_type' => $line['item_type'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'reserved_quantity' => $line['item_type'] === 'frame' && $demo['status'] !== 'draft' ? $line['quantity'] : 0,
                'issued_quantity' => 0,
                'unit_price' => $line['unit_price'],
                'cost_price' => $line['cost_price'],
                'discount_amount' => 0,
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'is_custom' => $line['is_custom'],
                'metadata' => json_encode($line['metadata']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($line['item_type'] === 'frame' && $demo['status'] !== 'draft') {
                $this->reserveFrameOnce($orderId, $itemId, $line, $branchId, $now);
            }
        }

        $this->payment($companyId, $branchId, $customerId, $orderId, $demo, $deposit, $salespersonId, $now);
        $this->events($orderId, $demo, $salespersonId, $labUserId, $now);
        $this->patientTimeline($orderId, $patientId, $branchId, $demo, $salespersonId, $now);
        $this->whatsapp($patient, $orderId, $demo, $now);
        $this->documents($orderId, $demo, $salespersonId, $now);

        if ($demo['status'] === 'remake') {
            DB::table('optical_order_lab_incidents')->updateOrInsert(
                ['incident_number' => 'REM-'.$demo['number']],
                [
                    'optical_order_id' => $orderId,
                    'product_id' => $frameId,
                    'incident_type' => 'remake',
                    'quantity' => 1,
                    'responsibility' => 'lab',
                    'reason' => 'QC found axis mismatch; remake required.',
                    'stock_written_off' => false,
                    'reported_at' => now()->subDay(),
                    'reported_by' => $labUserId,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function lines(int $branchId, ?int $frameId, ?int $lensId, array $demo, ?int $labId): array
    {
        $lines = [];
        if ($frameId) {
            $lines[] = $this->productLine($frameId, $branchId, 'frame', 1, null, false, ['lab_id' => $labId]);
        }
        if ($lensId && ! $demo['custom']) {
            $lines[] = $this->productLine($lensId, $branchId, 'lens', 2, null, false, ['lab_id' => $labId, 'lens_type' => $demo['lens_type']]);
        } else {
            $price = $demo['lens_type'] === 'progressive' ? 680 : 420;
            $tax = round($price * 0.15, 2);
            $lines[] = [
                'product_id' => null,
                'location_id' => null,
                'item_type' => 'custom_lens',
                'description' => str($demo['lens_type'])->replace('_', ' ')->title().' custom lens package',
                'quantity' => 1,
                'unit_price' => $price,
                'cost_price' => $demo['lens_type'] === 'progressive' ? 240 : 160,
                'tax_rate' => 15,
                'tax_amount' => $tax,
                'line_total' => round($price + $tax, 2),
                'subtotal' => $price,
                'is_custom' => true,
                'metadata' => [
                    'lens_type' => $demo['lens_type'],
                    'lens_material' => $demo['lens_material'],
                    'lens_index' => $demo['lens_index'],
                    'coating' => $demo['coating'],
                    'tint' => $demo['tint'],
                ],
            ];
        }

        return $lines;
    }

    private function productLine(int $productId, int $branchId, string $type, float $quantity, ?float $price, bool $custom, array $metadata): array
    {
        $product = DB::table('products')->where('id', $productId)->first();
        $stock = $this->stockRow($productId, $branchId);
        $unit = $price ?: (float) $product->retail_price;
        $subtotal = round($unit * $quantity, 2);
        $tax = round($subtotal * (float) $product->tax_rate / 100, 2);

        return [
            'product_id' => $productId,
            'location_id' => $stock?->inventory_location_id,
            'item_type' => $type,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'cost_price' => (float) $product->cost_price,
            'tax_rate' => (float) $product->tax_rate,
            'tax_amount' => $tax,
            'line_total' => round($subtotal + $tax, 2),
            'subtotal' => $subtotal,
            'is_custom' => $custom,
            'metadata' => [
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'brand' => $product->brand,
                ...$metadata,
            ],
        ];
    }

    private function reserveFrameOnce(int $orderId, int $itemId, array $line, int $branchId, $now): void
    {
        if (DB::table('inventory_reservations')->where('source_type', 'optical_order')->where('source_id', $orderId)->where('product_id', $line['product_id'])->exists()) {
            return;
        }

        $stock = $this->stockRow((int) $line['product_id'], $branchId);
        if (! $stock) {
            return;
        }

        DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
            'qty_reserved' => (float) $stock->qty_reserved + (float) $line['quantity'],
            'updated_at' => $now,
        ]);

        DB::table('inventory_reservations')->insert([
            'product_id' => $line['product_id'],
            'branch_id' => $branchId,
            'source_type' => 'optical_order',
            'source_id' => $orderId,
            'quantity' => $line['quantity'],
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
            'quantity' => $line['quantity'],
            'unit_cost' => $line['cost_price'],
            'balance_after' => $stock->qty_on_hand,
            'reference_type' => 'optical_order',
            'reference_id' => $orderId,
            'reason' => 'Demo optical order frame reservation',
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function payment(int $companyId, int $branchId, ?int $customerId, int $orderId, array $demo, float $deposit, int $salespersonId, $now): void
    {
        if ($deposit <= 0) {
            return;
        }

        $salesNumber = 'PAY-'.$demo['number'].'-DEP';
        if (! DB::table('sales_payments')->where('payment_number', $salesNumber)->exists()) {
            DB::table('sales_payments')->insert([
                'payment_number' => $salesNumber,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'payment_method' => $demo['payment_method'],
                'direction' => 'in',
                'amount' => $deposit,
                'status' => 'posted',
                'paid_at' => now()->subDays(2),
                'reference' => $demo['number'],
                'received_by' => $salespersonId,
                'notes' => 'Demo optical order deposit',
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }
        $salesPaymentId = (int) DB::table('sales_payments')->where('payment_number', $salesNumber)->value('id');

        if (! DB::table('optical_order_payments')->where('payment_number', 'OPAY-'.$demo['number'].'-DEP')->exists()) {
            DB::table('optical_order_payments')->insert([
                'payment_number' => 'OPAY-'.$demo['number'].'-DEP',
                'optical_order_id' => $orderId,
                'sales_payment_id' => $salesPaymentId,
                'payment_method' => $demo['payment_method'],
                'direction' => 'in',
                'amount' => $deposit,
                'paid_at' => now()->subDays(2),
                'received_by' => $salespersonId,
                'reference' => $demo['number'],
                'notes' => 'Demo optical order deposit',
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }
    }

    private function events(int $orderId, array $demo, int $salespersonId, int $labUserId, $now): void
    {
        $events = [
            ['confirmed', 'Order confirmed', 'Frame and lens selection confirmed.', now()->subDays(4), $salespersonId],
        ];

        if (in_array($demo['status'], ['sent_to_lab', 'ready_for_pickup', 'remake'], true)) {
            $events[] = ['sent_to_lab', 'Sent to lab', 'Lab order printed and sent.', now()->subDays(2), $labUserId];
        }
        if ($demo['status'] === 'ready_for_pickup') {
            $events[] = ['quality_check', 'Quality check passed', 'Prescription, fit, and coating checked.', now()->subHours(8), $labUserId];
            $events[] = ['ready_for_pickup', 'Ready for pickup', 'Customer notified by WhatsApp.', now()->subHours(6), $labUserId];
        }
        if ($demo['status'] === 'remake') {
            $events[] = ['quality_check', 'Quality check failed', 'Axis mismatch found during QC.', now()->subDay(), $labUserId];
            $events[] = ['remake', 'Marked for remake', 'Lab remake opened.', now()->subHours(20), $labUserId];
        }

        foreach ($events as [$status, $title, $notes, $at, $userId]) {
            if (DB::table('optical_order_status_events')->where('optical_order_id', $orderId)->where('to_status', $status)->where('event_title', $title)->exists()) {
                continue;
            }

            DB::table('optical_order_status_events')->insert([
                'optical_order_id' => $orderId,
                'from_status' => null,
                'to_status' => $status,
                'event_title' => $title,
                'notes' => $notes,
                'event_at' => $at,
                'changed_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function patientTimeline(int $orderId, int $patientId, int $branchId, array $demo, int $salespersonId, $now): void
    {
        DB::table('patient_timeline_events')->updateOrInsert(
            ['source_type' => 'optical_order', 'source_id' => $orderId, 'event_title' => 'Optical order '.$demo['status']],
            [
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'user_id' => $salespersonId,
                'event_type' => 'order',
                'event_at' => now()->subDays(4),
                'description' => $demo['number'].' '.$demo['status'],
                'metadata' => json_encode(['order_number' => $demo['number'], 'status' => $demo['status']]),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function whatsapp(?object $patient, int $orderId, array $demo, $now): void
    {
        if (! $patient || ! $patient->whatsapp_number) {
            return;
        }

        $messages = [
            'confirmed' => 'Your optical order '.$demo['number'].' is confirmed. Expected pickup: '.now()->addDays($demo['due_days'])->toDateString().'.',
            'sent_to_lab' => 'Update for order '.$demo['number'].': sent to lab.',
            'ready_for_pickup' => 'Good news. Your optical order '.$demo['number'].' is ready for pickup.',
            'remake' => 'Update for order '.$demo['number'].': remake is in progress after quality check.',
        ];

        $message = $messages[$demo['status']] ?? $messages['confirmed'];
        DB::table('patient_whatsapp_messages')->updateOrInsert(
            ['patient_id' => $patient->id, 'message' => $message],
            [
                'direction' => 'out',
                'whatsapp_number' => $patient->whatsapp_number,
                'status' => 'sent',
                'sent_at' => now()->subHours(6),
                'provider_message_id' => 'demo-'.$demo['number'],
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    private function documents(int $orderId, array $demo, int $salespersonId, $now): void
    {
        foreach ([
            ['ORDER', 'order_form', 'Order form'],
            ['LAB', 'lab_order', 'Lab order'],
            ['RX', 'prescription_copy', 'Prescription copy'],
        ] as [$prefix, $type, $title]) {
            DB::table('optical_order_documents')->updateOrInsert(
                ['document_number' => $prefix.'-'.$demo['number']],
                [
                    'optical_order_id' => $orderId,
                    'uploaded_by' => $salespersonId,
                    'document_type' => $type,
                    'title' => $title.' '.$demo['number'],
                    'file_path' => 'demo/'.$prefix.'-'.$demo['number'].'.pdf',
                    'original_filename' => $prefix.'-'.$demo['number'].'.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 180000,
                    'notes' => 'Generated demo document record.',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function prescriptionSnapshot(object $rx): array
    {
        return [
            'prescription_number' => $rx->prescription_number,
            'status' => $rx->status,
            'prescribed_on' => $rx->prescribed_on,
            'right' => [
                'sph' => $rx->right_sph,
                'cyl' => $rx->right_cyl,
                'axis' => $rx->right_axis,
                'add' => $rx->right_add,
                'pd' => $rx->right_pd,
                'va' => $rx->right_va,
            ],
            'left' => [
                'sph' => $rx->left_sph,
                'cyl' => $rx->left_cyl,
                'axis' => $rx->left_axis,
                'add' => $rx->left_add,
                'pd' => $rx->left_pd,
                'va' => $rx->left_va,
            ],
            'diagnosis' => $rx->diagnosis,
            'recommendation' => $rx->recommendation,
        ];
    }

    private function productSnapshot(int $productId, int $branchId): array
    {
        $product = DB::table('products')->where('id', $productId)->first();
        $stock = $this->stockRow($productId, $branchId);

        return [
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'brand' => $product->brand,
            'model' => $product->model,
            'color' => $product->color,
            'size' => $product->size,
            'available_stock' => $stock ? ((float) $stock->qty_on_hand - (float) $stock->qty_reserved) : 0,
        ];
    }

    private function stockRow(int $productId, int $branchId): ?object
    {
        return DB::table('inventory_stock_levels')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->orderByDesc('qty_on_hand')
            ->first();
    }

    private function permissionsAndRoles(int $companyId, $now): void
    {
        foreach (config('optical_orders.permissions') as $permission) {
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

        foreach (config('optical_orders.roles') as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'company_id' => $companyId,
                    'name' => $role['name'],
                    'description' => 'Optical Orders module role',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }
}
