<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuration_options')) {
            return;
        }

        $companyId = DB::table('companies')->orderBy('id')->value('id');
        $groups = [
            'product_types' => config('inventory.product_types', []),
            'tax_settings' => config('inventory.tax_types', []),
            'appointment_types' => config('appointments.appointment_types', []),
            'appointment_statuses' => config('appointments.statuses', []),
            'calendar_views' => config('appointments.calendar_views', []),
            'reminder_triggers' => config('appointments.reminder_triggers', []),
            'genders' => config('patients.genders', []),
            'document_types' => config('patients.document_types', []),
            'exam_statuses' => config('patients.exam_statuses', []),
            'prescription_statuses' => config('patients.prescription_statuses', []),
            'order_statuses' => config('optical_orders.statuses', []),
            'order_priorities' => config('optical_orders.priorities', []),
            'lens_types' => config('optical_orders.lens_types', []),
            'lens_materials' => config('optical_orders.lens_materials', []),
            'lens_indexes' => config('optical_orders.lens_indexes', []),
            'lens_coatings' => config('optical_orders.coatings', []),
            'lens_tints' => config('optical_orders.tints', []),
            'order_document_types' => config('optical_orders.document_types', []),
            'order_active_statuses' => array_intersect_key(config('optical_orders.statuses', []), array_flip(config('optical_orders.active_statuses', []))),
            'order_lab_statuses' => array_intersect_key(config('optical_orders.statuses', []), array_flip(config('optical_orders.lab_statuses', []))),
            'order_whatsapp_triggers' => config('optical_orders.whatsapp_triggers', []),
            'sales_document_types' => config('sales_pos.document_types', []),
            'invoice_statuses' => config('sales_pos.invoice_statuses', []),
            'sales_order_statuses' => config('sales_pos.order_statuses', []),
            'payment_methods' => config('sales_pos.payment_methods', []),
            'sale_modes' => config('sales_pos.sale_modes', []),
            'pickup_statuses' => [
                'not_required' => 'Not required',
                'pending' => 'Pending pickup',
                'ready' => 'Ready for pickup',
                'collected' => 'Collected',
            ],
            'whatsapp_template_categories' => config('whatsapp.template_categories', []),
        ];

        $now = now();
        foreach ($groups as $group => $options) {
            $sortOrder = 1;
            foreach ($options as $key => $label) {
                DB::table('configuration_options')->updateOrInsert([
                    'company_id' => $companyId,
                    'branch_id' => null,
                    'group' => $group,
                    'key' => (string) $key,
                ], [
                    'label_en' => (string) $label,
                    'label_ar' => null,
                    'value' => (string) $key,
                    'value_type' => 'string',
                    'sort_order' => $sortOrder++,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(['slug' => 'inventory.negative_stock'], [
                'module' => 'inventory',
                'action' => 'negative_stock',
                'name' => 'Allow negative inventory balances',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('configuration_options')->whereIn('group', [
            'sales_document_types', 'invoice_statuses', 'sales_order_statuses', 'sale_modes', 'pickup_statuses',
            'order_document_types', 'calendar_views', 'reminder_triggers',
        ])->delete();
    }
};
