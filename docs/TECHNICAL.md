# Boxlabel -- Technical Reference

Module number: **500010**
Version: **1.7.1**
Family: `products`
Dependencies: `modProduct`, `modMrp`, `modStock`
Minimum Dolibarr: 16.0 | Minimum PHP: 7.0


---

## Pages & URL Parameters

### `boxlabel_card.php` -- Label Card (create / view / edit / delete)

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | int (GET) | BoxLabel row ID |
| `ref` | string (GET) | BoxLabel reference |
| `action` | string (GET/POST) | `create`, `add`, `edit`, `update`, `delete`, `confirm_delete`, `builddoc` |
| `cancel` | string (POST) | Cancel current action |
| `backtopage` | string (GET/POST) | URL to redirect after create/cancel |
| `fk_product` | int (POST) | Product ID |
| `fk_mo` | int (POST) | Manufacturing Order ID |
| `fk_product_lot` | int (POST) | Product Lot ID (hidden, set by JS cascade) |
| `batch` | string (POST) | Batch reference (MO ref) |
| `serial_number` | string (POST) | Serial/batch number from production line |
| `product_label` | string (POST) | Product name (auto-filled) |
| `product_description` | string (POST) | Product description (auto-filled) |
| `date_manufacturedday/month/year` | int (POST) | Manufacturing date parts |
| `note_private` | string (POST) | Private note |
| `note_public` | string (POST) | Public note |
| `model` | string (GET) | PDF model class name for `builddoc` action |
| `confirm` | string (POST) | `yes` to confirm delete |

Actions:
- **create/add**: Creates a new BoxLabel, auto-generates PDF, sets status to Generated.
- **edit/update**: Updates an existing BoxLabel, re-syncs linked objects.
- **delete/confirm_delete**: Deletes a BoxLabel (requires delete permission).
- **builddoc**: Generates/regenerates the PDF, validates if still Draft.

Contains inline `boxlabelCascadeJs()` function that generates JavaScript for the Product -> MO -> Serial cascading dropdown form.

### `boxlabel_list.php` -- Label List

| Parameter | Type | Description |
|-----------|------|-------------|
| `action` | string | `list` |
| `sortfield` | string | Column to sort (default `t.rowid`) |
| `sortorder` | string | `ASC` or `DESC` (default `DESC`) |
| `page` | int | Page number (0-indexed) |
| `limit` | int | Records per page |
| `search_ref` | string | Filter by ref (LIKE) |
| `search_batch` | string | Filter by batch (LIKE) |
| `search_serial` | string | Filter by serial_number (LIKE) |
| `search_product` | string | Filter by product label or product ref (LIKE) |
| `search_status` | int | Filter by status (0=Draft, 1=Generated) |
| `button_removefilter` | string | Clear all search filters |

Columns: Ref, Product, Product Label, Batch, Serial Number, Manufacturing Date, Status.

### `boxlabel_note.php` -- Notes Tab

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | int | BoxLabel row ID |
| `ref` | string | BoxLabel reference |
| `action` | string | `edit`, `update` |
| `note_public` | string (POST) | Public note content |
| `note_private` | string (POST) | Private note content |

### `mo_boxlabel.php` -- MO Box Labels Tab

Displayed as a tab on Manufacturing Order cards.

| Parameter | Type | Description |
|-----------|------|-------------|
| `fk_mo` | int | Manufacturing Order ID (primary) |
| `id` | int | Alternate MO ID parameter |
| `action` | string | `generate`, `builddoc`, `builddoc_all`, `printall` |
| `confirm` | string | `yes` to confirm generation |
| `label_id` | int | Specific label ID for `builddoc` action |

Actions:
- **generate**: Creates BoxLabel records from produced MO lines with batch numbers, generates PDFs, validates them. Deduplicates by checking existing labels.
- **builddoc**: Regenerates PDF for a single label (`label_id`).
- **builddoc_all**: Regenerates PDFs for all labels on this MO.
- **printall**: Generates a combined multi-page PDF (one label per page) and streams it directly to the browser for printing.

### `product_template.php` -- Per-Product Label Template Tab

Displayed as a tab on Product cards.

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | int | Product ID |
| `ref` | string | Product reference |
| `action` | string | `save_template`, `copy_template` |
| `fields[]` | array (POST) | Array of enabled field keys for `save_template` |
| `source_product_id` | int (POST) | Product ID to copy template from |

