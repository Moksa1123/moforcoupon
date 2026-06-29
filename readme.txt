=== Moksa Coupons for WooCommerce ===
Contributors: moksa0923
Tags: coupons, woocommerce, bogo, cart conditions, ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.2
WC requires at least: 10.7
WC tested up to: 10.9
Stable tag: 0.4.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

AI-first WooCommerce coupon toolkit: BOGO, cart conditions, role limits, scheduling, URL coupons, auto-apply and one-click templates.

== Description ==

Moksa Coupons for WooCommerce is a free, modular coupon toolkit that brings the premium coupon features merchants usually pay for — BOGO, cart conditions, scheduling, URL coupons and more — to every WooCommerce store, and makes every coupon action available to the WordPress Abilities API, the in-dashboard AI assistant and external AI tools over MCP.

Every feature is a separate module that is **off by default**; turn on only what you need under the plugin's own "Moksa 優惠券 → 設定" screen.

= Coupon features =

* **BOGO (Buy X Get Y)** — buy N of a product/category, get M free or discounted.
* **Cart conditions** — minimum subtotal / quantity, customer-history rules (first-order-only, min/max orders or spend), required/excluded products and categories.
* **Role restrictions** — limit a coupon to chosen user roles.
* **Scheduling** — start/end date-time and day-of-week / time-of-day windows, enforced at checkout.
* **Free gift** — auto-add a gift product to the cart when the coupon applies.
* **Shipping override** — make a coupon set free shipping or a percentage/fixed shipping discount.
* **Stacking control** — allow or block combining a coupon with other coupons.
* **Discount cap** — cap the maximum amount a percentage coupon can discount.
* **Auto-apply** — apply qualifying coupons automatically, no code needed.
* **URL coupons** — a pretty `/coupon/<code>` link (with server-side QR) and an optional `?coupon=` apply, that auto-applies on click.
* **Coupon templates** — 40+ ready-made presets (new customer, spend-and-save, free shipping, BOGO, seasonal sales…) you can apply, tweak and create in one click.
* **Reports** — per-coupon usage and total discount, cached hourly.
* **Front-end coupon wall** — a `[moforcoupon_coupons]` shortcode that lists your advertised coupons as copy-to-clipboard cards.

= AI, Abilities and MCP (optional) =

Every coupon action is registered as a WordPress Ability, so the same capability is reachable from:

* the **in-dashboard AI assistant** — create or look up coupons by typing in plain language (uses the WordPress AI Client, which you configure with your own provider);
* the **WordPress Abilities API** and REST API;
* an optional **MCP server** that exposes coupon abilities to external AI tools.

These features are off by default and respect WordPress capabilities — every ability and REST route checks the current user's `manage_woocommerce` (or a coupon-specific) capability. Destructive abilities exposed over external MCP stay disabled unless you separately opt in, and even then run as a confirm-then-apply (propose/apply) flow.

= Off by default, enable what you need =

