<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $module = $request->segment(1) === 'api' ? $request->segment(3) : $request->segment(1);
        $page = $request->segment(1) === 'api' ? $request->segment(4) : $request->segment(2);
        $permission = match ($module) {
            'sales', 'sales-pos' => match ($page) {
                'returns' => 'sales_pos.refund',
                'cashier-closing' => 'accounting.close',
                default => 'sales_pos.checkout',
            },
            'patients' => 'patients.manage',
            'appointments' => 'appointments.manage',
            'optical-orders' => 'optical_orders.manage',
            'inventory' => match ($page) {
                'stock-adjustments', 'adjustments' => 'inventory.adjust',
                'purchase-orders', 'goods-receipts' => 'inventory.purchase',
                default => 'inventory.manage',
            },
            'whatsapp' => $page === 'settings' ? 'whatsapp.settings' : 'whatsapp.send',
            'admin', 'configuration' => 'settings.manage',
            'documents' => match ($page) {
                'sales-invoices', 'payments' => 'sales_pos.checkout',
                'prescriptions', 'patient-files' => 'patients.manage',
                'optical-orders' => 'optical_orders.manage',
                'purchase-orders', 'purchase-invoices', 'stock-report' => 'inventory.manage',
                'accounting-vouchers' => 'accounting.view',
                'financial-reports' => 'erp.export',
                default => 'erp.view',
            },
            'erp' => match ($page ?: 'dashboard') {
                'accounting' => 'accounting.view',
                'reports' => 'erp.export',
                'configuration', 'users-permissions', 'admin', 'settings' => 'settings.manage',
                default => 'erp.view',
            },
            default => null,
        };

        abort_if($permission && ! $request->user()->hasPermission($permission), 403);

        return $next($request);
    }
}
