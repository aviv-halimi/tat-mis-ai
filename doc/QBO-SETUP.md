# QuickBooks Online (QBO) integration

## Overview

- **Push to QuickBooks**: When a PO is in status 5 (Validated), a "Push to QuickBooks" button appears on the PO page. It creates a Bill in the store’s QBO company using:
  - **Vendor**: Mapped from the PO’s vendor (see vendor mapping).
  - **Line 1**: Order subtotal (`po.r_subtotal`) → GL account **14-100 Cannabis Products**.
  - **Line 2**: Total receiving discounts (from `po_discount` where `is_receiving = 1`) → GL account **40-102 Monthly Rebates** (as a credit).

- **Vendor mapping**: Each location has its own DB (e.g. `blaze1`, `blaze2`) and its own QBO company. Vendors in each store DB can be mapped to a QBO Vendor via the **Vendor → QBO mapping** page and/or when pushing a bill (modal if not mapped).

## 1. Database

### Vendor table (per store DB)

Add a column to store the QBO Vendor Id on each store’s `vendor` table (run on **each** store DB, e.g. `blaze1`, `blaze2`, …):

```sql
-- See sql/vendor_qbo_id.sql
ALTER TABLE vendor ADD COLUMN QBO_ID VARCHAR(32) NULL DEFAULT NULL COMMENT 'QuickBooks Online Vendor Id';
```

### Store table (theartisttree)

Each store that uses QBO needs credentials in `store.params` (JSON). Example:

```json
{
  "qbo_realm_id": "1234567890",
  "qbo_refresh_token": "AB115987...",
  "qbo_account_id_products": "45",
  "qbo_account_id_rebates": "67"
}
```

- **qbo_realm_id**: QBO Company ID (realm id from OAuth).
- **qbo_refresh_token**: OAuth2 refresh token for that company.
- **qbo_account_id_products**: QBO Account Id for **14-100 Cannabis Products**.
- **qbo_account_id_rebates**: QBO Account Id for **40-102 Monthly Rebates**.

You can get Account Ids from QBO (Account list) or the API.

## 2. Environment / config

Set these (e.g. in `.env` or `_config.php`) so the app can refresh the access token:

- **QBO_CLIENT_ID**: Intuit app Client ID.
- **QBO_CLIENT_SECRET**: Intuit app Client Secret.

Defining in PHP:

```php
define('QBO_CLIENT_ID', 'your_client_id');
define('QBO_CLIENT_SECRET', 'your_client_secret');
```

Or use environment variables: `QBO_CLIENT_ID`, `QBO_CLIENT_SECRET`.

## 3. Vendor mapping page

- **URL**: `/vendor-qbo-mapping` (add a nav item to this module if needed).
- Select a store, then map each vendor to a QBO vendor and click Save.

## 4. Push to QuickBooks flow

1. Open a PO in status 5 and click **Push to QuickBooks**.
2. If the PO’s vendor has no `QBO_ID`, a modal asks you to choose the matching QBO vendor and click **Save & Push to QuickBooks**. The mapping is saved and the bill is created in one step.
3. If the vendor is already mapped, the bill is created immediately.

## Files

- `inc/qbo.php` – QBO helpers (token, list vendors, create bill).
- `ajax/po-qbo-bill.php` – Push PO to QBO (and optional save-mapping).
- `ajax/po-qbo-map-vendor.php` – Save mapping from modal and push.
- `ajax/qbo-vendors.php` – List QBO vendors for a store.
- `ajax/vendor-qbo-save.php` – Save one vendor mapping (mapping page).
- `modal/po-qbo-map-vendor.php` – Modal to map vendor when pushing.
- `module/vendor-qbo-mapping.php` – Standalone mapping page.
- `sql/vendor_qbo_id.sql` – ALTER for `vendor.QBO_ID`.
