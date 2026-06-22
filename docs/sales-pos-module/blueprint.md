# Sales & POS Module Blueprint

This is the second module of the Optical ERP. It is implemented as a Laravel module that connects directly to the Inventory Module through products, stock levels, reservations, and stock movements.

## 1. Database Schema

Core tables:

- `sales_customers`: lightweight customer/patient placeholder until the Patient module is added.
- `sales_cash_sessions`: cashier drawer opening, closing, expected cash, actual cash, payment totals, and differences.
- `sales_quotations` and `sales_quotation_items`: quote documents that do not affect stock.
- `sales_orders` and `sales_order_items`: prescription orders, frame + lens packages, deposits, pickup balance, and optional stock reservations.
- `sales_invoices` and `sales_invoice_items`: posted sales documents that reduce inventory and store tax, discounts, and gross profit.
- `sales_payments`: cash, card, bank transfer, mobile wallet, payment link, credit note, split payments, and refunds.
- `sales_receipts`: printable payment/invoice receipt records.
- `sales_returns` and `sales_return_items`: full or selected-item returns with restock, damaged, or write-off behavior.
- `sales_credit_notes` and `sales_credit_note_items`: customer credit created from returns.
- `sales_discount_approvals`: manager approval trail for large discounts.
- `sales_promotions`: promotion support for future pricing rules.

Inventory integration tables used by this module:

- `products`
- `inventory_stock_levels`
- `inventory_stock_movements`
- `inventory_reservations`
- `inventory_locations`

## 2. Backend APIs

Base path: `/api/v1/sales-pos`

- `GET /meta`: module metadata, document types, payment methods, permissions, roles, reports, integration rules, and keyboard shortcuts.
- `GET /dashboard`: daily revenue, invoices, payment mix, recent invoices, and inventory connection flags.
- `GET /products`: searchable POS product catalog by product name, SKU, barcode, brand, and category.
- `GET /invoices`: invoice documents.
- `GET /quotations`: quotation documents.
- `GET /sales-orders`: sales orders.
- `GET /payments`: payment history.
- `GET /returns`: returns.
- `GET /cash-sessions`: cashier sessions.
- `POST /checkout`: post invoice, reduce stock, create payments, create receipts.
- `POST /quotations`: create quotation without stock movement.
- `POST /orders`: create sales order and optionally reserve inventory.
- `POST /returns`: post return, increase restockable inventory, and create credit note.
- `POST /cashier-closing`: close drawer and calculate cash difference.
- `GET /reports/{report}`: sales reports.

## 3. Frontend Pages

Routes use `/sales/{page}`:

- `dashboard`: daily revenue, pending balances, pending orders, open quotations, low stock, recent invoices, report links.
- `pos`: fast checkout with product cards, barcode/search flow, cart, discounts, tax, split payment, customer, cashier, branch, and keyboard shortcuts.
- `invoices`: invoice list with print links.
- `quotations`: quotation create form and quotation list.
- `sales-orders`: order create form with deposit and reserve-stock option.
- `returns`: selected item return/exchange form and return history.
- `payments`: split payments and refunds.
- `cashier-closing`: open drawer totals, expected cash, actual cash, difference, and close action.
- `print`: professional invoice/receipt print view.

The Inventory Module was also updated to use a top navbar instead of a left sidebar, so Sales and Inventory feel like one app.

## 4. Sales Workflows

### POS Checkout

1. Search or scan product by name, SKU, barcode, brand, or category.
2. Add products to cart.
3. Adjust quantity, unit price, discount type, and discount value.
4. Select customer/patient, cashier, branch, and sale mode.
5. Enter one or more payment methods.
6. Post invoice.
7. System creates invoice items, payments, receipts, audit log, and `sale_issue` stock movements.

### Quotation

1. Create quote lines and pricing.
2. Save quotation as sent.
3. No inventory reservation or stock reduction happens.

### Sales Order

1. Create prescription order or package order.
2. Add deposit and pickup due date.
3. Optionally reserve stock.
4. System increases `qty_reserved`, creates `inventory_reservations`, and logs reservation movement.

### Return / Exchange

1. Select original invoice and invoice line.
2. Choose quantity and restock action.
3. Post return.
4. Restockable items increase stock with `sale_return` movement.
5. Credit note is created.
6. Refund payment can be posted by selected method.

## 5. Inventory Integration Rules

- Quotation: no stock effect.
- Sales order with reservation: increases reserved stock, does not reduce on-hand stock.
- Invoice: reduces on-hand stock and records `sale_issue`.
- Return with restock: increases on-hand stock and records `sale_return`.
- Return damaged/write-off: records the return document but does not add sellable stock.
- Unavailable stock is blocked unless override is explicitly allowed.
- Gross profit and line costs are stored for future accounting and valuation posting.

## 6. Cashier Closing Logic

Expected cash:

```text
expected_cash = opening_cash + cash_sales - cash_refunds
```

Closing difference:

```text
difference = actual_cash - expected_cash
```

The close action recalculates cash, card, bank transfer, wallet, payment link, refund totals, expected cash, actual cash, and difference before marking the session closed.

## 7. Roles and Permissions

Roles:

- Sales Admin
- Store Manager
- Cashier
- Sales Associate
- Returns Clerk

Permission areas:

- Dashboard access
- POS checkout
- Stock override
- Large discount approval
- Quotations
- Sales orders
- Invoices and print
- Payments
- Returns and refunds
- Cashier open/close
- Reports
- Settings

## 8. Reports

Implemented report endpoints:

- Daily sales
- Sales by cashier
- Sales by branch
- Sales by product
- Sales by category
- Sales by brand
- Gross profit
- Returns
- Payment methods

Planned report extension:

- Promotion performance
- Pickup balance aging
- Credit note aging
- Sales tax report

## 9. Implementation Plan

Completed in this build:

1. Sales/POS config, roles, permissions, reports, payment methods, and integration rules.
2. Sales/POS migration with documents, payments, returns, credit notes, cashier sessions, approvals, and promotions.
3. Database-backed controller for checkout, quotes, orders, returns, cashier closing, APIs, and reports.
4. POS frontend with top navbar, product catalog, cart, totals, split payments, shortcuts, and responsive layout.
5. Invoice, quotation, order, return, payment, closing, dashboard, and print screens.
6. Inventory top navbar update.
7. Demo seeder with realistic customers, invoices, split payments, reserved order, return, credit note, open drawer, and closed drawer.

Next modules can connect through:

- Patient module: link `sales_customers.patient_id` and prescription/order details.
- Accounting module: consume invoice totals, tax, payments, refunds, credit notes, and inventory costs.
- WhatsApp module: use customer phone and invoice/order events for reminders and receipts.
