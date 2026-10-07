# Aide :: Demo Data Import — Implementation Plan

Living architecture notes for the current plugin. Keep this file aligned with shipped behavior.

## Goals

WordPress.org–ready plugin that lets administrators import curated demo content, and optionally generate large volumes beyond the static package:

| Content Type | Requirement | Notes |
|---|---|---|
| Blog / News Posts | Always | Categories, tags, featured images |
| Media | Always | Images from Aide CDN; used by posts |
| WooCommerce Products | WooCommerce active | Simple products, categories, CDN product photos |
| WooCommerce Customers | WooCommerce active | Customer role, billing/shipping, CDN avatars |
| WooCommerce Orders | WooCommerce active | Hard-requires products + customers first |

## Architecture

```
aidedemodata.php
├── includes/
│   ├── class-aidedemodataimport-core.php
│   ├── class-aidedemodataimport-installer.php
│   ├── class-aidedemodataimport-ajax.php
│   ├── class-aidedemodataimport-importer-base.php
│   ├── class-aidedemodataimport-importer-registry.php
│   ├── class-aidedemodataimport-dynamic-generator.php
│   ├── importers/
│   │   ├── class-aidedemodataimport-post-importer.php
│   │   ├── class-aidedemodataimport-media-importer.php
│   │   ├── class-aidedemodataimport-product-importer.php
│   │   ├── class-aidedemodataimport-customer-importer.php
│   │   └── class-aidedemodataimport-order-importer.php
│   └── helpers/
│       └── aidedemodataimport-helpers.php
├── admin/
│   ├── class-aidedemodataimport-admin.php
│   ├── controllers/ImportController.php
│   └── views/import/index.php
├── assets/css|js/          # Progress UI + custom confirm modal (no third-party)
├── demo-data/              # JSON catalogs only (no bundled image binaries)
│   ├── media.json
│   ├── posts.json
│   └── woocommerce/{products,customers,orders}.json
├── languages/
├── uninstall.php
└── doc/implementation-plan.md
```

## Design Decisions

- Menu: **Tools → Demo Data Import** (`manage_options`)
- WooCommerce optional — plugin works without it; Woo cards disabled when inactive
- Batch AJAX import; idempotency via `_demo_import_key` + `_demo_import_id`
- Prefix: `aidedemodataimport_` / `Aide_Demo_Data_Import_`
- Text domain: `aidedemodataimport`
- Confirm UI: first-party modal (no SweetAlert / external libs)
- Import selects **next pending** records (not always static offset `0` into the full JSON)

## Content strategy

### Static package (curated)

- Each type ships **100** curated rows in JSON under `demo-data/`
- Stable IDs: `demo-media-001`, `demo-post-001`, `demo-product-001`, etc.
- JSON image fields stay as **relative paths** (e.g. `media/product-01.jpg`, `avatars/avatar-01.jpg`, `media/products/product-01.jpg`)

### Remote images (CDN)

- Base URL: `https://wp.aide247.com/aidedemodataimport/demo-images/`
- Resolved by `aidedemodataimport_demo_image_url()`; downloaded with `download_url()` then `media_handle_sideload()`
- Host allowlist: `wp.aide247.com` (`aidedemodataimport_allowed_demo_image_hosts`)
- Optional local fallback if a file still exists under `demo-data/` (images are not shipped by default)
- Site must allow outbound HTTPS for image imports to succeed

### Dynamic generation (large counts)

- Max per type: **5,000** by default (`aidedemodataimport_max_import_total`, clamped 100–20,000)
- `get_package_total()` = curated JSON count (100)
- `get_total()` = max import ceiling
- Counts above the package are built by `Aide_Demo_Data_Import_Dynamic_Generator`:
  - Deterministic IDs / titles / SKUs / emails from the 1-based index
  - Templates cycle through static JSON rows
  - Images cycle through 20 CDN variants (`product-01`…`product-20`, `avatar-01`…`avatar-20`)
- Orders beyond the package cycle **already-imported** product/customer demo IDs from the ID maps
- Pending collection walks indexes near `count_imported()` and does **not** materialize all 5,000 rows in memory

## Import flow

1. Admin chooses type + quantity (capped to remaining toward max)
2. Custom modal confirms
3. `aidedemodataimport_start_import` — availability + dependency checks; returns session total
4. Repeated `aidedemodataimport_run_batch` — always pulls the **next** pending batch (`import_batch(0, $limit)`); session offset tracks progress
5. Default batch size **5** (remote downloads are slower than local copies)
6. Status / history refresh; last-run stats stored per type

### Dependencies

| Type | Depends on |
|---|---|
| Posts | Media (soft map / inline sideload fallback) |
| Products | None (sideloads own CDN product images) |
| Customers | None (sideloads CDN avatars) |
| Orders | Products + Customers (**hard-fail** if missing / empty line items) |

## Importer contract

Abstract / base surface:

- `get_type()`, `get_label()`, `get_description()`, `get_dependencies()`
- `get_package_total()`, `get_total()`, `count_imported()`, `count_pending()`
- `import_batch( $offset, $limit )`, `cleanup()`, `get_status()`, `validate_dependencies()`
- Helpers: `build_item_at_index()`, `collect_next_pending_items()`

`get_status()` returns: `total` (max), `package`, `imported`, `remaining`, `available`, `last_run`, `last_stats`.

## Admin UI

- Import history table (imported / max / remaining / last run)
- Per-type cards with qty picker, Import / Remove, progress
- Hint: curated package vs dynamic generation; CDN images
- Live activity log + custom confirm modal (import = confirm, remove = danger)

## Hooks

| Hook | Purpose |
|---|---|
| `aidedemodataimport_importers` | Register custom importers |
| `aidedemodataimport_before_import` / `aidedemodataimport_after_import` | Lifecycle |
| `aidedemodataimport_import_batch_size` | Batch size (default 5) |
| `aidedemodataimport_demo_data_path` | Override JSON directory |
| `aidedemodataimport_demo_images_base_url` | Override CDN base |
| `aidedemodataimport_allowed_demo_image_hosts` | Download allowlist |
| `aidedemodataimport_max_import_total` | Max records per type (default 5000) |
| `aidedemodataimport_required_capability` | Capability (default `manage_options`) |

## Security

- `ABSPATH` guards; AJAX nonce + capability checks
- Sanitize inputs; escape admin output
- Remote images only from allowlisted hosts; reject `..` path segments
- Local path helper still uses `realpath` containment under `demo-data/` when falling back
- No arbitrary user-supplied upload paths

## Packaging / uninstall

- `readme.txt`, `README.md`, `.distignore`, POT
- Uninstall removes demo-tagged content and plugin options/maps by default

## Acceptance criteria

- Activates without WooCommerce; posts/media import works
- With WooCommerce: products/customers/orders import
- Images download from Aide CDN (or local fallback if present)
- Importing ≤ package uses curated JSON; importing above package generates dynamic rows
- Re-import skips duplicates; cleanup deletes only tagged content
- Orders refuse to create empty / dependency-missing records
- Custom confirm modal works without third-party libraries
- WordPress.org packaging files present

## Status

Implemented through v1.0.0 feature set described above (CDN images, pending-item batches, dynamic large imports, custom modal, order hard-fail, product `WC_Data_Exception` handling).
