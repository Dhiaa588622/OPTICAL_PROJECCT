<?php

return [
    'module' => [
        'name' => 'Appointments',
        'version' => '0.1.0',
        'description' => 'Patient appointment booking, confirmation, check-in, waiting list, follow-up reminders, and visit tracking for optical stores.',
    ],

    'appointment_types' => [
        'eye_examination' => 'Eye examination',
        'prescription_check' => 'Prescription check',
        'frame_fitting' => 'Frame fitting',
        'lens_pickup' => 'Lens pickup',
        'follow_up' => 'Follow-up',
        'complaint_remake_check' => 'Complaint / remake check',
        'general_consultation' => 'General consultation',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked in',
        'waiting' => 'Waiting',
        'in_examination' => 'In examination',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
        'rescheduled' => 'Rescheduled',
    ],

    'status_colors' => [
        'draft' => '#64748b',
        'scheduled' => '#2563eb',
        'confirmed' => '#0f766e',
        'checked_in' => '#7c3aed',
        'waiting' => '#a16207',
        'in_examination' => '#0891b2',
        'completed' => '#087443',
        'cancelled' => '#b42318',
        'no_show' => '#be123c',
        'rescheduled' => '#475569',
    ],

    'calendar_views' => [
        'day' => 'Daily view',
        'week' => 'Weekly view',
        'month' => 'Monthly view',
        'branch' => 'Branch calendar',
        'doctor' => 'Doctor / optometrist calendar',
    ],

    'reminder_triggers' => [
        'confirmation' => 'Sent when appointment is scheduled or confirmed.',
        'before_appointment' => 'Sent before the appointment time.',
        'reschedule' => 'Sent when the appointment date or time changes.',
        'cancellation' => 'Sent when the appointment is cancelled.',
        'patient_reply_confirm' => 'Saved when a patient replies to confirm.',
        'follow_up_due' => 'Sent for pending or overdue follow-up appointments.',
    ],

    'permissions' => [
        'appointments.dashboard.view',
        'appointments.calendar.view',
        'appointments.booking.create',
        'appointments.booking.update',
        'appointments.booking.reschedule',
        'appointments.booking.cancel',
        'appointments.check_in.create',
        'appointments.waiting_list.view',
        'appointments.exam.start',
        'appointments.exam.complete',
        'appointments.no_show.mark',
        'appointments.followups.view',
        'appointments.whatsapp.send',
        'appointments.reports.view',
        'appointments.settings.manage',
    ],

    'roles' => [
        'appointments_admin' => [
            'name' => 'Appointments Admin',
            'permissions' => ['appointments.*'],
        ],
        'receptionist' => [
            'name' => 'Receptionist',
            'permissions' => [
                'appointments.dashboard.view',
                'appointments.calendar.view',
                'appointments.booking.*',
                'appointments.check_in.create',
                'appointments.waiting_list.view',
                'appointments.no_show.mark',
                'appointments.followups.view',
                'appointments.whatsapp.send',
            ],
        ],
        'optometrist' => [
            'name' => 'Optometrist',
            'permissions' => [
                'appointments.dashboard.view',
                'appointments.calendar.view',
                'appointments.waiting_list.view',
                'appointments.exam.start',
                'appointments.exam.complete',
                'appointments.followups.view',
            ],
        ],
        'clinic_manager' => [
            'name' => 'Clinic Manager',
            'permissions' => [
                'appointments.dashboard.view',
                'appointments.calendar.view',
                'appointments.booking.*',
                'appointments.check_in.create',
                'appointments.waiting_list.view',
                'appointments.exam.*',
                'appointments.no_show.mark',
                'appointments.followups.view',
                'appointments.whatsapp.send',
                'appointments.reports.view',
            ],
        ],
    ],

    'reports' => [
        'appointments_by_day' => 'Appointments by day',
        'appointments_by_branch' => 'Appointments by branch',
        'appointments_by_optometrist' => 'Appointments by optometrist',
        'no_show' => 'No-show report',
        'cancelled' => 'Cancelled appointments report',
        'follow_up' => 'Follow-up report',
        'waiting_time' => 'Waiting time report',
    ],

    'integration_rules' => [
        'patient_link' => 'Every appointment belongs to a patient and appears in the patient profile and timeline.',
        'exam_start' => 'Starting an exam from an appointment creates a visit encounter and can launch the eye examination workflow.',
        'whatsapp_history' => 'Confirmation, reminder, reschedule, cancellation, and reply messages are saved to patient WhatsApp history and timeline.',
        'sales_context' => 'Lens pickup and frame fitting appointments can show linked sales/order context later through patient history.',
        'follow_up' => 'Follow-up appointments can be created manually or from examination recommendations.',
        'audit' => 'Booking, status changes, check-in, reschedule, cancellation, no-show, and exam transitions create audit logs.',
    ],
];
