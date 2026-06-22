<?php

return [
    'module' => [
        'name' => 'Inventory',
        'version' => '0.1.0',
        'description' => 'Optical product catalog, stock control, purchasing, receiving, transfers, counts, and valuation.',
    ],

    'product_types' => [
        'frame' => 'Frames',
        'lens' => 'Lenses',
        'contact_lens' => 'Contact lenses',
        'sunglasses' => 'Sunglasses',
        'accessory' => 'Accessories',
        'cleaning_solution' => 'Cleaning solutions',
        'consumable' => 'Consumables',
    ],

    'tax_types' => [
        'standard' => 'Standard VAT',
        'zero_rated' => 'Zero rated',
        'exempt' => 'Tax exempt',
    ],

    'stock_statuses' => [
        'healthy',
        'low_stock',
        'out_of_stock',
        'reserved',
        'expired',
        'damaged',
    ],

    'movement_types' => [
        'purchase_receipt',
        'sale_issue',
        'sale_return',
        'optical_order_reserve',
        'reservation_release',
        'stock_adjustment',
        'stock_transfer_out',
        'stock_transfer_in',
        'stock_count_variance',
        'supplier_return',
        'damaged_write_off',
        'lost_write_off',
    ],

    'permissions' => [
        'inventory.dashboard.view',
        'inventory.products.view',
        'inventory.products.create',
        'inventory.products.update',
        'inventory.products.deactivate',
        'inventory.products.barcodes',
        'inventory.stock.view',
        'inventory.stock.adjust',
        'inventory.stock.transfer',
        'inventory.stock.count',
        'inventory.stock.write_off',
        'inventory.stock.reserve',
        'inventory.purchasing.view',
        'inventory.purchasing.create_po',
        'inventory.purchasing.approve_po',
        'inventory.purchasing.receive',
        'inventory.purchasing.return_supplier',
        'inventory.suppliers.view',
        'inventory.suppliers.manage',
        'inventory.reports.view',
        'inventory.reports.valuation',
        'inventory.audit.view',
        'inventory.settings.manage',
    ],

    'roles' => [
        'inventory_admin' => [
            'name' => 'Inventory Admin',
            'permissions' => ['inventory.*'],
        ],
        'inventory_manager' => [
            'name' => 'Inventory Manager',
            'permissions' => [
                'inventory.dashboard.view',
                'inventory.products.*',
                'inventory.stock.*',
                'inventory.purchasing.*',
                'inventory.suppliers.*',
                'inventory.reports.*',
                'inventory.audit.view',
            ],
        ],
        'stock_controller' => [
            'name' => 'Stock Controller',
            'permissions' => [
                'inventory.dashboard.view',
                'inventory.products.view',
                'inventory.stock.view',
                'inventory.stock.adjust',
                'inventory.stock.transfer',
                'inventory.stock.count',
                'inventory.stock.write_off',
                'inventory.purchasing.receive',
                'inventory.reports.view',
            ],
        ],
        'purchase_officer' => [
            'name' => 'Purchase Officer',
            'permissions' => [
                'inventory.dashboard.view',
                'inventory.products.view',
                'inventory.stock.view',
                'inventory.purchasing.*',
                'inventory.suppliers.*',
                'inventory.reports.view',
            ],
        ],
        'store_staff' => [
            'name' => 'Store Staff',
            'permissions' => [
                'inventory.dashboard.view',
                'inventory.products.view',
                'inventory.stock.view',
                'inventory.products.barcodes',
            ],
        ],
        'inventory_auditor' => [
            'name' => 'Inventory Auditor',
            'permissions' => [
                'inventory.dashboard.view',
                'inventory.stock.view',
                'inventory.reports.*',
                'inventory.audit.view',
            ],
        ],
    ],

    'reports' => [
        'current_stock' => 'Current stock report',
        'low_stock' => 'Low stock report',
        'expiring_items' => 'Expiring items report',
        'stock_movement' => 'Stock movement report',
        'inventory_valuation' => 'Inventory valuation report',
        'fast_moving_products' => 'Fast-moving products',
        'slow_moving_products' => 'Slow-moving products',
        'supplier_purchases' => 'Supplier purchase report',
    ],

    'integration_rules' => [
        'sales_issue' => 'Confirmed sales reduce available stock through stock movements.',
        'sales_return' => 'Accepted returns increase stock unless marked damaged or non-restockable.',
        'optical_order_reservation' => 'Optical orders reserve stock before lab fulfilment.',
        'purchase_receiving' => 'Goods receipt increases stock and updates average cost.',
        'stock_adjustment_audit' => 'Every adjustment writes an audit log and stock movement.',
        'valuation_accounting_hook' => 'Inventory valuation snapshots can later post to accounting.',
    ],
];
