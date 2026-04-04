# Boxlabel — Box Label Generation for Dolibarr

**Version 1.7.1** | [GitHub Repository](https://github.com/zacharymelo/boxlabel) | License: GPL-3.0

## Overview

Boxlabel lets you generate and print 4x6 box labels directly from Manufacturing Orders (MOs) in Dolibarr. Each label is tied to a specific production run, supports serial/lot tracking, and can be customized per product with its own PDF template. Ideal for warehouses and production lines that need consistent, branded box labeling.

## Features

### Label Generation from Manufacturing Orders

A dedicated **Box Labels** tab appears on each Manufacturing Order card. The tab displays a badge with the current label count. From this tab you can create individual labels or generate all labels at once.

### Per-Product PDF Templates

Each product can have its own label template. A **Label Template** tab on the product card lets you configure the layout for that product's labels. When a label is generated for an MO, the system uses the template assigned to the product being manufactured.

### Configurable Header

Customize the label header with:

- **Title** — Primary text at the top of the label
- **Subtitle** — Secondary line below the title
- **Logo** — Your company or product logo

### Serial Tracking

Labels automatically pull serial and lot numbers from the MO's batch/serial production data, so each label reflects the actual unit it represents.

### Print All Labels

Use the "Print All Labels" button to generate a single multi-page PDF containing every label for an MO. Send this directly to your printer for a complete production run.

### Automation

- **Auto-generate on MO production** — Labels are automatically created when an MO production is recorded, so no manual step is needed on the shop floor
- **Auto-archive shipped labels** — Once shipments are completed, associated labels are archived automatically with configurable retention
- **Cron cleanup** — A scheduled job removes expired archived labels to keep your system clean

### Numbering

Labels follow the format **BXL-YYYYMMDD-NNNN** (e.g., BXL-20260404-0001), providing a unique, date-based identifier for every label.

## Requirements

| Requirement | Details |
|---|---|
| Dolibarr | Version 16 or higher |
| PHP | Version 7.0 or higher |
| **Required modules** | Products, MRP (Manufacturing), Stock |

## Installation

1. Download the latest `.zip` file from the [GitHub Releases](https://github.com/zacharymelo/boxlabel/releases) page
2. Log in to your Dolibarr instance as an administrator
3. Navigate to **Home > Setup > Modules/Applications**
4. Click the **Deploy external module** button at the top of the page
5. Upload the `.zip` file you downloaded
6. Find "Boxlabel" in the module list and click the toggle to **enable** it
7. Click the gear icon to open the **Admin Setup** page and configure the module

## Configuration

After enabling the module, go to the admin setup page to configure:

- **Header Appearance** — Set the title, subtitle, and logo that appear on generated labels
- **Numbering Rule** — The default format is BXL-YYYYMMDD-NNNN; confirm or adjust the numbering model
- **PDF Model** — Select the PDF template used for label generation
- **Auto-Generate Toggle** — Turn on or off automatic label creation when MO production is recorded
- **Archiving Settings** — Enable auto-archiving of shipped labels and set the retention period (in days) before archived labels are cleaned up by the cron job

## Usage Guide

### Creating Labels from an MO

1. Open a Manufacturing Order
2. Click the **Box Labels** tab
3. Click "New Label" to create a single label, or "Generate All" to create labels for the entire production batch
4. Each label will use the PDF template assigned to the product being manufactured

### Printing Labels

From the Box Labels tab on an MO, click **Print All Labels** to download a single PDF containing all labels for that order. This PDF is formatted for 4x6 label stock and can be sent directly to a thermal or standard printer.

### Setting Up Product Templates

1. Open a Product card
2. Click the **Label Template** tab
3. Configure the layout, fields, and formatting for labels generated for this product
4. Save the template — it will be used automatically whenever labels are generated for this product

### Automation

Once auto-generate is enabled in the admin setup, labels are created automatically whenever production quantities are recorded on an MO. No manual label creation is needed. Shipped labels are archived after shipment, and the cron job handles cleanup based on your retention settings.

## Screenshots

**Label List**
![Label List](docs/screenshots/label-list.png)

**New Box Label Form**
![New Box Label](docs/screenshots/new-label-form.png)

**Admin Setup**
![Admin Setup](docs/screenshots/admin-setup.png)

## License

This module is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).