Available field keys: `weight`, `dimensions`, `volume`, `country`, `hs_code`, plus `extra_<attrname>` for each product extrafield.

Supports variant inheritance: if a product has no template, the parent product's template is used. Saving on a variant overrides the parent.

### `admin/setup.php` -- Module Setup

| Parameter | Type | Description |
|-----------|------|-------------|
| `action` | string | `update` |
| `BOXLABEL_ADDON` | string (POST) | Numbering rule class name |
| `BOXLABEL_ADDON_PDF` | string (POST) | PDF model class name |
| `BOXLABEL_HEADER_TITLE` | string (POST) | Header title on labels |
| `BOXLABEL_HEADER_SUBTITLE` | string (POST) | Header subtitle on labels |
| `BOXLABEL_HEADER_LOGO` | string (POST) | Logo file name or `none` |
| `BOXLABEL_RETENTION_DAYS` | int (POST) | Retention period in days |

Toggle constants (via `ajax_constantonoff`): `BOXLABEL_AUTO_GENERATE`, `BOXLABEL_AUTO_ARCHIVE`, `BOXLABEL_AUTO_DELETE`, `BOXLABEL_DEBUG_MODE`.

Requires admin rights.

### `ajax/fetch_mos.php` -- AJAX: Manufacturing Orders

| Parameter | Type | Description |
|-----------|------|-------------|
| `fk_product` | int (GET) | Product ID |

Returns JSON array of MOs (status IN_PROGRESS or PRODUCED) for the given product: `[{rowid, ref}, ...]`.

### `ajax/fetch_serials.php` -- AJAX: Serials & Product Info

| Parameter | Type | Description |
|-----------|------|-------------|
| `fk_product` | int (GET) | Product ID |
| `fk_mo` | int (GET) | Manufacturing Order ID (optional, filters serials to those produced by this MO) |

Returns JSON:
```json
{
  "product": {"label": "...", "description": "..."},
  "serials": [
    {
      "lot_id": 1,
      "batch": "SN-001",
      "manufacturing_date": "03/30/2026",
      "mfg_day": "30",
      "mfg_month": "03",
      "mfg_year": "2026",
      "total_qty": 1.0
    }
  ]
}
```

Manufacturing date is coalesced from: product_lot.manufacturing_date -> mrp_production.date_creation -> first stock_mouvement.datem.

### `ajax/debug.php` -- Debug Diagnostics

Gated by admin permission AND `BOXLABEL_DEBUG_MODE` constant.

| Parameter | Type | Description |
|-----------|------|-------------|
| `mode` | string (GET) | `overview`, `object`, `links`, `settings`, `classes`, `sql`, `triggers`, `hooks`, `all` |
| `type` | string (GET) | Object type for `object` mode (default `boxlabel`) |
| `id` | int (GET) | Object ID for `object` mode |
| `q` | string (GET) | Read-only SQL query for `sql` mode (SELECT only) |

Returns plain text diagnostics.


---

## Classes & Methods

### `BoxLabel` (`class/boxlabel.class.php`)

Extends `CommonObject`. Main CRUD class for box label records.

**Constants:**

| Constant | Value | Description |
|----------|-------|-------------|
| `STATUS_DRAFT` | 0 | Newly created, no PDF |
| `STATUS_GENERATED` | 1 | PDF has been generated |
| `STATUS_ARCHIVED` | 2 | Shipped / archived |

**Properties:**

`ref`, `entity`, `fk_product`, `fk_mo`, `fk_product_lot`, `batch`, `serial_number`, `product_label`, `product_description`, `date_manufactured`, `date_archived`, `qty_labels`, `status`, `note_private`, `note_public`, `date_creation`, `fk_user_creat`, `fk_user_modif`, `import_key`, `model_pdf`, `last_main_doc`

**Key attributes:** `module = 'boxlabel'`, `element = 'boxlabel'`, `table_element = 'box_label'`, `TRIGGER_PREFIX = 'BOXLABEL'`

**Methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `create` | `($user, $notrigger = 0): int` | Insert into DB, assign ref via numbering rule, link to MO and Lot via `llx_element_element`. Returns new ID or -1. |
| `fetch` | `($id, $ref = ''): int` | Load from DB by ID or ref. Returns 1 if found, 0 if not, -1 on error. |
| `update` | `($user, $notrigger = 0): int` | Update all fields, re-sync linked objects. Returns 1 or -1. |
| `delete` | `($user, $notrigger = 0): int` | Delete from DB. Returns 1 or -1. |
| `validate` | `($user, $notrigger = 0): int` | Set status to STATUS_GENERATED. Returns 1 or -1. |
| `archive` | `($user): int` | Set status to STATUS_ARCHIVED, record date_archived. Returns 1 or -1. |
| `getNextNumRef` | `(): string` | Get next reference from configured numbering module. Fallback: `BXL-YYYYMMDD-NNNN`. |
| `getNomUrl` | `($withpicto = 0, $option = '', $notooltip = 0): string` | Return clickable HTML link to card page. |
| `getLibStatut` | `($mode = 0): string` | Return HTML status badge for current object status. |
| `LibStatut` | `($status, $mode = 0): string` | (static) Return HTML status badge for given status value. |
| `buildLabelPdf` | `($outputlangs, $modelname = ''): int` | Load PDF model and call `write_file()`. Returns 1 or -1. |
| `generateFromMo` | `($mo_id, $user): int` | Generate BoxLabel records from MO produced lines. Deduplicates by serial. Returns count or negative on error. |
| `countForMo` | `($mo_id, $obj = null): int` | Count labels for a given MO ID (used for tab badge). |
| `syncLinkedObjects` | `(): void` | Delete and recreate `llx_element_element` links for MO and ProductLot. |
| `cleanupArchivedLabels` | `(): int` | Cron job method. Deletes archived labels past retention period. Gated by `BOXLABEL_AUTO_DELETE` and `BOXLABEL_RETENTION_DAYS`. |

### `ActionsBoxlabel` (`class/actions_boxlabel.class.php`)

Hook actions class.

| Method | Hook Context | Description |
|--------|-------------|-------------|
| `getElementProperties` | `elementproperties` | Registers element resolution for `boxlabel` and `boxlabel_boxlabel` element types, mapping to the BoxLabel class. |
| `showLinkToObjectBlock` | `elementproperties` | Placeholder for "Link to..." dropdown (currently no-op). |
| `formObjectOptions` | `productcard` | Injects "Auto-generate box labels" checkbox on Product cards (view and edit mode). Reads/displays current setting from `llx_boxlabel_product_auto`. |
| `doActions` | `productcard` | Saves auto-label checkbox on Product card update. Atomic delete-then-insert into `llx_boxlabel_product_auto`. |

### `InterfaceBoxlabelTrigger` (`core/triggers/interface_99_modBoxlabel_BoxlabelTrigger.class.php`)