Every feature is a separate module and ships turned off. The AI assistant, Abilities and MCP integrations build on the WordPress Abilities API and AI Client included in WordPress 7.0 (this plugin's minimum), but stay inactive until you switch them on; the core coupon features need no AI configuration at all.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/moforcoupon`, or install it from the Plugins screen.
2. Activate it. WooCommerce must be active.
3. Open **Moksa 優惠券 → 設定** (or, until you enable the independent menu, **WooCommerce → 優惠券設定**) and switch on the modules you want. Everything is off by default.

== Frequently Asked Questions ==

= Does it work without the AI features? =

Yes. All coupon features (BOGO, conditions, scheduling, URL coupons, templates, etc.) work on a standard WooCommerce store with no AI configuration. The AI assistant, Abilities and MCP are optional and off by default.

= Does the plugin send my data anywhere? =

No, not on its own. The plugin makes no outbound requests. The only way data leaves your site is if you enable the optional AI assistant — then the natural-language text you type is sent to the AI provider you configured in WordPress's AI Client. See "External services" below.

= Is it compatible with HPOS and block checkout? =

Yes. The plugin declares compatibility with WooCommerce High-Performance Order Storage and the cart/checkout blocks.

== External services ==

This plugin can connect to a third-party AI provider, but only when you, the site administrator, explicitly enable an optional feature, and only through infrastructure you configure yourself. The plugin bundles no AI SDK and ships with every AI/MCP feature turned OFF by default. The plugin makes no outbound HTTP requests of its own.

**AI coupon assistant (optional, off by default).** When you enable this feature, the natural-language text you type in the admin to create or query coupons — plus the coupon parameters needed to fulfil that request — is sent to the AI model provider you have configured in WordPress's AI Client (WordPress 7.0+). The plugin does not choose a provider, does not include or transmit any API keys of its own, and makes no AI request unless you enable the feature and configure a provider. Which provider receives the text, and the terms and privacy policy that apply, depend entirely on the AI Client connector you set up. Please review your chosen provider's terms of service and privacy policy.

**External MCP server (optional, off by default).** When you enable this feature, the plugin exposes coupon abilities to external AI tools over the Model Context Protocol. This is an inbound, authenticated endpoint: a request must come from a logged-in user with the `manage_woocommerce` capability. The plugin does not send your data to any external service through this feature; it only answers authorised incoming requests. Destructive abilities (create / update / delete coupons) remain disabled over external MCP unless you separately opt in.

== Screenshots ==

1. One-click coupon templates, grouped by goal with a category sidebar and a recently-used row.
2. Quick-configure a template — code, discount, usage limits, expiry, description — before creating the coupon.
3. Modular settings: switch on only the coupon features you need. Everything is off by default.
4. Every advanced coupon setting in one tabbed panel on the coupon editor (schedule, role, cart and customer conditions, BOGO, gift, shipping, stacking, URL, front-end).
5. Coupon management hub with at-a-glance counts, quick links and per-coupon reports.
6. Front-end coupon wall rendered by the [moforcoupon_coupons] shortcode.

== Changelog ==

= 0.4.0 =
* Marketing automation: a "My Account → 我的優惠券" page, post-purchase remarketing coupons, refer-a-friend rewards, birthday coupons, and expiry-reminder emails (a daily WP-Cron heartbeat).
* Store credit / cashback wallet: cashback becomes a real balance that is auto-applied at checkout and shown in My Account — with correct refund handling (refunds return spent credit and claw back reversed cashback).
* Gift cards: sell store credit as a product; the amount lands in the recipient's wallet, or is emailed to them as a one-off gift coupon when they have no account.
* AI coupon advisor: new abilities that suggest coupons from your own data (lift average order value, move slow-selling stock, duplicate the best performer) and audit coupons (expiring-but-unused, expired-but-live, over-discounting).
* Reports: a revenue / daily-trend overview and a per-campaign performance report.
* Free-shipping threshold nudge ("再買 NT$X 免運") on the cart and checkout.
* 34 WordPress Abilities in total (added remarketing config, revenue overview, campaign report, suggest / audit), all capability-checked and read-only-by-default over external MCP.
* Hardening: consistent coupon-type labels everywhere (no raw slugs in reports or emails), a prefix-based uninstall cleanup, and shared internals (CouponType / PersonalCoupon / OrderOnce / Cron) for less duplication.

= 0.3.0 =
* Tiered discounts, redesigned: choose the ladder basis (cart subtotal / quantity / weight), add/remove tier rows dynamically, and set each tier as a percentage OR a fixed amount — even mixing both in one ladder (the best actual discount for the cart wins). Fixed tiers distribute proportionally across the targeted lines.
* Advanced rule builder: product / category conditions now use WooCommerce's own search dropdown instead of raw IDs, and a new "this coupon's usage count" rule enables first-N-customer limits.
* New cart/checkout messaging: a "您總共省了 NT$X" savings summary and a tiered-coupon progress nudge ("再消費 NT$X 即可升級折扣") to drive average order value.
* Coupon list enhancements: an enabled/disabled status column, bulk enable/disable, and a one-click duplicate row action.
* CSV import / export of coupons (native fields plus tiered / advanced-rule JSON and campaign) for backup, audit and bulk editing.
* Live coupon-summary metabox on the editor showing the discount mechanic, enabled features and conflict warnings as you type.
* Front-end coupon cards: eligibility hints (滿額 / 限會員 / 含免運…), responsive single-column layout and accessibility (focus styles, copy announcement).
* Programmatic REST: a proper PUT/PATCH update route so integrations can edit a coupon without delete-and-recreate.
* AI / MCP: locale-aware assistant prompt, a structured field diff in the confirm card, more abilities (discovery, scheduled-coupon list, bulk reschedule expiry), and MCP resources + prompts in addition to tools.
* New rule type "this coupon's usage count" (enables first-N-customers limits) — 26 advanced rule types in all.
* New cashback / loyalty coupon type with its own editor tab (reward = percent of order or a fixed amount); grants a post-order reward via the moforcoupon_cashback_awarded hook for store-credit / points integrations.
* Coupon-settings metabox accessibility & polish: keyboard (arrow-key) tab navigation with ARIA roles, screen-reader labels on the tier table, and assorted layout hardening.
* New Gutenberg block for the coupon wall, a template keyword search, an AI-prompt + tier-percent + savings + report-row + coupon-saved filter surface for extensions, a translation .pot, and assorted performance fixes (report N+1, customer-history query caps, MCP cache invalidation).

= 0.2.0 =
* Tiered discounts: one coupon gives a different percentage by cart subtotal / quantity threshold (e.g. spend 1000 → 10% off, 2000 → 20% off).
* Visual AND/OR advanced rule builder with 25 condition types, including cart weight, shipping zone/country, payment method, ordered product/category, custom taxonomy and custom user / cart-item meta.
* Shipping-region and payment-method coupon conditions.
* More abilities for the WordPress Abilities API / AI assistant / MCP server: discovery & lookup (list rule types, settings schema, payment gateways, shipping zones, countries, validate rules), a coupon performance report, list / apply templates, plus propose-and-confirm shortcuts to create a tiered coupon, restore a deleted coupon and expire coupons immediately. New write abilities stay behind the same destructive opt-in and confirmation flow.
* 11 new Taiwan-focused coupon templates (tiered spend, advanced-rule combo, win-back, Taiwan-only free shipping, weight-based free shipping, capped percentage, auto-applied site-wide, VIP non-stacking, payment-specific, category-required, category buy-3-get-1).

= 0.1.0 =
* Initial release.
* Coupon features: BOGO, cart conditions (subtotal / quantity / customer history / required / excluded products and categories), role restrictions, scheduling (date-time and day/time windows), free gift, shipping override, stacking control, discount cap, auto-apply, URL coupons with QR, coupon templates, per-coupon reports, and a front-end coupon-wall shortcode.
* AI / platform: every coupon action registered as a WordPress Ability, an optional in-dashboard AI assistant via the WordPress AI Client, REST endpoints, and an optional MCP server. All AI/MCP features are off by default and capability-checked; destructive external MCP access is a separate opt-in with a propose/apply confirmation flow.
* Compatibility: WooCommerce HPOS and cart/checkout blocks.

== Upgrade Notice ==

= 0.4.0 =
Adds the full marketing-automation loop (My Account coupons, remarketing, referral, birthday, expiry reminders), a store-credit / cashback wallet with refund handling, gift cards, an AI coupon advisor, campaign + revenue reports, and a free-shipping nudge.

= 0.3.0 =
Adds dynamic tier rows, cart savings + tier-progress messaging, CSV import/export, a live editor summary, coupon-list bulk/duplicate tools, a cashback coupon type, a Gutenberg coupon-wall block, more AI/MCP abilities and a new advanced rule type.

= 0.2.0 =
Adds tiered discounts, a visual AND/OR advanced rule builder, shipping-region and payment conditions, more AI / MCP abilities, and 11 new Taiwan templates.

= 0.1.0 =
Initial public release.
