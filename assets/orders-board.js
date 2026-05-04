/* global OrdersBoardData, jQuery, Sortable */
( function ( $ ) {
    'use strict';

    const { ajaxUrl, nonce, statuses, allStatuses, perColumn } = OrdersBoardData;

    // Server timestamp of the last full/poll fetch -- used for change detection.
    let lastTs       = 0;
    // Per-column page tracker for load more.
    let colPages     = {};
    // Per-column total count from server.
    let colTotals    = {};
    // Drag detection flag.
    let isDragging   = false;

    // =========================================================================
    // Status colours
    // =========================================================================

    const STATUS_COLORS = {
        'pending':    { bg: '#FEF3C7', text: '#92400E', dot: '#F59E0B' },
        'processing': { bg: '#DBEAFE', text: '#1E40AF', dot: '#3B82F6' },
        'on-hold':    { bg: '#FEF9C3', text: '#854D0E', dot: '#EAB308' },
        'completed':  { bg: '#DCFCE7', text: '#166534', dot: '#22C55E' },
        'cancelled':  { bg: '#FEE2E2', text: '#991B1B', dot: '#EF4444' },
        'refunded':   { bg: '#F3E8FF', text: '#6B21A8', dot: '#A855F7' },
        'failed':     { bg: '#FFE4E6', text: '#9F1239', dot: '#F43F5E' },
    };

    function getColor( slug ) {
        return STATUS_COLORS[ slug ] || { bg: '#F1F5F9', text: '#475569', dot: '#94A3B8' };
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    function timeAgo( ts ) {
        const diff = Math.floor( Date.now() / 1000 ) - ts;
        if ( diff < 60 )    return 'just now';
        if ( diff < 3600 )  return Math.floor( diff / 60 ) + 'm ago';
        if ( diff < 86400 ) return Math.floor( diff / 3600 ) + 'h ago';
        return Math.floor( diff / 86400 ) + 'd ago';
    }

    function getInitials( name ) {
        return ( name || 'G' )
            .split( ' ' )
            .filter( Boolean )
            .map( w => w[0] )
            .slice( 0, 2 )
            .join( '' )
            .toUpperCase();
    }

    function formatTime( date ) {
        return date.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit', second: '2-digit' } );
    }

    // =========================================================================
    // Card rendering
    // =========================================================================

    function buildCard( order ) {
        const customer = order.customer || 'Guest';
        const items    = order.item_count === 1 ? '1 item' : order.item_count + ' items';
        const age      = order.date_raw ? timeAgo( order.date_raw ) : '';

        return `<div class="ob-card"
                     data-order-id="${ order.id }"
                     data-order-number="${ escHtml( String( order.number ) ) }"
                     data-edit-url="${ escHtml( order.edit_url || '' ) }"
                     tabindex="0"
                     role="button"
                     aria-label="Order #${ order.number }, ${ escHtml( customer ) }">
                    <div class="ob-card-top">
                        <div class="ob-card-avatar" aria-hidden="true">${ getInitials( customer ) }</div>
                        <div class="ob-card-meta">
                            <span class="ob-card-number">#${ escHtml( String( order.number ) ) }</span>
                            <span class="ob-card-age">${ age }</span>
                        </div>
                        <div class="ob-card-total">${ order.total }</div>
                    </div>
                    <div class="ob-card-customer">${ escHtml( customer ) }</div>
                    <div class="ob-card-footer">
                        <span class="ob-card-date">${ escHtml( order.date ) }</span>
                        <span class="ob-card-items">${ items }</span>
                    </div>
                </div>`;
    }

    function buildLoadMoreBtn( slug ) {
        return `<button class="ob-load-more" data-status="${ slug }" data-page="2">Load more</button>`;
    }

    // =========================================================================
    // Board rendering (full build)
    // =========================================================================

    function buildBoard( data ) {
        const $board = $( '#ob-board' );
        $board.empty();
        colPages  = {};
        colTotals = {};

        Object.keys( statuses ).forEach( function ( slug ) {
            const col = data[ slug ];
            if ( ! col ) return;

            colPages[ slug ]  = 1;
            colTotals[ slug ] = col.total || 0;

            const color    = getColor( slug );
            const count    = col.orders.length;
            const hasMore  = colTotals[ slug ] > count;
            const cardsHtml = count > 0
                ? col.orders.map( buildCard ).join( '' )
                : '<div class="ob-empty">No orders</div>';

            const $col = $( `
                <div class="ob-column" data-status="${ slug }">
                    <div class="ob-col-header">
                        <span class="ob-col-pill" style="background:${ color.bg };color:${ color.text };">
                            <span class="ob-col-dot" style="background:${ color.dot };"></span>
                            ${ escHtml( col.label ) }
                        </span>
                        <span class="ob-col-count" data-status="${ slug }">${ colTotals[ slug ] }</span>
                    </div>
                    <div class="ob-col-cards" data-status="${ slug }">${ cardsHtml }</div>
                    ${ hasMore ? buildLoadMoreBtn( slug ) : '' }
                </div>` );

            $board.append( $col );
        } );

        initSortable();
    }

    // =========================================================================
    // Poll: merge incoming changes without rebuilding the board
    // =========================================================================

    function mergeChanges( changes ) {
        let newCount = 0;

        Object.keys( changes ).forEach( function ( slug ) {
            const incoming = changes[ slug ];
            if ( ! incoming || ! incoming.length ) return;

            const $cards = $( '.ob-col-cards[data-status="' + slug + '"]' );
            if ( ! $cards.length ) return;

            incoming.forEach( function ( order ) {
                const existing = $cards.find( '.ob-card[data-order-id="' + order.id + '"]' );
                const cardHtml = buildCard( order );

                if ( existing.length ) {
                    // Update existing card in place.
                    existing.replaceWith( cardHtml );
                } else {
                    // New card -- prepend and flash.
                    $cards.find( '.ob-empty' ).remove();
                    $cards.prepend( cardHtml );
                    $cards.find( '.ob-card[data-order-id="' + order.id + '"]' ).addClass( 'ob-card-new' );
                    setTimeout( function () {
                        $cards.find( '.ob-card[data-order-id="' + order.id + '"]' ).removeClass( 'ob-card-new' );
                    }, 2000 );
                    newCount++;
                    colTotals[ slug ] = ( colTotals[ slug ] || 0 ) + 1;
                    updateColCount( slug, 0 ); // Refresh count display.
                }
            } );
        } );

        return newCount;
    }

    // =========================================================================
    // Load more
    // =========================================================================

    function loadMore( slug ) {
        const nextPage = ( colPages[ slug ] || 1 ) + 1;
        const $btn     = $( '.ob-load-more[data-status="' + slug + '"]' );

        $btn.text( 'Loading...' ).prop( 'disabled', true );

        $.post( ajaxUrl, {
            action: 'orders_board_load_more',
            nonce:  nonce,
            status: slug,
            page:   nextPage,
        } )
        .done( function ( res ) {
            if ( ! res.success ) {
                $btn.text( 'Load more' ).prop( 'disabled', false );
                return;
            }

            const { orders, has_more, page } = res.data;
            colPages[ slug ] = page;

            const $cards = $( '.ob-col-cards[data-status="' + slug + '"]' );
            orders.forEach( function ( order ) {
                $cards.append( buildCard( order ) );
            } );

            if ( has_more ) {
                $btn.text( 'Load more' ).prop( 'disabled', false ).data( 'page', page + 1 );
            } else {
                $btn.remove();
            }
        } )
        .fail( function () {
            $btn.text( 'Load more' ).prop( 'disabled', false );
            showToast( 'Could not load more orders.', 'error' );
        } );
    }

    // =========================================================================
    // SortableJS -- drag to change status
    // =========================================================================

    function initSortable() {
        document.querySelectorAll( '.ob-col-cards' ).forEach( function ( el ) {
            Sortable.create( el, {
                group:       'orders-board',
                animation:   150,
                ghostClass:  'ob-card-ghost',
                chosenClass: 'ob-card-chosen',
                dragClass:   'ob-card-drag',

                onStart: function () {
                    isDragging = true;
                },

                onEnd: function ( evt ) {
                    setTimeout( function () { isDragging = false; }, 0 );

                    const orderId   = evt.item.dataset.orderId;
                    const newStatus = evt.to.dataset.status;
                    const oldStatus = evt.from.dataset.status;

                    if ( newStatus === oldStatus ) return;

                    // Remove "No orders" placeholder if destination had one.
                    $( evt.to ).find( '.ob-empty' ).remove();

                    // Add "No orders" placeholder if source column is now empty.
                    if ( $( evt.from ).find( '.ob-card' ).length === 0 ) {
                        $( evt.from ).append( '<div class="ob-empty">No orders</div>' );
                    }

                    colTotals[ oldStatus ] = Math.max( 0, ( colTotals[ oldStatus ] || 1 ) - 1 );
                    colTotals[ newStatus ] = ( colTotals[ newStatus ] || 0 ) + 1;
                    updateColCount( oldStatus, 0 );
                    updateColCount( newStatus, 0 );

                    evt.item.classList.add( 'ob-card-moved' );
                    setTimeout( function () { evt.item.classList.remove( 'ob-card-moved' ); }, 800 );

                    $.post( ajaxUrl, {
                        action:   'orders_board_update_status',
                        nonce:    nonce,
                        order_id: orderId,
                        status:   newStatus,
                    } )
                    .fail( function () {
                        evt.from.insertBefore( evt.item, evt.from.children[ evt.oldIndex ] || null );
                        colTotals[ newStatus ] = Math.max( 0, ( colTotals[ newStatus ] || 1 ) - 1 );
                        colTotals[ oldStatus ] = ( colTotals[ oldStatus ] || 0 ) + 1;
                        updateColCount( newStatus, 0 );
                        updateColCount( oldStatus, 0 );
                        showToast( 'Status update failed. Please try again.', 'error' );
                    } )
                    .done( function ( res ) {
                        if ( ! res.success ) {
                            showToast( 'Could not update status.', 'error' );
                        } else {
                            showToast( 'Order #' + evt.item.dataset.orderNumber + ' moved to ' + ( allStatuses[ newStatus ] || newStatus ), 'success' );
                        }
                    } );
                },
            } );
        } );
    }

    function updateColCount( slug ) {
        $( '.ob-col-count[data-status="' + slug + '"]' ).text( colTotals[ slug ] || 0 );
    }

    // =========================================================================
    // Toast
    // =========================================================================

    function showToast( message, type ) {
        const $t = $( '<div class="ob-toast ob-toast-' + type + '">' + escHtml( message ) + '</div>' );
        $( 'body' ).append( $t );
        setTimeout( function () { $t.addClass( 'ob-toast-show' ); }, 10 );
        setTimeout( function () {
            $t.removeClass( 'ob-toast-show' );
            setTimeout( function () { $t.remove(); }, 300 );
        }, 3000 );
    }

    // =========================================================================
    // Timestamp display
    // =========================================================================

    function updateTimestamp() {
        $( '#ob-timestamp' ).text( formatTime( new Date() ) );
    }

    // =========================================================================
    // Quick-view modal
    // =========================================================================

    function openModal( orderId ) {
        const $modal = $( '#ob-modal' );
        $modal.removeAttr( 'hidden' );
        $( '#ob-modal-title' ).text( '' );
        $( '#ob-modal-status' ).text( '' ).removeAttr( 'style' );
        $( '#ob-modal-edit-link' ).attr( 'href', '#' );
        $( '#ob-modal-body' ).html(
            '<div class="ob-loading"><div class="ob-spinner"></div><span>Loading...</span></div>'
        );

        $.post( ajaxUrl, {
            action:   'orders_board_get_order',
            nonce:    nonce,
            order_id: orderId,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                renderModal( res.data );
            } else {
                $( '#ob-modal-body' ).html( '<p class="ob-error">Could not load order details.</p>' );
            }
        } )
        .fail( function () {
            $( '#ob-modal-body' ).html( '<p class="ob-error">Network error. Please try again.</p>' );
        } );
    }

    function closeModal() {
        $( '#ob-modal' ).attr( 'hidden', true );
    }

    function renderModal( data ) {
        const card  = data.card;
        const color = getColor( card.status );
        const label = allStatuses[ card.status ] || card.status;

        $( '#ob-modal-title' ).text( 'Order #' + card.number );
        $( '#ob-modal-edit-link' ).attr( 'href', card.edit_url );
        $( '#ob-modal-status' ).text( label ).css( { background: color.bg, color: color.text } );

        const itemRows = data.line_items.map( function ( item ) {
            return '<tr>' +
                '<td class="ob-tbl-name">' + escHtml( item.name ) + '</td>' +
                '<td class="ob-tbl-qty">x' + item.qty + '</td>' +
                '<td class="ob-tbl-price">' + item.subtotal + '</td>' +
                '</tr>';
        } ).join( '' );

        const discountRow = data.discount
            ? '<tr class="ob-tbl-meta"><td colspan="2">Discount</td><td>' + data.discount + '</td></tr>' : '';

        const shippingRow = data.shipping_lines && data.shipping_lines.length
            ? '<tr class="ob-tbl-meta"><td colspan="2">Shipping</td><td>' + data.shipping_total + '</td></tr>' : '';

        const html =
            '<div class="ob-modal-cols">' +
                '<div class="ob-modal-main">' +
                    '<div class="ob-modal-section">' +
                        '<table class="ob-modal-table">' +
                            '<thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>' +
                            '<tbody>' + itemRows + '</tbody>' +
                            '<tfoot>' +
                                discountRow + shippingRow +
                                '<tr class="ob-tbl-meta"><td colspan="2">Tax</td><td>' + data.tax + '</td></tr>' +
                                '<tr class="ob-tbl-total"><td colspan="2">Total</td><td>' + data.total + '</td></tr>' +
                            '</tfoot>' +
                        '</table>' +
                    '</div>' +
                    ( data.note ? '<div class="ob-modal-section"><h4 class="ob-modal-section-title">Customer Note</h4><p class="ob-modal-note">' + escHtml( data.note ) + '</p></div>' : '' ) +
                '</div>' +
                '<div class="ob-modal-side">' +
                    '<div class="ob-modal-section">' +
                        '<h4 class="ob-modal-section-title">Customer</h4>' +
                        '<div class="ob-modal-customer-block">' +
                            '<div class="ob-modal-avatar">' + getInitials( card.customer || 'Guest' ) + '</div>' +
                            '<div>' +
                                '<div class="ob-modal-customer-name">' + escHtml( card.customer || 'Guest' ) + '</div>' +
                                ( data.billing.email ? '<a href="mailto:' + escHtml( data.billing.email ) + '" class="ob-modal-email">' + escHtml( data.billing.email ) + '</a>' : '' ) +
                                ( data.billing.phone ? '<div class="ob-modal-phone">' + escHtml( data.billing.phone ) + '</div>' : '' ) +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="ob-modal-section"><h4 class="ob-modal-section-title">Billing Address</h4><address class="ob-modal-address">' + ( data.billing.address || 'Not provided' ) + '</address></div>' +
                    ( data.shipping_address ? '<div class="ob-modal-section"><h4 class="ob-modal-section-title">Shipping Address</h4><address class="ob-modal-address">' + data.shipping_address + '</address></div>' : '' ) +
                    '<div class="ob-modal-section"><h4 class="ob-modal-section-title">Payment</h4><p class="ob-modal-payment">' + escHtml( data.payment || 'N/A' ) + '</p></div>' +
                    '<div class="ob-modal-section"><h4 class="ob-modal-section-title">Order Date</h4><p class="ob-modal-date">' + escHtml( card.date ) + '</p></div>' +
                '</div>' +
            '</div>';

        $( '#ob-modal-body' ).html( html );
    }

    // =========================================================================
    // Fetch: full load
    // =========================================================================

    function fetchOrders( isRefresh ) {
        const $board = $( '#ob-board' );

        if ( ! isRefresh ) {
            $board.html( '<div class="ob-loading"><div class="ob-spinner"></div><span>Loading orders...</span></div>' );
        } else {
            $board.addClass( 'ob-refreshing' );
        }

        $.post( ajaxUrl, { action: 'orders_board_get_orders', nonce: nonce } )
        .done( function ( res ) {
            if ( res.success && res.data ) {
                buildBoard( res.data.board );
                lastTs = res.data.ts;
                updateTimestamp();
                $( '#ob-new-badge' ).attr( 'hidden', true ).text( '' );
            } else {
                $board.html( '<div class="ob-error">Failed to load orders. Please try again.</div>' );
            }
        } )
        .fail( function () {
            $board.html( '<div class="ob-error">Network error. Please check your connection.</div>' );
        } )
        .always( function () {
            $board.removeClass( 'ob-refreshing' );
        } );
    }

    // =========================================================================
    // Poll: lightweight change check
    // =========================================================================

    function pollForChanges() {
        if ( ! lastTs ) return; // Board not loaded yet.

        $.post( ajaxUrl, {
            action: 'orders_board_poll_orders',
            nonce:  nonce,
            since:  lastTs,
        } )
        .done( function ( res ) {
            if ( ! res.success ) return;

            const { changes, ts } = res.data;
            lastTs = ts;
            updateTimestamp();

            if ( changes && Object.keys( changes ).length ) {
                const newCount = mergeChanges( changes );
                if ( newCount > 0 ) {
                    const $badge = $( '#ob-new-badge' );
                    $badge.removeAttr( 'hidden' ).text( '+' + newCount + ' new' );
                    setTimeout( function () { $badge.attr( 'hidden', true ).text( '' ); }, 5000 );
                }
            }
        } );
    }

    // =========================================================================
    // Init
    // =========================================================================

    $( function () {
        fetchOrders( false );

        // Manual refresh -- full reload.
        $( '#ob-refresh' ).on( 'click', function () {
            fetchOrders( true );
        } );

        // Poll every 30 seconds for changes only.
        setInterval( pollForChanges, 30000 );

        // Card click -- delegated, survives rebuilds.
        $( '#ob-board' ).on( 'click', '.ob-card', function () {
            if ( isDragging ) return;
            openModal( $( this ).data( 'order-id' ) );
        } );

        $( '#ob-board' ).on( 'keydown', '.ob-card', function ( e ) {
            if ( e.key === 'Enter' || e.key === ' ' ) {
                e.preventDefault();
                openModal( $( this ).data( 'order-id' ) );
            }
        } );

        // Load more.
        $( '#ob-board' ).on( 'click', '.ob-load-more', function () {
            loadMore( $( this ).data( 'status' ) );
        } );

        // Modal close.
        $( '#ob-modal-close, .ob-modal-backdrop' ).on( 'click', closeModal );
        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) closeModal();
        } );
    } );

} )( jQuery );
