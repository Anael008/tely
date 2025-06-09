<?php
defined('ABSPATH') or die;

class CheckoutTemplateReplacer
{
    /**
     * TemplatesReplacer process.
     *
     * @param string $content
     *
     * @return string $content
     */
    public static function process($content)
    {
        if (is_admin()) {
            return $content; //not need process in admin menu
        }
        $content = preg_replace_callback('/<\!--checkout_template-->([\s\S]+?)<\!--\/checkout_template-->/', 'self::_processCheckoutTemplate', $content);
        return '<div class="woocommerce">' . $content . '</div>';
    }

    /**
     * Process checkout template content
     *
     * @param array $checkoutTemplateMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutTemplate($checkoutTemplateMatch)
    {
        $checkoutTemplateHtml = $checkoutTemplateMatch[1];
        $checkoutTemplateHtml = preg_replace_callback('/<\!--checkout_billing_details_block-->([\s\S]+?)<\!--\/checkout_billing_details_block-->/', 'self::_processCheckoutBillingDetailsBlock', $checkoutTemplateHtml);
        $checkoutTemplateHtml = preg_replace_callback('/<\!--checkout_totals_block-->([\s\S]+?)<\!--\/checkout_totals_block-->/', 'self::_processCheckoutTotalsBlock', $checkoutTemplateHtml);
        return $checkoutTemplateHtml;
    }

    /**
     * @param array $checkoutTotalsBlockMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutTotalsBlock($checkoutTotalsBlockMatch)
    {
        $checkoutTotalsBlockHtml = $checkoutTotalsBlockMatch[1];
        $checkoutTotalsBlockHtml = preg_replace_callback('/<\!--checkout_block_header-->([\s\S]+?)<\!--\/checkout_block_header-->/', 'self::_processCheckoutTotalsBlockHeader', $checkoutTotalsBlockHtml);
        $checkoutTotalsBlockHtml = preg_replace_callback('/<\!--checkout_block_content-->([\s\S]+?)<\!--\/checkout_block_content-->/', 'self::_processCheckoutTotalsBlockContent', $checkoutTotalsBlockHtml);
        return $checkoutTotalsBlockHtml;
    }

    /**
     * @param array $checkoutTotalsBlockHeaderMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutTotalsBlockHeader($checkoutTotalsBlockHeaderMatch)
    {
        $checkoutTotalsBlockHeaderHtml = $checkoutTotalsBlockHeaderMatch[1];
        return preg_replace('/<\!--checkout_block_header_content-->[\s\S]+?<\!--\/checkout_block_header_content-->/', np_translate('Your order:'), $checkoutTotalsBlockHeaderHtml);
    }

    /**
     * @param array $checkoutTotalsBlockContentMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutTotalsBlockContent($checkoutTotalsBlockContentMatch)
    {
        $checkoutTotalsBlockContentHtml = $checkoutTotalsBlockContentMatch[1];
        $checkoutTotalsBlockContentHtml = preg_replace_callback('/<\!--checkout_totals_table-->([\s\S]+?)<\!--\/checkout_totals_table-->/', 'self::_processCheckoutTotalsTable', $checkoutTotalsBlockContentHtml);
        $checkoutTotalsBlockContentHtml = preg_replace_callback('/<\!--checkout_placeorder_form_container-->([\s\S]+?)<\!--\/checkout_placeorder_form_container-->/', 'self::_processCheckoutPlaceorderFormContainer', $checkoutTotalsBlockContentHtml);
        return $checkoutTotalsBlockContentHtml;
    }

    /**
     * @param array $checkoutTotalsTableContentMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutTotalsTable($checkoutTotalsTableContentMatch)
    {
        $checkoutTotalsTableContentHtml = $checkoutTotalsTableContentMatch[1];
        $checkoutTotalsTableContentHtml = self::_checkoutTableParser($checkoutTotalsTableContentHtml);
        $checkoutTotalsTableContentHtml = self::processCheckoutTotals($checkoutTotalsTableContentHtml);
        return $checkoutTotalsTableContentHtml;
    }

    /**
     * @param string $checkoutTotalsTableContentHtml Html
     *
     * @return array|string|string[]|null
     */
    public static function processCheckoutTotals($checkoutTotalsTableContentHtml)
    {
        $checkoutTotalsTableContentHtml = preg_replace('/<\!--checkout_price_subtotal_text-->([\s\S]+?)<\!--\/checkout_price_subtotal_text-->/', np_translate('Subtotal'), $checkoutTotalsTableContentHtml);
        $subTotalPrice = '';
        if (function_exists('wc_cart_totals_subtotal_html')) {
            ob_start();
            wc_cart_totals_subtotal_html();
            $subTotalPrice = ob_get_clean();
        }
        $checkoutTotalsTableContentHtml = preg_replace('/<\!--checkout_price_subtotal-->([\s\S]+?)<\!--\/checkout_price_subtotal-->/', $subTotalPrice, $checkoutTotalsTableContentHtml);
        $checkoutTotalsTableContentHtml = preg_replace('/<\!--checkout_price_total_text-->([\s\S]+?)<\!--\/checkout_price_total_text-->/', np_translate('Total'), $checkoutTotalsTableContentHtml);
        $totalPrice = '';
        if (function_exists('wc_cart_totals_order_total_html')) {
            ob_start();
            wc_cart_totals_order_total_html();
            $totalPrice = ob_get_clean();
        }
        $checkoutTotalsTableContentHtml = preg_replace('/<\!--checkout_price_total-->([\s\S]+?)<\!--\/checkout_price_total-->/', $totalPrice, $checkoutTotalsTableContentHtml);
        return $checkoutTotalsTableContentHtml;
    }

