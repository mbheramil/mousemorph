=== MouseMorph — AI Caricature Maker for WooCommerce ===
Contributors: pixelbin
Tags: caricature, AI, mouse, image generation, WooCommerce, product customizer, pixelbin
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.6
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your customers into fun mouse caricatures! Powered by PixelBin AI, users upload a photo, describe a scene, and generate a unique mouse character printed on their product.

== Description ==

**MouseMorph** integrates PixelBin's AI into your WooCommerce store. Customers upload a photo, optionally describe a fun scene, and the AI transforms them into an adorable mouse character — ready to print on merchandise.

= Key Features =

* **Photo to Mouse Caricature** — Upload any photo and the AI reimagines the person as a cute mouse character.
* **Scene Prompt** — Customers describe scenes, backgrounds, activities (optional — can be disabled).
* **Inspiration Chips** — One-click scene ideas: Space Mouse, Pirate Mouse, Rock Star, etc.
* **WooCommerce Integration** — Caricature attaches to the cart item and order for printing.
* **Content Moderation** — Three-layer filtering: blocked terms, pattern matching, NSFW image detection.
* **Per-Product Control** — Enable the caricature maker on specific products or all products.
* **Standalone Shortcode** — Use `[mousemorph]` anywhere, even without WooCommerce.
* **Admin Order View** — See the caricature and download link in order details.
* **Rate Limiting** — Configurable daily limits for guests and logged-in users.
* **Two AI Modes** — Choose between Design Variation Generator (vg.generate) or AI Image Editor (img.edit).

= How It Works =

1. Customer visits a product page with MouseMorph enabled.
2. They upload a clear photo (JPG, PNG, or WebP, up to 10 MB).
3. They optionally describe the scene or pick from inspiration chips.
4. The AI generates a mouse caricature based on their photo and description.
5. They preview the result and can regenerate if needed.
6. They add to cart — the caricature is attached to their order.
7. Your team sees the caricature in order details for printing.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via **Plugins** menu.
3. Go to **MouseMorph** in the admin sidebar.
4. Enter your **Cloud Name** and **API Token** from console.pixelbin.io.
5. Click **Test Connection** to verify.
6. Enable on products (WooCommerce tab) or use `[mousemorph]` shortcode.

= Requirements =

* WordPress 5.8+
* PHP 7.4+
* WooCommerce 7.0+ (optional — shortcode works without it)
* A PixelBin account with API access

== Changelog ==

= 1.2.2 =
* Fix: Auto-updater now clears cache on every WordPress update check so new releases show immediately.
* New: "Check for updates" link added to plugin action links on the Plugins page.
* Improvement: Reduced cache TTL from 6 hours to 2 hours.

= 1.2.1 =
* Fix: Mouse transformation prompt was not being sent to the API when no user scene was provided.
* Improvement: Updated default prompt to produce actual mouse character caricatures, not just 3D portraits.

= 1.2.0 =
* New: GitHub-based auto-updater — plugin now shows updates in WordPress Plugins page with one-click update.

= 1.1.1 =
* Fix: portrait/generate API requires `image` (singular) not `images` — fixes validation error.
* Fix: Double file extension (.jpg.jpg) caused by PixelBin auto-appending extension to uploaded name.

= 1.1.0 =
* Fix: Resolve Cloudflare hotlink protection (Error 1011) blocking generated caricature images.
* New: Use PixelBin URL Upload API to copy prediction outputs to CDN storage before downloading.
* Fix: Images now display correctly on product pages, cart, and order admin view.
* Improvement: Switched to portrait/generate Predictions API for proper 3D caricature output.
* Fix: Transient data consistency — local image URLs are now stored correctly for WooCommerce integration.

= 1.0.0 =
* Initial release.
* Photo upload with drag-and-drop.
* AI mouse caricature generation via PixelBin.
* Scene prompt with inspiration chips.
* WooCommerce cart and order integration.
* Content moderation (blocked terms + NSFW detection).
* Admin settings with tabbed UI.
* [mousemorph] standalone shortcode.
* Per-product enable/disable.
* Daily rate limiting.
* Configurable transformation method (vg.generate / img.edit).
