<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $active_menu;
$active_menu = 'vendor-coupons';

$current_user_id = get_current_user_id();

// Handle coupon deletion
if ( isset($_GET['delete_coupon']) && current_user_can('delete_post', $_GET['delete_coupon']) ) {
    wp_delete_post( intval($_GET['delete_coupon']) );
    echo '<div class="dokan-alert dokan-alert-success">Coupon deleted.</div>';
}

// Handle coupon creation
if ( isset( $_POST['dvc_create_coupon'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'dvc_create_coupon_nonce' ) ) {
    $coupon_code   = sanitize_text_field( $_POST['coupon_code'] );
    $discount_type = sanitize_text_field( $_POST['discount_type'] );
    $coupon_amount = isset($_POST['coupon_amount']) ? floatval($_POST['coupon_amount']) : 0;
    $expiry_date   = sanitize_text_field( $_POST['expiry_date'] );
    $auto_apply    = isset( $_POST['auto_apply'] ) && $_POST['auto_apply'] === 'yes' ? 'yes' : 'no';
    $min_spend     = isset($_POST['min_spend']) ? floatval($_POST['min_spend']) : '';
    $max_spend     = isset($_POST['max_spend']) ? floatval($_POST['max_spend']) : '';
    $event_ids     = isset($_POST['event_ids']) && is_array($_POST['event_ids']) ? array_map('intval', $_POST['event_ids']) : [];

    if ( empty($event_ids) ) {
        echo '<div class="dokan-alert dokan-alert-danger">Please select at least one event.</div>';
    } else {
        $coupon = array(
            'post_title'   => $coupon_code,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => $current_user_id,
            'post_type'    => 'shop_coupon',
        );

        $new_coupon_id = wp_insert_post( $coupon );

        if ( $new_coupon_id ) {
            update_post_meta( $new_coupon_id, 'discount_type', $discount_type );
            update_post_meta( $new_coupon_id, 'coupon_amount', $coupon_amount );
            update_post_meta( $new_coupon_id, '_auto_apply', $auto_apply );
            update_post_meta( $new_coupon_id, 'minimum_amount', $min_spend );
            update_post_meta( $new_coupon_id, 'maximum_amount', $max_spend );
            update_post_meta( $new_coupon_id, '_eventin_event_ids', $event_ids );

            if ( $expiry_date ) {
                update_post_meta( $new_coupon_id, 'expiry_date', $expiry_date );
                update_post_meta( $new_coupon_id, 'date_expires', strtotime($expiry_date) );
            }

            echo '<div class="dokan-alert dokan-alert-success">Coupon created successfully!</div>';
        } else {
            echo '<div class="dokan-alert dokan-alert-danger">Failed to create coupon.</div>';
        }
    }
}
?>

<div class="dokan-dashboard-wrap">
    <?php dokan_get_template_part( 'global/dashboard-nav' ); ?>
    <div class="dokan-dashboard-content dokan-edit-account">
        <article class="dashboard-content-area">
            <header class="dokan-dashboard-header">
                <h1 class="entry-title">Create Coupon</h1>
            </header>

            <div class="dokan-dashboard-content-inner">
                <form method="post" class="dokan-form-horizontal">
                    <?php wp_nonce_field( 'dvc_create_coupon_nonce' ); ?>

                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Coupon Code</label>
                        <div class="dokan-w5">
                            <input type="text" name="coupon_code" class="dokan-form-control" required>
                        </div>
                    </div>

                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Discount Type</label>
                        <div class="dokan-w5">
                            <select name="discount_type" class="dokan-form-control" id="discount_type" required>
                                <option value="">-- Select Discount Type --</option>
                                <option value="percent">Percentage</option>
                                <option value="fixed_cart">Fixed Cart</option>
                            </select>
                        </div>
                    </div>

                    <div class="dokan-form-group discount-amount-group percent-group" style="display: none;">
                        <label class="dokan-w3 dokan-control-label">Percentage Discount (%)</label>
                        <div class="dokan-w5">
                            <input type="number" step="0.01" name="coupon_amount" class="dokan-form-control">
                        </div>
                    </div>

                    <div class="dokan-form-group discount-amount-group fixed_cart-group" style="display: none;">
                        <label class="dokan-w3 dokan-control-label">Fixed Amount (₹)</label>
                        <div class="dokan-w5">
                            <input type="number" step="0.01" name="coupon_amount" class="dokan-form-control">
                        </div>
                    </div>

                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Expiry Date & Time</label>
                        <div class="dokan-w5">
                            <input type="datetime-local" name="expiry_date" class="dokan-form-control">
                        </div>
                    </div>

                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Minimum Spend</label>
                        <div class="dokan-w5">
                            <input type="number" step="0.01" name="min_spend" class="dokan-form-control">
                        </div>
                    </div>
                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Maximum Spend</label>
                        <div class="dokan-w5">
                            <input type="number" step="0.01" name="max_spend" class="dokan-form-control">
                        </div>
                    </div>

                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Eventin Events <span style="color:red">*</span></label>
                        <div class="dokan-w5">
                            <select name="event_ids[]" id="event_ids" class="dokan-form-control" multiple required>
                                <?php
                                $vendor_events = get_posts([
                                    'post_type'      => 'eventin_event',
                                    'numberposts'    => -1,
                                    'post_status'    => 'publish',
                                    'author'         => $current_user_id
                                ]);

                                if ( $vendor_events ) {
                                    foreach ( $vendor_events as $event ) {
                                        echo '<option value="' . esc_attr($event->ID) . '">' . esc_html($event->post_title) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No events found.</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Select one or more events to apply the coupon.</p>
                        </div>
                    </div>

                    <div class="dokan-form-group">
                        <label class="dokan-w3 dokan-control-label">Automatic Apply</label>
                        <div class="dokan-w5">
                            <input type="checkbox" name="auto_apply" value="yes">
                        </div>
                    </div>

                    <div class="dokan-form-group">
                        <div class="dokan-w5 dokan-offset-3">
                            <input type="submit" name="dvc_create_coupon" class="dokan-btn dokan-btn-theme" value="Create Coupon">
                        </div>
                    </div>
                </form>

                <h2>Your Coupons</h2>
                <table class="dokan-table" style="width:100%;margin-top:24px;">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Min/Max Spend</th>
                            <th>Events</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $args = array(
                        'post_type'      => 'shop_coupon',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'author'         => $current_user_id,
                    );
                    $coupons = get_posts($args);
                    if ( $coupons ) {
                        foreach ( $coupons as $coupon ) {
                            $type = get_post_meta($coupon->ID, 'discount_type', true);
                            $amount = get_post_meta($coupon->ID, 'coupon_amount', true);
                            $min = get_post_meta($coupon->ID, 'minimum_amount', true);
                            $max = get_post_meta($coupon->ID, 'maximum_amount', true);
                            $event_ids = get_post_meta($coupon->ID, '_eventin_event_ids', true);
                            $event_titles = array_map('get_the_title', (array) $event_ids);
                            $expiry = get_post_meta($coupon->ID, 'expiry_date', true);
                            echo '<tr>';
                            echo '<td>' . esc_html($coupon->post_title) . '</td>';
                            echo '<td>' . esc_html($type) . '</td>';
                            echo '<td>' . esc_html($amount) . '</td>';
                            echo '<td>' . esc_html($min) . ' / ' . esc_html($max) . '</td>';
                            echo '<td>' . esc_html(implode(', ', $event_titles)) . '</td>';
                            echo '<td>' . esc_html($expiry) . '</td>';
                            echo '<td>
                                <a href="?edit_coupon=' . $coupon->ID . '" class="dokan-btn dokan-btn-sm dokan-btn-info">Edit</a>
                                <a href="?delete_coupon=' . $coupon->ID . '" class="dokan-btn dokan-btn-sm dokan-btn-danger" onclick="return confirm(\'Are you sure?\')">Delete</a>
                            </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7">No coupons found.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const discountType = document.getElementById('discount_type');
    const percentGroup = document.querySelector('.percent-group');
    const fixedGroup = document.querySelector('.fixed_cart-group');

    function toggleFields() {
        const type = discountType.value;
        percentGroup.style.display = (type === 'percent') ? '' : 'none';
        fixedGroup.style.display = (type === 'fixed_cart') ? '' : 'none';
    }

    discountType.addEventListener('change', toggleFields);
    toggleFields();

    // Initialize Select2 if loaded
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        jQuery('#event_ids').select2({
            placeholder: 'Select your events',
            allowClear: true,
            width: '100%'
        });
    }
});
</script>
