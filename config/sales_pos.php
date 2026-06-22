<?php

return [
    'module' => [
        'name' => 'Sales & POS',
        'version' => '0.1.0',
        'description' => 'Fast optical POS, sales documents, split payments, returns, cashier closing, and inventory posting.',
    ],

    'document_types' => [
        'quotation' => 'Quotation',
        'sales_order' => 'Sales order',
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'return_invoice' => 'Return invoice',
        'credit_note' => 'Credit note',
    ],

    'invoice_statuses' => [
        'draft' => 'Draft',
        'posted' => 'Posted',
        'partial' => 'Partially paid',
        'paid' => 'Paid',
        'partially_refunded' => 'Partially refunded',
        'refunded' => 'Refunded',
        'void' => 'Void',
    ],

    'order_statuses' => [
        'draft' => 'Draft',
        'reserved' => 'Reserved',
        'partially_invoiced' => 'Partially invoiced',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'payment_methods' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank transfer',
        'mobile_wallet' => 'Mobile wallet',
        'payment_link' => 'Payment link',
        'credit_note' => 'Credit note',
    ],

    'sale_modes' => [
        'ready_product' => 'Ready product',
        'prescription_order' => 'Prescription order',
        'frame_lens_package' => 'Frame + lens package',
        'pickup_balance' => 'Pickup balance payment',
    ],

    'permissions' => [
        'sales.dashboard.view',
        'sales.pos.checkout',
        'sales.pos.override_stock',
        'sales.pos.large_discount_approve',
        'sales.quotations.view',
        'sales.quotations.create',
        'sales.orders.view',
        'sales.orders.create',
        'sales.orders.reserve_stock',
        'sales.invoices.view',
        'sales.invoices.print',
        'sales.payments.collect',
        'sales.returns.create',
        'sales.returns.refund',
        'sales.cashier.open',
        'sales.cashier.close',
        'sales.reports.view',
        'sales.settings.manage',
    ],

    'roles' => [
        'sales_admin' => [
            'name' => 'Sales Admin',
            'permissions' => ['sales.*'],
        ],
        'store_manager' => [
            'name' => 'Store Manager',
            'permissions' => [
                'sales.dashboard.view',
                'sales.pos.*',
                'sales.quotations.*',
                'sales.orders.*',
                'sales.invoices.*',
                'sales.payments.*',
                'sales.returns.*',
                'sales.cashier.*',
                'sales.reports.*',
            ],
        ],
        'cashier' => [
            'name' => 'Cashier',
            'permissions' => [
                'sales.dashboard.view',
                'sales.pos.checkout',
                'sales.quotations.create',
                'sales.invoices.view',
                'sales.invoices.print',
                'sales.payments.collect',
                'sales.cashier.open',
                'sales.cashier.close',
            ],
        ],
        'sales_associate' => [
            'name' => 'Sales Associate',
            'permissions' => [
                'sales.dashboard.view',
                'sales.pos.checkout',
                'sales.quotations.*',
                'sales.orders.create',
                'sales.invoices.view',
            ],
        ],
        'returns_clerk' => [
            'name' => 'Returns Clerk',
            'permissions' => [
                'sales.invoices.view',
                'sales.returns.create',
                'sales.returns.refund',
                'sales.payments.collect',
            ],
        ],
    ],

    'reports' => [
        'daily_sales' => 'Daily sales report',
        'sales_by_cashier' => 'Sales by cashier',
        'sales_by_branch' => 'Sales by branch',
        'sales_by_product' => 'Sales by product',
        'sales_by_category' => 'Sales by category',
        'sales_by_brand' => 'Sales by brand',
        'gross_profit' => 'Gross profit report',
        'returns' => 'Returns report',
        'payment_methods' => 'Payment methods report',
    ],

    'integration_rules' => [
        'quotation_stock' => 'Quotations do not touch inventory.',
        'sales_order_reservation' => 'Sales orders can reserve stock by increasing reserved quantity.',
        'invoice_stock' => 'Posted invoices reduce on-hand stock and create sale_issue movements.',
        'return_stock' => 'Restockable returns increase on-hand stock and create sale_return movements.',
        'unavailable_stock' => 'The POS blocks unavailable stock unless a user has override permission.',
        'valuation_hook' => 'Invoice and return costs are stored for later accounting and inventory valuation.',
    ],

    'shortcuts' => [
        'F2' => 'Focus product search',
        'F4' => 'Focus payment amount',
        'Ctrl+Enter' => 'Post checkout',
        'Esc' => 'Clear current quick search',
    ],
];
