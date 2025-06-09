<?php
/**
 * Action on wp_footer
 * Print backlink html
 *
 * @param int|string $template_id
 */
function wpFooterActions($template_id)
{
    if (Nicepage::$override_with_plugin) {
        $data_provider = np_data_provider($template_id);
        $backlink = $data_provider->getPageBacklink();
        echo $backlink;
    }
}

add_action(
    'wp_enqueue_scripts', function () {
        if (get_option('np_theme_appearance') === 'plugin-option'
            && (function_exists('is_shop') && (is_shop() || is_product_category() || is_product())
            || isset($_GET['product-id']) || isset($_GET['products-list']))
        ) {
            if (!empty($GLOBALS['pluginTemplatesExists'])) {
                wp_dequeue_style('woocommerce-general'); //disable woocommerce.css
                wp_dequeue_style('woocommerce-layout'); //disable woocommerce-layout.css
            }
        }
    },
    20
);

add_action('wp_enqueue_scripts', 'add_plugin_templates_scripts_and_styles', 1003);
/**
 * Add our woocommerce scripts and styles for plugin templates
 */
function add_plugin_templates_scripts_and_styles() {
    if (get_option('np_theme_appearance') === 'plugin-option') {
        wp_enqueue_style('theme-default-styles', APP_PLUGIN_URL . 'includes/theme-builder/css/style.css');
        wp_register_script('theme-default-scripts', APP_PLUGIN_URL . 'includes/theme-builder/js/scripts.js');
        wp_enqueue_script('theme-default-scripts');
        if (function_exists('is_shop') && (is_shop() || is_product_category() || is_product())) {
            wp_register_script('theme-woo-scripts', APP_PLUGIN_URL . 'includes/theme-builder/js/woocommerce.js', array());
            wp_enqueue_script('theme-woo-scripts');
            wp_enqueue_style('theme-woo-styles', APP_PLUGIN_URL . 'includes/theme-builder/css/woocommerce.css');
        }
    }
}

add_action('wp', 'remove_theme_npProductsJsonUrl');
/**
 * Remove theme productsJsonUrl for plugin templates
 */
function remove_theme_npProductsJsonUrl() {
    if (get_option('np_theme_appearance') === 'plugin-option'
        && (get_query_var('product-id', null) !== null
        || get_query_var('products-list', null) !== null
        || get_query_var('thank-you', null) !== null)
    ) {
        remove_action('wp_head', 'add_npProductsJsonUrl');
    }
}

add_filter('woocommerce_loop_add_to_cart_link', 'np_add_to_cart_button', 10, 2);
/**
 * Add np css class to add to cart button when plugin woo templates
 *
 * @param string $button_html
 * @param object $product
 *
 * @return string $button_html
 */
function np_add_to_cart_button($button_html, $product) {
    $additional_classes = isset($GLOBALS['addToCartClasses']) ? $GLOBALS['addToCartClasses'] : '';
    $button_html = str_replace('class="', 'class="' . $additional_classes . ' ', $button_html);
    return $button_html;
}


/**
 * Add default class for product comment button
 */
add_filter(
    'comment_form_defaults', function ($defaults) {
        if (isset($defaults['submit_button']) && Nicepage::$override_with_plugin) {
            $defaults['submit_button'] = str_replace('class="', 'class="u-btn ', $defaults['submit_button']);
        }
        return $defaults;
    }
);

add_filter('wc_get_template', 'override_cart_totals_template', 10, 5);
/**
 * Update cart totals after update price in cart template
 *
 * @param $template
 * @param $template_name
 * @param $args
 * @param $template_path
 * @param $default_path
 *
 * @return mixed|string
 */
function override_cart_totals_template($template, $template_name, $args, $template_path, $default_path) {
    if (get_option('np_theme_appearance') === 'plugin-option' && $template_name === 'cart/cart-totals.php') {
        $plugin_template_path = dirname(__FILE__) . '/replacer/shop/template-parts/cart-totals-full.php';
        if (file_exists($plugin_template_path)) {
            return $plugin_template_path;
        }
    }
    return $template;
}

// add our class to button "confirm order"
add_filter('woocommerce_order_button_html', 'filter_class_payment_button_from_plugin_template', 11, 3);
/**
 * Change template for order button in checkout template for plugin template
 *
 * @param $html
 *
 * @return string
 */
function filter_class_payment_button_from_plugin_template($html)
{
    if (get_option('np_theme_appearance') !== 'plugin-option') {
        return $html;
    }
    ob_start();
    $payment_button_html_path = dirname(__FILE__) . '/replacer/shop/template-parts/order-button.php';
    if (file_exists($payment_button_html_path)) {
        include $payment_button_html_path;
    }
    $button_with_our_class = ob_get_clean();
    $hidden_original_button = str_replace('button ', 'button u-form-control-hidden ', $html);
    return $button_with_our_class . $hidden_original_button;
}

add_filter('woocommerce_locate_template', 'plugin_woocommerce_template', 10, 3);
/**
 * Change template for checkout template parts for plugin template
 *
 * @param $template
 * @param $template_name
 * @param $template_path
 *
 * @return mixed|string
 */
function plugin_woocommerce_template($template, $template_name, $template_path)
{
    if (get_option('np_theme_appearance') !== 'plugin-option') {
        return $template;
    }
    if ($template_name === 'checkout/payment.php') {
        if (file_exists(dirname(__FILE__) . '/replacer/shop/template-parts/payment.php')) {
            $template = dirname(__FILE__) . '/replacer/shop/template-parts/payment.php';
        }
    }

    if ($template_name === 'checkout/payment-method.php') {
        if (file_exists(dirname(__FILE__) . '/replacer/shop/template-parts/payment-method.php')) {
            $template = dirname(__FILE__) . '/replacer/shop/template-parts/payment-method.php';
        }
    }

    if ($template_name === 'checkout/form-billing.php') {
        if (file_exists(dirname(__FILE__) . '/replacer/shop/template-parts/form-billing.php')) {
            $template = dirname(__FILE__) . '/replacer/shop/template-parts/form-billing.php';
        }
    }

    if ($template_name === 'checkout/form-shipping.php') {
        if (file_exists(dirname(__FILE__) . '/replacer/shop/template-parts/form-shipping.php')) {
            $template = dirname(__FILE__) . '/replacer/shop/template-parts/form-shipping.php';
        }
    }

    if ($template_name === 'checkout/review-order.php') {
        if (file_exists(dirname(__FILE__) . '/replacer/shop/template-parts/review-order.php')) {
            $template = dirname(__FILE__) . '/replacer/shop/template-parts/review-order.php';
        }
    }

    return $template;
}