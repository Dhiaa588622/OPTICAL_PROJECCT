<?php

return [
    'registration_enabled' => env('ERP_REGISTRATION_ENABLED', false),
    'registration_role' => env('ERP_REGISTRATION_ROLE', 'receptionist'),

    'module' => [
        'name' => 'Optical Store ERP',
        'description' => 'Unified dashboard, workflows, search, reports, and accounting foundation for the optical ERP.',
        'currency' => env('ERP_CURRENCY', 'SAR'),
    ],

    'navigation' => [
        ['label_key' => 'nav.dashboard', 'route' => 'erp.dashboard', 'page' => 'dashboard', 'icon' => 'dashboard', 'permission' => 'erp.view'],
        ['label_key' => 'nav.patients', 'route' => 'erp.app', 'page' => 'patients', 'icon' => 'patients', 'permission' => 'patients.manage'],
        ['label_key' => 'nav.appointments', 'route' => 'erp.app', 'page' => 'appointments', 'icon' => 'appointments', 'permission' => 'appointments.manage'],
        ['label_key' => 'nav.optical_orders', 'route' => 'erp.app', 'page' => 'optical-orders', 'icon' => 'orders', 'permission' => 'optical_orders.manage'],
        ['label_key' => 'nav.sales_pos', 'route' => 'erp.app', 'page' => 'sales-pos', 'icon' => 'sales', 'permission' => 'sales_pos.checkout'],
        ['label_key' => 'nav.inventory', 'route' => 'erp.app', 'page' => 'inventory', 'icon' => 'inventory', 'permission' => 'inventory.manage'],
        ['label_key' => 'nav.whatsapp', 'route' => 'erp.app', 'page' => 'whatsapp', 'icon' => 'whatsapp', 'permission' => 'whatsapp.send'],
        ['label_key' => 'nav.accounting', 'route' => 'erp.app', 'page' => 'accounting', 'icon' => 'accounting', 'permission' => 'accounting.view'],
        ['label_key' => 'nav.reports', 'route' => 'erp.app', 'page' => 'reports', 'icon' => 'reports', 'permission' => 'erp.export'],
        ['label_key' => 'nav.configuration', 'route' => 'erp.app', 'page' => 'configuration', 'icon' => 'configuration', 'permission' => 'settings.manage'],
        ['label_key' => 'nav.users_permissions', 'route' => 'erp.app', 'page' => 'users-permissions', 'icon' => 'users', 'permission' => 'settings.manage'],
    ],

    'quick_actions' => [
        ['label_key' => 'quick.new_patient', 'hint_key' => 'quick.new_patient_hint', 'route' => 'patients.app', 'page' => 'patient-create'],
        ['label_key' => 'quick.book_appointment', 'hint_key' => 'quick.book_appointment_hint', 'route' => 'appointments.app', 'page' => 'create'],
        ['label_key' => 'quick.create_order', 'hint_key' => 'quick.create_order_hint', 'route' => 'optical-orders.app', 'page' => 'create'],
        ['label_key' => 'quick.open_pos', 'hint_key' => 'quick.open_pos_hint', 'route' => 'sales.app', 'page' => 'pos'],
        ['label_key' => 'quick.receive_stock', 'hint_key' => 'quick.receive_stock_hint', 'route' => 'inventory.app', 'page' => 'goods-receiving'],
        ['label_key' => 'quick.whatsapp_inbox', 'hint_key' => 'quick.whatsapp_inbox_hint', 'route' => 'whatsapp.app', 'page' => 'inbox'],
    ],

    'reports' => [
        'sales-today' => 'Today Sales',
        'appointments-today' => 'Today Appointments',
        'pending-orders' => 'Pending Optical Orders',
        'ready-pickup' => 'Ready for Pickup',
        'low-stock' => 'Low Stock Products',
        'unpaid-invoices' => 'Unpaid Invoices',
        'whatsapp-unread' => 'Unread WhatsApp Messages',
        'inventory-valuation' => 'Inventory Valuation',
        'accounting-journals' => 'Accounting Journals',
        'trial-balance' => 'Trial Balance',
        'general-ledger' => 'General Ledger',
        'profit-and-loss' => 'Profit and Loss',
        'balance-sheet' => 'Balance Sheet',
    ],

    'workflows' => [
        [
            'key' => 'patient_to_order',
            'title' => 'Patient to Optical Order',
            'steps' => ['Patient', 'Appointment', 'Eye Exam', 'Prescription', 'Optical Order', 'WhatsApp Confirmation'],
        ],
        [
            'key' => 'order_to_invoice',
            'title' => 'Optical Order to Invoice',
            'steps' => ['Order', 'Stock Reservation', 'Deposit', 'Lab Status', 'Ready for Pickup', 'Final Payment', 'Invoice'],
        ],
        [
            'key' => 'purchase_to_accounting',
            'title' => 'Purchase to Accounting',
            'steps' => ['Purchase Order', 'Goods Receipt', 'Stock Increase', 'Inventory Report', 'Accounting Entry'],
        ],
        [
            'key' => 'pos_to_accounting',
            'title' => 'POS Sale to Accounting',
            'steps' => ['POS Sale', 'Payment', 'Invoice', 'Stock Reduction', 'Accounting Entry'],
        ],
        [
            'key' => 'invoice_to_reminder',
            'title' => 'Invoice to Reminder',
            'steps' => ['Invoice Created', 'WhatsApp Message', 'Payment Reminder', 'Patient Balance Update'],
        ],
    ],

    'readiness_checks' => [
        'demo_branch' => 'Demo branch is available',
        'demo_products' => 'Demo products and branch stock are available',
        'demo_patients' => 'Demo patients and prescriptions are available',
        'roles_permissions' => 'Default roles and permissions are seeded',
        'chart_accounts' => 'Default chart of accounts is seeded',
        'whatsapp_settings' => 'WhatsApp settings and templates are configured',
        'system_settings' => 'System settings are stored in database',
        'audit_logs' => 'Audit logs are recording important actions',
        'balanced_journals' => 'Accounting journal entries are balanced',
        'report_exports' => 'Reports export to CSV and print-ready PDF views',
    ],
];
