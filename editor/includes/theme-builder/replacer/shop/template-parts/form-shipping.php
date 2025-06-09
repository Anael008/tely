<?php
/**
 * Checkout shipping information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-shipping.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 999.3.0
 */

defined('ABSPATH') || exit;
?>
<div class="woocommerce-shipping-fields">
    <?php if (true === WC()->cart->needs_shipping_address()) : ?>

        <h3 id="ship-to-different-address">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input id="ship-to-different-address-checkbox"
                       class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" <?php checked(apply_filters('woocommerce_ship_to_different_address_checked', 'shipping' === get_option('woocommerce_ship_to_destination') ? 1 : 0), 1); ?>
                       type="checkbox" name="ship_to_different_address" value="1"/>
                <span><?php echo esc_html(np_translate('Ship to a different address?')); ?></span>
            </label>
        </h3>

        <div class="shipping_address">

            <?php do_action('woocommerce_before_checkout_shipping_form', $checkout); ?>

            <div class="woocommerce-shipping-fields__field-wrapper">
                <?php plugin_render_checkout_fields($checkout, 'shipping'); ?>
            </div>

            <?php do_action('woocommerce_after_checkout_shipping_form', $checkout); ?>

        </div>

    <?php endif; ?>
</div>
