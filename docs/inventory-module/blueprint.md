# Optical ERP Inventory Module Blueprint

This module is the first module of the larger Optical ERP system. It focuses only on product catalog, optical product details, stock control, purchasing, receiving, supplier returns, stock counts, barcode/batch/serial tracking, inventory reports, and integration hooks.

## 1. Database Schema

Base tables created by `2026_06_14_000100_create_inventory_foundation_tables.php`:

| Table | Inventory purpose |
| --- | --- |
| `companies` | Company scope for products, suppliers, valuation snapshots. |
| `branches` | Store/warehouse branch for stock by location. |
| `suppliers` | Supplier master data. |
| `product_categories` | Product category tree for frames, lenses, contacts, accessories, consumables. |
| `products` | Main catalog: name, SKU, barcode, category, brand, model, color, size, cost price, selling price, supplier, tax type, active status. |
| `audit_logs` | Audit trail for stock adjustments and sensitive inventory changes. |

Inventory detail tables:

| Table | Purpose |
| --- | --- |
| `inventory_frame_details` | Frame size, bridge size, temple length, material, gender/style, rim type, shape. |
| `inventory_lens_details` | Lens type, material, index, coating, tint, prescription range, supplier/lab, custom order flag. |
| `inventory_contact_lens_details` | Power, base curve, diameter, wear schedule, pack size. Lot and expiry are tracked per batch. |
| `inventory_product_barcodes` | Primary and alternate barcodes for barcode scanning. |

Stock control:

| Table | Purpose |
| --- | --- |
| `inventory_locations` | Branch locations such as sales floor, stock room, lab, damaged, transit. |
| `inventory_batches` | Batch/lot tracking, expiry date, received date, unit cost. |
| `inventory_serials` | Serial number tracking for individually tracked products. |
| `inventory_stock_levels` | On hand, reserved, minimum stock, reorder point, average cost by branch/location/batch. |
| `inventory_stock_movements` | Immutable movement history with product, branch, batch, serial, source document, quantity, cost, balance. |
| `inventory_reservations` | Stock reservations for optical orders or other source documents. |

Purchasing and receiving:

| Table | Purpose |
| --- | --- |
| `inventory_purchase_orders` | Supplier purchase order header with approval and totals. |
| `inventory_purchase_order_lines` | Product, ordered quantity, received quantity, cost, tax, expected date. |
| `inventory_goods_receipts` | Goods receipt header against PO or direct supplier receipt. |
| `inventory_goods_receipt_lines` | Received, accepted, rejected quantities, lot, batch, expiry, cost. |
| `inventory_supplier_returns` | Supplier return header. |
| `inventory_supplier_return_lines` | Returned products, batch, quantity, condition, cost. |

Inventory operations:

| Table | Purpose |
| --- | --- |
| `inventory_adjustments` | Stock adjustment header for correction, damaged/lost write-off, or manual change. |
| `inventory_adjustment_lines` | Product-level adjustment variance and cost. |
| `inventory_transfers` | Branch/location transfer header with requested, approved, shipped, received states. |
| `inventory_transfer_lines` | Products and quantities transferred. |
| `inventory_stock_counts` | Cycle count or full stock count header. |
| `inventory_stock_count_lines` | System quantity, counted quantity, variance, review status. |

Valuation:

| Table | Purpose |
| --- | --- |
| `inventory_valuation_snapshots` | Cost and retail value snapshots by company/branch for later accounting integration. |

Important product fields:

- `products.name`
- `products.sku`
- `products.barcode`
- `products.product_category_id`
- `products.brand`
- `products.model`
- `products.color`
- `products.size`
- `products.cost_price`
- `products.retail_price` as selling price
- `products.supplier_id`
- `products.tax_type`
- `products.tax_rate`
- `products.is_active`

## 2. ERD

See `docs/inventory-module/erd.mmd` for the Mermaid source.

## 3. Backend APIs

Base path: `/api/v1/inventory`

