# Aide :: Demo Data Import — Implementation Plan

## Goals

WordPress.org–ready plugin that lets administrators import curated demo content:

| Content Type | Requirement | Notes |
|---|---|---|
| Blog / News Posts | Always | Categories, tags, featured images |
| Media | Always | Images referenced by demo content |
| WooCommerce Products | WooCommerce active | Simple products, categories, images |
| WooCommerce Customers | WooCommerce active | Customer role + billing/shipping meta |
| WooCommerce Orders | WooCommerce active | Requires products + customers |

## Architecture

```
aidedemodata.php
├── includes/
│   ├── class-aidedemodataimport-core.php
│   ├── class-aidedemodataimport-installer.php
│   ├── class-aidedemodataimport-ajax.php
│   ├── class-aidedemodataimport-importer-base.php
│   ├── class-aidedemodataimport-importer-registry.php
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
├── assets/css|js/
├── demo-data/
└── languages/
```

## Design Decisions

- Menu: **Tools → Demo Data Import** (`manage_options`)
- WooCommerce optional — plugin works without it; Woo cards disabled when inactive
- Batch AJAX import; idempotency via `_demo_import_key` + `_demo_import_id`
- Prefix: `aidedemodataimport_` / `Aide_Demo_Data_Import_`
- Text domain: `aidedemodataimport`

## Sprints

1. Bootstrap, core, installer, admin menu shell
2. Importer base + registry, post/media importers, demo JSON
3. AJAX batch runner, progress UI, cleanup
4. WooCommerce product/customer/order importers
5. uninstall.php, readme.txt, .distignore, POT

## Importer Contract

Abstract base methods: `get_type()`, `get_label()`, `get_description()`, `get_dependencies()`, `get_total()`, `import_batch( $offset, $limit )`, `cleanup()`, `get_status()`.

## Security

- `ABSPATH` guard, nonce + capability on AJAX, sanitize inputs, escape output
- Bundled demo files only (no user uploads in v1)

## Acceptance Criteria

- Activates without WooCommerce; posts/media import works
- With WooCommerce: products/customers/orders import
- Re-import skips duplicates; cleanup deletes only tagged content
- WordPress.org packaging files present
