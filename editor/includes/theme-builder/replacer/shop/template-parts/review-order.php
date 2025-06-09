<?php
/**
 * Review order table
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/review-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 999.3.0
 */

defined('ABSPATH') || exit;

require_once APP_PLUGIN_PATH . 'includes/theme-builder/replacer/shop/CheckoutTemplateReplacer.php';

if (function_exists('wc_gzd_get_cart_total_taxes')) {
    // for plugin "Germanized for WooCommerce"
    remove_action('woocommerce_review_order_before_order_total', 'woocommerce_gzd_template_cart_total_tax', 1);
}
$orderTable = get_option('checkout_review_order');
if (!$orderTable) {
    $orderTable = '';
}

ob_start();
CheckoutTemplateReplacer::plugin_render_checkout_products();
$plugin_render_checkout_products = ob_get_clean();
$orderTable = preg_replace('/(<tbody[^>]*>)/', '$1' . $plugin_render_checkout_products, $orderTable);
$orderTable = preg_replace('/(<table[\s\S]+?class=")/', '$1woocommerce-checkout-review-order-table ', $orderTable);
if (preg_match('/<tr[\s\S]*?\/tr>/', $orderTable, $trContentMatches)) {
    $trContent = $trContentMatches[0];
    ob_start();
    CheckoutTemplateReplacer::plugin_render_checkout_additional_options();
    do_action('woocommerce_review_order_before_order_total');
    $hooks = ob_get_clean();
    $orderTable = str_replace($trContent, $trContent . $hooks, $orderTable);
}
$orderTable = str_replace('</tbody>', do_action('woocommerce_review_order_after_order_total') . '</tbody>', $orderTable);
$orderTable = CheckoutTemplateReplacer::processCheckoutTotals($orderTable);
echo $orderTable;
?>
