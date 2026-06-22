# Optical ERP Finalization Checklist

## Updated Database Schema

- `accounting_accounts`: default chart of accounts for cash, bank, receivables, inventory, payables, VAT, revenue, COGS, expenses, and adjustments.
- `accounting_journals`: balanced journal headers linked to source documents such as sales invoices, payments, and goods receipts.
- `accounting_journal_lines`: debit and credit journal details with party and source references.
- `accounting_daily_closings`: daily cashier closing summary per branch.
- `accounting_integration_mappings`: future-proof mapping between ERP events and accounting accounts.

## Updated APIs

- `GET /api/v1/erp/dashboard`: live dashboard metrics, worklists, accounting health, and readiness checks.
- `GET /api/v1/erp/search?q=`: global search across patients, products, orders, invoices, appointments, and WhatsApp.
- `GET /api/v1/erp/workflows`: integrated workflow completion health.
- `GET /api/v1/erp/reports/{report}`: live report data.
- `GET /erp/reports/{report}/export`: CSV export for Excel.
- `GET /erp/reports/{report}/export?format=print`: print-ready report view for PDF output.

## Updated Frontend Pages

- `/`: unified ERP dashboard with simple sidebar navigation.
- `/erp/search`: global search page.
- `/erp/workflows`: integrated workflow health page.
- `/erp/accounting`: accounting summary surface.
- `/erp/reports`: report export surface.
- `/erp/settings`: readiness and default settings surface.

Existing modules remain available:

- `/patients/dashboard`
- `/appointments/dashboard`
- `/optical-orders/dashboard`
- `/sales/pos`
- `/inventory/dashboard`
- `/whatsapp/inbox`

## Complete Integrated Workflows

1. Patient to order: Patient -> Appointment -> Eye Exam -> Prescription -> Optical Order -> WhatsApp Confirmation.
2. Order to invoice: Optical Order -> Stock Reservation -> Deposit -> Lab Status -> Ready for Pickup -> Final Payment -> Invoice.
3. Purchase to accounting: Purchase Order -> Goods Receipt -> Stock Increase -> Inventory Report -> Accounting Entry.
4. POS to accounting: POS Sale -> Payment -> Invoice -> Stock Reduction -> Accounting Entry.
5. Invoice to reminder: Invoice Created -> WhatsApp Message -> Payment Reminder -> Patient Balance Update.

## WhatsApp Working Integration

- Meta WhatsApp Cloud API settings are stored in `whatsapp_settings`.
- Webhook verification is available at `GET /webhooks/whatsapp`.
- Incoming messages and status webhooks are handled at `POST /webhooks/whatsapp`.
- Consent is stored on patient records and enforced before automated messages.
- Failed messages can be retried from the WhatsApp module.
- Conversations and messages link back to patients and timeline history.

## Seed Data

- Demo company and branch.
- Demo optical products and stock.
- Demo patients, appointments, exams, prescriptions, orders, invoices, and payments.
- Default roles and permissions.
- Default chart of accounts.
- Default WhatsApp templates and automation rules.
- Initial balanced journal entries from existing business documents.

## Testing Checklist

- Open `/` and confirm dashboard metrics load.
- Search by patient name, phone, SKU, barcode, order number, and invoice number.
- Open every sidebar item and confirm no broken links.
- Create patient, appointment, prescription, optical order, POS invoice, goods receipt, and WhatsApp message.
- Confirm POS invoice reduces stock.
- Confirm sales return increases restockable stock.
- Confirm optical order reserves stock.
- Confirm goods receipt increases stock.
- Confirm WhatsApp opt-out blocks automated sending.
- Confirm report CSV downloads open in Excel.
- Confirm print report opens and can be saved as PDF.
- Confirm accounting journals have equal debit and credit totals.
- Confirm default user has ERP admin permissions.

## Deployment Checklist

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Configure database backups.
- Configure `APP_URL`.
- Configure queue worker for reminder and retry jobs when background jobs are added.
- Configure Meta WhatsApp Cloud API token, phone number ID, business account ID, and webhook verify token.
- Point Meta webhook callback to `/webhooks/whatsapp`.
- Run `php artisan migrate --seed`.
- Run `php artisan config:cache`.
- Keep `php artisan route:clear` for the current demo build, or convert the remaining closure-based demo inventory API routes before using `php artisan route:cache`.
- Validate file storage permissions for document and media uploads.
- Validate SSL certificate before enabling live WhatsApp webhooks.
