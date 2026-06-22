<?php

return [
    'module' => [
        'name' => 'WhatsApp Messaging',
        'version' => '0.1.0',
        'description' => 'Two-way WhatsApp inbox, patient timeline messaging, automation, consent, Cloud API webhooks, media, and reporting.',
    ],

    'provider' => [
        'name' => 'Meta WhatsApp Cloud API',
        'base_url' => env('WHATSAPP_CLOUD_BASE_URL', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'change-me'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'mode' => env('WHATSAPP_MODE', 'sandbox'),
    ],

    'conversation_statuses' => [
        'open' => 'Open',
        'pending' => 'Pending',
        'resolved' => 'Resolved',
    ],

    'conversation_priorities' => [
        'normal' => 'Normal',
        'urgent' => 'Urgent',
        'vip' => 'VIP',
    ],

    'message_statuses' => [
        'queued' => 'Queued',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
        'replied' => 'Replied',
    ],

    'message_types' => [
        'text' => 'Text',
        'template' => 'Template',
        'image' => 'Image',
        'document' => 'Document',
        'audio' => 'Audio',
        'video' => 'Video',
        'internal_note' => 'Internal note',
    ],

    'template_categories' => [
        'utility' => 'Utility',
        'service' => 'Customer service',
        'marketing' => 'Marketing',
        'authentication' => 'Authentication',
    ],

    'template_keys' => [
        'appointment_confirmation' => 'Appointment confirmation',
        'appointment_reminder' => 'Appointment reminder',
        'appointment_reschedule' => 'Appointment reschedule',
        'appointment_cancellation' => 'Appointment cancellation',
        'optical_order_confirmation' => 'Optical order confirmation',
        'order_sent_to_lab' => 'Order sent to lab',
        'order_ready_for_pickup' => 'Order ready for pickup',
        'payment_reminder' => 'Payment reminder',
        'invoice_message' => 'Invoice message',
        'follow_up_message' => 'Follow-up message',
        'customer_service_reply' => 'General customer service reply',
    ],

    'automation_triggers' => [
        'appointment_created' => 'Appointment is created',
        'appointment_tomorrow' => 'Appointment is tomorrow',
        'appointment_rescheduled' => 'Appointment is rescheduled',
        'appointment_cancelled' => 'Appointment is cancelled',
        'optical_order_confirmed' => 'Optical order is confirmed',
        'order_ready_for_pickup' => 'Order becomes ready for pickup',
        'invoice_created' => 'Invoice is created',
        'payment_overdue' => 'Payment is overdue',
        'follow_up_due' => 'Follow-up date is due',
    ],

    'content_policy' => [
        'default' => 'standard',
        'policies' => [
            'standard' => [
                'name' => 'Standard reminders',
                'description' => 'Send appointment, order, invoice, and payment context without clinical prescription details.',
                'allow_sensitive_clinical_details' => false,
                'allow_media' => true,
            ],
            'minimal' => [
                'name' => 'Minimal content',
                'description' => 'Use brief operational reminders only.',
                'allow_sensitive_clinical_details' => false,
                'allow_media' => false,
            ],
            'clinical_allowed' => [
                'name' => 'Clinical details allowed',
                'description' => 'Allows approved clinical details only when the patient consent record permits it.',
                'allow_sensitive_clinical_details' => true,
                'allow_media' => true,
            ],
        ],
    ],

    'permissions' => [
        'whatsapp.inbox.view',
        'whatsapp.conversations.view',
        'whatsapp.conversations.reply',
        'whatsapp.conversations.assign',
        'whatsapp.conversations.resolve',
        'whatsapp.conversations.escalate',
        'whatsapp.internal_notes.create',
        'whatsapp.templates.view',
        'whatsapp.templates.manage',
        'whatsapp.automation.view',
        'whatsapp.automation.manage',
        'whatsapp.consent.view',
        'whatsapp.consent.manage',
        'whatsapp.logs.view',
        'whatsapp.failed.retry',
        'whatsapp.settings.manage',
        'whatsapp.webhooks.manage',
        'whatsapp.reports.view',
    ],

    'roles' => [
        'whatsapp_admin' => [
            'name' => 'WhatsApp Admin',
            'permissions' => ['whatsapp.*'],
        ],
        'customer_service' => [
            'name' => 'Customer Service',
            'permissions' => [
                'whatsapp.inbox.view',
                'whatsapp.conversations.view',
                'whatsapp.conversations.reply',
                'whatsapp.conversations.assign',
                'whatsapp.internal_notes.create',
                'whatsapp.consent.view',
            ],
        ],
        'receptionist' => [
            'name' => 'Receptionist',
            'permissions' => [
                'whatsapp.inbox.view',
                'whatsapp.conversations.view',
                'whatsapp.conversations.reply',
                'whatsapp.consent.manage',
                'whatsapp.logs.view',
            ],
        ],
        'store_manager' => [
            'name' => 'Store Manager',
            'permissions' => [
                'whatsapp.inbox.view',
                'whatsapp.conversations.*',
                'whatsapp.internal_notes.create',
                'whatsapp.templates.view',
                'whatsapp.automation.view',
                'whatsapp.consent.*',
                'whatsapp.logs.view',
                'whatsapp.failed.retry',
                'whatsapp.reports.view',
            ],
        ],
    ],

    'reports' => [
        'total_messages_sent' => 'Total messages sent',
        'failed_messages' => 'Failed messages',
        'appointment_reminders_sent' => 'Appointment reminders sent',
        'order_ready_messages_sent' => 'Order-ready messages sent',
        'response_rate' => 'Response rate',
        'conversations_by_staff' => 'Conversations by staff',
        'unresolved_conversations' => 'Unresolved conversations',
        'opt_out' => 'Opt-out report',
    ],

    'integration_rules' => [
        'patient_profile' => 'Every linked conversation appears beside patient details and inside the patient timeline.',
        'appointments' => 'Appointment booking, reminder, reschedule, cancellation, and follow-up events can queue templates.',
        'optical_orders' => 'Order confirmation, lab status, ready-for-pickup, and payment reminders can queue templates.',
        'sales' => 'Invoices, receipts, payment links, and overdue balances can be sent from Sales/POS context.',
        'accounting' => 'Payment reminder and customer balance content is designed for future AR integration.',
        'consent' => 'Automatic messages require opt-in and stop when the patient opts out.',
        'clinical_privacy' => 'Sensitive clinical details are blocked by default unless an approved policy allows them.',
        'webhooks' => 'Incoming messages and delivery statuses are idempotently processed and linked to conversations.',
    ],
];