System APIs:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/meta` | Product types, tax types, permissions, roles, reports, integration rules. |
| `GET` | `/dashboard` | Inventory KPIs, stock mix, low stock and receiving alerts. |
| `GET` | `/search?q=` | Search product name, SKU, barcode, brand, category with branch/type/status filters. |

Catalog APIs:

| Resource | Endpoints |
| --- | --- |
| Product categories | `GET/POST /product-categories`, `GET/PATCH /product-categories/{id}` |
| Suppliers | `GET/POST /suppliers`, `GET/PATCH /suppliers/{id}` |
| Products | `GET/POST /products`, `GET/PATCH /products/{id}` |
| Frame details | `GET/POST /frame-details`, `GET/PATCH /frame-details/{id}` |
| Lens details | `GET/POST /lens-details`, `GET/PATCH /lens-details/{id}` |
| Contact lens details | `GET/POST /contact-lens-details`, `GET/PATCH /contact-lens-details/{id}` |
| Product barcodes | `GET/POST /product-barcodes`, `GET/PATCH /product-barcodes/{id}` |

Stock APIs:

| Resource | Endpoints |
| --- | --- |
| Locations | `GET/POST /locations`, `GET/PATCH /locations/{id}` |
| Batches | `GET/POST /batches`, `GET/PATCH /batches/{id}` |
| Serials | `GET/POST /serials`, `GET/PATCH /serials/{id}` |
| Stock levels | `GET/POST /stock-levels`, `GET/PATCH /stock-levels/{id}` |
| Stock movements | `GET/POST /stock-movements`, `GET/PATCH /stock-movements/{id}` |
| Reservations | `GET/POST /reservations`, `GET/PATCH /reservations/{id}` |

Purchasing APIs:

| Resource | Endpoints |
| --- | --- |
| Purchase orders | `GET/POST /purchase-orders`, `GET/PATCH /purchase-orders/{id}` |
| Goods receipts | `GET/POST /goods-receipts`, `GET/PATCH /goods-receipts/{id}` |
| Supplier returns | `GET/POST /supplier-returns`, `GET/PATCH /supplier-returns/{id}` |

Operation APIs:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/operations/scan-barcode` | Lookup product, batch, serial, and branch stock. |
| `POST` | `/operations/reserve-stock` | Reserve stock for an optical order. |
| `POST` | `/operations/release-reservation` | Release reserved stock. |
| `POST` | `/operations/issue-sale` | Reduce stock for confirmed sales. |
| `POST` | `/operations/receive-return` | Increase stock for restockable sales returns. |
| `POST` | `/operations/write-off` | Damaged/lost write-off. |
| `POST` | `/operations/post-adjustment` | Approve adjustment and write ledger. |
| `POST` | `/operations/approve-transfer` | Approve transfer. |
| `POST` | `/operations/ship-transfer` | Ship transfer from source branch. |
| `POST` | `/operations/receive-transfer` | Receive transfer at destination branch. |

Report APIs:

| Method | Endpoint |
| --- | --- |
| `GET` | `/reports/current-stock` |
| `GET` | `/reports/low-stock` |
| `GET` | `/reports/expiring-items` |
| `GET` | `/reports/stock-movements` |
| `GET` | `/reports/inventory-valuation` |
| `GET` | `/reports/fast-moving-products` |
| `GET` | `/reports/slow-moving-products` |
| `GET` | `/reports/supplier-purchases` |

## 4. Frontend Pages

Implemented module shell: `resources/views/inventory/dashboard.blade.php`

Routes:

- `/`
- `/inventory/{page?}`

Pages represented in the module shell:

| Page | Main UI elements |
| --- | --- |
| Inventory dashboard | Stock value, active SKUs, low stock, expiring items, quick actions. |
| Product list | Search by product name, SKU, barcode, brand, category, type, branch. |
| Product create/edit form | Product catalog fields, tax type, supplier, cost/selling price. |
| Product details page | Optical type-specific details for frames, lenses, contact lenses. |
| Stock movement page | Movement ledger with quantity, source document, branch, batch. |
| Purchase order page | Supplier PO list, approval state, open quantity, due date. |
| Goods receiving page | PO receiving, partial receiving, accepted/rejected quantities, lot and expiry. |
| Stock transfer page | Source/destination branch, transit status, shipped and received quantities. |
| Stock count page | Cycle count progress, variance review, approval. |
| Low stock alert page | Reorder suggestions by branch and product. |
| Reports page | Current stock, low stock, expiry, movements, valuation, fast/slow movers, supplier purchases. |

UX rules:

- Barcode scanner can type into global search.
- Branch and product type filters stay visible on top.
- Staff workflows are action-first: scan, receive, transfer, count, create product.
- Mobile layout stacks tables into readable cards.
- Product detail fields change by product type.
- Stock rows always show on hand, reserved, and available stock.

## 5. User Roles and Permissions

Config: `config/inventory.php`

Roles:

| Role | Purpose |
| --- | --- |
| Inventory Admin | Full inventory module access. |
| Inventory Manager | Products, stock, purchasing, suppliers, reports, audit. |
| Stock Controller | Stock view, adjustment, transfer, count, write-off, receiving. |
| Purchase Officer | Suppliers, purchase orders, receiving, supplier returns. |
| Store Staff | Product lookup, barcode scanning, stock visibility. |
| Inventory Auditor | Reports, stock history, audit logs, valuation. |

Permission groups:

- `inventory.dashboard.view`
- `inventory.products.*`
- `inventory.stock.*`
- `inventory.purchasing.*`
- `inventory.suppliers.*`
- `inventory.reports.*`
- `inventory.audit.view`
- `inventory.settings.manage`

Sensitive permissions:

