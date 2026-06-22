<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OfflineDraftController;
use App\Http\Controllers\OpticalOrderController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesPosController;
use App\Http\Controllers\TemplateSettingsController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('landing');
Route::view('/offline', 'offline')->name('offline');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', fn () => redirect()->to(request()->user()->homeUrl()))->name('dashboard');
    Route::get('/erp', [ErpController::class, 'index'])->name('erp.dashboard');
    Route::get('/erp/{page?}', [ErpController::class, 'index'])
        ->where('page', 'dashboard|patients|appointments|optical-orders|sales-pos|inventory|whatsapp|accounting|reports|configuration|users-permissions|search|workflows|admin|settings')
        ->name('erp.app');
    Route::get('/erp/reports/{report}/export', [ErpController::class, 'exportReport'])
        ->where('report', 'sales-today|appointments-today|pending-orders|ready-pickup|low-stock|unpaid-invoices|whatsapp-unread|inventory-valuation|accounting-journals|trial-balance|general-ledger|profit-and-loss|balance-sheet')
        ->name('erp.reports.export');
    Route::post('/configuration/{group}', [ConfigurationController::class, 'store'])->name('configuration.store');
    Route::patch('/configuration/{group}/{id}', [ConfigurationController::class, 'update'])->whereNumber('id')->name('configuration.update');
    Route::delete('/configuration/{group}/{id}', [ConfigurationController::class, 'destroy'])->whereNumber('id')->name('configuration.destroy');
    Route::get('/configuration/template-settings/edit', [TemplateSettingsController::class, 'edit'])->name('configuration.templates.edit');
    Route::put('/configuration/template-settings', [TemplateSettingsController::class, 'update'])->name('configuration.templates.update');

    Route::prefix('documents')->name('documents.')->group(function (): void {
        Route::get('/sales-invoices/{invoice}', [DocumentController::class, 'salesInvoice'])->whereNumber('invoice')->name('sales-invoices.show');
        Route::get('/payments/{payment}/voucher', [DocumentController::class, 'paymentVoucher'])->whereNumber('payment')->name('payments.voucher');
        Route::get('/payments/{payment}/receipt', [DocumentController::class, 'salesReceipt'])->whereNumber('payment')->name('payments.receipt');
        Route::get('/accounting-vouchers/{journal}', [DocumentController::class, 'journalVoucher'])->whereNumber('journal')->name('accounting-vouchers.show');
        Route::get('/prescriptions/{prescription}', [DocumentController::class, 'prescription'])->whereNumber('prescription')->name('prescriptions.show');
        Route::get('/patient-files/{patient}', [DocumentController::class, 'patientFile'])->whereNumber('patient')->name('patient-files.show');
        Route::get('/optical-orders/{order}', [DocumentController::class, 'opticalOrder'])->whereNumber('order')->name('optical-orders.show');
        Route::get('/optical-orders/{order}/glasses-card', [DocumentController::class, 'glassesCard'])->whereNumber('order')->name('optical-orders.glasses-card');
        Route::get('/purchase-orders/{order}', [DocumentController::class, 'purchaseOrder'])->whereNumber('order')->name('purchase-orders.show');
        Route::get('/purchase-invoices/{receipt}', [DocumentController::class, 'purchaseInvoice'])->whereNumber('receipt')->name('purchase-invoices.show');
        Route::get('/stock-report', [DocumentController::class, 'stockReport'])->name('stock-report');
        Route::get('/financial-reports/{report}', [DocumentController::class, 'financialReport'])
            ->where('report', 'accounting-journals|trial-balance|general-ledger|profit-and-loss|balance-sheet')->name('financial-reports.show');
    });
    Route::get('/sales/{page?}', [SalesPosController::class, 'index'])
        ->where('page', 'dashboard|pos|invoices|quotations|sales-orders|returns|payments|cashier-closing|print')
        ->name('sales.app');
    Route::post('/sales/checkout', [SalesPosController::class, 'checkout'])->name('sales.checkout');
    Route::post('/sales/quotations', [SalesPosController::class, 'createQuotation'])->name('sales.quotations.store');
    Route::patch('/sales/quotations/{quotation}', [SalesPosController::class, 'updateQuotation'])->whereNumber('quotation')->name('sales.quotations.update');
    Route::post('/sales/orders', [SalesPosController::class, 'createSalesOrder'])->name('sales.orders.store');
    Route::patch('/sales/orders/{order}', [SalesPosController::class, 'updateSalesOrder'])->whereNumber('order')->name('sales.orders.update');
    Route::post('/sales/returns', [SalesPosController::class, 'processReturn'])->name('sales.returns.store');
    Route::post('/sales/cashier-closing', [SalesPosController::class, 'closeCashier'])->name('sales.cashier.close');

    Route::get('/patients/{page?}', [PatientController::class, 'index'])
        ->where('page', 'dashboard|patients|patient-create|profile|eye-exam|prescription|prescription-print|timeline|documents|follow-ups|reports')
        ->name('patients.app');
    Route::post('/patients', [PatientController::class, 'storePatient'])->name('patients.store');
    Route::post('/patients/{patient}/update', [PatientController::class, 'updatePatient'])->name('patients.update');
    Route::post('/patients/exams', [PatientController::class, 'storeExam'])->name('patients.exams.store');
    Route::post('/patients/prescriptions', [PatientController::class, 'storePrescription'])->name('patients.prescriptions.store');
    Route::post('/patients/prescriptions/{prescription}/update', [PatientController::class, 'updatePrescription'])->name('patients.prescriptions.update');
    Route::post('/patients/prescriptions/{prescription}/lock', [PatientController::class, 'lockPrescription'])->name('patients.prescriptions.lock');
    Route::post('/patients/documents', [PatientController::class, 'storeDocument'])->name('patients.documents.store');
    Route::post('/patients/timeline-notes', [PatientController::class, 'storeTimelineNote'])->name('patients.timeline.store');

    Route::get('/optical-orders/{page?}', [OpticalOrderController::class, 'index'])
        ->where('page', 'dashboard|create|details|lab-board|ready-pickup|remake-cancel|documents|timeline|reports|print')
        ->name('optical-orders.app');
    Route::post('/optical-orders', [OpticalOrderController::class, 'storeOrder'])->name('optical-orders.store');
    Route::post('/optical-orders/status', [OpticalOrderController::class, 'updateStatus'])->name('optical-orders.status');
    Route::post('/optical-orders/payments', [OpticalOrderController::class, 'collectPayment'])->name('optical-orders.payments.store');
    Route::post('/optical-orders/documents', [OpticalOrderController::class, 'storeDocument'])->name('optical-orders.documents.store');

    Route::get('/appointments/{page?}', [AppointmentController::class, 'index'])
        ->where('page', 'dashboard|calendar|create|details|waiting-list|follow-ups|no-shows|reschedule-cancel|reports')
        ->name('appointments.app');
    Route::post('/appointments', [AppointmentController::class, 'storeAppointment'])->name('appointments.store');
    Route::post('/appointments/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
    Route::post('/appointments/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::post('/appointments/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/appointments/reminders', [AppointmentController::class, 'sendReminder'])->name('appointments.reminders.send');

    Route::get('/whatsapp/{page?}', [WhatsAppController::class, 'index'])
        ->where('page', 'inbox|conversation|patient-panel|templates|automations|consent|logs|failed|settings|reports')
        ->name('whatsapp.app');
    Route::post('/whatsapp/messages', [WhatsAppController::class, 'sendMessage'])->name('whatsapp.messages.send');
    Route::post('/whatsapp/conversations', [WhatsAppController::class, 'updateConversation'])->name('whatsapp.conversations.update');
    Route::post('/whatsapp/templates', [WhatsAppController::class, 'saveTemplate'])->name('whatsapp.templates.save');
    Route::post('/whatsapp/automations', [WhatsAppController::class, 'saveAutomation'])->name('whatsapp.automations.save');
    Route::post('/whatsapp/consent', [WhatsAppController::class, 'updateConsent'])->name('whatsapp.consent.update');
    Route::post('/whatsapp/settings', [WhatsAppController::class, 'saveSettings'])->name('whatsapp.settings.save');
    Route::post('/whatsapp/settings/test', [WhatsAppController::class, 'testConnection'])->name('whatsapp.settings.test');
    Route::post('/whatsapp/retry', [WhatsAppController::class, 'retryFailed'])->name('whatsapp.messages.retry');
    Route::get('/inventory/{page?}', [InventoryController::class, 'index'])
        ->where('page', 'dashboard|products|product-create|product-details|stock-movements|purchase-orders|goods-receiving|stock-transfers|stock-counts|low-stock-alerts|reports')
        ->name('inventory.app');
    Route::post('/inventory/products', [InventoryController::class, 'storeProduct'])->name('inventory.products.store');
    Route::post('/inventory/purchase-orders', [InventoryController::class, 'storePurchaseOrder'])->name('inventory.purchase-orders.store');
    Route::post('/inventory/stock-adjustments', [InventoryController::class, 'adjustStock'])->name('inventory.stock.adjust');
    Route::post('/inventory/goods-receipts', [InventoryController::class, 'receiveGoods'])->name('inventory.goods.receive');

    Route::middleware('permission:accounting.post')->group(function (): void {
        Route::post('/accounting/vouchers', [AccountingController::class, 'storeVoucher'])->name('accounting.vouchers.store');
        Route::patch('/accounting/vouchers/{journal}', [AccountingController::class, 'updateVoucher'])->whereNumber('journal')->name('accounting.vouchers.update');
        Route::post('/accounting/vouchers/{journal}/post', [AccountingController::class, 'postVoucher'])->whereNumber('journal')->name('accounting.vouchers.post');
        Route::post('/accounting/vouchers/{journal}/reverse', [AccountingController::class, 'reverseVoucher'])->whereNumber('journal')->name('accounting.vouchers.reverse');
    });
    Route::middleware('permission:accounting.close')->group(function (): void {
        Route::post('/accounting/closing/daily', [AccountingController::class, 'closeDaily'])->name('accounting.closing.daily');
        Route::post('/accounting/closing/monthly', [AccountingController::class, 'closeMonthly'])->name('accounting.closing.monthly');
    });
    Route::post('/offline-drafts', [OfflineDraftController::class, 'store'])->name('offline-drafts.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/webhooks/whatsapp', [WhatsAppController::class, 'verifyWebhook'])->name('whatsapp.webhook.verify');
Route::post('/webhooks/whatsapp', [WhatsAppController::class, 'incomingWebhook'])->name('whatsapp.webhook.incoming');

require __DIR__.'/auth.php';
