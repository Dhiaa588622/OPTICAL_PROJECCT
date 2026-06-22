<?php

return [
    'module' => [
        'name' => 'Optical Orders & Lab Workflow',
        'version' => '0.1.0',
        'description' => 'Prescription-to-pickup optical order workflow with lab tracking, inventory reservation, POS payments, WhatsApp history, and patient timeline.',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'confirmed' => 'Confirmed',
        'waiting_for_frame' => 'Waiting for frame',
        'waiting_for_lenses' => 'Waiting for lenses',
        'sent_to_lab' => 'Sent to lab',
        'in_lab' => 'In lab',
        'quality_check' => 'Quality check',
        'ready_for_pickup' => 'Ready for pickup',
        'delivered_collected' => 'Delivered / collected',
        'cancelled' => 'Cancelled',
        'remake' => 'Remake',
    ],

    'active_statuses' => [
        'draft',
        'confirmed',
        'waiting_for_frame',
        'waiting_for_lenses',
        'sent_to_lab',
        'in_lab',
        'quality_check',
        'ready_for_pickup',
        'remake',
    ],

    'lab_statuses' => [
        'sent_to_lab',
        'in_lab',
        'quality_check',
    ],

    'priorities' => [
        'normal' => 'Normal',
        'urgent' => 'Urgent',
        'vip' => 'VIP',
        'warranty' => 'Warranty / remake',
    ],

    'lens_types' => [
        'single_vision' => 'Single vision',
        'bifocal' => 'Bifocal',
        'progressive' => 'Progressive',
        'office' => 'Office / occupational',
        'reader' => 'Reader',
    ],

    'lens_materials' => [
        'cr39' => 'CR-39',
        'polycarbonate' => 'Polycarbonate',
        'trivex' => 'Trivex',
        'resin' => 'Resin',
        'glass' => 'Glass',
    ],

    'lens_indexes' => [
        '1.50' => '1.50',
        '1.56' => '1.56',
        '1.59' => '1.59 Polycarbonate',
        '1.60' => '1.60',
        '1.67' => '1.67',
        '1.74' => '1.74',
    ],

    'coatings' => [
        'standard_ar' => 'Standard AR',
        'premium_ar' => 'Premium AR',
        'blue_block' => 'Blue block',
        'scratch_resistant' => 'Scratch resistant',
        'photochromic' => 'Photochromic',
    ],

    'tints' => [
        'clear' => 'Clear',
        'brown' => 'Brown',
        'gray' => 'Gray',
        'green' => 'Green',
        'gradient' => 'Gradient',
        'polarized' => 'Polarized',
    ],

    'document_types' => [
        'order_form' => 'Order form',
        'lab_order' => 'Lab order',
        'prescription_copy' => 'Prescription copy',
        'pdf' => 'Generated PDF',
        'image' => 'Image / scan',
        'other' => 'Other',
    ],

    'whatsapp_triggers' => [
        'order_confirmation' => 'Sent when an order is confirmed.',
        'lab_status_update' => 'Sent when the order moves through lab statuses.',
        'ready_for_pickup' => 'Sent when the order is ready for pickup.',
        'payment_reminder' => 'Sent when a balance remains outstanding.',
    ],

    'permissions' => [
        'optical_orders.dashboard.view',
        'optical_orders.orders.view',
        'optical_orders.orders.create',
        'optical_orders.orders.confirm',
        'optical_orders.orders.update_status',
        'optical_orders.orders.cancel',
        'optical_orders.orders.remake',
        'optical_orders.lab.view',
        'optical_orders.lab.update',
        'optical_orders.pickup.view',
        'optical_orders.pickup.deliver',
        'optical_orders.pickup.allow_unpaid_delivery',
        'optical_orders.payments.collect',
        'optical_orders.inventory.reserve',
        'optical_orders.inventory.release',
        'optical_orders.inventory.issue',
        'optical_orders.documents.view',
        'optical_orders.documents.upload',
        'optical_orders.documents.print',
        'optical_orders.whatsapp.send',
        'optical_orders.reports.view',
        'optical_orders.settings.manage',
    ],

    'roles' => [
        'optical_order_admin' => [
            'name' => 'Optical Order Admin',
            'permissions' => ['optical_orders.*'],
        ],
        'store_manager' => [
            'name' => 'Store Manager',
            'permissions' => [
                'optical_orders.dashboard.view',
                'optical_orders.orders.*',
                'optical_orders.lab.*',
                'optical_orders.pickup.*',
                'optical_orders.payments.*',
                'optical_orders.inventory.*',
                'optical_orders.documents.*',
                'optical_orders.whatsapp.send',
                'optical_orders.reports.view',
            ],
        ],
        'order_desk' => [
            'name' => 'Order Desk',
            'permissions' => [
                'optical_orders.dashboard.view',
                'optical_orders.orders.view',
                'optical_orders.orders.create',
                'optical_orders.orders.confirm',
                'optical_orders.documents.print',
                'optical_orders.pickup.view',
            ],
        ],
        'lab_coordinator' => [
            'name' => 'Lab Coordinator',
            'permissions' => [
                'optical_orders.dashboard.view',
                'optical_orders.orders.view',
                'optical_orders.lab.view',
                'optical_orders.lab.update',
                'optical_orders.documents.view',
                'optical_orders.documents.print',
                'optical_orders.reports.view',
            ],
        ],
        'cashier' => [
            'name' => 'Cashier',
            'permissions' => [
                'optical_orders.pickup.view',
                'optical_orders.payments.collect',
                'optical_orders.documents.print',
            ],
        ],
    ],

    'reports' => [
        'pending_optical_orders' => 'Pending optical orders',
        'orders_by_status' => 'Orders by status',
        'orders_by_lab' => 'Orders by lab',
        'delayed_orders' => 'Delayed orders',
        'remake_orders' => 'Remake orders',
        'ready_for_pickup' => 'Ready-for-pickup orders',
        'orders_by_branch' => 'Orders by branch',
        'orders_by_salesperson' => 'Orders by salesperson',
    ],

    'integration_rules' => [
        'prescription_source' => 'Orders can be created from signed or locked patient prescriptions and store a prescription snapshot.',
        'frame_reservation' => 'Confirmed orders reserve the selected frame by increasing branch reserved stock and creating an inventory reservation.',
        'delivery_stock_issue' => 'Delivery consumes reserved frame stock and issues stocked lenses or accessories from inventory.',
        'cancel_release' => 'Cancelled orders release active inventory reservations when stock was not already issued.',
        'custom_lenses' => 'Custom lenses are tracked as order items without reducing stock until future lab purchase integration is added.',
        'pos_payment' => 'Deposits and balances create Sales/POS payment rows and are linked back to optical order payments.',
        'pos_invoice' => 'Delivery creates a POS invoice with stock already handled by the Optical Orders module.',
        'whatsapp_timeline' => 'WhatsApp notifications are saved to patient messages and timeline history.',
        'accounting_hook' => 'Order totals, invoices, payments, and outstanding balances are stored for later accounting posting.',
    ],
];