    /**
     * Prepare checkout total price table for render
     *
     * @param string $checkoutTotalsTableHtml Html
     *
     * @return array|string|string[]|null
     */
    private static function _checkoutTableParser($checkoutTotalsTableHtml)
    {
        $orderTable = '';
        if (preg_match('/(<table[\s\S]+?<\/table>)/', $checkoutTotalsTableHtml, $orderTableMatches)) {
            $orderTable = $orderTableMatches[1];
            update_option('checkout_review_order', $orderTable);
            if (preg_match('/<tr[\s\S]*?\/tr>/', $orderTable, $trContentMatches)) {
                $trContent = $trContentMatches[0];
                self::setStylesFromTr($trContent);
                ob_start();
                self::plugin_render_checkout_additional_options();
                do_action('woocommerce_review_order_before_order_total');
                $hooks = ob_get_clean();
                $orderTable = str_replace($trContent, $trContent . $hooks, $orderTable);
            }
            ob_start();
            self::plugin_render_checkout_products();
            $plugin_render_checkout_products = ob_get_clean();
            $orderTable = preg_replace('/(<tbody[^>]*>)/', '$1' . $plugin_render_checkout_products, $orderTable);
            $orderTable = preg_replace('/(<table[\s\S]+?class=")/', '$1woocommerce-checkout-review-order-table ', $orderTable);
            $orderTable = str_replace('</tbody>', do_action('woocommerce_review_order_after_order_total') . '</tbody>', $orderTable);
            ob_start();
            do_action('woocommerce_checkout_before_order_review');
            woocommerce_order_review();
            $hooks2 = ob_get_clean();
            $orderTable = preg_replace('/(<table[\s\S]+?<\/table>)/', '<form class="u-checkout-form-order">' . $hooks2 . '</form>', $orderTable);
        }
        return $orderTable;
    }

