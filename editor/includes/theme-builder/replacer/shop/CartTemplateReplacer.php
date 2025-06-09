<?php
defined('ABSPATH') or die;

class CartTemplateReplacer
{

    public static $_product;
    public static $product_id;
    public static $cart_item;
    public static $cart_item_key;
    public static $product_permalink;

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
        $content = preg_replace_callback('/<\!--cart_template-->([\s\S]+?)<\!--\/cart_template-->/', 'self::_processCart', $content);
        return $content;
    }

    /**
     * Process cart template
     *
     * @param array $cartMatch Matches
     *
     * @return string
     */
    private static function _processCart($cartMatch)
    {
        $cartHtml = $cartMatch[1];
        $cartHtml = preg_replace_callback('/<\!--cart_table-->([\s\S]+?)<\!--\/cart_table-->/', 'self::_processCartTable', $cartHtml);
        $cartHtml = preg_replace_callback('/<\!--cart_continue_shopping-->([\s\S]+?)<\!--\/cart_continue_shopping-->/', 'self::_processCartContinueShopping', $cartHtml);
        $cartHtml = preg_replace_callback('/<\!--cart_update-->([\s\S]+?)<\!--\/cart_update-->/', 'self::_processCartUpdate', $cartHtml);
        $cartHtml = preg_replace_callback('/<\!--cart_block-->([\s\S]+?)<\!--\/cart_block-->/', 'self::_processCartBlock', $cartHtml);
        $cartHtml = preg_replace_callback('/<\!--cart_total_block-->([\s\S]+?)<\!--\/cart_total_block-->/', 'self::_processCartTotalBlock', $cartHtml);
        return $cartHtml;
    }

    /**
     * Process cart table
     *
     * @param array $tableMatch Matches
     *
     * @return string
     */
    private static function _processCartTable($tableMatch)
    {
        $cartTableHtml = $tableMatch[1];
        $cartTableHtml = self::_processCartProductsTitles($cartTableHtml);
        $cartTableHtml = preg_replace_callback('/<\!--cart_products_body-->([\s\S]+?)<\!--\/cart_products_body-->/', 'self::_processCartProductsBody', $cartTableHtml);
        $cartTableHtml = preg_replace('/(<table[\s\S]+?class=")/', '$1woocommerce-cart-form__contents ', $cartTableHtml);
        $cartTableHtml = preg_replace('/<\!--cart_tfoot-->([\s\S]*?)<\!--\/cart_tfoot-->/', '', $cartTableHtml);
        ob_start();
        do_action('woocommerce_cart_totals_after_order_total');
        $woocommerce_cart_totals_after_order_total = ob_get_clean();
        $cartTableHtml = str_replace('</table>', $woocommerce_cart_totals_after_order_total . '</table>', $cartTableHtml);
        return '<form class="woocommerce-cart-form" action="' . esc_url(wc_get_cart_url()) . '" method="post">' . $cartTableHtml . '</form>';
    }

    /**
     * Process cart table titles
     *
     * @param array $tableHtml html
     *
     * @return string
     */
    private static function _processCartProductsTitles($tableHtml)
    {
        $callback = function ($matches) {
            $text = trim($matches[1]);
            switch ($text) {
            case 'Product':
                return np_translate('Product');
            case 'Price':
                return np_translate('Price');
            case 'Quantity':
                return np_translate('Quantity');
            case 'Subtotal':
                return np_translate('Subtotal');
            default:
                return $text;
            }
        };
        $result = preg_replace_callback('/<!--cart_th_header_content-->([\s\S]*?)<!--\/cart_th_header_content-->/s', $callback, $tableHtml);
        return $result;
    }

    /**
     * Process cart products body
     *
     * @param array $cartProductsBodyMatch Matches
     *
     * @return string
     */
    private static function _processCartProductsBody($cartProductsBodyMatch)
    {
        $cartProductsBodyHtml = $cartProductsBodyMatch[1];
        $pattern_item = '/<!--cart_product_table_item-->(.*?)<!--\/cart_product_table_item-->/s';
        $pattern_item_alt = '/<!--cart_product_table_item_alt-->(.*?)<!--\/cart_product_table_item_alt-->/s';
        preg_match($pattern_item, $cartProductsBodyHtml, $matches_item);
        preg_match($pattern_item_alt, $cartProductsBodyHtml, $matches_item_alt);

        $item_content = isset($matches_item[1]) ? $matches_item[1] : '';
        $item_alt_content = isset($matches_item_alt[1]) ? $matches_item_alt[1] : '';
        $cartProductsBodyHtml = preg_replace_callback([$pattern_item, $pattern_item_alt], 'self::_processClearProductsBody', $cartProductsBodyHtml);

        $items = [$item_content, $item_alt_content];
        $products_html = '';

        static $i = 0;
        if (!isset(WC()->cart)) {
            return $cartProductsBodyHtml;
        }
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $i++;
            self::$cart_item = $cart_item;
            self::$cart_item_key = $cart_item_key;
            self::$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            self::$product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
            self::$product_permalink = apply_filters('woocommerce_cart_item_permalink', self::$_product->is_visible() ? self::$_product->get_permalink(self::$cart_item) : '', self::$cart_item, self::$cart_item_key);
            if (self::$_product && self::$_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                $products_html .= self::_processCartProductTableItem($items[$i % 2]);
            }
        }

        do_action('woocommerce_cart_contents');
        ob_start(); ?>
        <tr style="display: none">
            <td colspan="6" class="actions">
                <?php if (wc_coupons_enabled()) { ?>
                    <div class="coupon">
                        <label for="coupon_code"><?php echo esc_html(np_translate('Coupon:')); ?></label> <input
                                type="text" name="coupon_code" class="input-text" id="coupon_code" value=""
                                placeholder="<?php echo esc_attr(np_translate('Coupon code')); ?>"/>
                        <button type="submit" class="button" name="apply_coupon"
                                value="<?php echo esc_attr(np_translate('Apply coupon')); ?>"><?php echo esc_attr(np_translate('Apply coupon')); ?></button>
                        <?php do_action('woocommerce_cart_coupon'); ?>
                    </div>
                <?php } ?>
                <button type="submit" class="button np-cart-update" name="update_cart"
                        value="<?php echo esc_attr(np_translate('Update cart')); ?>"><?php echo esc_html(np_translate('Update cart')); ?></button>
                <?php do_action('woocommerce_cart_actions'); ?>
                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
            </td>
        </tr>
        <?php $hiddenItem = ob_get_clean();

        $cartProductsBodyHtml = str_replace('<!--Cart_Products-->', $products_html . $hiddenItem, $cartProductsBodyHtml);
        return $cartProductsBodyHtml;
    }

    /**
     * Remove default products html and add placeholder
     *
     * @return string
     */
    private static function _processClearProductsBody()
    {
        static $replaced = false;
        if (!$replaced) {
            $replaced = true;
            return '<!--Cart_Products-->';
        }
        return '';
    }

    /**
     * Process product table item
     *
     * @param string $productTableItemHtml html
     *
     * @return string
     */
    private static function _processCartProductTableItem($productTableItemHtml)
    {
        $WooClasses = 'woocommerce-cart-form__cart-item ' . esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', self::$cart_item, self::$cart_item_key));
        $productTableItemHtml = str_replace('<tr', '<tr class="' . $WooClasses . '"', $productTableItemHtml);
        $productTableItemHtml = preg_replace_callback('/<\!--cart_product_table_remove_item-->([\s\S]+?)<\!--\/cart_product_table_remove_item-->/', 'self::_processProductRemove', $productTableItemHtml);
        $productTableItemHtml = preg_replace_callback('/<\!--cart_product_item_image-->([\s\S]+?)<\!--\/cart_product_item_image-->/', 'self::_processProductImage', $productTableItemHtml);
        $productTableItemHtml = preg_replace_callback('/<\!--cart_product_item_title-->([\s\S]+?)<\!--\/cart_product_item_title-->/', 'self::_processProductTitle', $productTableItemHtml);
        $productTableItemHtml = preg_replace_callback('/<\!--cart_product_item_price-->([\s\S]+?)<\!--\/cart_product_item_price-->/', 'self::_processProductPrice', $productTableItemHtml);
        $productTableItemHtml = preg_replace_callback('/<\!--cart_product_item_quantity-->([\s\S]+?)<\!--\/cart_product_item_quantity-->/', 'self::_processProductInput', $productTableItemHtml);
        $productTableItemHtml = preg_replace_callback('/<\!--cart_subtotal_price-->([\s\S]+?)<\!--\/cart_subtotal_price-->/', 'self::_processProductSubtotalPrice', $productTableItemHtml);
        return $productTableItemHtml;
    }

    /**
     * Process product remove
     *
     * @param string $productRemoveMatch match
     *
     * @return string
     */
    private static function _processProductRemove($productRemoveMatch)
    {
        $productRemoveHtml = $productRemoveMatch[1];
        $originalRemove = apply_filters(
            'woocommerce_cart_item_remove_link',
            sprintf(
                '<div class="product-remove" style="display:none"><a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a></div>',
                esc_url(wc_get_cart_remove_url(self::$cart_item_key)),
                esc_html(np_translate('Remove this item')),
                esc_attr(self::$product_id),
                esc_attr(self::$_product->get_sku())
            ),
            self::$cart_item_key
        );
        return $productRemoveHtml . $originalRemove;
    }

    /**
     * Process product image
     *
     * @param string $productImageMatch match
     *
     * @return string
     */
    private static function _processProductImage($productImageMatch)
    {
        $productImageHtml = $productImageMatch[1];
        preg_match('/class="([^"]+)"/', $productImageHtml, $classMatches);
        $classes = !empty($classMatches[1]) ? $classMatches[1] : '';
        $image = preg_replace('/class="/', 'class="' . $classes . ' ', self::$_product->get_image());
        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $image, self::$cart_item, self::$cart_item_key);
        if (!self::$product_permalink) {
            $result = $thumbnail;
        } else {
            $result = sprintf('<a href="%s">%s</a>', esc_url(self::$product_permalink), $thumbnail);
        }
        return $result;
    }

    /**
     * Process product title
     *
     * @param string $productTitleMatch match
     *
     * @return string
     */
    private static function _processProductTitle($productTitleMatch)
    {
        $productTitleHtml = $productTitleMatch[1];
        $productTitleHtml = preg_replace_callback(
            '/<a([^>]*)>[\s\S]+?<\/a>/', function ($matches) {
                $attributes = $matches[1];
                $hasHref = preg_match('/href=["\'][^"\']*["\']/', $attributes);
                if ($hasHref) {
                    $attributes = preg_replace('/href=["\'][^"\']*["\']/', sprintf('href="%s"', esc_url(self::$product_permalink)), $attributes);
                } else {
                    $attributes .= sprintf(' href="%s"', esc_url(self::$product_permalink));
                }
                if (!self::$product_permalink) {
                    return wp_kses_post(apply_filters('woocommerce_cart_item_name', self::$_product->get_name(), self::$cart_item, self::$cart_item_key) . '&nbsp;');
                } else {
                    return wp_kses_post(
                        apply_filters(
                            'woocommerce_cart_item_name', sprintf(
                                '<a %s>%s</a>',
                                $attributes,
                                self::$_product->get_name()
                            ),
                            self::$cart_item,
                            self::$cart_item_key
                        )
                    );
                }
            },
            $productTitleHtml
        );

        do_action('woocommerce_after_cart_item_name', self::$cart_item, self::$cart_item_key);

        $productTitleHtml .= wc_get_formatted_cart_item_data(self::$cart_item);

        return $productTitleHtml;
    }

    /**
     * Process product price
     *
     * @param string $productPriceMatch match
     *
     * @return string
     */
    private static function _processProductPrice($productPriceMatch)
    {
        $productPriceHtml = $productPriceMatch[1];
        $productPriceHtml = preg_replace_callback('/<\!--cart_product_item_old_price-->([\s\S]+?)<\!--\/cart_product_item_old_price-->/', 'self::_processProductOldPrice', $productPriceHtml);
        $productPriceHtml = preg_replace_callback('/<\!--cart_product_item_regular_price-->([\s\S]+?)<\!--\/cart_product_item_regular_price-->/', 'self::_processProductRegularPrice', $productPriceHtml);
        return $productPriceHtml;
    }

    /**
     * Process product old price
     *
     * @return string
     */
    private static function _processProductOldPrice()
    {
        return '';
    }

    /**
     * Process product regular price
     *
     * @param string $productRegularPriceMatch match
     *
     * @return string
     */
    private static function _processProductRegularPrice($productRegularPriceMatch)
    {
        $productRegularPriceHtml = $productRegularPriceMatch[1];
        $price = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price(self::$_product), self::$cart_item, self::$cart_item_key);
        $productRegularPriceHtml = preg_replace('/<\!--cart_product_item_regular_price_content-->([\s\S]+?)<\!--\/cart_product_item_regular_price_content-->/', $price, $productRegularPriceHtml);
        return $productRegularPriceHtml;
    }

    /**
     * Process product quantity
     *
     * @param string $productInputMatch match
     *
     * @return string
     */
    private static function _processProductInput($productInputMatch)
    {
        $productInputHtml = $productInputMatch[1];
        global $product;
        if (is_null($product)) {
            $product = $GLOBALS['product'];
        }
        if (self::$_product->is_sold_individually()) {
            $product_quantity = sprintf('1 <input type="hidden" name="cart[%s][qty]" value="1" />', self::$cart_item_key);
        } else {
            $cart_item_key = self::$cart_item_key;
            $product_quantity = woocommerce_quantity_input(
                array(
                    'input_name' => "cart[{$cart_item_key}][qty]",
                    'input_value' => self::$cart_item['quantity'],
                    'max_value' => self::$_product->get_max_purchase_quantity(),
                    'min_value' => '0',
                    'product_name' => self::$_product->get_name(),
                    'classes' => apply_filters('woocommerce_quantity_input_classes', array('input-text', 'qty', 'text', 'u-input'), $product),
                    'placeholder' => '',
                ),
                self::$_product,
                false
            );
        }
        $product_quantity = str_replace('class="quantity"', '', $product_quantity);
        $product_quantity = apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, self::$cart_item); // PHPCS: XSS ok.
        $productInputHtml = preg_replace('/<input[\s\S]*?>/', $product_quantity, $productInputHtml);
        return $productInputHtml;
    }

    /**
     * Process product subtotal price
     *
     * @param string $productSubtotalPriceMatch match
     *
     * @return string
     */
    private static function _processProductSubtotalPrice($productSubtotalPriceMatch)
    {
        $productSubtotalPriceHtml = $productSubtotalPriceMatch[1];
        $productSubtotalPriceHtml = preg_replace_callback('/<\!--cart_subtotal_old_price-->([\s\S]+?)<\!--\/cart_subtotal_old_price-->/', 'self::_processProductSubtotalOldPrice', $productSubtotalPriceHtml);
        $productSubtotalPriceHtml = preg_replace_callback('/<\!--cart_subtotal_regular_price-->([\s\S]+?)<\!--\/cart_subtotal_regular_price-->/', 'self::_processProductSubtotalRegularPrice', $productSubtotalPriceHtml);

        return $productSubtotalPriceHtml;
    }

    /**
     * Process product old price
     *
     * @return string
     */
    private static function _processProductSubtotalOldPrice()
    {
        return '';
    }

    /**
     * Process product regular price
     *
     * @return string
     */
    private static function _processProductSubtotalRegularPrice()
    {
        $price = apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal(self::$_product, self::$cart_item['quantity']), self::$cart_item, self::$cart_item_key);
        return $price;
    }

    /**
     * Process cart update button
     *
     * @param array $updateButtonMatch Matches
     *
     * @return string
     */
    private static function _processCartUpdate($updateButtonMatch)
    {
        $updateButtonHtml = $updateButtonMatch[1];
        $updateButtonHtml = preg_replace('/<\!--cart_update_content-->[\s\S]+?<\!--\/cart_update_content-->/', np_translate('Update cart'), $updateButtonHtml);
        return $updateButtonHtml;
    }

    /**
     * Process cart continue shopping button
     *
     * @param array $continueButtonMatch Matches
     *
     * @return string
     */
    private static function _processCartContinueShopping($continueButtonMatch)
    {
        $continueButtonHtml = $continueButtonMatch[1];
        $continueButtonHtml = str_replace('&nbsp;Continue Shopping', '&nbsp;' . np_translate('Continue shopping'), $continueButtonHtml);
        $continueButtonHtml = preg_replace('/href="[\s\S]+?"/', 'href="' . get_permalink(wc_get_page_id('shop')) . '"', $continueButtonHtml);
        return $continueButtonHtml;
    }

    /**
     * Process cart block with coupon
     *
     * @param array $cartBlockMatch Matches
     *
     * @return string
     */
    private static function _processCartBlock($cartBlockMatch)
    {
        $cartBlockHtml = $cartBlockMatch[1];
        $cartBlockHtml = preg_replace_callback('/<\!--cart_block_header-->([\s\S]+?)<\!--\/cart_block_header-->/', 'self::_processCartCouponHeader', $cartBlockHtml);
        $cartBlockHtml = preg_replace_callback('/<\!--cart_block_content-->([\s\S]+?)<\!--\/cart_block_content-->/', 'self::_processCartCouponContent', $cartBlockHtml);
        return $cartBlockHtml;
    }

    /**
     * Process cart coupon header
     *
     * @param array $cartCouponHeaderMatch Matches
     *
     * @return string
     */
    private static function _processCartCouponHeader($cartCouponHeaderMatch)
    {
        $cartCouponHeaderHtml = $cartCouponHeaderMatch[1];
        $cartCouponHeaderHtml = preg_replace('/<\!--cart_block_header_content-->[\s\S]+?<\!--\/cart_block_header_content-->/', np_translate('Coupon:'), $cartCouponHeaderHtml);
        return $cartCouponHeaderHtml;
    }

    /**
     * Process cart coupon content
     *
     * @param array $cartCouponContentMatch Matches
     *
     * @return string
     */
    private static function _processCartCouponContent($cartCouponContentMatch)
    {
        $cartCouponContentHtml = $cartCouponContentMatch[1];
        $cartCouponContentHtml = preg_replace_callback('/<\!--cart_block_content_input-->([\s\S]+?)<\!--\/cart_block_content_input-->/', 'self::_processCartCouponInput', $cartCouponContentHtml);
        $cartCouponContentHtml = preg_replace_callback('/<\!--cart_block_content_button-->([\s\S]+?)<\!--\/cart_block_content_button-->/', 'self::_processCartCouponButton', $cartCouponContentHtml);
        $cartCouponContentHtml = str_replace('<input type="submit" value="submit" class="u-form-control-hidden">', '', $cartCouponContentHtml);
        return $cartCouponContentHtml;
    }

    /**
     * Process cart coupon input
     *
     * @param array $cartCouponInputMatch Matches
     *
     * @return string
     */
    private static function _processCartCouponInput($cartCouponInputMatch)
    {
        $cartCouponInputHtml = $cartCouponInputMatch[1];
        $cartCouponInputHtml = preg_replace('/placeholder="[\s\S]+?"/', 'placeholder="' . np_translate('Coupon code.') . '"', $cartCouponInputHtml);
        return $cartCouponInputHtml;
    }

    /**
     * Process cart coupon button
     *
     * @param array $cartCouponButtonMatch Matches
     *
     * @return string
     */
    private static function _processCartCouponButton($cartCouponButtonMatch)
    {
        $cartCouponButtonHtml = $cartCouponButtonMatch[1];
        $cartCouponButtonHtml = preg_replace('/(<a[\s\S]*?>)([\s\S]*?)(<\/a>)/', '$1' . np_translate('Apply coupon') . '$3', $cartCouponButtonHtml);
        return $cartCouponButtonHtml;
    }

    /**
     * Process cart total block
     *
     * @param array $cartTotalBlockMatch Matches
     *
     * @return string
     */
    private static function _processCartTotalBlock($cartTotalBlockMatch)
    {
        $cartTotalBlockHtml = $cartTotalBlockMatch[1];
        $cartTotalBlockHtml = str_replace('$', '_dollar_symbol_', $cartTotalBlockHtml);
        preg_match('/<!--cart_block_header-->(.*?)<!--\/cart_block_header-->/s', $cartTotalBlockHtml, $matches_header);
        $cartBlockHeader = isset($matches_header[1]) ? $matches_header[1] : '';
        update_option('cart_totals_template_header', $cartBlockHeader);
        $cartBlockHeader = self::processCartTotalBlockHeader($cartBlockHeader);
        $cartTotalBlockHtml = preg_replace('/<\!--cart_block_header-->[\s\S]+?<\!--\/cart_block_header-->/', $cartBlockHeader, $cartTotalBlockHtml);
        preg_match('/<!--cart_block_content-->(.*?)<!--\/cart_block_content-->/s', $cartTotalBlockHtml, $matches_content);
        $cartBlockContent = isset($matches_content[1]) ? $matches_content[1] : '';
        $cartBlockContent = preg_replace('/<!--cart_tr-->[\s\S]+?<!--\/cart_tr-->/', '', $cartBlockContent);
        $cartBlockContent = self::_processAdditionalTrInTbody($cartBlockContent);
        update_option('cart_totals_template_content', $cartBlockContent);
        $cartBlockContent = self::processCartTotalBlockContent($cartBlockContent);
        ob_start();
        include dirname(__FILE__) . '/template-parts/cart-totals.php';
        $templatePart = ob_get_clean();
        $cartBlockContent = str_replace('[tr_template_parts]', $templatePart, $cartBlockContent);
        $cartTotalBlockHtml = preg_replace('/<\!--cart_block_content-->[\s\S]+?<\!--\/cart_block_content-->/', $cartBlockContent, $cartTotalBlockHtml);
        $cartTotalBlockHtml = preg_replace_callback('/<\!--cart_checkout_btn-->([\s\S]+?)<\!--\/cart_checkout_btn-->/', 'self::_processCartCheckoutButton', $cartTotalBlockHtml);
        $cartTotalBlockHtml = str_replace('_dollar_symbol_', '$', $cartTotalBlockHtml);
        ob_start();
        do_action('woocommerce_before_cart_totals');
        $woocommerce_before_cart_totals = ob_get_clean();
        ob_start();
        do_action('woocommerce_after_cart_totals');
        $woocommerce_after_cart_totals = ob_get_clean();
        return '<div class="cart_totals ' . (WC()->customer->has_calculated_shipping() ? 'calculated_shipping' : '') . '">' . $woocommerce_before_cart_totals . $cartTotalBlockHtml . $woocommerce_after_cart_totals . '</div>';
    }

    /**
     * Add tr in tbody for php code additional options
     *
     * @param array $cartTotalBlockHtml html
     *
     * @return string
     */
    private static function _processAdditionalTrInTbody($cartTotalBlockHtml)
    {
        $callback = function ($matches) {
            $start = $matches[1];
            $tbodyContent = $matches[2];
            $end = $matches[3];
            $hasThead = preg_match('/<thead[^>]*>/i', $start);
            $hasTfoot = preg_match('/<tfoot[^>]*>/i', $end);
            if ($hasThead && $hasTfoot) {
                $newTbodyContent = "[tr_template_parts]";
            } elseif ($hasThead) {
                // before first <tr>
                $newTbodyContent = preg_replace('/(<tr[^>]*>)/', "[tr_template_parts]\n$1", $tbodyContent, 1);
            } else {
                if ($hasTfoot) {
                    // after last <tr>
                    $newTbodyContent = preg_replace('/(<\/tr>)/', "$1\n[tr_template_parts]", $tbodyContent);
                } else {
                    // after first <tr>
                    $newTbodyContent = preg_replace('/(<\/tr>)/', "$1\n[tr_template_parts]", $tbodyContent, 1);
                }
            }
            return $start . $newTbodyContent . $end;
        };
        return preg_replace_callback('/(<table[\s\S]*?<tbody[^>]*>)([\s\S]*?)(<\/tbody>[\s\S]*?<\/table>)/', $callback, $cartTotalBlockHtml);
    }

    /**
     * Process cart total block header
     *
     * @param array $cartTotalBlockHeaderHtml html
     *
     * @return string
     */
    public static function processCartTotalBlockHeader($cartTotalBlockHeaderHtml)
    {
        $cartTotalBlockHeaderHtml = preg_replace('/<\!--cart_block_header_content-->[\s\S]+?<\!--\/cart_block_header_content-->/', np_translate('Cart totals'), $cartTotalBlockHeaderHtml);
        return $cartTotalBlockHeaderHtml;
    }

    /**
     * Process cart total block content
     *
     * @param string $cartTotalBlockContentHtml html
     *
     * @return string
     */
    public static function processCartTotalBlockContent($cartTotalBlockContentHtml)
    {
        $cartTotalBlockContentHtml = preg_replace_callback('/<\!--cart_sub_total_text-->([\s\S]+?)<\!--\/cart_sub_total_text-->/', 'self::_processSubtotalText', $cartTotalBlockContentHtml);
        $cartTotalBlockContentHtml = preg_replace_callback('/<\!--cart_sub_total_price-->([\s\S]+?)<\!--\/cart_sub_total_price-->/', 'self::_processSubTotalPrice', $cartTotalBlockContentHtml);
        $cartTotalBlockContentHtml = preg_replace_callback('/<\!--cart_total_price_text-->([\s\S]+?)<\!--\/cart_total_price_text-->/', 'self::_processTotalPriceText', $cartTotalBlockContentHtml);
        $cartTotalBlockContentHtml = preg_replace_callback('/<\!--cart_total_price_td-->([\s\S]+?)<\!--\/cart_total_price_td-->/', 'self::_processTotalPriceTd', $cartTotalBlockContentHtml);
        return $cartTotalBlockContentHtml;
    }

    /**
     * Process cart total block tr text
     *
     * @param array $cartSubTotalTextMatch Matches
     *
     * @return string
     */
    private static function _processSubtotalText($cartSubTotalTextMatch)
    {
        $cartSubTotalTextHtml = $cartSubTotalTextMatch[1];
        $cartSubTotalTextHtml = preg_replace('/<\!--cart_sub_total_text_content-->[\s\S]+?<\!--\/cart_sub_total_text_content-->/', np_translate('Subtotal'), $cartSubTotalTextHtml);
        return $cartSubTotalTextHtml;
    }

    /**
     * Process cart subtotal price
     *
     * @param array $cartSubTotalPriceMatch Matches
     *
     * @return string
     */
    private static function _processSubTotalPrice($cartSubTotalPriceMatch)
    {
        $cartSubTotalPriceHtml = $cartSubTotalPriceMatch[1];
        $subTotalPrice = '';
        if (function_exists('wc_cart_totals_subtotal_html')) {
            ob_start();
            wc_cart_totals_subtotal_html();
            $subTotalPrice = ob_get_clean();
        }
        $cartSubTotalPriceHtml = preg_replace('/<\!--cart_sub_total_price_content-->[\s\S]+?<\!--\/cart_sub_total_price_content-->/', $subTotalPrice, $cartSubTotalPriceHtml);
        return $cartSubTotalPriceHtml;
    }

    /**
     * Process cart total price text
     *
     * @param array $cartTotalPriceTextMatch Matches
     *
     * @return string
     */
    private static function _processTotalPriceText($cartTotalPriceTextMatch)
    {
        $cartTotalPriceTextHtml = $cartTotalPriceTextMatch[1];
        $cartTotalPriceTextHtml = preg_replace('/<\!--cart_total_price_text_content-->[\s\S]+?<\!--\/cart_total_price_text_content-->/', np_translate('Total'), $cartTotalPriceTextHtml);
        return $cartTotalPriceTextHtml;
    }

    /**
     * Process cart total price td
     *
     * @param array $cartTotalPriceTdMatch Matches
     *
     * @return string
     */
    private static function _processTotalPriceTd($cartTotalPriceTdMatch)
    {
        $cartTotalPriceTdHtml = $cartTotalPriceTdMatch[1];
        $totalPrice = '';
        if (function_exists('wc_cart_totals_order_total_html')) {
            ob_start();
            wc_cart_totals_order_total_html();
            $totalPrice = ob_get_clean();
        }
        $cartTotalPriceTdHtml = preg_replace('/<\!--cart_total_price_td_content-->[\s\S]+?<\!--\/cart_total_price_td_content-->/', $totalPrice, $cartTotalPriceTdHtml);
        return $cartTotalPriceTdHtml;
    }

    /**
     * Process cart checkout button
     *
     * @param array $cartCheckoutButtonMatch Matches
     *
     * @return string
     */
    private static function _processCartCheckoutButton($cartCheckoutButtonMatch)
    {
        $cartCheckoutButtonHtml = $cartCheckoutButtonMatch[1];
        $cartCheckoutButtonHtml = preg_replace('/href="[\s\S]+?"/', 'href="' . esc_url(wc_get_checkout_url()) . '"', $cartCheckoutButtonHtml);
        $cartCheckoutButtonHtml = preg_replace('/<\!--cart_checkout_btn_content-->[\s\S]+?<\!--\/cart_checkout_btn_content-->/', np_translate('Proceed to checkout'), $cartCheckoutButtonHtml);
        return $cartCheckoutButtonHtml;
    }

}
