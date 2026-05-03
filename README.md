# Orders Board

A WooCommerce plugin that adds a Kanban-style board to your WP admin, showing the most recent 10 orders per status column. Columns are generated dynamically from whatever order statuses exist in your store -- including any custom ones added by other plugins.

## Features

- Kanban board with one column per order status
- Up to 10 most recent orders per column
- Each card shows: order number, customer name, order total, date placed, item count
- Clicking a card opens the order edit screen
- Auto-refreshes every 60 seconds
- Manual refresh button
- Clean, readable design that fits the WP admin UI

## Installation

1. Upload the `orders-board` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins > Installed Plugins**
3. Navigate to **WooCommerce > Orders Board**

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

## Notes

- Statuses are fetched live from `wc_get_order_statuses()`, so any custom statuses registered by other plugins will appear automatically.
- The board requires the `manage_woocommerce` capability.
- No database tables are created -- all data is read directly from WooCommerce.