    /**
     * Add products to checkout total price table
     */
    public static function plugin_render_checkout_products()
    {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key)) {
                $trClass = get_option('checkout_trClass', '');
                $trStyle = get_option('checkout_trStyle', '');
                $td1Class = get_option('checkout_td1_class', '');
                $td1Style = get_option('checkout_td1_style', '');
                $td2Class = get_option('checkout_td2_class', '');
                $td2Style = get_option('checkout_td2_style', ''); ?>
                <tr style="<?php echo $trStyle; ?>"
                    class="<?php echo $trClass; ?> <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
                    <td style="<?php echo $td1Style; ?>" class="<?php echo $td1Class; ?>">
                        <?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key)) . '&nbsp;'; ?>
                        <?php echo apply_filters('woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf('&times;&nbsp;%s', $cart_item['quantity']) . '</strong>', $cart_item, $cart_item_key); ?>
                        <?php echo wc_get_formatted_cart_item_data($cart_item); ?>
                    </td>
                    <td style="<?php echo $td2Style; ?>" class="<?php echo $td2Class; ?>">
                        <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </td>
                </tr>
                <?php
            }
        }
        if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>

            <?php do_action('woocommerce_review_order_before_shipping'); ?>

            <?php wc_cart_totals_shipping_html(); ?>

            <?php do_action('woocommerce_review_order_after_shipping'); ?>

        <?php endif;
    }

    /**
     * Add shipping methods or another additional options in checkout total price table
     */
    public static function plugin_render_checkout_additional_options()
    {
        $trClass = get_option('checkout_trClass', '');
        $trStyle = get_option('checkout_trStyle', '');
        $td1Class = get_option('checkout_td1_class', '');
        $td1Style = get_option('checkout_td1_style', '');
        $td2Class = get_option('checkout_td2_class', '');
        $td2Style = get_option('checkout_td2_style', '');
        if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) : ?>
            <?php if ('itemized' === get_option('woocommerce_tax_total_display')) : ?>
                <?php foreach (WC()->cart->get_tax_totals() as $code => $tax) : ?>
                    <tr style="<?php echo $trStyle; ?>"
                        class="<?php echo $trClass; ?> tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
                        <td style="<?php echo $td1Style; ?>"
                            class="<?php echo $td1Class; ?>"><?php echo esc_html($tax->label); ?></td>
                        <td style="<?php echo $td2Style; ?>"
                            class="<?php echo $td2Class; ?>"><?php echo wp_kses_post($tax->formatted_amount); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr style="<?php echo $trStyle; ?>" class="<?php echo $trClass; ?> tax-total">
                    <td style="<?php echo $td1Style; ?>"
                        class="<?php echo $td1Class; ?>"><?php echo esc_html(WC()->countries->tax_or_vat()); ?></td>
                    <td style="<?php echo $td2Style; ?>"
                        class="<?php echo $td2Class; ?>"><?php wc_cart_totals_taxes_total_html(); ?></td>
                </tr>
            <?php endif; ?>
        <?php endif;
    }

    /**
     * Save parts of "checkout total price table" like woocommerce templates parts
     *
     * @param string $trContent Html
     */
    public static function setStylesFromTr($trContent)
    {
        $trClass = '';
        if (preg_match('/<tr.*? class="([^"]+)/', $trContent, $trClassMatches)) {
            $trClass = $trClassMatches[1];
        }
        $trStyle = '';
        if (preg_match('/<tr.*? style="([^"]+)/', $trContent, $trStyleMatches)) {
            $trStyle = $trStyleMatches[1];
        }
        $td1Class = '';
        $td2Class = '';
        if (preg_match_all('/<td[\s\S]*? class="([^"]*)">/', $trContent, $tdClassMatches)) {
            $td1Class = !empty($tdClassMatches[1][0]) ? $tdClassMatches[1][0] : '';
            $td2Class = !empty($tdClassMatches[1][1]) ? $tdClassMatches[1][1] : '';
        }
        $td1Style = '';
        $td2Style = '';
        if (preg_match('/<td[\s\S]*? style="([^"]*)">/', $trContent, $tdStyleMatches)) {
            $td1Style = !empty($tdStyleMatches[1][0]) ? $tdStyleMatches[1][0] : '';
            $td2Style = !empty($tdStyleMatches[1][1]) ? $tdStyleMatches[1][1] : '';
        }
        update_option('checkout_trClass', $trClass);
        update_option('checkout_trStyle', $trStyle);
        update_option('checkout_td1_class', $td1Class);
        update_option('checkout_td1_style', $td1Style);
        update_option('checkout_td2_class', $td2Class);
        update_option('checkout_td2_style', $td2Style);
    }

    /**
     * @param array $checkoutPlaceorderFormContainerMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutPlaceorderFormContainer($checkoutPlaceorderFormContainerMatch)
    {
        $checkoutPlaceorderFormContainerHtml = $checkoutPlaceorderFormContainerMatch[1];
        $checkoutPlaceorderFormContainerHtml = preg_replace('/(<form[\s\S]*? action=")[^"]*"/', '$1' . esc_url(wc_get_checkout_url()) . '"', $checkoutPlaceorderFormContainerHtml);
        $checkoutPlaceorderFormContainerHtml = preg_replace('/(<form[\s\S]+?name=")[^"]*"/', '$1checkout"', $checkoutPlaceorderFormContainerHtml);
        $checkoutPlaceorderFormContainerHtml = str_replace('u-inner-form', 'u-inner-form checkout woocommerce-checkout ', $checkoutPlaceorderFormContainerHtml);
        $checkoutPlaceorderFormContainerHtml = str_replace(' u-form-vertical', '', $checkoutPlaceorderFormContainerHtml);
        $checkoutPlaceorderFormContainerHtml = preg_replace_callback('/<\!--checkout_payment_radio_button-->([\s\S]+?)<\!--\/checkout_payment_radio_button-->/', 'self::_processCheckoutPaymentRadioButton', $checkoutPlaceorderFormContainerHtml);
        $checkoutPlaceorderFormContainerHtml = preg_replace_callback('/<\!--checkout_payment_submit_button_parent-->([\s\S]+?)<\!--\/checkout_payment_submit_button_parent-->/', 'self::_processCheckoutPaymentSubmitButtonParent', $checkoutPlaceorderFormContainerHtml);
        return $checkoutPlaceorderFormContainerHtml;
    }

    /**
     * @param array $checkoutPaymentRadioButtonMatch Found element
     *
     * @return string
     */
    private static function _processCheckoutPaymentRadioButton($checkoutPaymentRadioButtonMatch)
    {
        $checkoutPaymentRadioButtonHtml = $checkoutPaymentRadioButtonMatch[1];
        $paymentClass = '';
        if (preg_match('/<div\s+class="([^"]+)"/', $checkoutPaymentRadioButtonHtml, $paymentClassMatches)) {
            $paymentClass = $paymentClassMatches[1];
        }
        update_option('checkout_payment_classes', $paymentClass);

        $paymentMethodsClass = '';
        if (preg_match('/<div[\s\S]+?<div[\s\S]*? class="([^"]*)"/', $checkoutPaymentRadioButtonHtml, $paymentMethodsClassMatches)) {
            $paymentMethodsClass = $paymentMethodsClassMatches[1];
        }
        update_option('checkout_payment_methods_classes', $paymentMethodsClass);

        $paymentLabelClass = '';
        if (preg_match('/<label\s+class="([^"]+)"/', $checkoutPaymentRadioButtonHtml, $paymentLabelClassMatches)) {
            $paymentLabelClass = $paymentMethodsClassMatches[1];
        }
        update_option('checkout_payment_label_classes', $paymentLabelClass);

        ob_start();
        woocommerce_checkout_payment();
        do_action('woocommerce_checkout_after_order_review');
        $actions = ob_get_clean();

        return '<div class="data-from-checkout-form" style="display:none;"></div>' . $actions;
    }

    /**
     * @param array $checkoutPaymentSubmitButtonParentMatch Found element
     *
     * @return string
     */
    private static function _processCheckoutPaymentSubmitButtonParent($checkoutPaymentSubmitButtonParentMatch)
    {
        $checkoutPaymentSubmitButtonParentHtml = $checkoutPaymentSubmitButtonParentMatch[1];
        $checkoutPaymentSubmitButtonParentHtml = preg_replace_callback('/<\!--checkout_payment_submit_button-->([\s\S]+?)<\!--\/checkout_payment_submit_button-->/', 'self::_processCheckoutPaymentSubmitButton', $checkoutPaymentSubmitButtonParentHtml);
        update_option('checkout_payment_submit_button', $checkoutPaymentSubmitButtonParentHtml);
        return '';
    }

    /**
     * @param array $checkoutPaymentSubmitButtonMatch Found element
     *
     * @return string
     */
    private static function _processCheckoutPaymentSubmitButton($checkoutPaymentSubmitButtonMatch)
    {
        $checkoutPaymentSubmitButtonHtml = $checkoutPaymentSubmitButtonMatch[1];
        $checkoutPaymentSubmitButtonHtml = str_replace('class', 'onclick="submitCheckoutForm();" id="np_place_order" class', $checkoutPaymentSubmitButtonHtml);
        $checkoutPaymentSubmitButtonHtml = preg_replace(
            '/<\!--checkout_payment_submit_button_content-->([\s\S]+?)<\!--\/checkout_payment_submit_button_content-->/',
            np_translate('Place order'),
            $checkoutPaymentSubmitButtonHtml
        );
        return $checkoutPaymentSubmitButtonHtml;
    }

    /**
     * @param array $checkoutBillingDetailsBlockMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutBillingDetailsBlock($checkoutBillingDetailsBlockMatch)
    {
        $checkoutBillingDetailsBlockHtml = $checkoutBillingDetailsBlockMatch[1];
        $checkoutBillingDetailsBlockHtml = preg_replace_callback('/<\!--checkout_block_header-->([\s\S]+?)<\!--\/checkout_block_header-->/', 'self::_processCheckoutBlockHeader', $checkoutBillingDetailsBlockHtml);
        $checkoutBillingDetailsBlockHtml = preg_replace_callback('/<\!--checkout_block_content-->([\s\S]+?)<\!--\/checkout_block_content-->/', 'self::_processCheckoutBlockContent', $checkoutBillingDetailsBlockHtml);
        return $checkoutBillingDetailsBlockHtml;
    }

    /**
     * @param array $checkoutBlockHeaderMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutBlockHeader($checkoutBlockHeaderMatch)
    {
        $checkoutBlockHeaderHtml = $checkoutBlockHeaderMatch[1];
        return preg_replace('/<\!--checkout_block_header_content-->[\s\S]+?<\!--\/checkout_block_header_content-->/', np_translate('Billing details:'), $checkoutBlockHeaderHtml);
    }

    /**
     * @param array $checkoutBlockContentMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutBlockContent($checkoutBlockContentMatch)
    {
        $checkoutBlockContentHtml = $checkoutBlockContentMatch[1];
        $checkoutBlockContentHtml = preg_replace_callback('/<\!--checkout_form_container-->([\s\S]+?)<\!--\/checkout_form_container-->/', 'self::_processCheckoutFormContainer', $checkoutBlockContentHtml);
        return $checkoutBlockContentHtml;
    }

    /**
     * @param array $checkoutFormContainerMatch Found element
     *
     * @return array|string|string[]|null
     */
    private static function _processCheckoutFormContainer($checkoutFormContainerMatch)
    {
        $checkoutFormContainerHtml = $checkoutFormContainerMatch[1];
        $checkoutFormContainerHtml = preg_replace_callback('/<\!--checkout_form-->([\s\S]+?)<\!--\/checkout_form-->/', 'self::_processCheckoutForm', $checkoutFormContainerHtml);
        return $checkoutFormContainerHtml;
    }

    /**
     * @param array $checkoutFormMatch Found element
     *
     * @return array|string|string[]
     */
    private static function _processCheckoutForm($checkoutFormMatch)
    {
        $checkoutFormHtml = $checkoutFormMatch[1];
        $checkoutFormHtml = str_replace(' u-form-vertical', '', $checkoutFormHtml);
        if (preg_match_all('/<\!--checkout_form_group-->([\s\S]+?)<\!--\/checkout_form_group-->/', $checkoutFormHtml, $checkoutFormGroupMatches)) {
            $firstCheckoutFormGroupHtml = !empty($checkoutFormGroupMatches[1][0]) ? $checkoutFormGroupMatches[1][0] : '';
            preg_match('/<div[^>]*class="([^"]*)"/', $firstCheckoutFormGroupHtml, $parentMatches);
            $checkout_billing_field_classes = isset($parentMatches[1]) ? $parentMatches[1] : '';
            update_option('checkout_billing_field_classes', $checkout_billing_field_classes);
            preg_match('/<label[^>]*class="([^"]*)"/', $firstCheckoutFormGroupHtml, $labelMatches);
            $checkout_billing_label_classes = isset($labelMatches[1]) ? $labelMatches[1] : '';
            update_option('checkout_billing_label_classes', $checkout_billing_label_classes);
            preg_match('/<input[^>]*class="([^"]*)"/', $firstCheckoutFormGroupHtml, $inputMatches);
            $checkout_billing_input_classes = isset($inputMatches[1]) ? $inputMatches[1] : '';
            update_option('checkout_billing_input_classes', $checkout_billing_input_classes);
        }
        ob_start();
        do_action('woocommerce_checkout_before_customer_details');
        do_action('woocommerce_checkout_billing');
        do_action('woocommerce_checkout_shipping');
        do_action('woocommerce_checkout_after_customer_details');
        $render_result = ob_get_clean();
        $checkoutFormHtml = preg_replace('/<\!--checkout_form_group-->([\s\S]*)<\!--\/checkout_form_group-->/', $render_result, $checkoutFormHtml);
        return $checkoutFormHtml;
    }
}
