=== Orders Board for WooCommerce ===
Contributors: azeemazeez
Tags: woocommerce, orders, kanban, order management
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A live Kanban-style board for WooCommerce orders grouped by status. Supports HPOS, drag-and-drop, and custom order statuses.

== Description ==

Orders Board adds a Kanban-style board to your WP admin, showing WooCommerce orders grouped by status column. Columns are generated dynamically from your store's order statuses, including any custom ones added by other plugins.

**Features**

* Kanban board with one column per order status
* Up to 10 most recent orders per column (configurable)
* Each card shows: order number, customer name, order total, date placed, item count
* Drag cards between columns to update order status
* Clicking a card opens the order edit screen
* Auto-refreshes every 30 seconds
* Manual refresh button
* HPOS (High-Performance Order Storage) compatible
* Role-based access control
* Clean, readable design that fits the WP admin UI

== Installation ==

1. Upload the `orders-board` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins > Installed Plugins**
3. Navigate to **Orders Board** in the WP admin sidebar

== Frequently Asked Questions ==

= Does it support custom order statuses? =

Yes. Columns are generated from `wc_get_order_statuses()`, so any statuses registered by other plugins appear automatically.

= Does it work with HPOS? =

Yes. The plugin declares HPOS compatibility and uses `wc_get_orders()` exclusively.

== Changelog ==

= 1.1.0 =
* Added role-based access control
* Added drag-and-drop status updates
* Added HPOS compatibility

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Adds role-based access control and drag-and-drop status updates.
