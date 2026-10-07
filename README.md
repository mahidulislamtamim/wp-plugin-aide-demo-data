# Aide :: Demo Data Import

WordPress plugin that imports curated demo content for demos, theme previews, and development sites.

**Author:** [Aide](https://aide247.com/)  
**License:** GPL-2.0-or-later  
**Requires:** WordPress 6.0+, PHP 7.4+  
**Optional:** WooCommerce (for products, customers, and orders)

## Features

- Import **blog/news posts** with categories, tags, and featured images
- Import **media** from the Aide CDN (`wp.aide247.com`)
- When WooCommerce is active:
  - **Products** with product photos from the CDN
  - **Customers** with billing/shipping details and profile avatars
  - **Orders** linked to demo products and customers
- Batched AJAX import with progress bar and live log
- **Dynamic generation** for large counts (above the curated package of 100, up to 5,000 by default)
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
3. **Products** — can also run alone; product images download automatically  
4. **Customers** — includes avatar photos  
5. **Orders** — needs products and customers first  

Each card shows imported / remaining / max. The first **100** records per type come from curated JSON. Higher quantities are generated deterministically (cycling templates and CDN images).

## Demo data

| Type | Curated | Max (default) | Content | Images |
|------|--------:|--------------:|---------|--------|
| Media | 100 | 5000 | `demo-data/media.json` | CDN `media/*.jpg` |
| Posts | 100 | 5000 | `demo-data/posts.json` | CDN (+ media map) |
| Products | 100 | 5000 | `demo-data/woocommerce/products.json` | CDN `media/products/*.jpg` |
| Customers | 100 | 5000 | `demo-data/woocommerce/customers.json` | CDN `avatars/*.jpg` |
| Orders | 100 | 5000 | `demo-data/woocommerce/orders.json` | — |

Image base URL: `https://wp.aide247.com/aidedemodataimport/demo-images/`

Imported entities are tagged with:

- `_demo_import_key` = `aidedemodataimport`
- `_demo_import_id` = stable ID (e.g. `demo-post-001`)

## Development structure

```
aidedemodata.php
├── includes/          # Core, AJAX, dynamic generator, importers
├── admin/             # Menu, controller, views
├── assets/            # Admin CSS/JS
├── demo-data/         # JSON catalogs (images served from CDN)
├── languages/         # POT file
├── uninstall.php
└── readme.txt         # WordPress.org readme
```

Architecture notes: see [`doc/implementation-plan.md`](doc/implementation-plan.md).

## Hooks

- `aidedemodataimport_importers` — register custom importers
- `aidedemodataimport_before_import` / `aidedemodataimport_after_import`
- `aidedemodataimport_import_batch_size` — default batch size (5)
- `aidedemodataimport_demo_data_path` — override demo-data directory
- `aidedemodataimport_demo_images_base_url` — override CDN base URL
- `aidedemodataimport_allowed_demo_image_hosts` — allowlist for downloads
- `aidedemodataimport_max_import_total` — max records per type (default 5000)
- `aidedemodataimport_required_capability` — default `manage_options`

**Text domain:** `aidedemodataimport`

## Changelog

### 1.0.0

- Initial release: media, posts, products, customers, orders
- Remote demo images from Aide CDN
- Dynamic generation for large import counts
- Batched AJAX import, cleanup, WordPress.org packaging files
