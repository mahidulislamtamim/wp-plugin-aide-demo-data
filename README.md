# Aide :: Demo Data Import

WordPress plugin that imports curated demo content for demos, theme previews, and development sites.

**Author:** [Aide](https://aide247.com/)  
**License:** GPL-2.0-or-later  
**Requires:** WordPress 6.0+, PHP 7.4+  
**Optional:** WooCommerce (for products, customers, and orders)

## Features

- Import **blog/news posts** with categories, tags, and featured images
- Import **media** from bundled demo files
- When WooCommerce is active:
  - **Products** with real product photos (phone, headphones, bottle, etc.)
  - **Customers** with billing/shipping details and profile avatars
  - **Orders** linked to demo products and customers
- Batched AJAX import with progress bar and live log
- **Remove Demo Data** — deletes only items tagged by this plugin
- Re-import is safe: duplicate demo IDs are skipped

WooCommerce is optional. Without it, Media and Posts still work; WooCommerce cards stay disabled.

## Installation

1. Copy this folder to `wp-content/plugins/`
2. Activate **Aide :: Demo Data Import** in wp-admin
3. Open **Tools → Demo Data Import**

## Usage

Recommended import order:

1. **Media** — lifestyle images used by posts  
2. **Blog / News Posts**  
3. **Products** — can also run alone; product images upload automatically  
4. **Customers** — includes avatar photos  
5. **Orders** — needs products and customers first  

Each card shows item count, dependencies, and last run time. Use **Remove Demo Data** to clean up a type.

## Demo data (bundled)

| Type | Count | Source |
|------|------:|--------|
| Media | 100 | `demo-data/media.json` + `demo-data/media/*.jpg` (blog images) |
| Posts | 100 | `demo-data/posts.json` |
| Products | 100 | `demo-data/woocommerce/products.json` + `demo-data/media/products/*.jpg` (500×500) |
| Customers | 100 | `demo-data/woocommerce/customers.json` + `demo-data/avatars/*.jpg` |
| Orders | 100 | `demo-data/woocommerce/orders.json` |

Imported entities are tagged with:

- `_demo_import_key` = `aidedemodataimport`
- `_demo_import_id` = stable ID from JSON (e.g. `demo-post-001`)

## Development structure

```
aidedemodata.php
├── includes/          # Core, AJAX, installer, importers
├── admin/             # Menu, controller, views
├── assets/            # Admin CSS/JS
├── demo-data/         # JSON + images
├── languages/         # POT file
├── uninstall.php
└── readme.txt         # WordPress.org readme
```

Architecture notes: see [`doc/implementation-plan.md`](doc/implementation-plan.md).

## Hooks

- `aidedemodataimport_importers` — register custom importers
- `aidedemodataimport_before_import` / `aidedemodataimport_after_import`
- `aidedemodataimport_import_batch_size` — default batch size (10)
- `aidedemodataimport_demo_data_path` — override demo-data directory
- `aidedemodataimport_required_capability` — default `manage_options`

**Text domain:** `aidedemodataimport`

> **WordPress.org note:** The current folder name `wp-plugin-aide-demo-data` contains the restricted term `plugin`. Before submitting to the directory, rename the plugin directory to match the text domain (e.g. `aidedemodataimport`) or another allowed slug.

## Changelog

### 1.0.0

- Initial release: media, posts, products, customers, orders
- Batched AJAX import, cleanup, WordPress.org packaging files
