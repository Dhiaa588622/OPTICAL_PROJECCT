# WhatsApp Messaging Module Blueprint

Implemented in `C:\xampp\htdocs\OPTICAL_PROJECCT` as the communication layer for Patient Management, Appointments, Optical Orders, Sales/POS, Accounting, and Reports.

## External API Basis

- Meta states that WhatsApp Cloud API sends messages through Graph API and receives events through Webhooks over HTTPS: https://developers.facebook.com/documentation/business-messaging/whatsapp/about-the-platform
- Meta message sending is represented as POST requests to the business phone number `/messages` endpoint: https://developers.facebook.com/documentation/business-messaging/whatsapp/messages/send-messages
- Meta message webhooks carry inbound user messages and outbound message statuses: https://developers.facebook.com/documentation/business-messaging/whatsapp/webhooks/overview
- Meta templates are WhatsApp Business Account assets used for template messages: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview

## Database Schema

Core tables:

- `whatsapp_conversations`: one thread per patient/customer WhatsApp conversation, with branch, assignee, status, priority, unread count, escalation, and last-message state.
- `patient_whatsapp_messages`: extended existing patient message history into a full log with conversation link, message type, template, automation rule, delivery timestamps, provider ids, retry data, context fields, and metadata.
- `whatsapp_templates`: local and provider template definitions for appointment, order, invoice, payment, follow-up, and service replies.
- `whatsapp_automation_rules`: event-to-template rules with timing, branch scope, opt-in requirement, and status.
- `whatsapp_consent_events`: immutable opt-in/opt-out history by patient/customer number.
- `whatsapp_webhook_events`: idempotency and audit log for incoming messages and delivery-status callbacks.
- `whatsapp_message_attachments`: inbound/outbound media metadata for invoice PDFs, order PDFs, receipts, images, and customer documents.
- `whatsapp_internal_notes`: staff-only conversation notes and escalation notes.
- `whatsapp_settings`: Meta Cloud API settings, sandbox/live mode, token storage, webhook token, content policy, media permission, and retry limit.

Patient table extensions:

- `whatsapp_opt_in`
- `whatsapp_opt_out`
- `whatsapp_consent_at`
- `whatsapp_consent_source`
- `whatsapp_content_policy`

## Backend APIs

Web pages:

- `GET /whatsapp/inbox`
- `GET /whatsapp/conversation`
- `GET /whatsapp/patient-panel`
- `GET /whatsapp/templates`
- `GET /whatsapp/automations`
- `GET /whatsapp/consent`
- `GET /whatsapp/logs`
- `GET /whatsapp/failed`
- `GET /whatsapp/settings`
- `GET /whatsapp/reports`

REST/API:

- `GET /api/v1/whatsapp/meta`
- `GET /api/v1/whatsapp/dashboard`
- `GET /api/v1/whatsapp/conversations`
- `GET /api/v1/whatsapp/conversations/{conversation}`
- `GET /api/v1/whatsapp/logs`
- `GET /api/v1/whatsapp/reports/{report}`
- `POST /api/v1/whatsapp/messages`
- `POST /api/v1/whatsapp/conversations`
- `POST /api/v1/whatsapp/templates`
- `POST /api/v1/whatsapp/automations`
- `POST /api/v1/whatsapp/automation-trigger`
- `POST /api/v1/whatsapp/consent`
- `POST /api/v1/whatsapp/settings`
- `POST /api/v1/whatsapp/retry`

Webhook:

- `GET /webhooks/whatsapp`: verifies Meta webhook challenge.
- `POST /webhooks/whatsapp`: receives inbound messages and delivery/read/failed status events.

## Frontend Pages

- WhatsApp inbox: conversation list, unread badges, status and priority.
- Conversation details: WhatsApp Web-style chat, composer, template picker, delivery statuses.
- Patient-side chat panel: patient details, opt-in state, appointments, optical orders, invoices, balance, timeline context.
- Template manager: create/edit operational templates.
- Automation rules: event triggers and template mapping.
- Opt-in/opt-out page: consent status, source, policy version, and event history.
- Message logs: all inbound/outbound messages with provider ids and statuses.
- Failed messages: retry workflow and error visibility.
- WhatsApp settings: Cloud API credentials, sandbox/live mode, content policy, media, retry limit.
- Reports: links and summaries for message and conversation reporting.

