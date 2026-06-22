<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ErpFoundationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $companyId = (int) DB::table('companies')->orderBy('id')->value('id');
        $branchId = Schema::hasTable('branches') ? (int) DB::table('branches')->orderBy('id')->value('id') : null;

        $this->seedRolesAndPermissions($companyId, $branchId);
        $this->seedSystemSettings($companyId);
        $this->seedConfigurationOptions($companyId);
        $this->seedUiConfigurationOptions($companyId);

        if (Schema::hasTable('accounting_accounts')) {
            $accounts = $this->seedChartOfAccounts($companyId);
            $this->seedAccountingMappings($accounts);
            $this->seedAccountingJournals($companyId, $accounts);
            $this->seedDailyClosings($companyId);
        }
    }

    private function seedUiConfigurationOptions(int $companyId): void
    {
        if (! Schema::hasTable('configuration_options')) {
            return;
        }

        $groups = [
            'brands' => [
                'ray_ban' => ['Ray-Ban', 'راي بان'],
                'oakley' => ['Oakley', 'أوكلي'],
                'vogue' => ['Vogue', 'فوغ'],
                'tom_ford' => ['Tom Ford', 'توم فورد'],
            ],
            'frame_types' => [
                'full_rim' => ['Full rim', 'إطار كامل'],
                'semi_rimless' => ['Semi rimless', 'نصف إطار'],
                'rimless' => ['Rimless', 'بدون إطار'],
                'sport' => ['Sport', 'رياضي'],
                'kids' => ['Kids', 'أطفال'],
            ],
            'lens_types' => [
                'single_vision' => ['Single vision', 'أحادي البؤرة'],
                'bifocal' => ['Bifocal', 'ثنائي البؤرة'],
                'progressive' => ['Progressive', 'متدرج'],
                'office' => ['Office / occupational', 'مكتبي'],
                'reader' => ['Reader', 'قراءة'],
            ],
            'lens_materials' => [
                'cr39' => ['CR-39', 'سي آر 39'],
                'polycarbonate' => ['Polycarbonate', 'بولي كربونيت'],
                'trivex' => ['Trivex', 'ترايفكس'],
                'resin' => ['Resin', 'راتنج'],
                'glass' => ['Glass', 'زجاج'],
            ],
            'lens_coatings' => [
                'standard_ar' => ['Standard AR', 'مضاد انعكاس عادي'],
                'premium_ar' => ['Premium AR', 'مضاد انعكاس ممتاز'],
                'blue_block' => ['Blue block', 'حماية الضوء الأزرق'],
                'scratch_resistant' => ['Scratch resistant', 'مقاوم للخدش'],
                'photochromic' => ['Photochromic', 'متغير مع الضوء'],
            ],
            'lens_tints' => [
                'clear' => ['Clear', 'شفاف'],
                'brown' => ['Brown', 'بني'],
                'gray' => ['Gray', 'رمادي'],
                'green' => ['Green', 'أخضر'],
                'gradient' => ['Gradient', 'متدرج'],
                'polarized' => ['Polarized', 'مستقطب'],
            ],
            'payment_methods' => [
                'cash' => ['Cash', 'نقدي'],
                'card' => ['Card', 'بطاقة'],
                'bank_transfer' => ['Bank transfer', 'تحويل بنكي'],
                'mobile_wallet' => ['Mobile wallet', 'محفظة إلكترونية'],
                'payment_link' => ['Payment link', 'رابط دفع'],
                'credit_note' => ['Credit note', 'إشعار دائن'],
            ],
            'tax_settings' => [
                'standard' => ['Standard VAT', 'ضريبة قياسية'],
                'zero_rated' => ['Zero rated', 'ضريبة صفرية'],
                'exempt' => ['Tax exempt', 'معفى من الضريبة'],
            ],
            'appointment_types' => [
                'eye_examination' => ['Eye examination', 'فحص نظر'],
                'prescription_check' => ['Prescription check', 'مراجعة وصفة'],
                'frame_fitting' => ['Frame fitting', 'تركيب إطار'],
                'lens_pickup' => ['Lens pickup', 'استلام العدسات'],
                'follow_up' => ['Follow-up', 'متابعة'],
                'complaint_remake_check' => ['Complaint / remake check', 'شكوى أو إعادة تصنيع'],
                'general_consultation' => ['General consultation', 'استشارة عامة'],
            ],
            'order_statuses' => [
                'draft' => ['Draft', 'مسودة'],
                'confirmed' => ['Confirmed', 'مؤكد'],
                'waiting_for_frame' => ['Waiting for frame', 'بانتظار الإطار'],
                'waiting_for_lenses' => ['Waiting for lenses', 'بانتظار العدسات'],
                'sent_to_lab' => ['Sent to lab', 'أرسل للمختبر'],
                'in_lab' => ['In lab', 'في المختبر'],
                'quality_check' => ['Quality check', 'فحص الجودة'],
                'ready_for_pickup' => ['Ready for pickup', 'جاهز للاستلام'],
                'delivered_collected' => ['Delivered / collected', 'تم التسليم'],
                'cancelled' => ['Cancelled', 'ملغي'],
                'remake' => ['Remake', 'إعادة تصنيع'],
            ],
            'product_types' => [
                'frame' => ['Frame', 'إطار'],
                'lens' => ['Lens', 'عدسة'],
                'contact_lens' => ['Contact lens', 'عدسة لاصقة'],
                'sunglasses' => ['Sunglasses', 'نظارة شمسية'],
                'accessory' => ['Accessory', 'إكسسوار'],
                'cleaning_solution' => ['Cleaning solution', 'محلول تنظيف'],
                'consumable' => ['Consumable', 'مستهلكات'],
            ],
            'genders' => [
                'male' => ['Male', 'ذكر'],
                'female' => ['Female', 'أنثى'],
                'not_specified' => ['Not specified', 'غير محدد'],
            ],
            'document_types' => [
                'id_document' => ['Identity document', 'وثيقة هوية'],
                'medical_report' => ['Medical report', 'تقرير طبي'],
                'prescription' => ['Prescription', 'وصفة'],
                'insurance' => ['Insurance document', 'وثيقة تأمين'],
                'other' => ['Other', 'أخرى'],
            ],
            'exam_statuses' => [
                'draft' => ['Draft', 'مسودة'],
                'completed' => ['Completed', 'مكتمل'],
                'cancelled' => ['Cancelled', 'ملغي'],
            ],
            'prescription_statuses' => [
                'draft' => ['Draft', 'مسودة'],
                'signed' => ['Signed', 'موقعة'],
                'locked' => ['Locked', 'مقفلة'],
                'expired' => ['Expired', 'منتهية'],
            ],
            'appointment_statuses' => [
                'draft' => ['Draft', 'مسودة'],
                'scheduled' => ['Scheduled', 'مجدول'],
                'confirmed' => ['Confirmed', 'مؤكد'],
                'checked_in' => ['Checked in', 'تم تسجيل الوصول'],
                'waiting' => ['Waiting', 'قيد الانتظار'],
                'in_examination' => ['In examination', 'قيد الفحص'],
                'completed' => ['Completed', 'مكتمل'],
                'rescheduled' => ['Rescheduled', 'أعيدت جدولته'],
                'cancelled' => ['Cancelled', 'ملغي'],
                'no_show' => ['No-show', 'لم يحضر'],
            ],
            'order_priorities' => [
                'normal' => ['Normal', 'عادي'],
                'urgent' => ['Urgent', 'عاجل'],
            ],
            'lens_indexes' => [
                '1.50' => ['1.50', '1.50'],
                '1.56' => ['1.56', '1.56'],
                '1.60' => ['1.60', '1.60'],
                '1.67' => ['1.67', '1.67'],
                '1.74' => ['1.74', '1.74'],
            ],
            'whatsapp_template_categories' => [
                'utility' => ['Utility', 'خدمية'],
                'marketing' => ['Marketing', 'تسويقية'],
                'authentication' => ['Authentication', 'مصادقة'],
            ],
        ];

        $now = now();
        foreach ($groups as $group => $options) {
            $sortOrder = 1;
            foreach ($options as $key => [$labelEn, $labelAr]) {
                DB::table('configuration_options')->updateOrInsert([
                    'company_id' => $companyId ?: null,
                    'branch_id' => null,
                    'group' => $group,
                    'key' => $key,
                ], [
                    'label_en' => $labelEn,
                    'label_ar' => $labelAr,
                    'value' => $key,
                    'value_type' => 'string',
                    'sort_order' => $sortOrder++,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedConfigurationOptions(int $companyId): void
    {
        if (! Schema::hasTable('configuration_options')) {
            return;
        }

        $now = now();
        $groups = [
            'brands' => [
                ['ray_ban', 'Ray-Ban', 'راي بان'],
                ['oakley', 'Oakley', 'أوكلي'],
                ['vogue', 'Vogue', 'فوغ'],
                ['tom_ford', 'Tom Ford', 'توم فورد'],
            ],
            'frame_types' => [
                ['full_rim', 'Full rim', 'إطار كامل'],
                ['semi_rimless', 'Semi rimless', 'نصف إطار'],
                ['rimless', 'Rimless', 'بدون إطار'],
                ['sport', 'Sport', 'رياضي'],
                ['kids', 'Kids', 'أطفال'],
            ],
            'lens_types' => [
                ['single_vision', 'Single vision', 'أحادي البؤرة'],
                ['bifocal', 'Bifocal', 'ثنائي البؤرة'],
                ['progressive', 'Progressive', 'متدرج'],
                ['office', 'Office / occupational', 'مكتبي'],
                ['reader', 'Reader', 'قراءة'],
            ],
            'lens_materials' => [
                ['cr39', 'CR-39', 'سي آر 39'],
                ['polycarbonate', 'Polycarbonate', 'بولي كربونيت'],
                ['trivex', 'Trivex', 'ترايفكس'],
                ['resin', 'Resin', 'راتنج'],
                ['glass', 'Glass', 'زجاج'],
            ],
            'lens_coatings' => [
                ['standard_ar', 'Standard AR', 'مضاد انعكاس عادي'],
                ['premium_ar', 'Premium AR', 'مضاد انعكاس ممتاز'],
                ['blue_block', 'Blue block', 'حماية الضوء الأزرق'],
                ['scratch_resistant', 'Scratch resistant', 'مقاوم للخدش'],
                ['photochromic', 'Photochromic', 'فوتوكروميك'],
            ],
            'lens_tints' => [
                ['clear', 'Clear', 'شفاف'],
                ['brown', 'Brown', 'بني'],
                ['gray', 'Gray', 'رمادي'],
                ['green', 'Green', 'أخضر'],
                ['gradient', 'Gradient', 'متدرج'],
                ['polarized', 'Polarized', 'مستقطب'],
            ],
            'payment_methods' => [
                ['cash', 'Cash', 'نقدي'],
                ['card', 'Card', 'بطاقة'],
                ['bank_transfer', 'Bank transfer', 'تحويل بنكي'],
                ['mobile_wallet', 'Mobile wallet', 'محفظة إلكترونية'],
                ['payment_link', 'Payment link', 'رابط دفع'],
                ['credit_note', 'Credit note', 'إشعار دائن'],
            ],
            'tax_settings' => [
                ['standard', 'Standard VAT', 'ضريبة قياسية'],
                ['zero_rated', 'Zero rated', 'ضريبة صفرية'],
                ['exempt', 'Tax exempt', 'معفى من الضريبة'],
            ],
            'appointment_types' => [
                ['eye_examination', 'Eye examination', 'فحص نظر'],
                ['prescription_check', 'Prescription check', 'مراجعة وصفة'],
                ['frame_fitting', 'Frame fitting', 'تركيب إطار'],
                ['lens_pickup', 'Lens pickup', 'استلام العدسات'],
                ['follow_up', 'Follow-up', 'متابعة'],
                ['complaint_remake_check', 'Complaint / remake check', 'شكوى أو إعادة تصنيع'],
                ['general_consultation', 'General consultation', 'استشارة عامة'],
            ],
            'order_statuses' => [
                ['draft', 'Draft', 'مسودة'],
                ['confirmed', 'Confirmed', 'مؤكد'],
                ['waiting_for_frame', 'Waiting for frame', 'بانتظار الإطار'],
                ['waiting_for_lenses', 'Waiting for lenses', 'بانتظار العدسات'],
                ['sent_to_lab', 'Sent to lab', 'أرسل للمختبر'],
                ['in_lab', 'In lab', 'في المختبر'],
                ['quality_check', 'Quality check', 'فحص الجودة'],
                ['ready_for_pickup', 'Ready for pickup', 'جاهز للاستلام'],
                ['delivered_collected', 'Delivered / collected', 'تم التسليم'],
                ['cancelled', 'Cancelled', 'ملغي'],
                ['remake', 'Remake', 'إعادة تصنيع'],
            ],
            'invoice_settings' => [
                ['invoice_prefix', 'Invoice prefix', 'بادئة الفاتورة', 'INV'],
                ['show_vat_number', 'Show VAT number', 'إظهار الرقم الضريبي', '1'],
            ],
            'receipt_settings' => [
                ['receipt_prefix', 'Receipt prefix', 'بادئة الإيصال', 'REC'],
                ['print_after_payment', 'Print after payment', 'طباعة بعد الدفع', '1'],
            ],
            'prescription_print_settings' => [
                ['show_doctor_signature', 'Show optometrist signature', 'إظهار توقيع الأخصائي', '1'],
                ['show_next_visit', 'Show next visit', 'إظهار الزيارة القادمة', '1'],
            ],
            'currency_settings' => [
                ['currency', 'Currency', 'العملة', 'SAR'],
                ['currency_position', 'Currency position', 'موضع العملة', 'before'],
            ],
            'numbering_settings' => [
                ['patient_prefix', 'Patient prefix', 'بادئة المريض', 'PT'],
                ['order_prefix', 'Optical order prefix', 'بادئة أمر النظارة', 'OPT'],
                ['payment_prefix', 'Payment prefix', 'بادئة الدفع', 'PAY'],
            ],
            'accounting_settings' => [
                ['auto_post_transactions', 'Auto post transactions', 'ترحيل تلقائي للقيود', '1'],
                ['daily_closing_required', 'Daily closing required', 'الإغلاق اليومي مطلوب', '1'],
            ],
        ];

        foreach ($groups as $group => $options) {
            foreach ($options as $index => $option) {
                [$key, $labelEn, $labelAr] = $option;
                $value = $option[3] ?? $key;
                DB::table('configuration_options')->updateOrInsert([
                    'company_id' => $companyId ?: null,
                    'branch_id' => null,
                    'group' => $group,
                    'key' => $key,
                ], [
                    'label_en' => $labelEn,
                    'label_ar' => $labelAr,
                    'value' => $value,
                    'value_type' => is_numeric($value) ? 'number' : 'string',
                    'sort_order' => $index + 1,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedSystemSettings(int $companyId): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();
        $settings = [
            ['general', 'currency', 'Currency', 'SAR', 'string', 'Default ERP currency used by invoices and reports.', true],
            ['general', 'business_day_close_time', 'Business Day Close Time', '23:59', 'string', 'Default cut-off time for daily closing.', false],
            ['inventory', 'low_stock_alerts_enabled', 'Low Stock Alerts', '1', 'boolean', 'Show low-stock alerts on dashboards and reports.', true],
            ['sales', 'manager_approval_discount_percent', 'Manager Discount Approval', '20', 'number', 'Discount percentage that requires manager approval.', false],
            ['accounting', 'auto_post_transactions', 'Auto Post Accounting', '1', 'boolean', 'Automatically create balanced journals for invoices, payments, receipts, and adjustments.', false],
            ['whatsapp', 'auto_send_enabled', 'WhatsApp Automations', '1', 'boolean', 'Allow configured WhatsApp reminders and notifications to send automatically.', false],
            ['reports', 'default_export_format', 'Default Report Export', 'csv', 'string', 'Default export format for operational reports.', true],
        ];

        foreach ($settings as [$group, $key, $label, $value, $type, $description, $public]) {
            DB::table('system_settings')->updateOrInsert([
                'company_id' => $companyId ?: null,
                'branch_id' => null,
                'group' => $group,
                'key' => $key,
            ], [
                'label' => $label,
                'value' => $value,
                'value_type' => $type,
                'description' => $description,
                'is_public' => $public,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedRolesAndPermissions(int $companyId, ?int $branchId): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            ['erp', 'view', 'View ERP Dashboard'],
            ['erp', 'search', 'Use Global Search'],
            ['erp', 'export', 'Export Reports'],
            ['patients', 'manage', 'Manage Patients'],
            ['appointments', 'manage', 'Manage Appointments'],
            ['optical_orders', 'manage', 'Manage Optical Orders'],
            ['sales_pos', 'checkout', 'Use POS Checkout'],
            ['sales_pos', 'refund', 'Process Returns and Refunds'],
            ['sales_pos', 'discount_approve', 'Approve Large Discounts'],
            ['inventory', 'manage', 'Manage Inventory'],
            ['inventory', 'adjust', 'Adjust Stock'],
            ['inventory', 'purchase', 'Receive Purchases'],
            ['whatsapp', 'send', 'Send WhatsApp Messages'],
            ['whatsapp', 'settings', 'Manage WhatsApp Settings'],
            ['accounting', 'view', 'View Accounting'],
            ['accounting', 'post', 'Post Accounting Entries'],
            ['accounting', 'close', 'Close Cashier Day'],
            ['settings', 'manage', 'Manage System Settings'],
        ];

        foreach ($permissions as [$module, $action, $name]) {
            DB::table('permissions')->updateOrInsert([
                'slug' => $module.'.'.$action,
            ], [
                'module' => $module,
                'action' => $action,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = [
            'erp-admin' => ['ERP Admin', 'Full system owner', ['*']],
            'store-manager' => ['Store Manager', 'Runs the branch day to day', [
                'erp.view', 'erp.search', 'erp.export', 'patients.manage', 'appointments.manage',
                'optical_orders.manage', 'sales_pos.checkout', 'sales_pos.refund',
                'sales_pos.discount_approve', 'inventory.manage', 'inventory.adjust',
                'inventory.purchase', 'whatsapp.send', 'accounting.view', 'accounting.close',
            ]],
            'receptionist' => ['Receptionist', 'Patients, bookings, and check-in', [
                'erp.view', 'erp.search', 'patients.manage', 'appointments.manage', 'whatsapp.send',
            ]],
            'optometrist' => ['Optometrist', 'Eye exams and prescriptions', [
                'erp.view', 'erp.search', 'patients.manage', 'appointments.manage', 'optical_orders.manage',
            ]],
            'cashier' => ['Cashier', 'Checkout, payments, and closing', [
                'erp.view', 'erp.search', 'sales_pos.checkout', 'sales_pos.refund', 'accounting.close',
            ]],
            'inventory-officer' => ['Inventory Officer', 'Products, receiving, transfers, counts', [
                'erp.view', 'erp.search', 'inventory.manage', 'inventory.adjust', 'inventory.purchase',
            ]],
            'accountant' => ['Accountant', 'Reports, journals, and closings', [
                'erp.view', 'erp.search', 'erp.export', 'accounting.view', 'accounting.post', 'accounting.close',
            ]],
            'whatsapp-agent' => ['WhatsApp Agent', 'Customer conversations and reminders', [
                'erp.view', 'erp.search', 'whatsapp.send',
            ]],
        ];

        foreach ($roles as $slug => [$name, $description, $rolePermissions]) {
            DB::table('roles')->updateOrInsert([
                'slug' => $slug,
            ], [
                'company_id' => $companyId ?: null,
                'name' => $name,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $roleId = (int) DB::table('roles')->where('slug', $slug)->value('id');
            $permissionIds = $rolePermissions === ['*']
                ? DB::table('permissions')->pluck('id')->all()
                : DB::table('permissions')->whereIn('slug', $rolePermissions)->pluck('id')->all();

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ], [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $user = User::query()->where('email', 'test@example.com')->first();
        if ($user) {
            $adminRoleId = (int) DB::table('roles')->where('slug', 'erp-admin')->value('id');
            DB::table('role_user')->updateOrInsert([
                'role_id' => $adminRoleId,
                'user_id' => $user->id,
            ], [
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($branchId && Schema::hasTable('branch_user')) {
                DB::table('branch_user')->updateOrInsert([
                    'branch_id' => $branchId,
                    'user_id' => $user->id,
                ], [
                    'is_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedChartOfAccounts(int $companyId): array
    {
        return [
            'cash' => $this->account($companyId, '1000', 'Cash on Hand', 'asset', 'debit', true, false),
            'bank' => $this->account($companyId, '1010', 'Bank Account', 'asset', 'debit', false, true),
            'wallet' => $this->account($companyId, '1020', 'Mobile Wallet Clearing', 'asset', 'debit', false, false),
            'payment_link' => $this->account($companyId, '1030', 'Payment Link Clearing', 'asset', 'debit', false, false),
            'receivable' => $this->account($companyId, '1100', 'Accounts Receivable', 'asset'),
            'inventory' => $this->account($companyId, '1200', 'Inventory Asset', 'asset'),
            'payable' => $this->account($companyId, '2000', 'Accounts Payable', 'liability', 'credit'),
            'vat_payable' => $this->account($companyId, '2100', 'VAT Payable', 'liability', 'credit'),
            'equity' => $this->account($companyId, '3000', 'Owner Equity', 'equity', 'credit'),
            'sales' => $this->account($companyId, '4000', 'Sales Revenue', 'revenue', 'credit'),
            'services' => $this->account($companyId, '4100', 'Optical Services Revenue', 'revenue', 'credit'),
            'cogs' => $this->account($companyId, '5000', 'Cost of Goods Sold', 'expense'),
            'expenses' => $this->account($companyId, '6000', 'Operating Expenses', 'expense'),
            'adjustments' => $this->account($companyId, '6100', 'Inventory Adjustments', 'expense'),
        ];
    }

    private function account(int $companyId, string $code, string $name, string $type, string $normalBalance = 'debit', bool $cash = false, bool $bank = false): int
    {
        $now = now();
        DB::table('accounting_accounts')->updateOrInsert([
            'code' => $code,
        ], [
            'company_id' => $companyId ?: null,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_cash' => $cash,
            'is_bank' => $bank,
            'is_system' => true,
            'is_active' => true,
            'description' => 'Default account for the Optical ERP demo company.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('accounting_accounts')->where('code', $code)->value('id');
    }

    private function seedAccountingMappings(array $accounts): void
    {
        $now = now();
        $mappings = [
            'pos_sale' => [$accounts['receivable'], $accounts['sales'], 'POS sale invoice', 'Invoice posts revenue and customer receivable.'],
            'payment_received' => [$accounts['cash'], $accounts['receivable'], 'Payment received', 'Payment reduces customer balance.'],
            'inventory_purchase' => [$accounts['inventory'], $accounts['payable'], 'Inventory purchase receipt', 'Goods receipt increases stock value and supplier payable.'],
            'stock_adjustment_loss' => [$accounts['adjustments'], $accounts['inventory'], 'Stock adjustment write-off', 'Damaged or lost stock reduces inventory.'],
            'order_deposit' => [$accounts['cash'], $accounts['receivable'], 'Optical order deposit', 'Deposit reduces outstanding customer balance.'],
        ];

        foreach ($mappings as $eventKey => [$debit, $credit, $title, $description]) {
            DB::table('accounting_integration_mappings')->updateOrInsert([
                'event_key' => $eventKey,
            ], [
                'debit_account_id' => $debit,
                'credit_account_id' => $credit,
                'title' => $title,
                'description' => $description,
                'is_active' => true,
                'settings' => json_encode(['auto_post' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedAccountingJournals(int $companyId, array $accounts): void
    {
        if (Schema::hasTable('sales_invoices')) {
            DB::table('sales_invoices')->orderBy('id')->get()->each(function ($invoice) use ($companyId, $accounts): void {
                $grandTotal = round((float) $invoice->grand_total, 2);
                if ($grandTotal <= 0) {
                    return;
                }

                $tax = round((float) $invoice->tax_total, 2);
                $revenue = round($grandTotal - $tax, 2);
                $lines = [
                    $this->line($accounts['receivable'], 'Customer invoice '.$invoice->invoice_number, $grandTotal, 0, 'sales_customer', $invoice->customer_id),
                    $this->line($accounts['sales'], 'Sales revenue '.$invoice->invoice_number, 0, $revenue, 'sales_customer', $invoice->customer_id),
                ];

                if ($tax > 0) {
                    $lines[] = $this->line($accounts['vat_payable'], 'VAT '.$invoice->invoice_number, 0, $tax, 'sales_customer', $invoice->customer_id);
                }

                $this->journal($companyId, $invoice->branch_id, 'sales_invoice', $invoice->id, $invoice->invoice_date, 'Sales invoice '.$invoice->invoice_number, $lines);
            });
        }

        if (Schema::hasTable('sales_payments')) {
            DB::table('sales_payments')->where('status', 'posted')->orderBy('id')->get()->each(function ($payment) use ($companyId, $accounts): void {
                $amount = round((float) $payment->amount, 2);
                if ($amount <= 0) {
                    return;
                }

                $debitAccount = match ($payment->payment_method) {
                    'card', 'bank_transfer' => $accounts['bank'],
                    'mobile_wallet' => $accounts['wallet'],
                    'payment_link' => $accounts['payment_link'],
                    default => $accounts['cash'],
                };

                $lines = [
                    $this->line($debitAccount, 'Payment '.$payment->payment_number, $amount, 0, 'sales_customer', $payment->customer_id),
                    $this->line($accounts['receivable'], 'Customer balance payment '.$payment->payment_number, 0, $amount, 'sales_customer', $payment->customer_id),
                ];

                $this->journal($companyId, $payment->branch_id, 'sales_payment', $payment->id, substr((string) $payment->paid_at, 0, 10), 'Payment received '.$payment->payment_number, $lines);
            });
        }

        if (Schema::hasTable('inventory_goods_receipts') && Schema::hasTable('inventory_goods_receipt_lines')) {
            DB::table('inventory_goods_receipts')->orderBy('id')->get()->each(function ($receipt) use ($companyId, $accounts): void {
                $amount = DB::table('inventory_goods_receipt_lines')
                    ->where('inventory_goods_receipt_id', $receipt->id)
                    ->sum(DB::raw('accepted_quantity * unit_cost'));
                $amount = round((float) $amount, 2);

                if ($amount <= 0) {
                    return;
                }

                $lines = [
                    $this->line($accounts['inventory'], 'Goods received '.$receipt->receipt_number, $amount, 0, 'supplier', $receipt->supplier_id),
                    $this->line($accounts['payable'], 'Supplier payable '.$receipt->receipt_number, 0, $amount, 'supplier', $receipt->supplier_id),
                ];

                $this->journal($companyId, $receipt->branch_id, 'inventory_goods_receipt', $receipt->id, $receipt->received_on, 'Goods receipt '.$receipt->receipt_number, $lines);
            });
        }
    }

    private function seedDailyClosings(int $companyId): void
    {
        if (! Schema::hasTable('sales_cash_sessions') || ! Schema::hasTable('accounting_daily_closings')) {
            return;
        }

        $groups = [];
        foreach (DB::table('sales_cash_sessions')->get() as $session) {
            $date = substr((string) ($session->closed_at ?: $session->opened_at), 0, 10);
            $key = $session->branch_id.'|'.$date;
            $groups[$key] ??= [
                'branch_id' => $session->branch_id,
                'closing_date' => $date,
                'opening_cash' => 0,
                'cash_sales' => 0,
                'card_sales' => 0,
                'bank_transfer_sales' => 0,
                'mobile_wallet_sales' => 0,
                'payment_link_sales' => 0,
                'refunds' => 0,
                'expected_cash' => 0,
                'actual_cash' => 0,
                'difference' => 0,
                'status' => 'closed',
            ];

            $groups[$key]['opening_cash'] += (float) $session->opening_cash;
            $groups[$key]['cash_sales'] += (float) $session->total_cash_sales;
            $groups[$key]['card_sales'] += (float) $session->total_card_sales;
            $groups[$key]['bank_transfer_sales'] += (float) $session->total_bank_transfer_sales;
            $groups[$key]['mobile_wallet_sales'] += (float) $session->total_mobile_wallet_sales;
            $groups[$key]['payment_link_sales'] += (float) $session->total_payment_link_sales;
            $groups[$key]['refunds'] += (float) $session->total_refunds;
            $groups[$key]['expected_cash'] += (float) $session->expected_cash;
            $groups[$key]['actual_cash'] += (float) ($session->actual_cash ?? $session->expected_cash);
            $groups[$key]['difference'] += (float) $session->difference;
            if ($session->status !== 'closed') {
                $groups[$key]['status'] = 'open';
            }
        }

        $now = now();
        foreach ($groups as $group) {
            DB::table('accounting_daily_closings')->updateOrInsert([
                'branch_id' => $group['branch_id'],
                'closing_date' => $group['closing_date'],
            ], [
                'company_id' => $companyId ?: null,
                'opening_cash' => round($group['opening_cash'], 2),
                'cash_sales' => round($group['cash_sales'], 2),
                'card_sales' => round($group['card_sales'], 2),
                'bank_transfer_sales' => round($group['bank_transfer_sales'], 2),
                'mobile_wallet_sales' => round($group['mobile_wallet_sales'], 2),
                'payment_link_sales' => round($group['payment_link_sales'], 2),
                'refunds' => round($group['refunds'], 2),
                'expected_cash' => round($group['expected_cash'], 2),
                'actual_cash' => round($group['actual_cash'], 2),
                'difference' => round($group['difference'], 2),
                'status' => $group['status'],
                'closed_at' => $group['status'] === 'closed' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function line(int $accountId, string $description, float $debit, float $credit, ?string $partyType = null, mixed $partyId = null): array
    {
        return [
            'account_id' => $accountId,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'party_type' => $partyType,
            'party_id' => $partyId,
        ];
    }

    private function journal(int $companyId, mixed $branchId, string $sourceType, mixed $sourceId, string $date, string $description, array $lines): void
    {
        if (DB::table('accounting_journals')->where('source_type', $sourceType)->where('source_id', $sourceId)->exists()) {
            return;
        }

        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if (abs($debit - $credit) > 0.009 || $debit <= 0) {
            return;
        }

        $now = now();
        $journalId = DB::table('accounting_journals')->insertGetId([
            'company_id' => $companyId ?: null,
            'branch_id' => $branchId ?: null,
            'journal_number' => $this->nextJournalNumber(),
            'journal_date' => $date,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'posted',
            'description' => $description,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'posted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($lines as $line) {
            DB::table('accounting_journal_lines')->insert([
                'accounting_journal_id' => $journalId,
                'accounting_account_id' => $line['account_id'],
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'party_type' => $line['party_type'],
                'party_id' => $line['party_id'],
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function nextJournalNumber(): string
    {
        $next = DB::table('accounting_journals')->count() + 1;

        return 'JRN-'.now()->format('ymd').'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
