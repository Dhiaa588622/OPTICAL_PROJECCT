# Appointments Module

This module is installed as part of the Optical ERP and connects Patient Management, Optical Examination, WhatsApp history, Sales context, and Reports.

## Database Schema

The module extends `patient_appointments` and adds:

- `appointment_type`
- `duration_minutes`
- `appointment_end_at`
- `visit_reason`
- check-in, waiting, exam, completed, cancelled, no-show, and reschedule timestamps
- cancellation and reschedule reasons
- WhatsApp confirmation/reminder timestamps
- patient reply confirmation fields
- `rescheduled_from_id`
- `created_by`

New tables:

- `appointment_visits`: check-in encounters, waiting list, exam start, completion, waiting minutes.
- `appointment_status_events`: status and history tracking.
- `appointment_reminders`: WhatsApp confirmation, reminders, reschedule, cancellation, and follow-up reminders.

Shared integration tables:

- `patients`
- `patient_eye_exams`
- `patient_timeline_events`
- `patient_whatsapp_messages`
- `sales_invoices` through patient sales history
- `audit_logs`
- `branches`
- `users`

## Backend APIs

Base prefix: `/api/v1/appointments`

- `GET /meta`: module config, appointment types, statuses, colors, roles, permissions, reports.
- `GET /dashboard`: daily metrics, today appointments, waiting list, follow-ups.
- `GET /search`: search by appointment number, patient code, name, phone, WhatsApp.
- `GET /calendar`: day/week/month calendar events with status colors and drag-drop API target.
- `GET /{appointment}`: appointment details, visits, status history, reminders, patient context, recent exams and sales.
- `POST /`: create appointment and send confirmation.
- `POST /status`: update status, check-in, waiting, start examination, complete, no-show.
- `POST /reschedule`: create a replacement appointment and mark old one rescheduled.
- `POST /cancel`: cancel appointment with reason.
- `POST /reminders`: send WhatsApp reminder.
- `POST /patient-reply`: save patient WhatsApp reply and confirm appointment when reply is positive.
- `GET /reports/{report}`: appointments by day, branch, optometrist, no-show, cancelled, follow-up, waiting time.

## Frontend Pages

Web route: `/appointments/{page?}`

- Appointment dashboard
- Appointment calendar
- Create appointment
- Appointment details
- Waiting list
- Follow-up list
- No-show list
- Reschedule / cancel
- Reports

## Appointment Workflow

1. Reception selects patient, branch, optometrist, date/time, appointment type, duration, reason, and notes.
2. System creates appointment, status event, reminder row, patient timeline event, and WhatsApp confirmation.
3. Patient can reply by WhatsApp to confirm.
4. On arrival, receptionist marks checked-in.
5. Check-in creates an `appointment_visits` encounter and starts waiting time.
6. Optometrist sees patient in waiting list and starts examination.
7. Starting examination stamps `exam_started_at` and waiting minutes.
8. Completion closes the visit and appointment.
9. No-show, cancellation, and reschedule are tracked with history and audit logs.

## WhatsApp Reminder Triggers

- `confirmation`
- `before_appointment`
- `reschedule`
- `cancellation`
- `patient_reply_confirm`
- `follow_up_due`

All outbound and inbound messages are saved in `patient_whatsapp_messages` and mirrored into `patient_timeline_events`.

## Patient Integration Rules

- Every appointment belongs to a patient.
- Appointments appear in patient profile history.
- Timeline records are written for booking, check-in, status changes, reschedule, cancellation, reminders, and replies.
- Patient search supports name, phone, WhatsApp, and patient code.

## Exam Integration Rules

- Check-in creates a visit/encounter.
- Starting examination moves appointment to `in_examination`.
- The details page links directly to the patient eye exam form.
- Waiting time is calculated from check-in/waiting start to exam start.

## User Roles and Permissions

Roles:

- Appointments Admin
- Receptionist
- Optometrist
- Clinic Manager

Permission areas:

- Dashboard
- Calendar
- Booking create/update/reschedule/cancel
- Check-in
- Waiting list
- Exam start/complete
- No-show marking
- Follow-up management
- WhatsApp sending
- Reports
- Settings

## Reports

- Appointments by day
- Appointments by branch
- Appointments by optometrist
- No-show report
- Cancelled appointments report
- Follow-up report
- Waiting time report

## Implementation Plan

Completed:

- Config-driven appointment types, statuses, colors, roles, permissions, reports, and integration rules.
- Schema extension for `patient_appointments`.
- New visit, reminder, and status history tables.
- Controller for booking, calendar data, check-in, waiting list, exam start, completion, no-show, reschedule, cancellation, reminders, replies, and reports.
- Responsive SaaS-style UI with drag-and-drop calendar support.
- Demo seeder with realistic appointments.
- Documentation and Mermaid ERD.

Recommended next steps:

- Real WhatsApp provider webhook for inbound replies.
- Dedicated drag-and-drop AJAX reschedule without form submission.
- Direct eye exam creation linked to `appointment_visits`.
- Appointment capacity rules by branch and optometrist schedule.
