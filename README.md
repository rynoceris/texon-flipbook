# Texon Flipbook

A WordPress plugin that turns any PDF catalog into an interactive page-turn flipbook with clickable hotspots — a self-hosted alternative to FlipSnack / Issuu.

Built for [Texon Towel](https://texontowel.com), but works with any PDF.

## Features

- **Realistic page-turn viewer** powered by [StPageFlip](https://nodlik.github.io/StPageFlip/) — smooth page curl, touch/swipe, keyboard arrows
- **PDF → JPG pre-rendering** via Ghostscript (preferred) or Imagick, so page loads are instant
- **Clickable hotspots** — draw rectangles over any page and point them at product URLs, categories, external sites; opens in a new tab
- **Two embed modes** — inline on a page, or a button that opens the flipbook in a full-screen modal
- **Per-catalog hotspot editor** — click-and-drag to draw, drag to move, drag corner to resize, right-click to delete, autosaves via AJAX
- **URL → filesystem path auto-conversion** — paste any PDF URL (media library URL, site-relative path, etc.) and the plugin resolves it to the correct server path

## Requirements

- WordPress 5.5+
- PHP 7.4+
- Either **Ghostscript** (`/usr/bin/gs`) OR the **Imagick** PHP extension (for PDF rendering)

## Installation

1. Download/clone into `wp-content/plugins/texon-flipbook/`
2. Activate **Texon Flipbook** in the WordPress admin
3. Go to **Flipbooks** in the admin sidebar → **Add New**

## Usage

### Create a flipbook

1. **Flipbooks → Add New**
2. Title it (e.g., "2026 Product Guide")
3. Paste the PDF URL or filesystem path into the PDF Path field, or click **Choose from Media Library**
4. Save — the plugin renders all pages to JPGs (~1–2 seconds per page)

### Add hotspots

1. Click **Edit Hotspots →** from the edit screen
2. Select a page from the dropdown
3. **Click and drag** on the page to draw a hotspot
4. Enter the destination URL when prompted
5. **Click a hotspot** to edit its URL, **drag** to move, **drag the handle** to resize, **right-click** to delete
6. Changes autosave

### Embed on a page

Use one of these shortcodes:

```
[texon_flipbook id="123"]
[texon_flipbook id="123" height="800"]
[texon_flipbook id="123" trigger="button" label="Browse Our 2026 Catalog"]
```

- `id` — the flipbook ID (shown on the Flipbooks list screen)
- `trigger` — `inline` (default) or `button` (opens in a modal)
- `label` — button text when `trigger="button"`
- `height` — inline embed height in px (default: `700`)

## Architecture

- `texon-flipbook.php` — main plugin bootstrap
- `includes/class-post-type.php` — `texon_flipbook` custom post type for storing flipbooks
- `includes/class-renderer.php` — PDF → JPG rendering (Ghostscript or Imagick)
- `includes/class-admin.php` — admin screens, AJAX save, URL/path normalization
- `includes/class-shortcode.php` — `[texon_flipbook]` shortcode
- `assets/flipbook.js` / `.css` — front-end viewer
- `assets/admin.js` / `.css` — admin hotspot editor
- `vendor/page-flip.browser.js` — [StPageFlip](https://github.com/Nodlik/StPageFlip) v2.0.7 (MIT)

Hotspots are stored as percentages (0–1) of page dimensions, so they stay accurate at any viewer size.

## License

MIT
