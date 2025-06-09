<?php
$html = get_option('checkout_payment_submit_button');
if (!$html) {
    $html = '';
}
preg_match('/<input[^>]+>/', $html, $matches);
$html = preg_replace('/<input[^>]+>/', '', $html);
$original_button = !empty($matches[0]) ? $matches[0] : '';
$original_button = str_replace('<input type=', '<button name="woocommerce_checkout_place_order" id="place_order" type=', $original_button);
$original_button = str_replace('>', '>' . np_translate('Place order') . '</button>', $original_button);
$html = $html . $original_button;
return $html;