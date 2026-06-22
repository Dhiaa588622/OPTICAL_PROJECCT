<?php

return [
    'module' => [
        'name' => 'Patient Management & Optical Examination',
        'version' => '0.1.0',
        'description' => 'Patient files, optical exams, prescriptions, documents, follow-ups, and patient timeline.',
    ],

    'genders' => [
        'female' => 'Female',
        'male' => 'Male',
        'other' => 'Other',
        'not_specified' => 'Not specified',
    ],

    'exam_statuses' => [
        'draft' => 'Draft',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'prescription_statuses' => [
        'draft' => 'Draft',
        'signed' => 'Signed',
        'locked' => 'Locked',
    ],

    'document_types' => [
        'id_document' => 'ID document',
        'prescription_file' => 'Prescription file',
        'scan' => 'Scan file',
        'medical_report' => 'Medical report',
        'image' => 'Image',
        'other' => 'Other',
    ],

    'timeline_types' => [
        'registration' => 'Registration',
        'appointment' => 'Appointment',
        'exam' => 'Eye exam',
        'prescription' => 'Prescription',
        'order' => 'Order',
        'invoice' => 'Invoice',
        'payment' => 'Payment',
        'whatsapp' => 'WhatsApp message',
        'document' => 'Document',
        'note' => 'Note',
    ],

    'permissions' => [
        'patients.dashboard.view',
        'patients.records.view',
        'patients.records.create',
        'patients.records.update',
        'patients.records.deactivate',
        'patients.exams.view',
        'patients.exams.create',
        'patients.exams.update',
        'patients.prescriptions.view',
        'patients.prescriptions.create',
        'patients.prescriptions.update_draft',
        'patients.prescriptions.lock',
        'patients.prescriptions.print',
        'patients.documents.view',
        'patients.documents.upload',
        'patients.timeline.view',
        'patients.followups.view',
        'patients.reports.view',
        'patients.settings.manage',
    ],

    'roles' => [
        'patient_admin' => [
            'name' => 'Patient Admin',
            'permissions' => ['patients.*'],
        ],
        'receptionist' => [
            'name' => 'Receptionist',
            'permissions' => [
                'patients.dashboard.view',
                'patients.records.*',
                'patients.documents.view',
                'patients.documents.upload',
                'patients.timeline.view',
                'patients.followups.view',
                'patients.reports.view',
            ],
        ],
        'optometrist' => [
            'name' => 'Optometrist',
            'permissions' => [
                'patients.dashboard.view',
                'patients.records.view',
                'patients.exams.*',
                'patients.prescriptions.*',
                'patients.documents.view',
                'patients.timeline.view',
                'patients.followups.view',
                'patients.reports.view',
            ],
        ],
        'clinic_manager' => [
            'name' => 'Clinic Manager',
            'permissions' => [
                'patients.dashboard.view',
                'patients.records.*',
                'patients.exams.*',
                'patients.prescriptions.*',
                'patients.documents.*',
                'patients.timeline.view',
                'patients.followups.view',
                'patients.reports.*',
            ],
        ],
    ],

    'reports' => [
        'new_patients' => 'New patients report',
        'active_patients' => 'Active patients report',
        'patient_visit_history' => 'Patient visit history',
        'prescription_history' => 'Prescription history report',
        'exams_by_optometrist' => 'Exams by optometrist',
        'follow_up' => 'Follow-up report',
    ],

    'integration_rules' => [
        'prescription_to_order' => 'Locked prescriptions can create optical orders later.',
        'patient_to_pos' => 'Registered patients are synced to POS customers and can be selected at checkout.',
        'whatsapp_reminders' => 'WhatsApp reminders use patient WhatsApp number and timeline events.',
        'accounting_balance' => 'Patient balances will be read from accounting and receivables later.',
        'sales_history' => 'Patient profile shows linked sales, invoices, and payment history from POS.',
    ],
];
