# Patient Management & Optical Examination Module Blueprint

This is the third module of the Optical ERP. It is implemented as a Laravel module that connects to Sales/POS through `sales_customers.patient_id` and is designed to connect later to Appointments, Accounting, Inventory, and WhatsApp.

## 1. Database Schema

Core tables:

- `patients`: patient registration, patient code, personal information, phone, WhatsApp, emergency contact, notes, and active status.
- `patient_appointments`: appointment history placeholder until the Appointment module owns scheduling.
- `patient_eye_exams`: full optical exam with OD/OS SPH, CYL, Axis, ADD, PD, VA, diagnosis, notes, recommendation, and next visit date.
- `patient_prescriptions`: draft, signed, and locked prescriptions; printable and linked to source exams.
- `patient_documents`: uploaded documents, scans, prescription files, medical reports, and images.
- `patient_whatsapp_messages`: patient-linked WhatsApp conversation history placeholder.
- `patient_timeline_events`: unified timeline for registration, appointments, exams, prescriptions, orders, invoices, payments, WhatsApp, documents, and notes.

Integration tables used:

- `sales_customers.patient_id`
- `sales_invoices`
- `sales_payments`
- `branches`
- `users`
- `audit_logs`

## 2. Backend APIs

Base path: `/api/v1/patients`

- `GET /meta`: module metadata, roles, permissions, reports, genders, statuses, integration rules.
- `GET /dashboard`: patient metrics, recent patients, and follow-ups.
- `GET /search?q=`: search by name, phone, WhatsApp, or patient code.
- `GET /{patient}`: full patient profile with appointments, exams, prescriptions, documents, timeline, WhatsApp, sales, and payments.
- `GET /{patient}/timeline`: patient timeline.
- `POST /`: create patient and sync to POS customers.
- `POST /{patient}/update`: update patient and sync POS customer.
- `POST /exams`: create eye exam and timeline event.
- `POST /prescriptions`: create prescription.
- `POST /prescriptions/{prescription}/update`: edit draft prescription.
- `POST /prescriptions/{prescription}/lock`: lock signed prescription.
- `POST /documents`: upload or register document.
- `POST /timeline-notes`: add manual timeline note.
- `GET /reports/{report}`: patient reports.

## 3. Frontend Pages

Routes use `/patients/{page}`:

- `dashboard`: patient metrics, patient list, and follow-up summary.
- `patients`: searchable patient list.
- `patient-create`: create/edit patient registration form.
- `profile`: complete patient file with personal info, exams, prescriptions, documents, timeline, sales, and payments.
- `eye-exam`: full OD/OS exam form and exam history.
- `prescription`: prescription form and history.
- `prescription-print`: professional printable prescription view and old/new comparison hint.
- `timeline`: all patient activity and manual notes.
- `documents`: document upload and document list.
- `follow-ups`: next visit and follow-up worklist.
- `reports`: report links.

## 4. Patient Workflow

1. Receptionist registers patient.
2. System generates or stores patient code.
3. Patient is synced into `sales_customers` with `patient_id`.
4. Registration timeline event is written.
5. Patient can be selected in POS through the synced customer record.

## 5. Eye Exam Workflow

1. Optometrist selects patient, branch, and exam date.
2. OD and OS fields are captured: SPH, CYL, Axis, ADD, PD, and VA.
3. Diagnosis, notes, recommendation, and next visit date are recorded.
4. Exam history and follow-up report update automatically.
5. Timeline event is written.

## 6. Prescription Workflow

1. Prescription can be created from an exam or entered manually.
2. Draft prescriptions can be edited.
3. Signed/locked prescriptions are protected from draft editing.
4. Lock action stores `signed_at` and `locked_at`.
5. Print view shows professional prescription layout.
6. Locked prescription can later create an optical order.

## 7. Roles and Permissions

Roles:

- Patient Admin
- Receptionist
- Optometrist
- Clinic Manager

Permission areas:

- Dashboard
- Patient record view/create/update/deactivate
- Eye exams
- Prescriptions and locking
- Prescription print
- Document upload
- Timeline view
- Follow-ups
- Reports
- Settings

## 8. Reports

Implemented report endpoints:

- New patients
- Active patients
- Patient visit history
- Prescription history
- Exams by optometrist
- Follow-up report

## 9. Integration Rules

- Prescription can create optical order later.
- Patient registration syncs to POS customer selection.
- WhatsApp reminders use `whatsapp_number` and timeline events.
- Patient balance will come from accounting later.
- Profile shows linked Sales/POS invoices and payments.

## 10. Implementation Plan

Completed:

1. Patient module config with roles, permissions, statuses, reports, and integration rules.
2. Patient database migration for patients, appointments, exams, prescriptions, documents, WhatsApp history, and timeline.
3. Patient controller with pages, APIs, registration, update, exams, prescriptions, locking, documents, notes, reports, and POS customer sync.
4. Modern responsive UI with top navbar across Sales, Inventory, and Patients.
5. Patient profile showing Sales/POS invoice and payment history.
6. Demo data for patients, exams, prescriptions, documents, WhatsApp messages, appointments, and timeline.
7. Browser and API verification.

Future modules can connect through:

- Appointments: replace `patient_appointments` with full scheduling ownership.
- WhatsApp: send messages and write to `patient_whatsapp_messages`.
- Accounting: expose balances and receivables in patient profile.
- Inventory/Sales: use locked prescription to create optical orders and reserve stock.
