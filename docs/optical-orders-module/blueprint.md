# Optical Orders / Lab Workflow Module

This module is installed as the fourth module of the Optical ERP. It connects Patient Management, Prescriptions, Inventory, Sales/POS, WhatsApp history, and later Accounting.

## Database Schema

Core tables:

- `optical_orders`: order header, patient, prescription, branch, lab, status, priority, lens selections, expected delivery, totals, payment state, inventory flags, and POS invoice link.
- `optical_order_items`: frame, stocked lens, custom lens, accessory, reserved quantity, issued quantity, tax, cost, and metadata snapshots.
- `optical_order_status_events`: full lab and pickup status history.
- `optical_order_payments`: deposits and balance payments linked to `sales_payments`.
- `optical_order_documents`: order form, lab order, prescription copy, PDFs, images, and uploaded files.
- `optical_order_lab_incidents`: remake, damage, lost item, and QC incident tracking.

Integration tables used:

- `patients`, `patient_prescriptions`, `patient_timeline_events`, `patient_whatsapp_messages`
- `products`, `inventory_stock_levels`, `inventory_reservations`, `inventory_stock_movements`
- `sales_customers`, `sales_payments`, `sales_invoices`, `sales_invoice_items`
- `audit_logs`, `branches`, `suppliers`, `users`

## Status Model

Supported statuses:

- Draft
- Confirmed
- Waiting for frame
- Waiting for lenses
- Sent to lab
- In lab
- Quality check
- Ready for pickup
- Delivered / collected
- Cancelled
- Remake

## Backend APIs

Base prefix: `/api/v1/optical-orders`

- `GET /meta`: statuses, roles, permissions, reports, lens options, WhatsApp triggers, integration rules.
- `GET /dashboard`: dashboard metrics, lab board, delayed orders, ready orders.
- `GET /search`: order search by order number, patient, phone, WhatsApp, SKU, barcode.
- `GET /{order}`: full order details, items, status events, payments, documents, incidents.
- `POST /`: create optical order from patient and prescription.
- `POST /status`: move order through lab and pickup workflow.
- `POST /payments`: collect deposit or balance and post to Sales/POS payments.
- `POST /documents`: upload order documents.
- `GET /reports/{report}`: pending orders, by status, by lab, delayed, remake, ready pickup, by branch, by salesperson.

## Frontend Pages

Web route: `/optical-orders/{page?}`

- Dashboard
- Create optical order
- Order details
- Lab workflow board
- Ready-for-pickup queue
- Remake / cancel
- Order documents
- Status timeline
- Reports
- Print documents

## Workflow Logic

1. Order desk selects patient and signed or locked prescription.
2. Staff selects frame, lens product or custom lab lens, accessory, lab, priority, notes, due date, and deposit.
3. Confirmed orders reserve selected frame inventory.
4. Lab board moves order through waiting, sent, in lab, QC, and ready statuses.
5. Ready-for-pickup sends a WhatsApp message and payment reminder when a balance remains.
6. Balance payment posts to Sales/POS payments and updates optical order balance.
7. Delivery is blocked when balance remains unless manager override is selected.
8. Delivery consumes inventory stock and creates a Sales/POS invoice without double-posting stock.
9. Cancelled orders release active reservations when stock was not already issued.
10. Remakes create lab incident records and timeline events.

## Inventory Integration Rules

- Confirmed frame selection creates `inventory_reservations` with source `optical_order`.
- Confirmed frame selection increases `inventory_stock_levels.qty_reserved`.
- Delivery reduces `qty_on_hand` for frame, stocked lenses, and accessories.
- Delivery consumes active reservations and clears reserved quantity.
- Cancellation releases active reservations and creates a `reservation_release` movement.
- Custom lenses are tracked as custom order items without reducing stock.
- Stock movements use `reference_type = optical_order` for traceability.

## POS / Payment Integration Rules

- Deposits and balances insert `sales_payments`.
- Optical payment rows store the `sales_payment_id` link.
- Delivery creates a `sales_invoices` header and `sales_invoice_items`.
- Sales invoice items have `stock_quantity = 0` because Optical Orders already handled stock.
- Existing optical payments are linked to the delivery invoice.
- Outstanding amount is stored for later Accounts Receivable integration.

## WhatsApp Triggers

- `order_confirmation`: sent when order is confirmed.
- `lab_status_update`: sent when moving through sent, in lab, or QC.
- `ready_for_pickup`: sent when order becomes ready.
- `payment_reminder`: sent when ready order still has outstanding balance.

Messages are stored in `patient_whatsapp_messages` and mirrored into `patient_timeline_events`.

## User Roles and Permissions

Roles:

- Optical Order Admin
- Store Manager
- Order Desk
- Lab Coordinator
- Cashier

Permission areas:

- Dashboard
- Order create/view/confirm/status/cancel/remake
- Lab view/update
- Pickup delivery and unpaid override
- Payment collection
- Inventory reserve/release/issue
- Documents upload/print
- WhatsApp send
- Reports
- Settings

## Reports

- Pending optical orders
- Orders by status
- Orders by lab
- Delayed orders
- Remake orders
- Ready-for-pickup orders
- Orders by branch
- Orders by salesperson

## Implementation Plan

Completed:

- Schema migration for optical order workflow.
- Config-driven statuses, roles, permissions, reports, WhatsApp triggers, lens options, and integration rules.
- Controller actions for order creation, status update, payment collection, document upload, APIs, dashboard, and reports.
- Modern responsive Blade UI with module navbar.
- Demo seeder with realistic orders, reservations, documents, payments, WhatsApp, and lab incidents.
- ERD in Mermaid format.

Next recommended modules:

- Accounting posting from optical orders and POS invoice events.
- Real WhatsApp provider adapter.
- Appointment-driven follow-up reminders.
- Purchase order automation for custom lens lab charges.
