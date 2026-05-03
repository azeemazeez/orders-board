<?php
/**
 * Plugin Name: Orders Board for WooCommerce
 * Plugin URI:  https://github.com/azeemazeez/orders-board
 * Description: A live Kanban-style board showing WooCommerce orders grouped by status.
 * Version:     1.1.0
 * Author:      Azeem Azeez
 * License:     GPL-2.0+
 * Text Domain: orders-board
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'ORDERS_BOARD_VERSION', '1.1.0' );
define( 'ORDERS_BOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'ORDERS_BOARD_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 * This must be done before WooCommerce initialises, so we hook early.
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/**
 * Boot the plugin after WooCommerce is confirmed active.
 */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Orders Board requires WooCommerce to be installed and active.', 'orders-board' )
                . '</p></div>';
        } );
        return;
    }

    require_once ORDERS_BOARD_PATH . 'includes/class-orders-board.php';
    Orders_Board::init();
} );
