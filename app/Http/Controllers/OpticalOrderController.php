<?php

namespace App\Http\Controllers;

use App\Support\AccountingPoster;
use App\Support\SetupOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OpticalOrderController extends Controller
{
    private const PAGES = [
        'dashboard' => 'Optical Orders Dashboard',
        'create' => 'Create Optical Order',
        'details' => 'Order Details',
        'lab-board' => 'Lab Workflow Board',
        'ready-pickup' => 'Ready for Pickup',
        'remake-cancel' => 'Remake / Cancel',
        'documents' => 'Order Documents',
        'timeline' => 'Status Timeline',
        'reports' => 'Reports',
        'print' => 'Print Documents',
    ];

    public function index(Request $request, ?string $page = null): View
    {
        $page = $page ?: 'dashboard';

        if (! array_key_exists($page, self::PAGES)) {
            abort(404);
        }

        if (! Schema::hasTable('optical_orders') || ! Schema::hasTable('patients') || ! Schema::hasTable('products')) {
            return view('optical-orders.app', [
                'page' => 'setup',
                'pages' => self::PAGES,
                'databaseReady' => false,
            ]);
        }

        return view('optical-orders.app', [
            'page' => $page,
            'pages' => self::PAGES,
            'databaseReady' => true,
            ...$this->orderData($request),
        ]);
    }

    public function meta()
    {
        return response()->json([
            'module' => config('optical_orders.module'),
            'statuses' => app(SetupOptions::class)->options('order_statuses'),
            'priorities' => app(SetupOptions::class)->options('order_priorities'),
            'lens_types' => app(SetupOptions::class)->options('lens_types'),
            'lens_materials' => app(SetupOptions::class)->options('lens_materials'),
            'lens_indexes' => app(SetupOptions::class)->options('lens_indexes'),
            'coatings' => app(SetupOptions::class)->options('lens_coatings'),
            'tints' => app(SetupOptions::class)->options('lens_tints'),
            'document_types' => app(SetupOptions::class)->options('order_document_types'),
            'whatsapp_triggers' => app(SetupOptions::class)->options('order_whatsapp_triggers'),
            'roles' => config('optical_orders.roles'),
            'permissions' => config('optical_orders.permissions'),
            'reports' => config('optical_orders.reports'),
            'integration_rules' => config('optical_orders.integration_rules'),
        ]);
    }

    public function dashboardApi(Request $request)
    {
        if (! Schema::hasTable('optical_orders')) {
            return response()->json(['status' => 'database_not_ready'], 503);
        }

        $branchId = $request->integer('branch_id') ?: null;

        return response()->json([
            'business_date' => now()->toDateString(),
            'branch_id' => $branchId,
            'metrics' => $this->metrics($branchId),
            'ready_for_pickup' => $this->orders($branchId, '', 'ready_for_pickup')->take(10)->values(),
            'delayed_orders' => $this->delayedOrders($branchId)->take(10)->values(),
            'lab_board' => $this->labBoard($branchId),
        ]);
    }

    public function ordersApi(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'query' => $q,
            'branch_id' => $request->integer('branch_id') ?: null,
            'status' => $request->query('status'),
            'searchable_fields' => ['order_number', 'patient_name', 'phone', 'whatsapp', 'frame_barcode', 'sku'],
            'data' => $this->orders($request->integer('branch_id') ?: null, $q, $request->query('status')),
        ]);
    }

    public function showApi(int $order)
    {
        $details = $this->orderDetails($order);
        if (! $details) {
            abort(404);
        }

        return response()->json($details);
    }

    public function reportApi(Request $request, string $report)
    {
        $branchId = $request->integer('branch_id') ?: null;
        $dateFrom = $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $base = DB::table('optical_orders')
            ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'optical_orders.branch_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
            ->leftJoin('users', 'users.id', '=', 'optical_orders.salesperson_id')
            ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId));

        $data = match ($report) {
            'pending-optical-orders' => (clone $base)
                ->select('optical_orders.order_number', 'patients.full_name', 'branches.name as branch_name', 'optical_orders.status', 'optical_orders.expected_delivery_date', 'optical_orders.outstanding_amount')
                ->whereIn('optical_orders.status', app(SetupOptions::class)->keys('order_active_statuses'))
                ->orderBy('optical_orders.expected_delivery_date')
                ->get(),
            'orders-by-status' => DB::table('optical_orders')
                ->selectRaw('status, count(*) as orders, sum(grand_total) as total_value')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'orders-by-lab' => DB::table('optical_orders')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
                ->selectRaw('coalesce(suppliers.name, "No lab assigned") as lab_name, count(*) as orders, sum(optical_orders.grand_total) as total_value')
                ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
                ->groupBy('suppliers.name')
                ->orderByDesc('orders')
                ->get(),
            'delayed-orders' => $this->delayedOrders($branchId),
            'remake-orders' => (clone $base)
                ->select('optical_orders.order_number', 'patients.full_name', 'branches.name as branch_name', 'suppliers.name as lab_name', 'optical_orders.remake_reason', 'optical_orders.updated_at')
                ->where('optical_orders.status', 'remake')
                ->orderByDesc('optical_orders.updated_at')
                ->get(),
            'ready-for-pickup' => $this->orders($branchId, '', 'ready_for_pickup'),
            'orders-by-branch' => DB::table('optical_orders')
                ->join('branches', 'branches.id', '=', 'optical_orders.branch_id')
                ->selectRaw('branches.name as branch_name, count(*) as orders, sum(optical_orders.grand_total) as total_value, sum(optical_orders.outstanding_amount) as outstanding')
                ->whereBetween('optical_orders.order_date', [$dateFrom, $dateTo])
                ->groupBy('branches.name')
                ->orderByDesc('orders')
                ->get(),
            'orders-by-salesperson' => DB::table('optical_orders')
                ->leftJoin('users', 'users.id', '=', 'optical_orders.salesperson_id')
                ->selectRaw('coalesce(users.name, "Unassigned") as salesperson_name, count(*) as orders, sum(optical_orders.grand_total) as total_value')
                ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
                ->whereBetween('optical_orders.order_date', [$dateFrom, $dateTo])
                ->groupBy('users.name')
                ->orderByDesc('orders')
                ->get(),
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

    public function storeOrder(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer'],
            'patient_prescription_id' => ['nullable', 'integer'],
            'branch_id' => ['required', 'integer'],
            'salesperson_id' => ['nullable', 'integer'],
            'lab_supplier_id' => ['nullable', 'integer'],
            'frame_product_id' => ['nullable', 'integer'],
            'lens_product_id' => ['nullable', 'integer'],
            'accessory_product_id' => ['nullable', 'integer'],
            'accessory_quantity' => ['nullable', 'numeric', 'min:0'],
            'lens_quantity' => ['nullable', 'numeric', 'min:0'],
            'frame_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lens_unit_price' => ['nullable', 'numeric', 'min:0'],
            'accessory_unit_price' => ['nullable', 'numeric', 'min:0'],
            'custom_lens_price' => ['nullable', 'numeric', 'min:0'],
            'custom_lens_cost' => ['nullable', 'numeric', 'min:0'],
            'expected_delivery_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'lens_type' => ['nullable', 'string', 'max:255'],
            'lens_material' => ['nullable', 'string', 'max:255'],
            'lens_index' => ['nullable', 'string', 'max:255'],
            'coating' => ['nullable', 'string', 'max:255'],
            'tint' => ['nullable', 'string', 'max:255'],
            'deposit_required' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_method' => ['nullable', 'string', 'max:255'],
            'deposit_reference' => ['nullable', 'string', 'max:255'],
            'fitting_notes' => ['nullable', 'string', 'max:2000'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'allow_unpaid_delivery' => ['nullable', 'boolean'],
            'allow_unavailable' => ['nullable', 'boolean'],
        ]);

        $orderId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $companyId = $this->companyId();
            $branchId = (int) $validated['branch_id'];
            $patient = DB::table('patients')->where('id', $validated['patient_id'])->first();
            if (! $patient) {
                throw ValidationException::withMessages(['patient_id' => 'Patient was not found.']);
            }

            $prescription = $this->resolvePrescription((int) $validated['patient_id'], $validated['patient_prescription_id'] ?? null);
            $salesCustomerId = $this->ensureSalesCustomer($patient, $companyId, $now);
            $status = $this->normalizedStatus($validated['status'] ?? 'confirmed');
            $priority = in_array($validated['priority'] ?? '', app(SetupOptions::class)->keys('order_priorities'), true)
                ? $validated['priority']
                : 'normal';
            $customLensOrder = $request->boolean('custom_lens_order') || ! ($validated['lens_product_id'] ?? null);
            $lines = $this->prepareOrderLines($request, $validated, $branchId, $customLensOrder, $request->boolean('allow_unavailable'));
            $totals = $this->lineTotals($lines);
            $deposit = min((float) ($validated['deposit_amount'] ?? 0), $totals['grand_total']);
            $outstanding = max(0, round($totals['grand_total'] - $deposit, 2));

            $orderId = DB::table('optical_orders')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'patient_id' => $patient->id,
                'patient_prescription_id' => $prescription?->id,
                'sales_customer_id' => $salesCustomerId,
                'salesperson_id' => $validated['salesperson_id'] ?? null,
                'lab_supplier_id' => $validated['lab_supplier_id'] ?? null,
                'frame_product_id' => $validated['frame_product_id'] ?? null,
                'order_number' => $this->nextNumber('OPT', 'optical_orders', 'order_number'),
                'status' => $status,
                'priority' => $priority,
                'order_date' => $now->toDateString(),
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? $now->copy()->addDays(3)->toDateString(),
                'lens_type' => $validated['lens_type'] ?? null,
                'lens_material' => $validated['lens_material'] ?? null,
                'lens_index' => $validated['lens_index'] ?? null,
                'coating' => $validated['coating'] ?? null,
                'tint' => $validated['tint'] ?? null,
                'custom_lens_order' => $customLensOrder,
                'prescription_snapshot' => $prescription ? json_encode($this->prescriptionSnapshot($prescription)) : null,
                'frame_snapshot' => ($validated['frame_product_id'] ?? null) ? json_encode($this->productSnapshot((int) $validated['frame_product_id'], $branchId)) : null,
                'fitting_notes' => $validated['fitting_notes'] ?? null,
                'special_instructions' => $validated['special_instructions'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'deposit_required' => $validated['deposit_required'] ?? 0,
                'paid_amount' => $deposit,
                'outstanding_amount' => $outstanding,
                'payment_status' => $this->paymentStatus($totals['grand_total'], $deposit),
                'allow_unpaid_delivery' => $request->boolean('allow_unpaid_delivery'),
                'created_by' => $validated['salesperson_id'] ?? null,
                'confirmed_by' => $status !== 'draft' ? ($validated['salesperson_id'] ?? null) : null,
                'lab_sent_at' => $status === 'sent_to_lab' ? $now : null,
                'ready_at' => $status === 'ready_for_pickup' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                $itemId = DB::table('optical_order_items')->insertGetId([
                    'optical_order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'inventory_location_id' => $line['location_id'],
                    'lab_supplier_id' => $line['lab_supplier_id'],
                    'item_type' => $line['item_type'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'reserved_quantity' => 0,
                    'issued_quantity' => 0,
                    'unit_price' => $line['unit_price'],
                    'cost_price' => $line['cost_price'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'is_custom' => $line['is_custom'],
                    'metadata' => $line['metadata'] ? json_encode($line['metadata']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($status !== 'draft' && $line['item_type'] === 'frame' && $line['product_id']) {
                    $this->reserveFrame($line, $branchId, $orderId, $itemId, $now);
                }
            }

            if ($deposit > 0) {
                $this->recordPayment(
                    $orderId,
                    $deposit,
                    $validated['deposit_method'] ?? 'cash',
                    $validated['salesperson_id'] ?? null,
                    $validated['deposit_reference'] ?? null,
                    'Optical order deposit',
                    $now,
                );
            }

            $order = DB::table('optical_orders')->where('id', $orderId)->first();
            $this->statusEvent($orderId, null, $status, 'Order '.$this->statusLabel($status), 'Created from patient prescription workflow.', $now, $validated['salesperson_id'] ?? null);
            $this->timeline((int) $patient->id, $branchId, $validated['salesperson_id'] ?? null, 'order', 'Optical order '.$this->statusLabel($status), $now, 'Order '.$order->order_number.' total '.$totals['grand_total'].'.', 'optical_order', $orderId, ['status' => $status]);
            $this->audit('optical_orders.order.created', 'optical_order', $orderId, $branchId, null, ['status' => $status, 'grand_total' => $totals['grand_total']], $now);

            if ($status !== 'draft') {
                $this->sendWhatsapp($order, 'order_confirmation', $now);
            }

            return $orderId;
        });

        return $this->respond($request, ['status' => 'created', 'order_id' => $orderId], 'details', 'Optical order created, frame reserved when confirmed, and patient timeline updated.');
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'max:255'],
            'changed_by' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allow_unpaid_delivery' => ['nullable', 'boolean'],
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
            'remake_reason' => ['nullable', 'string', 'max:2000'],
            'damaged_product_id' => ['nullable', 'integer'],
            'damaged_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $now = now();
            $order = DB::table('optical_orders')->where('id', $validated['order_id'])->first();
            if (! $order) {
                throw ValidationException::withMessages(['order_id' => 'Optical order was not found.']);
            }

            $toStatus = $this->normalizedStatus($validated['status']);
            $fromStatus = $order->status;
            $updates = [
                'status' => $toStatus,
                'updated_at' => $now,
            ];

            if ($toStatus === 'confirmed' && ! $order->frame_stock_reserved) {
                $this->reserveOrderFrame((int) $order->id, $now);
                $updates['confirmed_by'] = $validated['changed_by'] ?? null;
            }

            if ($toStatus === 'sent_to_lab') {
                $updates['lab_sent_at'] = $order->lab_sent_at ?: $now;
            }

            if ($toStatus === 'ready_for_pickup') {
                $updates['ready_at'] = $order->ready_at ?: $now;
            }

            if ($toStatus === 'cancelled') {
                if (! $order->stock_reduced) {
                    $this->releaseReservation((int) $order->id, $now);
                    $updates['frame_stock_reserved'] = false;
                }
                $updates['cancelled_at'] = $now;
                $updates['cancellation_reason'] = $validated['cancellation_reason'] ?? $validated['notes'] ?? null;
            }

            if ($toStatus === 'remake') {
                $updates['remake_reason'] = $validated['remake_reason'] ?? $validated['notes'] ?? null;
                $this->recordLabIncident($order, $validated, $now);
            }

            if ($toStatus === 'delivered_collected') {
                $allowUnpaid = $request->boolean('allow_unpaid_delivery') || (bool) $order->allow_unpaid_delivery;
                if ((float) $order->outstanding_amount > 0.009 && ! $allowUnpaid) {
                    throw ValidationException::withMessages(['status' => 'Outstanding balance must be paid before delivery. Manager permission can allow unpaid delivery.']);
                }

                if (! $order->stock_reduced) {
                    $this->issueOrderStock((int) $order->id, $now);
                }

                $invoiceId = $this->createSalesInvoiceForOrder((int) $order->id, $now);
                $updates['sales_invoice_id'] = $invoiceId;
                $updates['stock_reduced'] = true;
                $updates['frame_stock_reserved'] = false;
                $updates['delivered_at'] = $order->delivered_at ?: $now;
                $updates['allow_unpaid_delivery'] = $allowUnpaid;
            }

            DB::table('optical_orders')->where('id', $order->id)->update($updates);
            $fresh = DB::table('optical_orders')->where('id', $order->id)->first();

            $this->statusEvent((int) $order->id, $fromStatus, $toStatus, 'Status changed to '.$this->statusLabel($toStatus), $validated['notes'] ?? null, $now, $validated['changed_by'] ?? null);
            $this->timeline((int) $order->patient_id, (int) $order->branch_id, $validated['changed_by'] ?? null, 'order', 'Order '.$this->statusLabel($toStatus), $now, $fresh->order_number.' moved from '.$this->statusLabel($fromStatus).' to '.$this->statusLabel($toStatus).'.', 'optical_order', (int) $order->id, ['from_status' => $fromStatus, 'to_status' => $toStatus]);
            $this->audit('optical_orders.status.updated', 'optical_order', (int) $order->id, (int) $order->branch_id, ['status' => $fromStatus], ['status' => $toStatus], $now);

            if (in_array($toStatus, app(SetupOptions::class)->keys('order_lab_statuses'), true)) {
                $this->sendWhatsapp($fresh, 'lab_status_update', $now);
            }
            if ($toStatus === 'ready_for_pickup') {
                $this->sendWhatsapp($fresh, 'ready_for_pickup', $now);
                if ((float) $fresh->outstanding_amount > 0.009) {
                    $this->sendWhatsapp($fresh, 'payment_reminder', $now);
                }
            }
        });

        return $this->respond($request, ['status' => 'updated', 'order_id' => (int) $validated['order_id']], 'details', 'Order status updated and integrations posted.');
    }

    public function collectPayment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:255'],
            'received_by' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated): void {
            $order = DB::table('optical_orders')->where('id', $validated['order_id'])->first();
            if (! $order) {
                throw ValidationException::withMessages(['order_id' => 'Optical order was not found.']);
            }
            if (! in_array($validated['payment_method'], app(SetupOptions::class)->keys('payment_methods'), true)) {
                throw ValidationException::withMessages(['payment_method' => 'Payment method is not supported by POS.']);
            }

            $outstanding = (float) $order->outstanding_amount;
            if ($outstanding <= 0.009) {
                throw ValidationException::withMessages(['amount' => 'This optical order is already paid.']);
            }

            $amount = min((float) $validated['amount'], $outstanding);
            $now = now();
            $this->recordPayment((int) $order->id, $amount, $validated['payment_method'], $validated['received_by'] ?? null, $validated['reference'] ?? null, $validated['notes'] ?? 'Optical order payment', $now);
        });

        return $this->respond($request, ['status' => 'paid', 'order_id' => (int) $validated['order_id']], 'details', 'Payment recorded in Optical Orders and Sales/POS.');
    }

    public function storeDocument(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'uploaded_by' => ['nullable', 'integer'],
            'document_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'max:10240'],
        ]);

        $documentId = DB::transaction(function () use ($request, $validated): int {
            $now = now();
            $order = DB::table('optical_orders')->where('id', $validated['order_id'])->first();
            if (! $order) {
                throw ValidationException::withMessages(['order_id' => 'Optical order was not found.']);
            }

            $path = null;
            $original = null;
            $mime = null;
            $size = null;
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $path = $file->store('optical-order-documents', 'public');
                $original = $file->getClientOriginalName();
                $mime = $file->getClientMimeType();
                $size = $file->getSize();
            }

            $documentId = DB::table('optical_order_documents')->insertGetId([
                'optical_order_id' => $order->id,
                'uploaded_by' => $validated['uploaded_by'] ?? null,
                'document_number' => $this->nextNumber('ODC', 'optical_order_documents', 'document_number'),
                'document_type' => $validated['document_type'],
                'title' => $validated['title'],
                'file_path' => $path,
                'original_filename' => $original,
                'mime_type' => $mime,
                'file_size' => $size,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->timeline((int) $order->patient_id, (int) $order->branch_id, $validated['uploaded_by'] ?? null, 'document', 'Optical order document uploaded', $now, $validated['title'], 'optical_order_document', $documentId, ['order_id' => $order->id]);

            return $documentId;
        });

        return $this->respond($request, ['status' => 'uploaded', 'document_id' => $documentId, 'order_id' => (int) $validated['order_id']], 'documents', 'Order document saved.');
    }

    private function orderData(Request $request): array
    {
        $branches = DB::table('branches')->orderBy('name')->get();
        $branchId = (int) ($request->integer('branch_id') ?: ($branches->first()->id ?? 0));
        $query = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $orderId = $request->integer('order_id') ?: (int) (DB::table('optical_orders')->latest('id')->value('id') ?? 0);
        $selected = $orderId ? $this->orderDetails($orderId) : null;

        return [
            'branches' => $branches,
            'branchId' => $branchId,
            'query' => $query,
            'statusFilter' => $status,
            'statuses' => app(SetupOptions::class)->options('order_statuses'),
            'priorities' => app(SetupOptions::class)->options('order_priorities'),
            'lensTypes' => app(SetupOptions::class)->options('lens_types'),
            'lensMaterials' => app(SetupOptions::class)->options('lens_materials'),
            'lensIndexes' => app(SetupOptions::class)->options('lens_indexes'),
            'coatings' => app(SetupOptions::class)->options('lens_coatings'),
            'tints' => app(SetupOptions::class)->options('lens_tints'),
            'documentTypes' => app(SetupOptions::class)->options('order_document_types'),
            'paymentMethods' => app(SetupOptions::class)->options('payment_methods'),
            'reports' => config('optical_orders.reports'),
            'metrics' => $this->metrics($branchId),
            'orders' => $this->orders($branchId, $query, $status),
            'selectedOrder' => $selected,
            'patients' => $this->patients($query),
            'prescriptions' => $this->prescriptions(),
            'frames' => $this->productsByType($branchId, ['frame', 'sunglasses'], $query),
            'lenses' => $this->productsByType($branchId, ['lens'], $query),
            'accessories' => $this->productsByType($branchId, ['accessory', 'cleaning_solution', 'consumable'], $query),
            'labs' => DB::table('suppliers')->where('is_active', true)->orderBy('name')->get(),
            'users' => DB::table('users')->orderBy('name')->get(),
            'labBoard' => $this->labBoard($branchId),
            'readyOrders' => $this->orders($branchId, '', 'ready_for_pickup'),
            'delayedOrders' => $this->delayedOrders($branchId),
        ];
    }

    private function metrics(?int $branchId = null): array
    {
        $activeStatuses = app(SetupOptions::class)->keys('order_active_statuses');

        return [
            'open_orders' => DB::table('optical_orders')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereIn('status', $activeStatuses)->count(),
            'in_lab' => DB::table('optical_orders')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereIn('status', app(SetupOptions::class)->keys('order_lab_statuses'))->count(),
            'ready_for_pickup' => DB::table('optical_orders')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'ready_for_pickup')->count(),
            'delayed' => DB::table('optical_orders')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->whereIn('status', $activeStatuses)->whereDate('expected_delivery_date', '<', now()->toDateString())->count(),
            'remakes' => DB::table('optical_orders')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'remake')->count(),
            'outstanding' => DB::table('optical_orders')->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('outstanding_amount', '>', 0)->sum('outstanding_amount'),
            'paid_today' => DB::table('optical_order_payments')
                ->join('optical_orders', 'optical_orders.id', '=', 'optical_order_payments.optical_order_id')
                ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
                ->whereDate('optical_order_payments.paid_at', now()->toDateString())
                ->sum('optical_order_payments.amount'),
            'order_value_month' => DB::table('optical_orders')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereDate('order_date', '>=', now()->startOfMonth()->toDateString())
                ->sum('grand_total'),
        ];
    }

    private function orders(?int $branchId, string $query, ?string $status)
    {
        return DB::table('optical_orders')
            ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'optical_orders.branch_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
            ->leftJoin('users', 'users.id', '=', 'optical_orders.salesperson_id')
            ->leftJoin('products as frame', 'frame.id', '=', 'optical_orders.frame_product_id')
            ->select([
                'optical_orders.*',
                'patients.patient_code',
                'patients.full_name as patient_name',
                'patients.phone',
                'patients.whatsapp_number',
                'branches.name as branch_name',
                'suppliers.name as lab_name',
                'users.name as salesperson_name',
                'frame.name as frame_name',
                'frame.sku as frame_sku',
                'frame.barcode as frame_barcode',
            ])
            ->when($branchId, fn ($builder) => $builder->where('optical_orders.branch_id', $branchId))
            ->when($status, fn ($builder) => $builder->where('optical_orders.status', $status))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('optical_orders.order_number', 'like', "%{$query}%")
                        ->orWhere('patients.full_name', 'like', "%{$query}%")
                        ->orWhere('patients.patient_code', 'like', "%{$query}%")
                        ->orWhere('patients.phone', 'like', "%{$query}%")
                        ->orWhere('patients.whatsapp_number', 'like', "%{$query}%")
                        ->orWhere('frame.sku', 'like', "%{$query}%")
                        ->orWhere('frame.barcode', 'like', "%{$query}%");
                });
            })
            ->orderByRaw("case when optical_orders.status = 'ready_for_pickup' then 0 when optical_orders.status in ('sent_to_lab', 'in_lab', 'quality_check') then 1 else 2 end")
            ->orderBy('optical_orders.expected_delivery_date')
            ->orderByDesc('optical_orders.id')
            ->limit(120)
            ->get();
    }

    private function orderDetails(int $orderId): ?array
    {
        $order = DB::table('optical_orders')
            ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
            ->leftJoin('patient_prescriptions', 'patient_prescriptions.id', '=', 'optical_orders.patient_prescription_id')
            ->leftJoin('branches', 'branches.id', '=', 'optical_orders.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'optical_orders.company_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
            ->leftJoin('users', 'users.id', '=', 'optical_orders.salesperson_id')
            ->leftJoin('sales_invoices', 'sales_invoices.id', '=', 'optical_orders.sales_invoice_id')
            ->select([
                'optical_orders.*',
                'patients.patient_code',
                'patients.full_name as patient_name',
                'patients.phone',
                'patients.whatsapp_number',
                'patients.email',
                'patient_prescriptions.prescription_number',
                'patient_prescriptions.status as prescription_status',
                'branches.name as branch_name',
                'branches.phone as branch_phone',
                'branches.address as branch_address',
                'companies.name as company_name',
                'companies.tax_number',
                'suppliers.name as lab_name',
                'users.name as salesperson_name',
                'sales_invoices.invoice_number',
            ])
            ->where('optical_orders.id', $orderId)
            ->first();

        if (! $order) {
            return null;
        }

        return [
            'order' => $order,
            'items' => DB::table('optical_order_items')
                ->leftJoin('products', 'products.id', '=', 'optical_order_items.product_id')
                ->leftJoin('inventory_locations', 'inventory_locations.id', '=', 'optical_order_items.inventory_location_id')
                ->select('optical_order_items.*', 'products.sku', 'products.barcode', 'products.brand', 'products.type as product_type', 'inventory_locations.name as location_name')
                ->where('optical_order_items.optical_order_id', $orderId)
                ->orderBy('optical_order_items.id')
                ->get(),
            'events' => DB::table('optical_order_status_events')
                ->leftJoin('users', 'users.id', '=', 'optical_order_status_events.changed_by')
                ->select('optical_order_status_events.*', 'users.name as user_name')
                ->where('optical_order_status_events.optical_order_id', $orderId)
                ->orderByDesc('event_at')
                ->get(),
            'payments' => DB::table('optical_order_payments')
                ->leftJoin('sales_payments', 'sales_payments.id', '=', 'optical_order_payments.sales_payment_id')
                ->select('optical_order_payments.*', 'sales_payments.payment_number as pos_payment_number')
                ->where('optical_order_payments.optical_order_id', $orderId)
                ->orderByDesc('paid_at')
                ->get(),
            'documents' => DB::table('optical_order_documents')
                ->where('optical_order_id', $orderId)
                ->orderByDesc('id')
                ->get(),
            'incidents' => DB::table('optical_order_lab_incidents')
                ->leftJoin('products', 'products.id', '=', 'optical_order_lab_incidents.product_id')
                ->select('optical_order_lab_incidents.*', 'products.name as product_name', 'products.sku')
                ->where('optical_order_lab_incidents.optical_order_id', $orderId)
                ->orderByDesc('reported_at')
                ->get(),
            'patient_timeline' => DB::table('patient_timeline_events')
                ->where('source_type', 'optical_order')
                ->where('source_id', $orderId)
                ->orderByDesc('event_at')
                ->get(),
        ];
    }

    private function patients(string $query)
    {
        return DB::table('patients')
            ->select('id', 'patient_code', 'full_name', 'phone', 'whatsapp_number')
            ->where('is_active', true)
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('full_name', 'like', "%{$query}%")
                        ->orWhere('patient_code', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('whatsapp_number', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('id')
            ->limit(80)
            ->get();
    }

    private function prescriptions()
    {
        return DB::table('patient_prescriptions')
            ->join('patients', 'patients.id', '=', 'patient_prescriptions.patient_id')
            ->select('patient_prescriptions.*', 'patients.full_name', 'patients.patient_code')
            ->whereIn('patient_prescriptions.status', ['signed', 'locked'])
            ->orderByDesc('patient_prescriptions.prescribed_on')
            ->limit(100)
            ->get();
    }

    private function productsByType(int $branchId, array $types, string $query)
    {
        $stock = DB::table('inventory_stock_levels')
            ->selectRaw('product_id, sum(qty_on_hand) as qty_on_hand, sum(qty_reserved) as qty_reserved')
            ->where('branch_id', $branchId)
            ->groupBy('product_id');

        return DB::table('products')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoinSub($stock, 'stock', 'stock.product_id', '=', 'products.id')
            ->select([
                'products.*',
                'product_categories.name as category_name',
                DB::raw('coalesce(stock.qty_on_hand, 0) as qty_on_hand'),
                DB::raw('coalesce(stock.qty_reserved, 0) as qty_reserved'),
                DB::raw('(coalesce(stock.qty_on_hand, 0) - coalesce(stock.qty_reserved, 0)) as available_stock'),
            ])
            ->whereIn('products.type', $types)
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

    private function labBoard(?int $branchId): array
    {
        $board = [];
        foreach (app(SetupOptions::class)->options('order_statuses') as $status => $label) {
            if (in_array($status, ['draft', 'delivered_collected', 'cancelled'], true)) {
                continue;
            }

            $board[$status] = [
                'label' => $label,
                'orders' => $this->orders($branchId, '', $status)->take(12)->values(),
            ];
        }

        return $board;
    }

    private function delayedOrders(?int $branchId)
    {
        return DB::table('optical_orders')
            ->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'optical_orders.branch_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
            ->select('optical_orders.*', 'patients.full_name as patient_name', 'patients.phone', 'branches.name as branch_name', 'suppliers.name as lab_name')
            ->when($branchId, fn ($query) => $query->where('optical_orders.branch_id', $branchId))
            ->whereIn('optical_orders.status', app(SetupOptions::class)->keys('order_active_statuses'))
            ->whereDate('optical_orders.expected_delivery_date', '<', now()->toDateString())
            ->orderBy('optical_orders.expected_delivery_date')
            ->get();
    }

    private function prepareOrderLines(Request $request, array $validated, int $branchId, bool $customLensOrder, bool $allowUnavailable): array
    {
        $lines = [];
        $labSupplierId = $validated['lab_supplier_id'] ?? null;

        if ($validated['frame_product_id'] ?? null) {
            $lines[] = $this->productLine((int) $validated['frame_product_id'], $branchId, 'frame', 1, $validated['frame_unit_price'] ?? null, false, $labSupplierId, $allowUnavailable);
        }

        if (($validated['lens_product_id'] ?? null) && ! $customLensOrder) {
            $lines[] = $this->productLine((int) $validated['lens_product_id'], $branchId, 'lens', (float) ($validated['lens_quantity'] ?? 2), $validated['lens_unit_price'] ?? null, false, $labSupplierId, $allowUnavailable, [
                'lens_type' => $validated['lens_type'] ?? null,
                'lens_material' => $validated['lens_material'] ?? null,
                'lens_index' => $validated['lens_index'] ?? null,
                'coating' => $validated['coating'] ?? null,
                'tint' => $validated['tint'] ?? null,
            ]);
        } else {
            $customPrice = (float) ($validated['custom_lens_price'] ?? 480);
            $customCost = (float) ($validated['custom_lens_cost'] ?? 180);
            $taxRate = 15.0;
            $taxAmount = round($customPrice * $taxRate / 100, 2);
            $lines[] = [
                'product_id' => null,
                'location_id' => null,
                'lab_supplier_id' => $labSupplierId,
                'item_type' => 'custom_lens',
                'description' => 'Custom lens package - '.$this->optionLabel('lens_types', $validated['lens_type'] ?? 'single_vision'),
                'quantity' => 1,
                'unit_price' => $customPrice,
                'cost_price' => $customCost,
                'discount_amount' => 0,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => round($customPrice + $taxAmount, 2),
                'subtotal' => $customPrice,
                'gross_profit' => round($customPrice - $customCost, 2),
                'is_custom' => true,
                'metadata' => [
                    'lens_type' => $validated['lens_type'] ?? null,
                    'lens_material' => $validated['lens_material'] ?? null,
                    'lens_index' => $validated['lens_index'] ?? null,
                    'coating' => $validated['coating'] ?? null,
                    'tint' => $validated['tint'] ?? null,
                    'custom_order' => true,
                ],
            ];
        }

        if ($validated['accessory_product_id'] ?? null) {
            $quantity = (float) ($validated['accessory_quantity'] ?? 1);
            if ($quantity > 0) {
                $lines[] = $this->productLine((int) $validated['accessory_product_id'], $branchId, 'accessory', $quantity, $validated['accessory_unit_price'] ?? null, false, null, $allowUnavailable);
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Select at least a frame, lens, or custom lens line.']);
        }

        return $lines;
    }

    private function productLine(int $productId, int $branchId, string $type, float $quantity, mixed $unitPriceOverride, bool $isCustom, ?int $labSupplierId, bool $allowUnavailable, array $metadata = []): array
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if (! $product) {
            throw ValidationException::withMessages(['items' => 'Selected product was not found.']);
        }

        $stock = $this->stockRow($productId, $branchId);
        $available = (float) ($stock?->qty_on_hand ?? 0) - (float) ($stock?->qty_reserved ?? 0);
        if (! $isCustom && $quantity > $available && ! $allowUnavailable) {
            throw ValidationException::withMessages(['items' => $product->name.' has only '.number_format($available, 3).' available in this branch.']);
        }

        $unitPrice = (float) ($unitPriceOverride ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = (float) $product->retail_price;
        }

        $subtotal = round($quantity * $unitPrice, 2);
        $taxRate = (float) $product->tax_rate;
        $taxAmount = round($subtotal * $taxRate / 100, 2);

        return [
            'product_id' => $product->id,
            'location_id' => $stock?->inventory_location_id ?: $this->sellableLocationId($branchId),
            'lab_supplier_id' => $labSupplierId,
            'item_type' => $type,
            'description' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => (float) $product->cost_price,
            'discount_amount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => round($subtotal + $taxAmount, 2),
            'subtotal' => $subtotal,
            'gross_profit' => round($subtotal - ((float) $product->cost_price * $quantity), 2),
            'is_custom' => $isCustom,
            'metadata' => [
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'brand' => $product->brand,
                'model' => $product->model,
                'available_at_order' => $available,
                ...$metadata,
            ],
        ];
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

    private function reserveOrderFrame(int $orderId, $now): void
    {
        $item = DB::table('optical_order_items')
            ->where('optical_order_id', $orderId)
            ->where('item_type', 'frame')
            ->whereNotNull('product_id')
            ->first();

        $order = DB::table('optical_orders')->where('id', $orderId)->first();
        if (! $item || ! $order) {
            return;
        }

        $line = [
            'product_id' => (int) $item->product_id,
            'location_id' => $item->inventory_location_id,
            'quantity' => (float) $item->quantity,
            'cost_price' => (float) $item->cost_price,
            'description' => $item->description,
        ];

        $this->reserveFrame($line, (int) $order->branch_id, $orderId, (int) $item->id, $now);
    }

    private function reserveFrame(array $line, int $branchId, int $orderId, int $itemId, $now): void
    {
        $stock = $this->stockRow((int) $line['product_id'], $branchId);
        if (! $stock) {
            throw ValidationException::withMessages(['frame_product_id' => 'No branch stock exists for selected frame.']);
        }

        $available = (float) $stock->qty_on_hand - (float) $stock->qty_reserved;
        if ((float) $line['quantity'] > $available) {
            throw ValidationException::withMessages(['frame_product_id' => $line['description'].' has only '.number_format($available, 3).' available.']);
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
            'inventory_location_id' => $line['location_id'] ?: $stock->inventory_location_id,
            'movement_type' => 'optical_order_reserve',
            'direction' => 'out',
            'quantity' => $line['quantity'],
            'unit_cost' => $line['cost_price'],
            'balance_after' => $stock->qty_on_hand,
            'reference_type' => 'optical_order',
            'reference_id' => $orderId,
            'reason' => 'Frame reserved for optical order',
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('optical_order_items')->where('id', $itemId)->update([
            'reserved_quantity' => (float) $line['quantity'],
            'updated_at' => $now,
        ]);

        DB::table('optical_orders')->where('id', $orderId)->update([
            'frame_stock_reserved' => true,
            'updated_at' => $now,
        ]);
    }

    private function releaseReservation(int $orderId, $now): void
    {
        $reservations = DB::table('inventory_reservations')
            ->where('source_type', 'optical_order')
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

                DB::table('inventory_stock_movements')->insert([
                    'product_id' => $reservation->product_id,
                    'branch_id' => $reservation->branch_id,
                    'inventory_location_id' => $stock->inventory_location_id,
                    'movement_type' => 'reservation_release',
                    'direction' => 'in',
                    'quantity' => $reservation->quantity,
                    'unit_cost' => $stock->average_cost,
                    'balance_after' => $stock->qty_on_hand,
                    'reference_type' => 'optical_order',
                    'reference_id' => $orderId,
                    'reason' => 'Optical order cancelled',
                    'occurred_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('inventory_reservations')
            ->where('source_type', 'optical_order')
            ->where('source_id', $orderId)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('optical_order_items')->where('optical_order_id', $orderId)->update([
            'reserved_quantity' => 0,
            'updated_at' => $now,
        ]);
    }

    private function issueOrderStock(int $orderId, $now): void
    {
        $order = DB::table('optical_orders')->where('id', $orderId)->first();
        $items = DB::table('optical_order_items')
            ->where('optical_order_id', $orderId)
            ->whereNotNull('product_id')
            ->where('is_custom', false)
            ->get();

        foreach ($items as $item) {
            $quantity = (float) $item->quantity;
            if ($quantity <= 0) {
                continue;
            }

            $stock = $this->stockRow((int) $item->product_id, (int) $order->branch_id);
            if (! $stock || (float) $stock->qty_on_hand < $quantity) {
                throw ValidationException::withMessages(['stock' => $item->description.' does not have enough stock to deliver.']);
            }

            $newOnHand = (float) $stock->qty_on_hand - $quantity;
            $newReserved = max(0, (float) $stock->qty_reserved - (float) $item->reserved_quantity);
            DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                'qty_on_hand' => $newOnHand,
                'qty_reserved' => $newReserved,
                'updated_at' => $now,
            ]);

            DB::table('inventory_stock_movements')->insert([
                'product_id' => $item->product_id,
                'branch_id' => $order->branch_id,
                'inventory_location_id' => $item->inventory_location_id ?: $stock->inventory_location_id,
                'movement_type' => 'optical_order_issue',
                'direction' => 'out',
                'quantity' => $quantity,
                'unit_cost' => $item->cost_price,
                'balance_after' => $newOnHand,
                'reference_type' => 'optical_order',
                'reference_id' => $orderId,
                'reason' => 'Optical order delivered',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('optical_order_items')->where('id', $item->id)->update([
                'issued_quantity' => $quantity,
                'reserved_quantity' => 0,
                'updated_at' => $now,
            ]);
        }

        DB::table('inventory_reservations')
            ->where('source_type', 'optical_order')
            ->where('source_id', $orderId)
            ->where('status', 'active')
            ->update([
                'status' => 'consumed',
                'consumed_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function createSalesInvoiceForOrder(int $orderId, $now): ?int
    {
        $order = DB::table('optical_orders')->where('id', $orderId)->first();
        if (! $order || $order->sales_invoice_id) {
            return $order?->sales_invoice_id;
        }

        $items = DB::table('optical_order_items')->where('optical_order_id', $orderId)->get();
        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'customer_id' => $order->sales_customer_id,
            'cashier_id' => $order->salesperson_id,
            'invoice_number' => $this->nextNumber('INV', 'sales_invoices', 'invoice_number'),
            'invoice_type' => 'invoice',
            'status' => (float) $order->outstanding_amount <= 0.009 ? 'paid' : 'partial',
            'invoice_date' => $now->toDateString(),
            'sale_mode' => 'prescription_order',
            'pickup_status' => 'collected',
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'grand_total' => $order->grand_total,
            'paid_total' => $order->paid_amount,
            'balance_due' => $order->outstanding_amount,
            'gross_profit' => $this->grossProfit($items),
            'notes' => 'Generated from optical order '.$order->order_number.'. Stock handled by Optical Orders.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($items as $item) {
            DB::table('sales_invoice_items')->insert([
                'sales_invoice_id' => $invoiceId,
                'product_id' => $item->product_id,
                'inventory_location_id' => $item->inventory_location_id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'stock_quantity' => 0,
                'unit_price' => $item->unit_price,
                'cost_price' => $item->cost_price,
                'discount_type' => 'none',
                'discount_value' => 0,
                'discount_amount' => $item->discount_amount,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'line_total' => $item->line_total,
                'optical_package_key' => $order->order_number,
                'prescription_snapshot' => $order->prescription_snapshot,
                'is_custom_order' => $item->is_custom,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $paymentIds = DB::table('optical_order_payments')
            ->where('optical_order_id', $orderId)
            ->whereNotNull('sales_payment_id')
            ->pluck('sales_payment_id');

        DB::table('optical_orders')->where('id', $orderId)->update([
            'sales_invoice_id' => $invoiceId,
            'updated_at' => $now,
        ]);

        app(AccountingPoster::class)->postSalesInvoice($invoiceId);
        foreach ($paymentIds as $paymentId) {
            app(AccountingPoster::class)->postSalesPayment((int) $paymentId);
        }

        return $invoiceId;
    }

    private function recordPayment(int $orderId, float $amount, string $method, ?int $receivedBy, ?string $reference, ?string $notes, $now): int
    {
        $order = DB::table('optical_orders')->where('id', $orderId)->first();
        if (! $order) {
            throw ValidationException::withMessages(['order_id' => 'Optical order was not found.']);
        }

        $sessionId = $this->ensureCashSession((int) $order->branch_id, $receivedBy, null, $now);
        $salesPaymentId = DB::table('sales_payments')->insertGetId([
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'customer_id' => $order->sales_customer_id,
            'sales_invoice_id' => $order->sales_invoice_id,
            'sales_cash_session_id' => $sessionId,
            'payment_number' => $this->nextNumber('PAY', 'sales_payments', 'payment_number'),
            'payment_method' => $method,
            'direction' => 'in',
            'amount' => round($amount, 2),
            'status' => 'posted',
            'paid_at' => $now,
            'reference' => $reference ?: $order->order_number,
            'received_by' => $receivedBy,
            'notes' => $notes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $paymentId = DB::table('optical_order_payments')->insertGetId([
            'optical_order_id' => $orderId,
            'sales_payment_id' => $salesPaymentId,
            'payment_number' => $this->nextNumber('OPAY', 'optical_order_payments', 'payment_number'),
            'payment_method' => $method,
            'direction' => 'in',
            'amount' => round($amount, 2),
            'paid_at' => $now,
            'received_by' => $receivedBy,
            'reference' => $reference,
            'notes' => $notes,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $paid = (float) DB::table('optical_order_payments')
            ->where('optical_order_id', $orderId)
            ->where('direction', 'in')
            ->sum('amount');
        $outstanding = max(0, round((float) $order->grand_total - $paid, 2));

        DB::table('optical_orders')->where('id', $orderId)->update([
            'paid_amount' => $paid,
            'outstanding_amount' => $outstanding,
            'payment_status' => $this->paymentStatus((float) $order->grand_total, $paid),
            'updated_at' => $now,
        ]);

        $this->recalculateCashSession($sessionId);
        $this->timeline((int) $order->patient_id, (int) $order->branch_id, $receivedBy, 'payment', 'Optical order payment collected', $now, 'Payment '.$method.' amount '.number_format($amount, 2).'.', 'optical_order', $orderId, ['payment_id' => $paymentId, 'sales_payment_id' => $salesPaymentId]);
        $this->audit('optical_orders.payment.collected', 'optical_order', $orderId, (int) $order->branch_id, null, ['amount' => $amount, 'method' => $method], $now);
        app(AccountingPoster::class)->postSalesPayment($salesPaymentId);

        return $paymentId;
    }

    private function recordLabIncident(object $order, array $validated, $now): void
    {
        $quantity = (float) ($validated['damaged_quantity'] ?? 0);
        $productId = $validated['damaged_product_id'] ?? null;

        $incidentId = DB::table('optical_order_lab_incidents')->insertGetId([
            'optical_order_id' => $order->id,
            'product_id' => $productId,
            'incident_number' => $this->nextNumber('REM', 'optical_order_lab_incidents', 'incident_number'),
            'incident_type' => 'remake',
            'quantity' => $quantity,
            'responsibility' => 'lab_review',
            'reason' => $validated['remake_reason'] ?? $validated['notes'] ?? null,
            'stock_written_off' => false,
            'reported_at' => $now,
            'reported_by' => $validated['changed_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($productId && $quantity > 0) {
            $stock = $this->stockRow((int) $productId, (int) $order->branch_id);
            $balanceAfter = $stock?->qty_on_hand;

            if ($stock) {
                $balanceAfter = max(0, (float) $stock->qty_on_hand - $quantity);
                DB::table('inventory_stock_levels')->where('id', $stock->id)->update([
                    'qty_on_hand' => $balanceAfter,
                    'updated_at' => $now,
                ]);

                DB::table('optical_order_lab_incidents')->where('id', $incidentId)->update([
                    'stock_written_off' => true,
                    'updated_at' => $now,
                ]);
            }

            DB::table('inventory_stock_movements')->insert([
                'product_id' => $productId,
                'branch_id' => $order->branch_id,
                'inventory_location_id' => $stock?->inventory_location_id,
                'movement_type' => 'damaged_write_off',
                'direction' => 'out',
                'quantity' => $quantity,
                'unit_cost' => $stock?->average_cost ?? 0,
                'balance_after' => $balanceAfter,
                'reference_type' => 'optical_order',
                'reference_id' => $order->id,
                'reason' => 'Remake or damaged lab item',
                'notes' => $validated['remake_reason'] ?? null,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function sendWhatsapp(object $order, string $trigger, $now): void
    {
        $patient = DB::table('patients')->where('id', $order->patient_id)->first();
        if (! $patient || ! $patient->whatsapp_number) {
            return;
        }

        $message = match ($trigger) {
            'order_confirmation' => 'Your optical order '.$order->order_number.' is confirmed. Expected pickup: '.($order->expected_delivery_date ?: 'soon').'.',
            'lab_status_update' => 'Update for order '.$order->order_number.': '.$this->statusLabel($order->status).'.',
            'ready_for_pickup' => 'Good news. Your optical order '.$order->order_number.' is ready for pickup.',
            'payment_reminder' => 'Reminder: order '.$order->order_number.' has an outstanding balance of SAR '.number_format((float) $order->outstanding_amount, 2).'.',
            default => 'Optical order '.$order->order_number.' update: '.$this->statusLabel($order->status).'.',
        };

        $messageId = DB::table('patient_whatsapp_messages')->insertGetId([
            'patient_id' => $patient->id,
            'direction' => 'out',
            'whatsapp_number' => $patient->whatsapp_number,
            'message' => $message,
            'status' => 'sent',
            'sent_at' => $now,
            'provider_message_id' => 'demo-'.$trigger.'-'.$order->id.'-'.Str::lower(Str::random(5)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->timeline((int) $patient->id, (int) $order->branch_id, null, 'whatsapp', 'WhatsApp '.$this->triggerLabel($trigger), $now, $message, 'patient_whatsapp_message', $messageId, ['trigger' => $trigger, 'order_id' => $order->id]);
    }

    private function resolvePrescription(int $patientId, ?int $prescriptionId): ?object
    {
        $query = DB::table('patient_prescriptions')
            ->where('patient_id', $patientId)
            ->whereIn('status', ['signed', 'locked']);

        if ($prescriptionId) {
            $prescription = (clone $query)->where('id', $prescriptionId)->first();
            if (! $prescription) {
                throw ValidationException::withMessages(['patient_prescription_id' => 'Choose a signed or locked prescription for this patient.']);
            }

            return $prescription;
        }

        return $query->orderByDesc('prescribed_on')->first();
    }

    private function prescriptionSnapshot(object $prescription): array
    {
        return [
            'prescription_number' => $prescription->prescription_number,
            'status' => $prescription->status,
            'prescribed_on' => $prescription->prescribed_on,
            'right' => [
                'sph' => $prescription->right_sph,
                'cyl' => $prescription->right_cyl,
                'axis' => $prescription->right_axis,
                'add' => $prescription->right_add,
                'pd' => $prescription->right_pd,
                'va' => $prescription->right_va,
            ],
            'left' => [
                'sph' => $prescription->left_sph,
                'cyl' => $prescription->left_cyl,
                'axis' => $prescription->left_axis,
                'add' => $prescription->left_add,
                'pd' => $prescription->left_pd,
                'va' => $prescription->left_va,
            ],
            'diagnosis' => $prescription->diagnosis,
            'recommendation' => $prescription->recommendation,
        ];
    }

    private function productSnapshot(int $productId, int $branchId): ?array
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if (! $product) {
            return null;
        }

        $stock = $this->stockRow($productId, $branchId);

        return [
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'brand' => $product->brand,
            'model' => $product->model,
            'color' => $product->color,
            'size' => $product->size,
            'material' => $product->material,
            'available_stock' => $stock ? ((float) $stock->qty_on_hand - (float) $stock->qty_reserved) : 0,
        ];
    }

    private function ensureSalesCustomer(object $patient, int $companyId, $now): ?int
    {
        if (! Schema::hasTable('sales_customers')) {
            return null;
        }

        $existing = DB::table('sales_customers')->where('patient_id', $patient->id)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return DB::table('sales_customers')->insertGetId([
            'company_id' => $companyId,
            'patient_id' => $patient->id,
            'name' => $patient->full_name,
            'phone' => $patient->phone ?: $patient->whatsapp_number,
            'email' => $patient->email,
            'whatsapp_opt_in' => (bool) $patient->whatsapp_number,
            'notes' => 'Synced from Optical Orders.',
            'created_at' => $now,
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
        $id = DB::table('inventory_locations')
            ->where('branch_id', $branchId)
            ->where('is_sellable', true)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
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

    private function grossProfit($items): float
    {
        return round($items->sum(fn ($item) => ((float) $item->line_total - (float) $item->tax_amount) - ((float) $item->cost_price * (float) $item->quantity)), 2);
    }

    private function statusEvent(int $orderId, ?string $fromStatus, string $toStatus, string $title, ?string $notes, $eventAt, ?int $changedBy): void
    {
        DB::table('optical_order_status_events')->insert([
            'optical_order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_title' => $title,
            'notes' => $notes,
            'event_at' => $eventAt,
            'changed_by' => $changedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function timeline(int $patientId, ?int $branchId, ?int $userId, string $type, string $title, $eventAt, ?string $description, string $sourceType, int $sourceId, ?array $metadata = null): void
    {
        DB::table('patient_timeline_events')->insert([
            'patient_id' => $patientId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'event_type' => $type,
            'event_title' => $title,
            'event_at' => $eventAt,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function paymentStatus(float $grandTotal, float $paid): string
    {
        if ($paid <= 0.009) {
            return 'unpaid';
        }
        if ($paid + 0.009 >= $grandTotal) {
            return 'paid';
        }

        return 'partial';
    }

    private function normalizedStatus(string $status): string
    {
        if (! in_array($status, app(SetupOptions::class)->keys('order_statuses'), true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported order status.']);
        }

        return $status;
    }

    private function statusLabel(?string $status): string
    {
        return app(SetupOptions::class)->label('order_statuses', $status);
    }

    private function triggerLabel(string $trigger): string
    {
        return str($trigger)->replace('_', ' ')->title();
    }

    private function optionLabel(string $group, ?string $key): string
    {
        $configurationGroup = match ($group) {
            'coatings' => 'lens_coatings',
            'tints' => 'lens_tints',
            default => $group,
        };

        return app(SetupOptions::class)->label($configurationGroup, $key);
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

    private function respond(Request $request, array $payload, string $page, string $message)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload);
        }

        $params = ['page' => $page];
        if ($payload['order_id'] ?? null) {
            $params['order_id'] = $payload['order_id'];
        }

        return redirect()->route('optical-orders.app', $params)->with('status', __($message));
    }
}
