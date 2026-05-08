# StatusBoard for WooCommerce

A WooCommerce plugin that adds a Kanban-style board to your WordPress admin, showing the most recent orders per status column. Columns are generated dynamically from whatever order statuses exist in your store, including any custom ones added by other plugins.

## Features

- Kanban board with one column per order status
- Each card shows: order number, customer name, order total, date placed, item count
- Quick-view to quickly see more information about an order
- Clean, readable design that fits the WordPress admin UI
- Settings page that allows you to set number of orders per column, which columns to show, the order of the columns and what permissions a user needs to view this board.

## Installation

1. Upload the `orders-board` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins > Installed Plugins**
3. Navigate to **StatusBoard** in the WP admin sidebar

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

## Notes

- Statuses are fetched live from `wc_get_order_statuses()`, so any custom statuses registered by other plugins will appear automatically.
- The board requires the `manage_woocommerce` capability.
- No database tables are created -- all data is read directly from WooCommerce.
