<?php
// dashboard.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vendor Coupon Dashboard
 */
add_action( 'dokan_dashboard_content_inside_after', function() {

    if ( ! dokan_is_seller_dashboard() ) return;

    $vendor_id = get_current_user_id();
    $editing   = false;
    $coupon    = null;

    // ----------------------------
    // Detect edit mode
    // ----------------------------
    if ( isset($_GET['edit_coupon']) ) {
        $coupon_id = absint($_GET['edit_coupon']);
        $coupon    = get_post($coupon_id);

        if ( $coupon && $coupon->post_author == $vendor_id ) {
            $editing = true;
        } else {
            $coupon = null;
        }
    }

    // ----------------------------
    // Handle delete
    // ----------------------------
    if ( isset($_GET['delete_coupon']) ) {
        $coupon_id = absint($_GET['delete_coupon']);
        $coupon    = get_post($coupon_id);

        if ( $coupon && $coupon->post_author == $vendor_id ) {
            wp_delete_post($coupon_id, true);
            echo '<div class="notice notice-success"><p>Coupon deleted successfully.</p></div>';
        }
    }

    // ----------------------------
    // Handle create/update
    // ----------------------------
    if ( isset($_POST['dokan_coupon_nonce']) && wp_verify_nonce($_POST['dokan_coupon_nonce'], 'dokan_coupon_action') ) {
        $action        = sanitize_text_field($_POST['action']);
        $coupon_code   = sanitize_text_field($_POST['coupon_code']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $coupon_amount = floatval($_POST['coupon_amount']);
        $expiry_date   = sanitize_text_field($_POST['expiry_date']);
        $event_ids     = isset($_POST['event_ids']) ? array_map('intval', $_POST['event_ids']) : [];
        $auto_apply    = isset($_POST['auto_apply']) && $_POST['auto_apply'] === 'yes' ? 'yes' : 'no';
        $bogo_buy_qty  = isset($_POST['bogo_buy_qty']) ? intval($_POST['bogo_buy_qty']) : 1;
        $bogo_get_qty  = isset($_POST['bogo_get_qty']) ? intval($_POST['bogo_get_qty']) : 1;

        // ----------------------------
        // Create Coupon
        // ----------------------------
        if ( $action === 'create' ) {
            $coupon_id = dokan_vendor_create_coupon( $coupon_code, $discount_type, $coupon_amount, $expiry_date, $auto_apply, $bogo_buy_qty, $bogo_get_qty );
            if ( ! is_wp_error($coupon_id) ) {
                update_post_meta($coupon_id, '_eventin_event_ids', $event_ids);
                echo '<div class="notice notice-success"><p>Coupon created successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($coupon_id->get_error_message()) . '</p></div>';
            }
        }

        // ----------------------------
        // Update Coupon
        // ----------------------------
        if ( $action === 'update' && $editing && $coupon ) {
            $coupon_id = absint($_POST['coupon_id']);

            wp_update_post([
                'ID'         => $coupon_id,
                'post_title' => $coupon_code,
            ]);

            update_post_meta($coupon_id, 'discount_type', $discount_type);
            update_post_meta($coupon_id, 'coupon_amount', $coupon_amount);
            update_post_meta($coupon_id, 'expiry_date', $expiry_date);
            update_post_meta($coupon_id, '_auto_apply', $auto_apply);
            update_post_meta($coupon_id, '_eventin_event_ids', $event_ids);

            if ( $discount_type === 'bogo' ) {
                update_post_meta($coupon_id, '_bogo_buy_qty', $bogo_buy_qty);
                update_post_meta($coupon_id, '_bogo_get_qty', $bogo_get_qty);
            }

            echo '<div class="notice notice-success"><p>Coupon updated successfully.</p></div>';
        }
    }

    // ----------------------------
    // Form HTML
    // ----------------------------
    ?>
    <div class="dokan-coupons">
        <h2><?php echo $editing ? 'Edit Coupon' : 'Create New Coupon'; ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'dokan_coupon_action', 'dokan_coupon_nonce' ); ?>
            <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="coupon_id" value="<?php echo esc_attr($coupon->ID); ?>">
            <?php endif; ?>

            <p>
                <label>Coupon Code</label>
                <input type="text" name="coupon_code" value="<?php echo $editing ? esc_attr($coupon->post_title) : ''; ?>" required>
            </p>

            <p>
                <label>Discount Type</label>
                <select name="discount_type" id="discount_type" required>
                    <?php
                    $saved_type = $editing ? get_post_meta($coupon->ID, 'discount_type', true) : '';
                    $types = [
                        'percent'       => 'Percentage',
                        'fixed_cart'    => 'Fixed Cart',
                        'fixed_product' => 'Fixed Product',
                        'bogo'          => 'Buy One Get One'
                    ];
                    foreach ($types as $val => $label) {
                        printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($saved_type, $val, false), esc_html($label));
                    }
                    ?>
                </select>
            </p>

            <p class="discount_value_field">
                <label>Discount Value</label>
                <input type="number" step="0.01" name="coupon_amount" value="<?php echo $editing ? esc_attr(get_post_meta($coupon->ID, 'coupon_amount', true)) : ''; ?>">
            </p>

            <div class="bogo_fields" style="display: <?php echo ($saved_type === 'bogo') ? 'block' : 'none'; ?>;">
                <p>
                    <label>Buy Quantity</label>
                    <input type="number" name="bogo_buy_qty" value="<?php echo $editing ? esc_attr(get_post_meta($coupon->ID, '_bogo_buy_qty', true)) : 1; ?>" min="1">
                </p>
                <p>
                    <label>Get Quantity</label>
                    <input type="number" name="bogo_get_qty" value="<?php echo $editing ? esc_attr(get_post_meta($coupon->ID, '_bogo_get_qty', true)) : 1; ?>" min="1">
                </p>
            </div>

            <p>
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value="<?php echo $editing ? esc_attr(get_post_meta($coupon->ID, 'expiry_date', true)) : ''; ?>">
            </p>

            <p>
                <label>Linked Events</label>
                <?php
                $selected_events = $editing ? (array) get_post_meta($coupon->ID, '_eventin_event_ids', true) : [];
                $events = get_posts([
                    'post_type'   => 'etn_event',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'author'      => $vendor_id
                ]);
                ?>
                <select name="event_ids[]" id="event_ids" multiple style="width:100%">
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo esc_attr($event->ID); ?>" <?php selected(in_array($event->ID, $selected_events)); ?>>
                            <?php echo esc_html($event->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label><input type="checkbox" name="auto_apply" value="yes" <?php checked($editing && get_post_meta($coupon->ID, '_auto_apply', true), 'yes'); ?>> Auto Apply</label>
            </p>

            <p>
                <button type="submit" class="button button-primary"><?php echo $editing ? 'Update Coupon' : 'Create Coupon'; ?></button>
            </p>
        </form>
    </div>

    <hr>

    <div class="dokan-coupon-list">
        <h2>My Coupons</h2>
        <?php
        $coupons = get_posts([
            'post_type'   => 'shop_coupon',
            'post_status' => 'publish',
            'numberposts' => -1,
            'author'      => $vendor_id
        ]);

        if ($coupons):
        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Amount / BOGO</th>
                    <th>Expiry</th>
                    <th>Events</th>
                    <th>Auto Apply</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($coupons as $c): ?>
                <tr>
                    <td><?php echo esc_html($c->post_title); ?></td>
                    <td><?php echo esc_html(get_post_meta($c->ID, 'discount_type', true)); ?></td>
                    <td>
                        <?php
                        $type = get_post_meta($c->ID, 'discount_type', true);
                        if ($type === 'bogo') {
                            echo 'Buy ' . get_post_meta($c->ID, '_bogo_buy_qty', true) . ' Get ' . get_post_meta($c->ID, '_bogo_get_qty', true);
                        } else {
                            echo esc_html(get_post_meta($c->ID, 'coupon_amount', true));
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html(get_post_meta($c->ID, 'expiry_date', true)); ?></td>
                    <td>
                        <?php
                        $linked = (array) get_post_meta($c->ID, '_eventin_event_ids', true);
                        if ($linked) {
                            $names = array_map(function($id){ $ev = get_post($id); return $ev ? $ev->post_title : ''; }, $linked);
                            echo esc_html(implode(', ', $names));
                        } else echo '—';
                        ?>
                    </td>
                    <td><?php echo get_post_meta($c->ID, '_auto_apply', true) === 'yes' ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="?edit_coupon=<?php echo esc_attr($c->ID); ?>" class="button">Edit</a>
                        <a href="?delete_coupon=<?php echo esc_attr($c->ID); ?>" class="button" onclick="return confirm('Delete this coupon?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No coupons found.</p>
        <?php endif; ?>
    </div>

    <!-- Load Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
            jQuery('#event_ids').select2({placeholder:'Select your events',allowClear:true,width:'100%'});
        }

        const discountSelect = document.getElementById('discount_type');
        const bogoFields = document.querySelector('.bogo_fields');
        discountSelect.addEventListener('change', function() {
            if (this.value === 'bogo') {
                bogoFields.style.display = 'block';
                document.querySelector('.discount_value_field').style.display = 'none';
            } else {
                bogoFields.style.display = 'none';
                document.querySelector('.discount_value_field').style.display = 'block';
            }
        });
    });
    </script>
<?php
});

// ----------------------------
// Restrict coupon usage to vendor events
// ----------------------------
add_filter('woocommerce_coupon_is_valid_for_product', function($valid,$product,$coupon,$values){
    $coupon_post = get_page_by_title($coupon->get_code(), OBJECT, 'shop_coupon');
    if(!$coupon_post) return $valid;

    $coupon_author = (int)$coupon_post->post_author;
    $allowed_events = (array)get_post_meta($coupon_post->ID,'_eventin_event_ids',true);

    if($coupon_author != get_current_user_id()) return false;
    if(empty($allowed_events)) return false;

    $product_id = $product->get_id();
    $event_id = get_post_meta($product_id,'_eventin_event_id',true);
    if(!$event_id) return false;

    return in_array((int)$event_id,$allowed_events);
},10,4);
