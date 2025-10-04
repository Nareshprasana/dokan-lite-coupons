<?php
// filepath: c:\Users\Naresh1\Local Sites\doken-plugin-coupon\app\public\wp-content\plugins\dokan-lite-coupons\dokan-lite-coupons.php

/**
 * Plugin Name: Dokan Lite Vendor Coupons
 * Description: Allows Dokan Lite vendors to create WooCommerce coupons.
 * Version: 1.0
 * Author: Naresh
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Activation: Ensure Dokan Lite is active
register_activation_hook( __FILE__, function() {
    if ( ! class_exists( 'WeDevs_Dokan' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'Dokan Lite must be installed and activated for this plugin to work.' );
    }
});

// Include logic files
require_once plugin_dir_path( __FILE__ ) . 'includes/coupon-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/dashboard.php';

// (Optional) Enqueue custom CSS + Select2
add_action( 'wp_enqueue_scripts', function() {
    if ( is_user_logged_in() && function_exists('dokan_is_seller_dashboard') && dokan_is_seller_dashboard() ) {
        wp_enqueue_style(
            'dokan-coupon-style',
            plugins_url( 'assets/coupon-style.css', __FILE__ ),
            array(),
            '1.0'
        );
        // Load Select2
        wp_enqueue_script('select2', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_style('select2-css', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    }
});

/**
 * Auto-apply coupon logic
 * Only applies coupons to cart items that match the vendor's selected events.
 */
add_action( 'woocommerce_before_cart', function() {
    if ( ! WC()->cart ) return;

    // Get all auto-apply coupons
    $coupons = get_posts([
        'post_type'   => 'shop_coupon',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_key'    => '_auto_apply',
        'meta_value'  => 'yes',
    ]);

    if ( empty($coupons) ) return;

    foreach ( $coupons as $coupon ) {
        $allowed_events = (array) get_post_meta($coupon->ID, '_eventin_event_ids', true);

        // Skip if coupon has no linked events
        if ( empty($allowed_events) ) {
            continue;
        }

        $apply_coupon = false;

        // Check cart for matching events
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $event_id   = get_post_meta($product_id, '_eventin_event_id', true);

            if ( $event_id && in_array( (int) $event_id, $allowed_events, true ) ) {
                $apply_coupon = true;
                break;
            }
        }

        // Apply coupon if match found
        $code = $coupon->post_title;
        if ( $apply_coupon && ! WC()->cart->has_discount( $code ) ) {
            WC()->cart->apply_coupon( $code );
        }
    }
});

/**
 * BOGO logic (Buy X Get Y)
 * Works only when a BOGO coupon is applied.
 */
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $applied_coupons = $cart->get_applied_coupons();
    if ( empty( $applied_coupons ) ) return;

    $bogo_coupon_found = false;
    $bogo_buy_qty = 1;
    $bogo_get_qty = 1;

    foreach ( $applied_coupons as $code ) {
        $coupon = get_page_by_title( $code, OBJECT, 'shop_coupon' );
        if ( $coupon && get_post_meta( $coupon->ID, '_bogo_coupon', true ) === 'yes' ) {
            $bogo_coupon_found = true;
            $bogo_buy_qty = max(1, intval(get_post_meta( $coupon->ID, '_bogo_buy_qty', true )));
            $bogo_get_qty = max(1, intval(get_post_meta( $coupon->ID, '_bogo_get_qty', true )));
            break;
        }
    }

    if ( ! $bogo_coupon_found ) return;

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        $qty = $cart_item['quantity'];
        $group_size = $bogo_buy_qty + $bogo_get_qty;

        if ( $qty >= $group_size ) {
            $free_qty = floor( $qty / $group_size ) * $bogo_get_qty;
            $product_price = $cart_item['data']->get_price();
            $discount = $free_qty * $product_price / $qty;
            $cart_item['data']->set_price( $product_price - $discount );
        }
    }
});
