=== StatusBoard for WooCommerce ===
Contributors: azeemazeez
Tags: woocommerce, orders, kanban, order management
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

StatusBoard is a live Kanban-style board for WooCommerce orders grouped by status. Supports HPOS, drag-and-drop, and custom order statuses.

== Description ==

StatusBoard adds a Kanban-style board to your WP admin, showing WooCommerce orders grouped by status column. Columns are generated dynamically from your store's order statuses, including any custom ones added by other plugins.

**Features**

* Kanban board with one column per order status
* Each card shows: order number, customer name, order total, date placed, item count
* Quick-view to quickly see more information about an order
* Clean, readable design that fits the WordPress admin UI
* Settings page that allows you to set number of orders per column, which columns to show, the order of the columns and what permissions a user needs to view this board.

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

= 1.1.2 =
* Renamed plugin to StatusBoard for WooCommerce
* Updated Sortable.js from 1.15.2 to 1.15.7

= 1.1.1 =
* Fix fatal error caused by refund orders being returned by wc_get_orders
* Fix "No orders" placeholder showing alongside dragged card

= 1.1.0 =
* Added role-based access control
* Added drag-and-drop status updates
* Added HPOS compatibility

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Adds role-based access control and drag-and-drop status updates.