- `inventory.stock.adjust`
- `inventory.stock.write_off`
- `inventory.purchasing.approve_po`
- `inventory.purchasing.return_supplier`
- `inventory.reports.valuation`

## 6. Inventory Workflows

Product creation:

1. Create product in `products`.
2. Assign category, product type, supplier, tax type, pricing.
3. Add one optical details row based on type:
   - Frame details for frames and sunglasses.
   - Lens details for stock or custom lenses.
   - Contact lens details for contact lenses.
4. Add primary and alternate barcodes.
5. Set branch stock minimums and reorder points.

Purchase order:

1. Purchase officer creates `inventory_purchase_orders`.
2. Manager approves PO.
3. Lines track ordered, received, and remaining quantities.
4. PO can be partially received.

Goods receiving:

1. Receiver opens PO or creates direct receipt.
2. Staff records accepted and rejected quantities.
3. Batch/lot and expiry are captured for lenses, contact lenses, solutions, and consumables.
4. Accepted quantity increases stock.
5. Stock movement is created with type `purchase_receipt`.
6. Average cost updates.

Supplier return:

1. Staff creates supplier return for rejected, damaged, expired, or incorrect stock.
2. Approved return reduces stock.
3. Movement type is `supplier_return`.

Stock transfer:

1. Source branch creates transfer.
2. Manager approves.
3. Shipping creates `stock_transfer_out` and moves stock to transit.
4. Destination receiving creates `stock_transfer_in`.
5. Variance is recorded if shipped and received quantities differ.

Stock adjustment and write-off:

1. User creates adjustment with reason: correction, damaged, lost, expired.
2. Approval is required for posting.
3. Posting updates stock levels, creates stock movements, and writes audit logs.

Stock count:

1. Create count by branch/location/category.
2. Freeze or snapshot system quantities.
3. Staff scans/counts products.
4. Variances are reviewed.
5. Approved count posts adjustment movements.

Reservation for optical orders:

1. Optical order reserves selected frame/lens/contact product.
2. Reserved quantity increases and available quantity decreases.
3. Fulfilment consumes reservation and reduces on-hand stock.
4. Cancellation releases reservation.

Sales and returns integration:

- Confirmed sales call `/operations/issue-sale`.
- Restockable returns call `/operations/receive-return`.
- Non-restockable returns call `/operations/write-off`.

Valuation integration:

- Inventory valuation snapshots calculate quantity, average cost, and cost value.
- Later accounting can post valuation changes or COGS entries from movement records.

## 7. Reports

Current stock report:

- Product, SKU, barcode, category, brand, branch, location, batch, on hand, reserved, available.

Low stock report:

- Products where available stock is less than reorder point or minimum level.

Expiring items report:

- Contact lenses, cleaning solutions, consumables, and batches with `expires_on` in selected window.

Stock movement report:

- All movements by type, date, branch, product, source document, user, and balance after.

Inventory valuation report:

- Quantity multiplied by average cost by branch/category/product, with retail value comparison.

Fast-moving products:

- Products with highest sales or issue movement count over selected period.

Slow-moving products:

- Products with low or zero movement over selected period.

Supplier purchase report:

- PO totals, received quantities, rejected quantities, supplier returns, and cost by supplier.

## 8. Implementation Plan

Phase 1: Inventory foundation

- Run migrations.
- Seed product types, categories, suppliers, branches, locations.
- Add models and relationships for products, optical detail tables, stock levels, movements, purchases, receiving, transfers, counts.
- Add form requests for product, PO, receipt, transfer, count, adjustment.

Phase 2: Catalog

- Build product list and product form.
- Add optical type-specific field panels.
- Add barcode generation and alternate barcode support.
- Add active/inactive product workflow.

Phase 3: Stock ledger

- Implement stock service that updates stock levels only through stock movements.
- Add available stock calculation: `on_hand - reserved`.
- Add batch, lot, expiry, and serial tracking.
- Add audit logging for adjustments and write-offs.

Phase 4: Purchasing and receiving

- Build supplier CRUD.
- Build PO creation and approval.
- Build partial receiving and goods receipt posting.
- Add supplier return workflow.

Phase 5: Transfers and counts

- Build branch/location transfer workflow.
- Build stock count with barcode scanning and variance approval.
- Add damaged/lost/expired write-off workflow.

Phase 6: Integration hooks

- Expose service methods for sales issue, returns receipt, and optical order reservation.
- Add idempotency keys for external module calls.
- Prepare accounting hook for inventory valuation and COGS.

Phase 7: Reports

- Build current stock, low stock, expiring, movement, valuation, fast-moving, slow-moving, supplier purchase reports.
- Add CSV/XLSX export later.

Phase 8: Hardening

- Add authorization middleware from `config/inventory.php`.
- Add tests for stock calculations, receiving, transfers, reservations, adjustments, and stock count variance posting.
- Add database indexes for search, report filters, and barcode lookup.
