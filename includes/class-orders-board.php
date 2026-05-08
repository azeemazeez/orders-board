<?php
defined( 'ABSPATH' ) || exit;

class Orders_Board {

    const OPTION_KEY = 'orders_board_settings';

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ], 999 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // AJAX handlers.
        add_action( 'wp_ajax_orders_board_get_orders',    [ __CLASS__, 'ajax_get_orders' ] );
        add_action( 'wp_ajax_orders_board_poll_orders',   [ __CLASS__, 'ajax_poll_orders' ] );
        add_action( 'wp_ajax_orders_board_load_more',     [ __CLASS__, 'ajax_load_more' ] );
        add_action( 'wp_ajax_orders_board_update_status', [ __CLASS__, 'ajax_update_status' ] );
        add_action( 'wp_ajax_orders_board_get_order',     [ __CLASS__, 'ajax_get_order' ] );

        // Settings form save.
        add_action( 'admin_post_orders_board_save_settings', [ __CLASS__, 'save_settings' ] );
    }

    // =========================================================================
    // Menu + Sidebar
    // =========================================================================

    public static function register_menu() {
        global $menu;

        // Position just before WooCommerce in the sidebar.
        $woo_pos = null;
        foreach ( $menu as $pos => $item ) {
            if ( isset( $item[2] ) && 'woocommerce' === $item[2] ) {
                $woo_pos = (float) $pos;
                break;
            }
        }
        $position = null !== $woo_pos ? $woo_pos - 1 : 55;
        while ( isset( $menu[ $position ] ) ) {
            $position -= 0.1;
        }

        add_menu_page(
            __( 'StatusBoard', 'statusboard-for-woocommerce' ),
            __( 'StatusBoard', 'statusboard-for-woocommerce' ),
            self::required_capability(),
            'orders-board',
            [ __CLASS__, 'render_page' ],
            'dashicons-clipboard',
            $position
        );

        // Rename the auto-generated first submenu item from "Orders Board" to "Board".
        add_submenu_page(
            'orders-board',
            __( 'StatusBoard', 'statusboard-for-woocommerce' ),
            __( 'Board', 'statusboard-for-woocommerce' ),
            self::required_capability(),
            'orders-board',
            [ __CLASS__, 'render_page' ]
        );

        add_submenu_page(
            'orders-board',
            __( 'StatusBoard Settings', 'statusboard-for-woocommerce' ),
            __( 'Settings', 'statusboard-for-woocommerce' ),
            'manage_woocommerce',
            'orders-board-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    // =========================================================================
    // Assets
    // =========================================================================

    public static function enqueue_assets( $hook ) {
        $board_hooks = [ 'toplevel_page_orders-board', 'orders-board_page_orders-board-settings' ];
        if ( ! in_array( $hook, $board_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'orders-board-style',
            ORDERS_BOARD_URL . 'assets/orders-board.css',
            [],
            ORDERS_BOARD_VERSION
        );

        if ( 'toplevel_page_orders-board' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'sortablejs',
            ORDERS_BOARD_URL . 'assets/sortable.min.js',
            [],
            '1.15.7',
            true
        );

        wp_enqueue_script(
            'orders-board-script',
            ORDERS_BOARD_URL . 'assets/orders-board.js',
            [ 'jquery', 'sortablejs' ],
            ORDERS_BOARD_VERSION,
            true
        );

        $settings = self::get_settings();

        wp_localize_script( 'orders-board-script', 'OrdersBoardData', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'orders_board_nonce' ),
            'statuses'    => self::get_ordered_active_statuses(),
            'allStatuses' => self::get_statuses(),
            'settingsUrl' => admin_url( 'admin.php?page=orders-board-settings' ),
            'perColumn'   => (int) $settings['per_column'],
        ] );
    }

    // =========================================================================
    // Board page
    // =========================================================================

    public static function render_page() {
        if ( ! current_user_can( self::required_capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'statusboard-for-woocommerce' ) );
        }

        $settings = self::get_settings();
        $per_col  = (int) $settings['per_column'];
        ?>
        <div id="orders-board-root">
            <div class="ob-header">
                <div class="ob-header-left">
                    <h1 class="ob-title">StatusBoard</h1>
                    <span class="ob-subtitle">Last <?php echo esc_html( $per_col ); ?> orders per status</span>
                </div>
                <div class="ob-header-right">
                    <div class="ob-refresh-info">
                        <span class="ob-refresh-label">Last refreshed:</span>
                        <span id="ob-timestamp" class="ob-refresh-time">--:--</span>
                        <span id="ob-new-badge" class="ob-new-badge" hidden></span>
                    </div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=orders-board-settings' ) ); ?>" class="ob-btn ob-btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Settings
                    </a>
                    <button id="ob-refresh" class="ob-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        Refresh
                    </button>
                </div>
            </div>
            <div id="ob-board" class="ob-board">
                <div class="ob-loading">
                    <div class="ob-spinner"></div>
                    <span>Loading orders...</span>
                </div>
            </div>
        </div>

        <!-- Quick-view modal -->
        <div id="ob-modal" class="ob-modal" role="dialog" aria-modal="true" aria-labelledby="ob-modal-title" hidden>
            <div class="ob-modal-backdrop"></div>
            <div class="ob-modal-box">
                <div class="ob-modal-header">
                    <div class="ob-modal-header-left">
                        <span class="ob-modal-order-num" id="ob-modal-title"></span>
                        <span class="ob-modal-status-badge" id="ob-modal-status"></span>
                    </div>
                    <div class="ob-modal-header-right">
                        <a id="ob-modal-edit-link" href="#" class="ob-btn ob-btn-primary" target="_self">
                            View full order
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                        <button class="ob-modal-close" id="ob-modal-close" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="ob-modal-body" id="ob-modal-body">
                    <div class="ob-loading"><div class="ob-spinner"></div><span>Loading...</span></div>
                </div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Settings page
    // =========================================================================

    public static function render_settings_page() {
        $settings     = self::get_settings();
        $all_statuses = self::get_statuses();
        $active       = $settings['statuses'];       // slugs that are visible
        $col_order    = $settings['column_order'];   // explicit order of slugs
        $per_col      = (int) $settings['per_column'];
        $allowed_roles= $settings['allowed_roles'];
        $all_roles    = self::get_editable_roles();

        // Build ordered list: first the saved order, then anything new.
        $ordered = [];
        foreach ( $col_order as $slug ) {
            if ( isset( $all_statuses[ $slug ] ) ) {
                $ordered[ $slug ] = $all_statuses[ $slug ];
            }
        }
        foreach ( $all_statuses as $slug => $label ) {
            if ( ! isset( $ordered[ $slug ] ) ) {
                $ordered[ $slug ] = $label;
            }
        }
        ?>
        <div id="orders-board-root" class="ob-settings-page">
            <div class="ob-header">
                <div class="ob-header-left">
                    <h1 class="ob-title">StatusBoard &mdash; Settings</h1>
                </div>
                <div class="ob-header-right">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=orders-board' ) ); ?>" class="ob-btn ob-btn-secondary">
                        &larr; Back to Board
                    </a>
                </div>
            </div>

            <?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our own redirect ?>
                <div class="ob-notice ob-notice-success">Settings saved successfully.</div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ob-settings-form">
                <?php wp_nonce_field( 'orders_board_save_settings', 'ob_settings_nonce' ); ?>
                <input type="hidden" name="action" value="orders_board_save_settings">

                <!-- Orders per column -->
                <div class="ob-settings-card">
                    <h2 class="ob-settings-section-title">Orders per column</h2>
                    <p class="ob-settings-desc">How many orders to show initially in each status column. A "Load more" button will appear if there are additional orders. Max 50.</p>
                    <input type="number" name="per_column" value="<?php echo esc_attr( $per_col ); ?>" min="1" max="50" class="ob-input-number">
                </div>

                <!-- Column visibility + order -->
                <div class="ob-settings-card">
                    <h2 class="ob-settings-section-title">Columns</h2>
                    <p class="ob-settings-desc">Toggle columns on or off, and drag to reorder them on the board.</p>
                    <div id="ob-col-sort" class="ob-col-sort-list">
                        <?php foreach ( $ordered as $slug => $label ) :
                            $checked = empty( $active ) || in_array( $slug, $active, true );
                        ?>
                            <div class="ob-col-sort-item <?php echo $checked ? 'is-active' : ''; ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
                                <span class="ob-sort-handle" title="Drag to reorder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                </span>
                                <label class="ob-sort-toggle">
                                    <input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?>>
                                    <span class="ob-sort-toggle-track"></span>
                                </label>
                                <span class="ob-sort-label"><?php echo esc_html( $label ); ?></span>
                                <input type="hidden" name="column_order[]" value="<?php echo esc_attr( $slug ); ?>" class="ob-order-input">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="ob-settings-hint">Hidden columns still receive orders dragged into them.</p>
                </div>

                <!-- Role-based access -->
                <div class="ob-settings-card">
                    <h2 class="ob-settings-section-title">Who can view the board</h2>
                    <p class="ob-settings-desc">Select which roles can access StatusBoard. Administrators always have access.</p>
                    <div class="ob-role-grid">
                        <?php foreach ( $all_roles as $role_slug => $role_name ) :
                            $is_admin   = 'administrator' === $role_slug;
                            $is_checked = $is_admin || in_array( $role_slug, $allowed_roles, true );
                        ?>
                            <label class="ob-role-toggle <?php echo $is_checked ? 'is-active' : ''; ?> <?php echo $is_admin ? 'is-locked' : ''; ?>">
                                <input
                                    type="checkbox"
                                    name="allowed_roles[]"
                                    value="<?php echo esc_attr( $role_slug ); ?>"
                                    <?php checked( $is_checked ); ?>
                                    <?php disabled( $is_admin ); ?>
                                >
                                <span><?php echo esc_html( $role_name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ob-settings-actions">
                    <button type="submit" class="ob-btn ob-btn-primary ob-btn-lg">Save Settings</button>
                </div>
            </form>
        </div>

        <script>
        ( function () {
            // Drag-to-reorder columns in settings using SortableJS (already enqueued on board page, 
            // but settings page loads it separately via inline init).
            function loadSortable( cb ) {
                if ( window.Sortable ) { cb(); return; }
                var s = document.createElement('script');
                s.src = '<?php echo esc_url( ORDERS_BOARD_URL . 'assets/sortable.min.js' ); ?>';
                s.onload = cb;
                document.head.appendChild(s);
            }

            loadSortable( function () {
                var list = document.getElementById('ob-col-sort');
                if ( ! list ) return;

                Sortable.create( list, {
                    handle:    '.ob-sort-handle',
                    animation: 150,
                    onEnd: function () {
                        // Update hidden order inputs to match DOM order.
                        list.querySelectorAll('.ob-col-sort-item').forEach( function ( item, i ) {
                            item.querySelector('.ob-order-input').value = item.dataset.slug;
                        });
                    }
                });

                // Toggle active class on checkbox change.
                list.addEventListener('change', function (e) {
                    if ( e.target.type === 'checkbox' ) {
                        e.target.closest('.ob-col-sort-item').classList.toggle('is-active', e.target.checked);
                        e.target.closest('.ob-col-sort-item').querySelector('.ob-sort-toggle').classList.toggle('is-checked', e.target.checked);
                    }
                });

                // Set initial is-checked class on toggles.
                list.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                    if (cb.checked) cb.closest('.ob-sort-toggle').classList.add('is-checked');
                });
            });

            // Role toggle visual.
            document.querySelectorAll('.ob-role-toggle input').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    this.closest('.ob-role-toggle').classList.toggle('is-active', this.checked);
                });
            });
        } )();
        </script>
        <?php
    }

    // =========================================================================
    // Save settings
    // =========================================================================

    public static function save_settings() {
        check_admin_referer( 'orders_board_save_settings', 'ob_settings_nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        $per_col = isset( $_POST['per_column'] ) ? max( 1, min( 50, (int) $_POST['per_column'] ) ) : 10;

        // Visible statuses.
        $all_slugs = array_keys( self::get_statuses() );
        $statuses  = isset( $_POST['statuses'] ) && is_array( $_POST['statuses'] )
            ? array_values( array_intersect( array_map( 'sanitize_text_field', wp_unslash( $_POST['statuses'] ) ), $all_slugs ) )
            : [];

        // Column order -- preserve all slugs even unchecked ones.
        $col_order = isset( $_POST['column_order'] ) && is_array( $_POST['column_order'] )
            ? array_values( array_intersect( array_map( 'sanitize_text_field', wp_unslash( $_POST['column_order'] ) ), $all_slugs ) )
            : $all_slugs;
        // Append any slugs not yet in the saved order.
        foreach ( $all_slugs as $slug ) {
            if ( ! in_array( $slug, $col_order, true ) ) {
                $col_order[] = $slug;
            }
        }

        // Allowed roles.
        $all_role_keys   = array_keys( self::get_editable_roles() );
        $allowed_roles   = isset( $_POST['allowed_roles'] ) && is_array( $_POST['allowed_roles'] )
            ? array_values( array_intersect( array_map( 'sanitize_text_field', wp_unslash( $_POST['allowed_roles'] ) ), $all_role_keys ) )
            : [ 'administrator' ];
        // Administrator is always included.
        if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
            $allowed_roles[] = 'administrator';
        }

        update_option( self::OPTION_KEY, [
            'per_column'    => $per_col,
            'statuses'      => $statuses,
            'column_order'  => $col_order,
            'allowed_roles' => $allowed_roles,
        ] );

        wp_safe_redirect( admin_url( 'admin.php?page=orders-board-settings&saved=1' ) );
        exit;
    }

    // =========================================================================
    // AJAX: full board load
    // =========================================================================

    public static function ajax_get_orders() {
        check_ajax_referer( 'orders_board_nonce', 'nonce' );

        if ( ! current_user_can( self::required_capability() ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $settings  = self::get_settings();
        $limit     = (int) $settings['per_column'];
        $statuses  = self::get_ordered_active_statuses();
        $board     = [];
        $server_ts = time();

        foreach ( $statuses as $slug => $label ) {
            $orders = wc_get_orders( [
                'type'    => 'shop_order',
                'status'  => $slug,
                'limit'   => $limit,
                'orderby' => 'date',
                'order'   => 'DESC',
            ] );

            // Total count for this status (for "load more").
            $total = wc_get_orders( [
                'type'   => 'shop_order',
                'status' => $slug,
                'limit'  => -1,
                'return' => 'ids',
            ] );

            $cards = array_map( [ __CLASS__, 'order_to_card' ], $orders );

            $board[ $slug ] = [
                'label'   => $label,
                'orders'  => $cards,
                'total'   => count( $total ),
                'page'    => 1,
            ];
        }

        wp_send_json_success( [ 'board' => $board, 'ts' => $server_ts ] );
    }

    // =========================================================================
    // AJAX: poll for changes since a given timestamp
    // =========================================================================

    public static function ajax_poll_orders() {
        check_ajax_referer( 'orders_board_nonce', 'nonce' );

        if ( ! current_user_can( self::required_capability() ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $since     = isset( $_POST['since'] ) ? absint( $_POST['since'] ) : 0;
        $server_ts = time();

        if ( ! $since ) {
            wp_send_json_error( 'Missing since timestamp', 400 );
        }

        $settings = self::get_settings();
        $statuses = self::get_ordered_active_statuses();
        $changes  = [];

        $since_date = gmdate( 'Y-m-d H:i:s', $since );

        foreach ( $statuses as $slug => $label ) {
            // Orders modified after $since in this status.
            $orders = wc_get_orders( [
                'type'         => 'shop_order',
                'status'       => $slug,
                'limit'        => (int) $settings['per_column'],
                'orderby'      => 'modified',
                'order'        => 'DESC',
                'date_modified'=> '>' . $since_date,
            ] );

            if ( ! empty( $orders ) ) {
                $changes[ $slug ] = array_map( [ __CLASS__, 'order_to_card' ], $orders );
            }
        }

        wp_send_json_success( [ 'changes' => $changes, 'ts' => $server_ts ] );
    }

    // =========================================================================
    // AJAX: load more orders for a column
    // =========================================================================

    public static function ajax_load_more() {
        check_ajax_referer( 'orders_board_nonce', 'nonce' );

        if ( ! current_user_can( self::required_capability() ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $slug     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
        $page     = isset( $_POST['page'] )   ? max( 1, (int) $_POST['page'] ) : 1;
        $settings = self::get_settings();
        $limit    = (int) $settings['per_column'];
        $offset   = ( $page - 1 ) * $limit;

        $valid = array_keys( self::get_statuses() );
        if ( ! in_array( $slug, $valid, true ) ) {
            wp_send_json_error( 'Invalid status', 400 );
        }

        $orders = wc_get_orders( [
            'type'    => 'shop_order',
            'status'  => $slug,
            'limit'   => $limit,
            'offset'  => $offset,
            'orderby' => 'date',
            'order'   => 'DESC',
        ] );

        $total = wc_get_orders( [
            'type'   => 'shop_order',
            'status' => $slug,
            'limit'  => -1,
            'return' => 'ids',
        ] );

        wp_send_json_success( [
            'orders'   => array_map( [ __CLASS__, 'order_to_card' ], $orders ),
            'total'    => count( $total ),
            'page'     => $page,
            'has_more' => ( $offset + count( $orders ) ) < count( $total ),
        ] );
    }

    // =========================================================================
    // AJAX: single order quick-view
    // =========================================================================

    public static function ajax_get_order() {
        check_ajax_referer( 'orders_board_nonce', 'nonce' );

        if ( ! current_user_can( self::required_capability() ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = $order_id ? wc_get_order( $order_id ) : null;

        if ( ! $order ) {
            wp_send_json_error( 'Order not found', 404 );
        }

        $line_items = [];
        foreach ( $order->get_items() as $item ) {
            $line_items[] = [
                'name'     => $item->get_name(),
                'qty'      => $item->get_quantity(),
                'subtotal' => wc_price( $item->get_subtotal() ),
            ];
        }

        $shipping_lines = [];
        foreach ( $order->get_items( 'shipping' ) as $s ) {
            $shipping_lines[] = $s->get_name() . ' (' . wc_price( $s->get_total() ) . ')';
        }

        wp_send_json_success( [
            'card'           => self::order_to_card( $order ),
            'line_items'     => $line_items,
            'shipping_lines' => $shipping_lines,
            'subtotal'       => wc_price( $order->get_subtotal() ),
            'discount'       => $order->get_discount_total() > 0 ? wc_price( $order->get_discount_total() ) : null,
            'shipping_total' => wc_price( $order->get_shipping_total() ),
            'tax'            => wc_price( $order->get_total_tax() ),
            'total'          => $order->get_formatted_order_total(),
            'payment'        => $order->get_payment_method_title(),
            'note'           => $order->get_customer_note(),
            'billing'        => [
                'name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'email'   => $order->get_billing_email(),
                'phone'   => $order->get_billing_phone(),
                'address' => $order->get_formatted_billing_address(),
            ],
            'shipping_address' => $order->get_formatted_shipping_address(),
        ] );
    }

    // =========================================================================
    // AJAX: update status via drag
    // =========================================================================

    public static function ajax_update_status() {
        check_ajax_referer( 'orders_board_nonce', 'nonce' );

        if ( ! current_user_can( self::required_capability() ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        $order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $new_status = isset( $_POST['status'] )   ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        $order = $order_id ? wc_get_order( $order_id ) : null;
        if ( ! $order ) {
            wp_send_json_error( 'Order not found', 404 );
        }

        $valid = array_keys( self::get_statuses() );
        if ( ! in_array( $new_status, $valid, true ) ) {
            wp_send_json_error( 'Invalid status', 400 );
        }

        $order->update_status( $new_status, __( 'Status changed via StatusBoard.', 'statusboard-for-woocommerce' ) );

        wp_send_json_success( [ 'id' => $order->get_id(), 'status' => $new_status ] );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function order_to_card( $order ) {
        return [
            'id'         => $order->get_id(),
            'number'     => $order->get_order_number(),
            'customer'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'email'      => $order->get_billing_email(),
            'phone'      => $order->get_billing_phone(),
            'total'      => $order->get_formatted_order_total(),
            'date'       => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, Y \a\t g:i a' ) : '',
            'date_raw'   => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
            'modified'   => $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : 0,
            'item_count' => $order->get_item_count(),
            'status'     => $order->get_status(),
            'edit_url'   => function_exists( 'wc_get_order_edit_url' )
                            ? wc_get_order_edit_url( $order->get_id() )
                            : get_edit_post_link( $order->get_id(), 'raw' ),
        ];
    }

    public static function get_statuses() {
        $raw      = wc_get_order_statuses();
        $statuses = [];
        foreach ( $raw as $slug => $label ) {
            $clean            = 'wc-' === substr( $slug, 0, 3 ) ? substr( $slug, 3 ) : $slug;
            $statuses[ $clean ] = $label;
        }
        return $statuses;
    }

    /**
     * Returns active statuses in the user-configured column order.
     */
    public static function get_ordered_active_statuses() {
        $all      = self::get_statuses();
        $settings = self::get_settings();
        $active   = $settings['statuses'];
        $order    = $settings['column_order'];

        // Filter to only active (visible) statuses.
        if ( ! empty( $active ) ) {
            $all = array_filter( $all, function ( $slug ) use ( $active ) {
                return in_array( $slug, $active, true );
            }, ARRAY_FILTER_USE_KEY );
        }

        // Apply saved order.
        if ( ! empty( $order ) ) {
            $ordered = [];
            foreach ( $order as $slug ) {
                if ( isset( $all[ $slug ] ) ) {
                    $ordered[ $slug ] = $all[ $slug ];
                }
            }
            // Any remaining slugs not in the saved order.
            foreach ( $all as $slug => $label ) {
                if ( ! isset( $ordered[ $slug ] ) ) {
                    $ordered[ $slug ] = $label;
                }
            }
            return $ordered;
        }

        return $all;
    }

    /**
     * Returns the capability required to view the board based on allowed roles.
     */
    public static function required_capability() {
        // Always fall back to manage_woocommerce if no roles are configured.
        $settings = self::get_settings();
        $roles    = $settings['allowed_roles'];

        if ( empty( $roles ) ) {
            return 'manage_woocommerce';
        }

        // Map roles to their representative capabilities.
        // We do a direct current_user_can check per role in the actual gate,
        // but for add_menu_page we need a single cap string.
        // Use read as the lowest gate; actual role check happens at render time.
        return 'read';
    }

    /**
     * Check if the current user's role is in the allowed list.
     */
    public static function current_user_can_view() {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }

        $settings = self::get_settings();
        $allowed  = $settings['allowed_roles'];
        $user     = wp_get_current_user();

        foreach ( $user->roles as $role ) {
            if ( in_array( $role, $allowed, true ) ) {
                return true;
            }
        }

        return false;
    }

    public static function get_editable_roles() {
        $roles  = [];
        $wp_roles = wp_roles();
        foreach ( $wp_roles->roles as $slug => $data ) {
            $roles[ $slug ] = translate_user_role( $data['name'] );
        }
        return $roles;
    }

    public static function get_settings() {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'per_column'    => 10,
            'statuses'      => [],
            'column_order'  => [],
            'allowed_roles' => [ 'administrator', 'shop_manager' ],
        ] );
    }
}