## Cloud API Integration Design

The module runs in `sandbox` mode by default. Sandbox mode stores messages locally, assigns a fake provider message id, and allows staff to test end-to-end workflows without sending real WhatsApp messages.

Live mode posts to:

`{base_url}/{api_version}/{phone_number_id}/messages`

The sender builds:

- Text payloads for manual staff replies.
- Template payloads when a local template has a `provider_template_name`.
- Future media/document payloads through `whatsapp_message_attachments`.

Settings are saved in `whatsapp_settings`. Access tokens are encrypted when submitted through the settings form, and the UI only shows whether credentials are configured.

## Webhook Logic

Verification:

1. Meta calls `GET /webhooks/whatsapp`.
2. The module compares `hub.verify_token` with the saved verify token.
3. If valid, it returns `hub.challenge`.

Incoming message:

1. Store webhook event in `whatsapp_webhook_events` using an idempotent `event_uid`.
2. Resolve or create a patient from the WhatsApp number.
3. Resolve or create an open conversation.
4. Insert an inbound `patient_whatsapp_messages` row.
5. Store media metadata if the message has an image/document payload.
6. Increment unread count and update last-message preview.
7. Add the message to patient timeline.
8. If the body is `STOP`, `UNSUBSCRIBE`, `OPT OUT`, or `OPT-OUT`, record opt-out.

Delivery status:

1. Store the webhook event idempotently.
2. Locate message by provider message id.
3. Update `sent`, `delivered`, `read`, or `failed` status timestamps.
4. Preserve error code/message for failed callbacks.

## Automation Triggers

Supported trigger keys:

- `appointment_created`
- `appointment_tomorrow`
- `appointment_rescheduled`
- `appointment_cancelled`
- `optical_order_confirmed`
- `order_ready_for_pickup`
- `invoice_created`
- `payment_overdue`
- `follow_up_due`

Rules can be immediate, scheduled, before-event, or after-event. Automatic sends require opt-in by default.

## Consent Rules

- Automatic messages are blocked unless the patient is opted in.
- Opt-out blocks manual sends unless a staff member explicitly uses override.
- Opt-in/opt-out events are stored with date, source, policy version, staff member, and notes.
- Sensitive clinical details are blocked by the default `standard` content policy.
- Clinical details can only be sent when the patient policy allows it and the template is configured for it.

## User Roles and Permissions

Roles:

- WhatsApp Admin
- Customer Service
- Receptionist
- Store Manager

Permission areas:

- Inbox and conversation view
- Reply/send
- Assign, resolve, escalate
- Internal notes
- Templates
- Automations
- Consent
- Logs and failed retry
- Settings and webhooks
- Reports

## Reports

- Total messages sent
- Failed messages
- Appointment reminders sent
- Order-ready messages sent
- Response rate
- Conversations by staff
- Unresolved conversations
- Opt-out report

## Integration Rules

- Patient: every linked message appears in the patient profile timeline.
- Appointments: confirmation, reminder, reschedule, cancellation, follow-up triggers are supported.
- Optical Orders: confirmation, sent-to-lab, ready-for-pickup, payment reminder triggers are supported.
- Sales/POS: invoice, receipt, and payment reminder messages are supported by context fields.
- Accounting: payment overdue and balance data are ready for future AR integration.
- Reports: message logs and conversation status feed operational reports.

## Implementation Plan

1. Ship the sandbox inbox, templates, consent, and reporting screens.
2. Add real Meta Cloud API credentials in settings and switch mode to `live`.
3. Configure Meta webhook URL to `/webhooks/whatsapp` and use the saved verify token.
4. Register/approve provider templates matching local template slugs.
5. Connect appointment/order/invoice lifecycle events to `POST /api/v1/whatsapp/automation-trigger`.
6. Add PDF generation endpoints for invoice, receipt, and optical order attachments.
7. Add queue workers for scheduled reminders and failed-message retry batches.
8. Add manager approval controls for opt-out override and sensitive policy changes.
