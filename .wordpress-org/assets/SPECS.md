# WordPress.org directory assets — specs

These images are **not** part of the plugin ZIP. After the plugin is approved, they go
into the SVN `assets/` directory (this `.wordpress-org/assets/` folder maps to it). They
control how the plugin looks on its wordpress.org directory page and in search results.

Drop the finished PNGs next to this file with these exact names.

## Required

| File | Size (px) | Purpose |
|------|-----------|---------|
| `icon-256x256.png` | 256×256 | Plugin icon (search results, plugin card). Square. |
| `icon-128x128.png` | 128×128 | Icon fallback for older/smaller displays. |
| `banner-772x250.png` | 772×250 | Header banner on the plugin page (standard DPI). |
| `banner-1544x500.png` | 1544×500 | Header banner (retina / hi-DPI). |

## Screenshots (must match the readme `== Screenshots ==` captions, in order)

| File | Shows |
|------|-------|
| `screenshot-1.png` | The Kissmetrics settings screen (API key + event checkboxes). |
| `screenshot-2.png` | The WooCommerce section of the settings screen. |
| `screenshot-3.png` | Tracked events in the Kissmetrics Live View. |

Screenshots have no fixed size but should be clear and reasonably sized (e.g. ~1200px wide).
`screenshot-1.png` and `screenshot-2.png` can be captured right now from the local test site:
`http://localhost:8080/wp-admin/options-general.php?page=kissmetrics`.

## Design notes
- Icon: Kissmetrics brand mark on a solid brand-color background; keep it legible at 128px.
- Banner: logo + a short tagline (e.g. "Behavioral analytics for WordPress & WooCommerce").
- Optional `icon.svg` may be supplied instead of the PNG icons (WordPress prefers SVG if available).
