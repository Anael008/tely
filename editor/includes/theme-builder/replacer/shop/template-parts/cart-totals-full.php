<?php
/**
 * Cart totals
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-totals.php.
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

require_once APP_PLUGIN_PATH . 'includes/theme-builder/replacer/shop/CartTemplateReplacer.php';

ob_start(); ?>

    <div class="cart_totals <?php echo (WC()->customer->has_calculated_shipping()) ? 'calculated_shipping' : ''; ?>">

        <?php do_action('woocommerce_before_cart_totals'); ?>

        {cart_totals_title}

        {cart_totals_content}

        <?php do_action('woocommerce_after_cart_totals'); ?>

    </div>

<?php $generated_html = ob_get_clean();
$generated_html_header = get_option('cart_totals_template_header');
$generated_html_content = get_option('cart_totals_template_content');
if (!empty($generated_html_header) && !empty($generated_html_content)) {
    ob_start();
    include 'cart-totals.php';
    $templatePart = ob_get_clean();
    $generated_html = str_replace('{cart_totals_title}', CartTemplateReplacer::processCartTotalBlockHeader($generated_html_header), $generated_html);
    $generated_html_content = str_replace('[tr_template_parts]', $templatePart, $generated_html_content);
    $generated_html = str_replace('{cart_totals_content}', CartTemplateReplacer::processCartTotalBlockContent($generated_html_content), $generated_html);
    echo $generated_html;
}