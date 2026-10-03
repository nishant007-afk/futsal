# Manager Dashboard, Subscription Billing & Court Editor — UI/UX Spec

Date: 2026-10-03
Status: Approved, in progress

## Non-negotiable rule: NOTHING IS DELETED

Every replacement in this work **comments out** the code it supersedes instead of
removing it. Markers used consistently:

| Kind | Marker |
|---|---|
| PHP / HTML | `<!-- KEPT (removed per request): ... -->` / `// KEPT (removed per request): ...` |
| CSS | `/* KEPT (removed per request): ... */` |
| JS | `// KEPT (removed per request): ...` |

Old code stays in the file, inert, so it can be inspected or restored. Verified by
`php -l` plus a render pass on every touched page for every role.

## Locked decisions

1. Invoice data lives in a **new `subscription_payments` table**.
2. **Admin** records payment channel + transaction reference when confirming
   setup fee or renewal in `admin/settlements.php`.
3. Chart range toggle uses a **JSON AJAX endpoint**.
4. The **Subscription Alert** banner is the target of the accent-bracket fix
   (not the needs-attention strip).

## Already present — do not duplicate

- `.editor-side-pane { position: sticky; top: 24px }` already exists in
  `manager/grounds.php` (~line 939), disabled below the 1180px breakpoint.
  Work here is polish (internal scroll) + confirming the breakpoint.
- `.mb-cta` has no button styling at all; that is the entire cause of the
  "Mark paid renders as plain text" report.
- `includes/receipt_pdf.php` provides a dependency-free `ReceiptPdf` class,
  reused for subscription invoices.

## A. Subscription alert banner

Target: `manager/dashboard.php`. The banner currently reuses the fixed-position
toast component (`.toast.toast-warning.toast-inline`, and `.toast-inline` is
`position: fixed`), so its accent reads as detached.

Replace with a purpose-built inline notice: single card, unified 4px left border
in `--warn`, subtle `--warn-soft` tint, icon + copy preserved.

## B. Booking table actions and badges

Target: `includes/lib/bookings_table.php` (single source for all 5 call sites).

1. Give `.mb-cta` / `.mb-cta-mark` real compact outline-button styling with a
   hover state.
2. Status column carries **only** booking state. The payment flag moves beneath
   the Total as a concise sub-label.

## C. Bar chart usability

Targets: `manager/dashboard.php`, new `manager/dashboard_chart.php`.

- Horizontal reference gridlines + left-hand scale markers.
- Per-bar tooltip: date, bookings, gross revenue. Keyboard accessible.
- 7 / 14 / 30 day toggle fetches JSON and re-renders bars, axis and legend
  totals with no page reload. Endpoint is `require_manager()`-guarded, `days`
  whitelisted. Falls back to normal navigation if the fetch fails.

## D. Subscription & Billing

Targets: `manager/subscription.php`, `admin/settlements.php`, new
`manager/invoice_pdf.php`, `tools/migrate.php`.

### Schema

```sql
CREATE TABLE subscription_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  manager_id INT NOT NULL,
  kind ENUM('setup','renewal') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  channel ENUM('esewa','khalti','bank') NOT NULL,
  txn_ref VARCHAR(80) NOT NULL,
  receipt_no VARCHAR(24) NOT NULL,
  period_start DATE NULL,
  period_end DATE NULL,
  paid_at DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipt (receipt_no),
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
);
```

No backfill is possible: no subscription payment was ever recorded anywhere
(`settlements` table exists but is never written to). History therefore begins
with the first admin confirmation; the UI shows an explicit empty state.

### Changes

- `admin/settlements.php`: both confirmation actions gain a channel select and a
  required transaction reference; on success insert the payment row and flash
  the generated receipt number.
- `manager/subscription.php`: replace the Marketing Perks grid with a Recent
  Invoices table (date, receipt no, type, amount, channel, txn ref, period, PDF).
  Invoice PDF reuses `ReceiptPdf`; the new viewer is manager-scoped.
- Action row: one primary "Extend / Renew Subscription" plus two outlined
  secondaries.
- Platform payment details and venue checkout QR become in-page `<dialog>`
  overlays instead of navigation.

## E. Add New Court

Target: `manager/grounds.php`.

1. Sticky preview: keep existing sticky, add internal scroll so tall previews do
   not overflow; confirm behaviour above the 1180px breakpoint.
2. Tabs become numbered steps with completion checkmarks; Promo Codes is visually
   separated from the step sequence.
3. Media & QR: drag-and-drop image zone with immediate thumbnails and live-card
   cover sync (existing object-URL logic already renders both).

## F. Header notification badge

Target: `assets/css/style.css`. `.bell-dot` is absolutely positioned inside
`.bell-wrap`, which is wider than the 32px button, so the count sits off-axis.
Anchor to `.bell-btn` and centre over the glyph.

## Verification

- `php -l` across the tree after each group.
- Render harness (`php render.php <role> <page>`) for every touched page across
  admin / manager / player, asserting no fatals and no PHP notices.