Extends `DolibarrTriggers`. See [Triggers](#triggers) section below.

### `ModelePDFBoxLabel` (`core/modules/boxlabel/modules_boxlabel.php`)

Abstract class extending `CommonDocGenerator`. Parent for all BoxLabel PDF models.

| Method | Description |
|--------|-------------|
| `liste_modeles($db, $maxfilenamelength)` | (static) Return list of active PDF models. |
| `write_file(...)` | (abstract) Generate PDF to disk. |

### `ModeleNumRefBoxlabel` (`core/modules/boxlabel/modules_boxlabel.php`)

Abstract class extending `CommonNumRefGenerator`. Parent for all BoxLabel numbering models.

| Method | Description |
|--------|-------------|
| `getNextValue($objsoc, $object)` | (abstract) Return next ref value. |
| `getExample()` | (abstract) Return example ref string. |

### `mod_boxlabel_standard` (`core/modules/boxlabel/mod_boxlabel_standard.php`)

Standard numbering rule. Pattern: `BXL-YYYYMMDD-NNNN` (zero-padded 4-digit daily sequence).

| Method | Description |
|--------|-------------|
| `getExample()` | Returns `BXL-20260330-0001`. |
| `getNextValue($objsoc, $object)` | Queries MAX sequence for today's date prefix, returns next value. |

### `pdf_boxlabel_standard` (`core/modules/boxlabel/doc/pdf_boxlabel_standard.modules.php`)

See [PDF Models](#pdf-models) section below.

### Library: `boxlabel.lib.php` (`lib/boxlabel.lib.php`)

| Function | Description |
|----------|-------------|
| `boxlabel_admin_prepare_head()` | Returns tab array for admin setup page. |
| `boxlabel_prepare_head($object)` | Returns tab array for BoxLabel card (Card + Notes tabs). |


---

## Hooks

### Registered Contexts

Defined in `modBoxlabel::__construct()`:

```php
'hooks' => array('data' => array('elementproperties', 'mocard', 'productcard'), 'entity' => '0')
```

Contexts: **elementproperties**, **mocard**, **productcard**

### Hook Methods in `ActionsBoxlabel`

| Method | Context | What It Does |
|--------|---------|-------------|
| `getElementProperties` | `elementproperties` | Maps `boxlabel` / `boxlabel_boxlabel` element types to the BoxLabel class for linked object resolution. |
| `showLinkToObjectBlock` | `elementproperties` | Placeholder for linked object dropdown (no-op). |
| `formObjectOptions` | `productcard` | Renders auto-label checkbox in view/edit mode on Product cards. |
| `doActions` | `productcard` | Saves auto-label setting when product is updated. |


---

## Triggers

### `InterfaceBoxlabelTrigger` (priority 99)

**Own object triggers (logging only):**

| Event Code | Description |
|------------|-------------|
| `BOXLABEL_BOXLABEL_CREATE` | Logged when a BoxLabel is created |
| `BOXLABEL_BOXLABEL_MODIFY` | Logged when a BoxLabel is updated |
| `BOXLABEL_BOXLABEL_DELETE` | Logged when a BoxLabel is deleted |
| `BOXLABEL_BOXLABEL_VALIDATE` | Logged when a BoxLabel is validated |

**Cross-module triggers (active logic):**

| Event Code | Handler | Description |
|------------|---------|-------------|
| `MRP_MO_PRODUCE` | `_handleMoProduction()` | Auto-generates box labels when an MO produces items. Gated by: (1) `BOXLABEL_AUTO_GENERATE` global switch, (2) per-product `auto_label` flag in `llx_boxlabel_product_auto`. Creates labels via `generateFromMo()`, generates PDFs, validates them. |
| `SHIPPING_CLOSED` | `_handleShippingClosed()` | Auto-archives labels when a shipment is closed. Gated by `BOXLABEL_AUTO_ARCHIVE`. Iterates shipment lines, finds batch details, matches serial numbers against Generated labels, archives them. |


---

## Database Schema

### `llx_box_label` -- Main label table

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `rowid` | INTEGER | NO | AUTO_INCREMENT | Primary key |
| `ref` | VARCHAR(30) | NO | | Label reference (e.g. BXL-20260330-0001) |
| `entity` | INTEGER | NO | 1 | Multi-entity ID |
| `fk_product` | INTEGER | NO | | Product ID |
| `fk_mo` | INTEGER | YES | NULL | Manufacturing Order ID |
| `fk_product_lot` | INTEGER | YES | NULL | Product Lot ID |
| `batch` | VARCHAR(128) | YES | NULL | Batch reference (typically MO ref) |
| `serial_number` | VARCHAR(128) | YES | NULL | Serial number from production |
| `product_label` | VARCHAR(255) | YES | NULL | Snapshot of product name |
| `product_description` | TEXT | YES | NULL | Snapshot of product description |
| `date_manufactured` | DATETIME | YES | NULL | Manufacturing date |
| `date_archived` | DATETIME | YES | NULL | Date when label was archived (v2 migration) |
| `qty_labels` | INTEGER | NO | 1 | Number of PDF copies to generate |
| `status` | SMALLINT | NO | 0 | 0=Draft, 1=Generated, 2=Archived |
| `note_private` | TEXT | YES | NULL | Private note |
| `note_public` | TEXT | YES | NULL | Public note |
| `date_creation` | DATETIME | NO | | Creation timestamp |
| `tms` | TIMESTAMP | YES | | Last modification (auto-updated) |
| `fk_user_creat` | INTEGER | NO | | Creating user ID |
| `fk_user_modif` | INTEGER | YES | NULL | Last modifying user ID |
| `import_key` | VARCHAR(14) | YES | NULL | Import batch key |
| `model_pdf` | VARCHAR(255) | YES | NULL | PDF model name used |
| `last_main_doc` | VARCHAR(255) | YES | NULL | Relative path to last generated PDF |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `uk_box_label_ref` | `ref, entity` | UNIQUE |
| `idx_box_label_fk_product` | `fk_product` | INDEX |
| `idx_box_label_fk_mo` | `fk_mo` | INDEX |
| `idx_box_label_fk_product_lot` | `fk_product_lot` | INDEX |
| `idx_box_label_status` | `status` | INDEX |
| `idx_box_label_batch` | `batch` | INDEX |
| `idx_box_label_serial_number` | `serial_number` | INDEX |
| `idx_box_label_date_archived` | `date_archived` | INDEX |

**Migration `llx_box_label_v2.sql`:** Adds `date_archived` column and its index.

### `llx_box_label_extrafields` -- Extrafields support

| Column | Type | Description |
|--------|------|-------------|
| `rowid` | INTEGER | Primary key |
| `tms` | TIMESTAMP | Last modification |
| `fk_object` | INTEGER | FK to llx_box_label.rowid |
| `import_key` | VARCHAR(14) | Import batch key |

Index: `idx_box_label_extrafields_fk_object` on `fk_object`.

### `llx_boxlabel_product_auto` -- Per-product auto-label flag

| Column | Type | Description |
|--------|------|-------------|
| `rowid` | INTEGER | Primary key |
| `fk_product` | INTEGER | Product ID |
| `entity` | INTEGER | Multi-entity ID |
| `auto_label` | SMALLINT | 1=enabled, 0=disabled |
| `date_creation` | DATETIME | Creation timestamp |
| `tms` | TIMESTAMP | Last modification |
| `fk_user_creat` | INTEGER | Creating user ID |
| `import_key` | VARCHAR(14) | Import batch key |

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `uk_boxlabel_product_auto` | `fk_product, entity` | UNIQUE |
| `idx_boxlabel_product_auto_fk_product` | `fk_product` | INDEX |

### `llx_boxlabel_product_template` -- Per-product label template

| Column | Type | Description |
|--------|------|-------------|
| `rowid` | INTEGER | Primary key |
| `fk_product` | INTEGER | Product ID |
| `entity` | INTEGER | Multi-entity ID |
| `enabled_fields` | TEXT | Comma-separated list of enabled field keys |
| `date_creation` | DATETIME | Creation timestamp |
| `tms` | TIMESTAMP | Last modification |
| `fk_user_creat` | INTEGER | Creating user ID |
| `import_key` | VARCHAR(14) | Import batch key |

Valid field keys: `weight`, `dimensions`, `volume`, `country`, `hs_code`, `extra_<attrname>`.

**Indexes:**

| Index | Columns | Type |
|-------|---------|------|
| `uk_boxlabel_product_template` | `fk_product, entity` | UNIQUE |
| `idx_boxlabel_product_template_fk_product` | `fk_product` | INDEX |

### Linked objects (`llx_element_element`)

BoxLabel creates links using Dolibarr's `add_object_linked()`:

| sourcetype | fk_source | targettype | fk_target |
|------------|-----------|------------|-----------|
| `mo` | MO rowid | `boxlabel_boxlabel` | BoxLabel rowid |
| `productlot` | Lot rowid | `boxlabel_boxlabel` | BoxLabel rowid |

Links are synced on create and update via `syncLinkedObjects()`.


---

## Permissions

| ID | Right | Type | Module | Action |
|----|-------|------|--------|--------|
| 500011 | Read box labels | r | boxlabel | read |
| 500012 | Create and edit box labels | w | boxlabel | write |
| 500013 | Delete box labels | d | boxlabel | delete |

Rights class: `boxlabel`

Usage pattern: `$user->hasRight('boxlabel', 'boxlabel', 'read|write|delete')`


---

## Cron Jobs

### CleanupArchivedLabels

| Property | Value |
|----------|-------|
| Label | `CleanupArchivedLabels` |
| Type | `method` |
| Class | `/boxlabel/class/boxlabel.class.php` |
| Object | `BoxLabel` |
| Method | `cleanupArchivedLabels` |
| Frequency | Daily (86400 seconds) |
| Default status | Disabled (0) |
| Test | `isModEnabled("boxlabel")` |
| Priority | 50 |

Behavior:
1. Checks `BOXLABEL_AUTO_DELETE` -- skips if disabled.
2. Reads `BOXLABEL_RETENTION_DAYS` (default 90).
3. Finds all archived labels where `date_archived` is older than the retention cutoff.
4. Deletes the PDF file from disk (and empty parent directory).
5. Deletes the BoxLabel database record (with `notrigger=1`).
6. Returns count of deleted labels.


---

## Language Keys

File: `langs/en_US/boxlabel.lang`

### Module

| Key | Value |
|-----|-------|
| `Module500010Name` | Box Label |
| `Module500010Desc` | Generate and print 4x6 box labels with product, batch, and serial information after manufacturing |

### Object / Navigation

| Key | Value |
|-----|-------|
| `BoxLabel` | Box Label |
| `BoxLabels` | Box Labels |
| `BoxLabelList` | Label List |
| `NewBoxLabel` | New Box Label |

### Fields

| Key | Value |
|-----|-------|
| `BoxLabelSerialNumber` | Serial Number |
| `BoxLabelBatch` | Batch |
| `ManufacturingDate` | Manufacturing Date |
| `LabelQuantity` | Number of Copies |
| `ProductLabel` | Product Name |
| `ManufacturingOrder` | Manufacturing Order |
| `BoxLabelLot` | Lot/Serial |

### Status

| Key | Value |
|-----|-------|
| `BoxLabelDraft` | Draft |
| `BoxLabelGenerated` | Generated |
| `StatusDraft` | Draft |
| `StatusGenerated` | Generated |
| `StatusArchived` | Archived |

### Actions

| Key | Value |
|-----|-------|
| `GenerateBoxLabels` | Generate Box Labels |
| `GenerateBoxLabelsFromMO` | Generate labels from this Manufacturing Order |
| `GeneratePDF` | Generate PDF |
| `RegeneratePDF` | Regenerate PDF |
| `RegenerateAllPDFs` | Regenerate All PDFs |
| `PrintAllLabels` | Print All Labels |
| `DownloadPDF` | Download PDF |
| `ConfirmGenerateLabels` | *(confirmation prompt text)* |
| `BoxLabelCreatedFromMO` | %s box label(s) created from Manufacturing Order %s |
| `NoLabelsForThisMO` | No box labels generated for this Manufacturing Order yet |
| `NoProducedLinesWithBatch` | No produced lines with batch/serial numbers found for this Manufacturing Order |
| `LabelsAlreadyExist` | Labels already exist for all produced serials in this Manufacturing Order |
| `LabelGeneratedSuccess` | Box label PDF generated successfully |

### Permissions

| Key | Value |
|-----|-------|
| `Permission500011a` | Read box labels |
| `Permission500012a` | Create/edit box labels |
| `Permission500013a` | Delete box labels |

### Admin / Setup

| Key | Value |
|-----|-------|
| `BoxLabelSetup` | Box Label Setup |
| `LabelHeaderSettings` | Label Header Appearance |
| `LabelHeaderTitle` | Header Title |
| `LabelHeaderTitleDesc` | Company or brand name displayed on the label. Defaults to your company name if empty. |
| `LabelHeaderSubtitle` | Header Subtitle |
| `LabelHeaderSubtitleDesc` | Location, tagline, or other text displayed below the title. Leave empty to hide. |
| `LabelHeaderLogo` | Logo |
| `LabelHeaderLogoDesc` | Logo image displayed on the label. Select from uploaded company logos. |
| `CompanyLogo` | Company Logo |
| `NoLogo` | No Logo |
| `BoxLabelDefault` | default |
| `NumberingRule` | Numbering Rule |
| `PDFModel` | PDF Model |
| `LabelArchiving` | Label Archiving |
| `AutoArchiveOnShipment` | Auto-archive on shipment |
| `AutoArchiveOnShipmentDesc` | When enabled, labels are automatically archived when their serial number ships (expedition closed) |
| `RetentionDays` | Retention period |
| `RetentionDaysDesc` | Number of days to keep archived labels before automatic deletion |
| `BoxLabelDays` | days |
| `AutoDeleteAfterRetention` | Auto-delete after retention |
| `AutoDeleteAfterRetentionDesc` | When enabled, archived labels are permanently deleted after the retention period expires (daily cron job) |
| `CleanupArchivedLabels` | Cleanup archived box labels |
| `DebugMode` | Debug Mode |
| `DebugModeDesc` | When enabled, exposes a diagnostic endpoint at /custom/boxlabel/ajax/debug.php for troubleshooting |

### Cascading Form

| Key | Value |
|-----|-------|
| `SelectProductFirst` | Select a product first |
| `SelectMOFirst` | Select a Manufacturing Order first |
| `SelectSerial` | Select Serial/Batch |
| `SelectMO` | Select Manufacturing Order |
| `NoSerialsFound` | No serials in stock for this MO |
| `NoMOsFound` | No Manufacturing Orders found |
| `BoxLabelLoading` | Loading |

### Auto-generation

| Key | Value |
|-----|-------|
| `AutoGenerateLabels` | Auto-generate labels on MO production |
| `AutoGenerateLabelsDesc` | When enabled, automatically creates box labels when a Manufacturing Order produces items. Requires the product to also have auto-labeling enabled on its product card. |
| `AutoLabelProduct` | Auto-generate box labels |
| `AutoLabelProductDesc` | When checked, box labels will be automatically created when this product is produced via a Manufacturing Order |
| `AutoLabelEnabled` | Auto-label enabled |
| `AutoLabelNotSet` | Not configured |

### Label Template

| Key | Value |
|-----|-------|
| `LabelTemplate` | Label Template |
| `LabelTemplateDesc` | Select which data fields appear on the box label for this product |
| `LabelTemplateAlwaysShown` | Always shown on every label |
| `LabelTemplateSaved` | Label template saved |
| `LabelTemplateInherited` | This variant inherits its label template from parent product %s. |
| `LabelTemplateOverride` | Set custom template for this variant |
| `FieldWeight` | Weight |
| `FieldDimensions` | Dimensions (L x W x H) |
| `FieldVolume` | Volume |
| `FieldColor` | Color |
| `FieldCountry` | Country of Origin |
| `FieldHSCode` | HS Code |
| `FieldWeightDesc` | Product weight from the product card |
| `FieldDimensionsDesc` | Length, width, and height from the product card |
| `FieldVolumeDesc` | Product volume from the product card |
| `FieldColorDesc` | Color from product extra fields |
| `FieldCountryDesc` | Country of origin from the product card |
| `FieldHSCodeDesc` | Customs/tariff code from the product card |
| `FieldSurface` | Surface Area |
| `FieldSurfaceDesc` | Product surface area from the product card |
| `FieldNetMeasure` | Net Measure |
| `FieldNetMeasureDesc` | Net measure/weight from the product card |
| `FieldBarcode` | Barcode |
| `FieldBarcodeDesc` | Product barcode value from the product card |
| `ExtraField` | Product extra field |
| `BoxLabelField` | Field |
| `BoxLabelEnabled` | Enabled |
| `BoxLabelEmpty` | empty |
| `CopyTemplateFrom` | Copy Template From Another Product |
| `CopyTemplateFromDesc` | Select a product to copy its label template configuration to this product |
| `LabelTemplateCopied` | Label template copied successfully |
| `LabelTemplateNotFound` | No label template found on the selected product |

### Linked Objects

| Key | Value |
|-----|-------|
| `LinkToBoxLabel` | Link to Box Label |


---

## Configuration Constants

| Constant | Type | Default | Description |
|----------|------|---------|-------------|
| `BOXLABEL_ADDON` | chaine | `mod_boxlabel_standard` | Numbering rule class name |
| `BOXLABEL_ADDON_PDF` | chaine | `pdf_boxlabel_standard` | PDF model class name |
| `BOXLABEL_HEADER_TITLE` | chaine | `$mysoc->name` | Company/brand name on label header |
| `BOXLABEL_HEADER_SUBTITLE` | chaine | *(empty)* | Subtitle text on label header |
| `BOXLABEL_HEADER_LOGO` | chaine | *(empty)* | Logo file name from mycompany/logos, or `none` |
| `BOXLABEL_AUTO_GENERATE` | int | 0 | Enable auto-generation of labels on MRP_MO_PRODUCE trigger |
| `BOXLABEL_AUTO_ARCHIVE` | int | 0 | Enable auto-archiving of labels on SHIPPING_CLOSED trigger |
| `BOXLABEL_AUTO_DELETE` | int | 0 | Enable automatic deletion of expired archived labels |
| `BOXLABEL_RETENTION_DAYS` | int | 90 | Days to keep archived labels before deletion |
| `BOXLABEL_DEBUG_MODE` | int | 0 | Enable debug diagnostics endpoint |


---

## PDF Models

### `pdf_boxlabel_standard`

Extends `ModelePDFBoxLabel`. Generates 4x6 inch (101.6 x 152.4 mm) thermal/laser labels using TCPDF.

**Page dimensions:** 101.6mm x 152.4mm with 4mm margins on all sides (93.6mm usable width).

**Layout zones (top to bottom):**

1. **Header** -- Company logo (left) + configurable title and subtitle (right). Logo from `BOXLABEL_HEADER_LOGO` constant or company default. Title from `BOXLABEL_HEADER_TITLE`.

2. **Product Name & Description** -- Centered product label in bold, with truncated description text below.

3. **Product Barcode** -- Code 128 barcode of the product's barcode value (or product ref as fallback). Labeled "PRODUCT".

4. **Data Grid** -- Adaptive-height bordered cells in a 2-column layout:
   - Row 1: MFG ORDER (batch/MO ref) | SERIAL (serial_number)
   - Row 2: MFG DATE (full width)
   - Dynamic rows: Optional fields from the per-product template (weight, dimensions, volume, country, HS code, surface, net measure, product extrafields). Fields are paired into 2-column rows; an odd field gets a full-width row.

5. **Serial Barcode** -- Code 128 barcode of the serial number, pinned to the bottom of the label. Labeled "SERIAL NUMBER".

**Adaptive sizing:** Font sizes and cell heights scale based on the number of data grid rows to prevent content overlap. More fields result in smaller fonts and tighter spacing.

**Template integration:** Respects `llx_boxlabel_product_template` settings. If a template exists, only selected fields are shown (even if empty, displayed as a dash). If no template exists, all populated fields are shown. Supports variant inheritance from parent product templates.

**Methods:**

| Method | Description |
|--------|-------------|
| `write_file($object, $outputlangs, ...)` | Generate single-label PDF to disk. Outputs `qty_labels` copies as pages. Updates `last_main_doc` and `model_pdf` on the object. |
| `write_file_multi($labels, $outputlangs, $mo_ref)` | Generate combined multi-page PDF with one label per page. Saved as `{MO_REF}_all_labels.pdf`. Returns filepath. |
| `_generateLabelPage(&$pdf, $object, $outputlangs)` | (private) Render a single label page with all zones. |
| `_drawDataCell(&$pdf, $x, $y, $w, $h, $label, $value, ...)` | (private) Draw a bordered cell with small uppercase label and large value text. Auto-shrinks font for long values. |

### Linked Object Template

`boxlabel/tpl/linkedobjectblock.tpl.php` renders BoxLabel rows in linked object blocks on other cards (MO, Product Lot). Columns: Type, Ref (link), Serial Number, Date, Status, Remove link action.


---

## Module Descriptor Summary

File: `core/modules/modBoxlabel.class.php`

| Property | Value |
|----------|-------|
| Module number | 500010 |
| Family | products |
| Position | 90 |
| Version | 1.7.1 |
| Picto | mrp |
| Dependencies | modProduct, modMrp, modStock |
| Config page | `setup.php@boxlabel` |
| Language files | `boxlabel@boxlabel` |
| Models | enabled (triggers=1, models=1) |

**Tabs injected on native cards:**

| Target | Tab Key | Label | Page |
|--------|---------|-------|------|
| MO card (`mo@mrp`) | `boxlabel` | BoxLabels (with count badge via `countForMo`) | `/boxlabel/mo_boxlabel.php?fk_mo=__ID__` |
| Product card (`product`) | `boxlabel_template` | LabelTemplate | `/boxlabel/product_template.php?id=__ID__` |

**Menu entries (under MRP left menu):**

| Position | Title | URL | Permission |
|----------|-------|-----|------------|
| 300 | BoxLabels | `/boxlabel/boxlabel_list.php` | read |
| 310 | NewBoxLabel | `/boxlabel/boxlabel_card.php?action=create` | write |

**Directories created:** `/boxlabel/temp`
