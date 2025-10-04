<?php
// filepath: wp-content/plugins/dokan-lite-coupons/includes/coupon-functions.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Create a WooCommerce coupon for the current vendor.
 */
function dokan_vendor_create_coupon( $coupon_code, $discount_type, $coupon_amount, $expiry_date, $auto_apply = 'no', $bogo_buy_qty = 1, $bogo_get_qty = 1 ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $vendor_id = get_current_user_id();

    // Check if coupon exists
    if ( get_page_by_title( $coupon_code, OBJECT, 'shop_coupon' ) ) {
        return new WP_Error( 'coupon_exists', 'Coupon code already exists.' );
    }

    $coupon = array(
        'post_title'   => $coupon_code,
        'post_content' => '',
        'post_status'  => 'publish',
        'post_author'  => $vendor_id,
        'post_type'    => 'shop_coupon'
    );

    $coupon_id = wp_insert_post( $coupon );

    if ( $coupon_id ) {
        dokan_vendor_save_coupon_meta( $coupon_id, $discount_type, $coupon_amount, $expiry_date, $auto_apply, $bogo_buy_qty, $bogo_get_qty );
        return $coupon_id;
    }

    return false;
}

/**
 * Update an existing WooCommerce coupon (vendor-only).
 */
function dokan_vendor_update_coupon( $coupon_id, $coupon_code, $discount_type, $coupon_amount, $expiry_date, $auto_apply = 'no', $bogo_buy_qty = 1, $bogo_get_qty = 1 ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $vendor_id = get_current_user_id();
    $coupon    = get_post( $coupon_id );

    if ( ! $coupon || $coupon->post_type !== 'shop_coupon' || $coupon->post_author != $vendor_id ) {
        return new WP_Error( 'not_allowed', 'You cannot edit this coupon.' );
    }

    // Update coupon post
    wp_update_post([
        'ID'         => $coupon_id,
        'post_title' => $coupon_code,
    ]);

    // Save meta
    dokan_vendor_save_coupon_meta( $coupon_id, $discount_type, $coupon_amount, $expiry_date, $auto_apply, $bogo_buy_qty, $bogo_get_qty );

    return $coupon_id;
}

/**
 * Save coupon meta (used by both create & update).
 */
function dokan_vendor_save_coupon_meta( $coupon_id, $discount_type, $coupon_amount, $expiry_date, $auto_apply, $bogo_buy_qty, $bogo_get_qty ) {
    update_post_meta( $coupon_id, 'discount_type', $discount_type );
    update_post_meta( $coupon_id, 'coupon_amount', $coupon_amount );
    update_post_meta( $coupon_id, 'expiry_date', $expiry_date );
    update_post_meta( $coupon_id, '_auto_apply', $auto_apply );

    if ( $expiry_date ) {
        update_post_meta( $coupon_id, 'date_expires', strtotime( $expiry_date . ' 23:59:59' ) );
    } else {
        delete_post_meta( $coupon_id, 'date_expires' );
    }

    if ( $discount_type === 'bogo' ) {
        update_post_meta( $coupon_id, '_bogo_coupon', 'yes' );
        update_post_meta( $coupon_id, '_bogo_buy_qty', $bogo_buy_qty );
        update_post_meta( $coupon_id, '_bogo_get_qty', $bogo_get_qty );
    } else {
        delete_post_meta( $coupon_id, '_bogo_coupon' );
        delete_post_meta( $coupon_id, '_bogo_buy_qty' );
        delete_post_meta( $coupon_id, '_bogo_get_qty' );
    }
}

/**
 * Restrict coupon usage:
 * - Coupon only applies to products/events belonging to the vendor who created it.
 * - Coupon only applies to selected Eventin events (saved in _eventin_event_ids).
 */
add_filter('woocommerce_coupon_is_valid_for_product', function ($valid, $product, $coupon, $values) {
    // Get the coupon post object
    $coupon_post = get_page_by_title($coupon->get_code(), OBJECT, 'shop_coupon');
    if ( ! $coupon_post ) {
        return $valid; // fallback to WooCommerce default
    }

    $coupon_author  = (int) $coupon_post->post_author; // vendor who created coupon
    $allowed_events = (array) get_post_meta($coupon_post->ID, '_eventin_event_ids', true);

    // If vendor did not select events, coupon is not valid
    if ( empty($allowed_events) ) {
        return false;
    }

    // Get product ID from cart
    $product_id = $product->get_id();

    // Eventin usually links product → event via meta (adjust if different key)
    $event_id = get_post_meta($product_id, '_eventin_event_id', true);

    // If product has no linked event, reject coupon
    if ( ! $event_id ) {
        return false;
    }

    // Coupon valid only if product's event is in vendor's allowed list
    if ( in_array((int) $event_id, $allowed_events) ) {
        return true;
    }

    return false;
}, 10, 4);
