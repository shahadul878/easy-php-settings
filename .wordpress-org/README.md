# Easy PHP Settings — WordPress.org Assets

This directory contains assets displayed on the [WordPress.org plugin page](https://wordpress.org/plugins/easy-php-settings/).
These files are **not** included in the plugin ZIP — they are deployed separately to the SVN `assets/` directory.

---

## Asset Inventory

### Banners

| File | Dimensions | Purpose |
|------|-----------|---------|
| `banner-772x250.png` | 772 x 250 px | Standard banner (required) |
| `banner-1544x500.png` | 1544 x 500 px | Retina / HiDPI banner |

### Icons

| File | Dimensions | Purpose |
|------|-----------|---------|
| `icon-128x128.png` | 128 x 128 px | Standard icon (required) |
| `icon-256x256.png` | 256 x 256 px | Retina / HiDPI icon |
| `icon.svg` | Vector | SVG icon (preferred over PNG) |

### Screenshots

Screenshots correspond to the `== Screenshots ==` section in `readme.txt`. They must be numbered sequentially.

| File | Tab | Description |
|------|-----|-------------|
| `screenshot-1.png` | General Settings | PHP memory, upload limits, execution time, presets, WordPress memory constants |
| `screenshot-2.png` | Tools | Debugging toggles, log viewer, export/import, reset options |
| `screenshot-3.png` | PHP Settings | Full table of all PHP directives with search and copy |
| `screenshot-4.png` | Extensions | Loaded PHP extensions by category with missing extension alerts |
| `screenshot-5.png` | Status | Live comparison of current vs. recommended PHP and WP memory values |
| `screenshot-6.png` | About | Plugin information, author details, and support links |

---

## Naming Rules

- File names are **case-sensitive** — use lowercase only.
- Supported formats: **PNG** and **JPEG** for banners, icons, and screenshots.
- Screenshots must be numbered sequentially starting from `1`.
- Banner and icon filenames must match the exact names above for WordPress.org to recognize them.

## Deployment

Assets are deployed to WordPress.org SVN via two GitHub Actions workflows:

- **`deploy-assets.yml`** — Pushes assets only (triggers on `.wordpress-org/` changes or manual dispatch).
- **`deploy-to-wordpress-org.yml`** — Full release: plugin code + assets + SVN tag (triggers on `v*` tag push).

Both workflows sync this folder to `https://plugins.svn.wordpress.org/easy-php-settings/assets/` using `rsync --delete`.

## Adding / Updating Assets

1. Add or replace files in this directory.
2. Push to `master` — the `deploy-assets.yml` workflow will automatically update WordPress.org.
3. Alternatively, run the workflow manually from the GitHub Actions tab.
