<?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
    <tr class="cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
        <th class="u-align-left u-border-1 u-border-grey-dark-1 u-first-column u-table-cell u-table-cell-17"><?php wc_cart_totals_coupon_label($coupon); ?></th>
        <td class="u-align-left u-border-1 u-border-grey-dark-1 u-first-column u-table-cell u-table-cell-17"
            data-title="<?php echo esc_attr(wc_cart_totals_coupon_label($coupon, false)); ?>"><?php wc_cart_totals_coupon_html($coupon); ?></td>
    </tr>
<?php endforeach; ?>

<?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>

    <?php do_action('woocommerce_cart_totals_before_shipping'); ?>

    <?php wc_cart_totals_shipping_html(); ?>

    <?php do_action('woocommerce_cart_totals_after_shipping'); ?>

<?php elseif (WC()->cart->needs_shipping() && 'yes' === get_option('woocommerce_enable_shipping_calc')) : ?>

    <tr class="shipping">
        <th><?php echo esc_html(np_translate('Shipping')); ?></th>
        <td data-title="<?php echo esc_attr(np_translate('Shipping')); ?>"><?php woocommerce_shipping_calculator(); ?></td>
    </tr>

<?php endif; ?>

<?php foreach (WC()->cart->get_fees() as $fee) : ?>
    <tr class="fee">
        <th><?php echo esc_html($fee->name); ?></th>
        <td data-title="<?php echo esc_attr($fee->name); ?>"><?php wc_cart_totals_fee_html($fee); ?></td>
    </tr>
<?php endforeach; ?>

<?php
if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) {
    $taxable_address = WC()->customer->get_taxable_address();
    $estimated_text = '';

    if (WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping()) {
        /* translators: %s location. */
        $estimated_text = sprintf(' <small>' . esc_html(np_translate('(estimated for %s)')) . '</small>', WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]);
    }

    if ('itemized' === get_option('woocommerce_tax_total_display')) {
        foreach (WC()->cart->get_tax_totals() as $code => $tax) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            ?>
            <tr class="tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
                <th><?php echo esc_html($tax->label) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
                <td data-title="<?php echo esc_attr($tax->label); ?>"><?php echo wp_kses_post($tax->formatted_amount); ?></td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr class="tax-total">
            <th><?php echo esc_html(WC()->countries->tax_or_vat()) . $estimated_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
            <td data-title="<?php echo esc_attr(WC()->countries->tax_or_vat()); ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
        </tr>
        <?php
    }
} ?>

<?php do_action('woocommerce_cart_totals_before_order_total'); ?>
