<?php

namespace App\Http\Controllers;

use App\Support\AccountingReportService;
use App\Support\DocumentSettings;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function salesInvoice(Request $request, int $invoice): Response|View
    {
        $record = DB::table('sales_invoices')
            ->join('branches', 'branches.id', '=', 'sales_invoices.branch_id')
            ->join('companies', 'companies.id', '=', 'sales_invoices.company_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('users', 'users.id', '=', 'sales_invoices.cashier_id')
            ->select('sales_invoices.*', 'branches.name as branch_name', 'branches.phone as branch_phone', 'branches.address as branch_address', 'companies.name as company_name', 'companies.tax_number', 'sales_customers.name as customer_name', 'sales_customers.phone as customer_phone', 'users.name as cashier_name')
            ->where('sales_invoices.id', $invoice)->first();
        abort_unless($record, 404);
        $record->items = DB::table('sales_invoice_items')->leftJoin('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->select('sales_invoice_items.*', 'products.sku')->where('sales_invoice_id', $record->id)->orderBy('sales_invoice_items.id')->get();
        $record->payments = DB::table('sales_payments')->where('sales_invoice_id', $record->id)->orderBy('paid_at')->get();

        return $this->render($request, 'documents.sales-invoice', compact('record'), 'sales-invoice-'.$record->invoice_number, 'invoice', (int) $record->branch_id);
    }

    public function paymentVoucher(Request $request, int $payment): Response|View
    {
        $record = $this->payment($payment);

        return $this->render($request, 'documents.receipt-voucher', compact('record'), 'receipt-voucher-'.$record->payment_number, 'receipt', (int) $record->branch_id);
    }

    public function salesReceipt(Request $request, int $payment): Response|View
    {
        $record = $this->payment($payment);
        $record->items = $record->sales_invoice_id
            ? DB::table('sales_invoice_items')->where('sales_invoice_id', $record->sales_invoice_id)->orderBy('id')->get()
            : collect();

        return $this->render($request, 'documents.sales-receipt', compact('record'), 'receipt-'.$record->payment_number, 'thermal', (int) $record->branch_id);
    }

    public function journalVoucher(Request $request, int $journal): Response|View
    {
        $record = DB::table('accounting_journals')
            ->leftJoin('branches', 'branches.id', '=', 'accounting_journals.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'accounting_journals.company_id')
            ->leftJoin('users', 'users.id', '=', 'accounting_journals.created_by')
            ->select('accounting_journals.*', 'branches.name as branch_name', 'companies.name as company_name', 'users.name as prepared_by_name')
            ->where('accounting_journals.id', $journal)->first();
        abort_unless($record, 404);
        $record->lines = DB::table('accounting_journal_lines')->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_journal_lines.accounting_account_id')
            ->select('accounting_journal_lines.*', 'accounting_accounts.code as account_code', 'accounting_accounts.name as account_name')
            ->where('accounting_journal_id', $record->id)->orderBy('accounting_journal_lines.id')->get();
        $record->voucher_type = $request->query('type', 'journal');

        return $this->render($request, 'documents.journal-voucher', compact('record'), $record->voucher_type.'-voucher-'.$record->journal_number, 'receipt', $record->branch_id ? (int) $record->branch_id : null);
    }

    public function prescription(Request $request, int $prescription): Response|View
    {
        $record = DB::table('patient_prescriptions')
            ->join('patients', 'patients.id', '=', 'patient_prescriptions.patient_id')
            ->leftJoin('branches', 'branches.id', '=', 'patient_prescriptions.branch_id')
            ->leftJoin('users', 'users.id', '=', 'patient_prescriptions.optometrist_id')
            ->select('patient_prescriptions.*', 'patients.patient_code', 'patients.full_name', 'patients.date_of_birth', 'patients.phone', 'patients.whatsapp_number', 'branches.name as branch_name', 'users.name as optometrist_name')
            ->where('patient_prescriptions.id', $prescription)->first();
        abort_unless($record, 404);

        return $this->render($request, 'documents.prescription', compact('record'), 'prescription-'.$record->prescription_number, 'optometry', $record->branch_id ? (int) $record->branch_id : null);
    }

    public function patientFile(Request $request, int $patient): Response|View
    {
        $record = DB::table('patients')->where('id', $patient)->first();
        abort_unless($record, 404);
        $record->latest_exam = DB::table('patient_eye_exams')->leftJoin('users', 'users.id', '=', 'patient_eye_exams.optometrist_id')
            ->select('patient_eye_exams.*', 'users.name as optometrist_name')->where('patient_id', $patient)->orderByDesc('exam_date')->orderByDesc('patient_eye_exams.id')->first();
        $record->latest_prescription = DB::table('patient_prescriptions')->leftJoin('users', 'users.id', '=', 'patient_prescriptions.optometrist_id')
            ->select('patient_prescriptions.*', 'users.name as optometrist_name')->where('patient_id', $patient)->orderByDesc('prescribed_on')->orderByDesc('patient_prescriptions.id')->first();
        $branchId = $record->latest_exam?->branch_id ?: $record->latest_prescription?->branch_id;

        return $this->render($request, 'documents.patient-file', compact('record'), 'patient-file-'.$record->patient_code, 'a4-landscape', $branchId ? (int) $branchId : null);
    }

    public function opticalOrder(Request $request, int $order): Response|View
    {
        $record = $this->opticalOrderRecord($order);

        return $this->render($request, 'documents.optical-order', compact('record'), 'optical-order-'.$record->order_number, 'a4', (int) $record->branch_id);
    }

    public function glassesCard(Request $request, int $order): Response|View
    {
        $record = $this->opticalOrderRecord($order);

        return $this->render($request, 'documents.glasses-card', compact('record'), 'glasses-card-'.$record->order_number, 'glasses-card', (int) $record->branch_id);
    }

    public function purchaseOrder(Request $request, int $order): Response|View
    {
        $record = DB::table('inventory_purchase_orders')
            ->join('companies', 'companies.id', '=', 'inventory_purchase_orders.company_id')
            ->join('suppliers', 'suppliers.id', '=', 'inventory_purchase_orders.supplier_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_purchase_orders.branch_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'inventory_purchase_orders.created_by')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'inventory_purchase_orders.approved_by')
            ->select('inventory_purchase_orders.*', 'companies.name as company_name', 'suppliers.name as supplier_name', 'suppliers.phone as supplier_phone', 'suppliers.address as supplier_address', 'suppliers.tax_number as supplier_tax_number', 'branches.name as branch_name', 'creators.name as prepared_by_name', 'approvers.name as approved_by_name')
            ->where('inventory_purchase_orders.id', $order)->first();
        abort_unless($record, 404);
        $record->lines = DB::table('inventory_purchase_order_lines')->join('products', 'products.id', '=', 'inventory_purchase_order_lines.product_id')
            ->select('inventory_purchase_order_lines.*', 'products.sku', 'products.name as product_name')->where('inventory_purchase_order_id', $order)->orderBy('inventory_purchase_order_lines.id')->get();

        return $this->render($request, 'documents.purchase-order', compact('record'), 'purchase-order-'.$record->po_number, 'a4', $record->branch_id ? (int) $record->branch_id : null);
    }

    public function purchaseInvoice(Request $request, int $receipt): Response|View
    {
        $record = DB::table('inventory_goods_receipts')
            ->join('suppliers', 'suppliers.id', '=', 'inventory_goods_receipts.supplier_id')
            ->join('branches', 'branches.id', '=', 'inventory_goods_receipts.branch_id')
            ->leftJoin('inventory_purchase_orders', 'inventory_purchase_orders.id', '=', 'inventory_goods_receipts.inventory_purchase_order_id')
            ->leftJoin('users', 'users.id', '=', 'inventory_goods_receipts.received_by')
            ->select('inventory_goods_receipts.*', 'suppliers.name as supplier_name', 'suppliers.phone as supplier_phone', 'suppliers.tax_number as supplier_tax_number', 'branches.name as branch_name', 'inventory_purchase_orders.po_number', 'users.name as received_by_name')
            ->where('inventory_goods_receipts.id', $receipt)->first();
        abort_unless($record, 404);
        $record->lines = DB::table('inventory_goods_receipt_lines')->join('products', 'products.id', '=', 'inventory_goods_receipt_lines.product_id')
            ->select('inventory_goods_receipt_lines.*', 'products.sku', 'products.name as product_name')->where('inventory_goods_receipt_id', $receipt)->orderBy('inventory_goods_receipt_lines.id')->get();
        $record->subtotal = $record->lines->sum(fn ($line) => (float) $line->accepted_quantity * (float) $line->unit_cost);

        return $this->render($request, 'documents.purchase-invoice', compact('record'), 'purchase-invoice-'.$record->receipt_number, 'a4', (int) $record->branch_id);
    }

    public function stockReport(Request $request): Response|View
    {
        $branchId = $request->integer('branch_id') ?: null;
        $rows = DB::table('inventory_stock_levels')->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_stock_levels.branch_id')
            ->leftJoin('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock_levels.inventory_location_id')
            ->selectRaw('products.sku, products.name as product, products.type, products.brand, branches.name as branch, inventory_locations.name as location, inventory_stock_levels.qty_on_hand, inventory_stock_levels.qty_reserved, inventory_stock_levels.average_cost, (inventory_stock_levels.qty_on_hand * inventory_stock_levels.average_cost) as stock_value')
            ->when($branchId, fn ($query) => $query->where('inventory_stock_levels.branch_id', $branchId))->orderBy('products.name')->get();

        return $this->render($request, 'documents.stock-report', compact('rows'), 'stock-report-'.now()->format('Ymd'), 'a4-landscape', $branchId);
    }

    public function financialReport(Request $request, string $report, AccountingReportService $service): Response|View
    {
        abort_unless(in_array($report, ['accounting-journals', 'trial-balance', 'general-ledger', 'profit-and-loss', 'balance-sheet'], true), 404);
        $branchId = $request->integer('branch_id') ?: null;
        $rows = match ($report) {
            'accounting-journals' => DB::table('accounting_journals')->leftJoin('branches', 'branches.id', '=', 'accounting_journals.branch_id')
                ->select('accounting_journals.journal_number', 'accounting_journals.journal_date', 'branches.name as branch', 'accounting_journals.description', 'accounting_journals.status', 'accounting_journals.total_debit', 'accounting_journals.total_credit')
                ->when($branchId, fn ($query) => $query->where('accounting_journals.branch_id', $branchId))->orderByDesc('journal_date')->get(),
            'trial-balance' => $service->trialBalance($branchId, $request->query('date_from'), $request->query('date_to')),
            'general-ledger' => $service->ledger((int) $request->query('account_id'), $branchId, $request->query('date_from'), $request->query('date_to')),
            'profit-and-loss' => $service->profitAndLoss($branchId, $request->query('date_from'), $request->query('date_to')),
            'balance-sheet' => $service->balanceSheet($branchId, $request->query('date_to')),
        };
        $rows = $rows instanceof Collection ? $rows : collect($rows);
        $title = __('reports.'.$report);

        return $this->render($request, 'documents.financial-report', compact('rows', 'report', 'title'), $report.'-'.now()->format('Ymd'), 'a4-landscape', $branchId);
    }

    private function payment(int $id): object
    {
        $record = DB::table('sales_payments')->join('branches', 'branches.id', '=', 'sales_payments.branch_id')
            ->join('companies', 'companies.id', '=', 'sales_payments.company_id')
            ->leftJoin('sales_customers', 'sales_customers.id', '=', 'sales_payments.customer_id')
            ->leftJoin('sales_invoices', 'sales_invoices.id', '=', 'sales_payments.sales_invoice_id')
            ->leftJoin('users', 'users.id', '=', 'sales_payments.received_by')
            ->select('sales_payments.*', 'branches.name as branch_name', 'companies.name as company_name', 'sales_customers.name as customer_name', 'sales_customers.phone as customer_phone', 'sales_invoices.invoice_number', 'sales_invoices.grand_total', 'sales_invoices.balance_due', 'users.name as received_by_name')
            ->where('sales_payments.id', $id)->first();
        abort_unless($record, 404);

        return $record;
    }

    private function opticalOrderRecord(int $id): object
    {
        $record = DB::table('optical_orders')->join('patients', 'patients.id', '=', 'optical_orders.patient_id')
            ->join('branches', 'branches.id', '=', 'optical_orders.branch_id')
            ->leftJoin('users', 'users.id', '=', 'optical_orders.salesperson_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'optical_orders.lab_supplier_id')
            ->select('optical_orders.*', 'patients.patient_code', 'patients.full_name as patient_name', 'patients.phone', 'patients.whatsapp_number', 'branches.name as branch_name', 'users.name as salesperson_name', 'suppliers.name as lab_name')
            ->where('optical_orders.id', $id)->first();
        abort_unless($record, 404);
        $record->items = DB::table('optical_order_items')->leftJoin('products', 'products.id', '=', 'optical_order_items.product_id')
            ->select('optical_order_items.*', 'products.sku', 'products.brand')->where('optical_order_id', $id)->orderBy('optical_order_items.id')->get();
        $record->prescription = $record->patient_prescription_id
            ? DB::table('patient_prescriptions')->leftJoin('users', 'users.id', '=', 'patient_prescriptions.optometrist_id')
                ->select('patient_prescriptions.*', 'users.name as optometrist_name')->where('patient_prescriptions.id', $record->patient_prescription_id)->first()
            : null;
        $record->prescription_snapshot = $record->prescription_snapshot ? json_decode($record->prescription_snapshot, true) : [];

        return $record;
    }

    private function render(Request $request, string $view, array $data, string $filename, string $paper, ?int $branchId): Response|View
    {
        $branding = app(DocumentSettings::class)->all($branchId);
        $page = $this->paper($paper);
        $language = in_array($request->query('lang', app()->getLocale()), ['ar', 'en'], true) ? $request->query('lang', app()->getLocale()) : 'en';
        $templateLanguage = $branding['template_language'] === 'bilingual' ? 'bilingual' : $language;
        $translate = fn (string $en, string $ar) => $templateLanguage === 'bilingual' ? $ar.' / '.$en : ($language === 'ar' ? $ar : $en);
        $payload = [...$data, 'branding' => $branding, 'page' => $page, 'isPdf' => $request->query('format') === 'pdf', 'language' => $language, 't' => $translate];
        if (! $payload['isPdf']) {
            return view($view, $payload);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot([public_path(), storage_path()]);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $payload)->render(), 'UTF-8');
        $dompdf->setPaper($page['dompdf'], $page['orientation']);
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function paper(string $paper): array
    {
        return match ($paper) {
            'invoice' => ['width' => 170, 'height' => 240, 'orientation' => 'portrait', 'dompdf' => [0, 0, 481.89, 680.32]],
            'receipt' => ['width' => 170, 'height' => 120, 'orientation' => 'portrait', 'dompdf' => [0, 0, 481.89, 340.16]],
            'thermal' => ['width' => 80, 'height' => 200, 'orientation' => 'portrait', 'dompdf' => [0, 0, 226.77, 566.93]],
            'glasses-card' => ['width' => 210, 'height' => 74.5, 'orientation' => 'landscape', 'dompdf' => [0, 0, 595.28, 211.18]],
            'optometry' => ['width' => 148.5, 'height' => 203.2, 'orientation' => 'portrait', 'dompdf' => [0, 0, 420.84, 576]],
            'a4-landscape' => ['width' => 297, 'height' => 210, 'orientation' => 'landscape', 'dompdf' => 'a4'],
            default => ['width' => 210, 'height' => 297, 'orientation' => 'portrait', 'dompdf' => 'a4'],
        };
    }
}
