<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/shop/CartTemplateReplacer.php';
require_once dirname(__FILE__) . '/shop/CheckoutTemplateReplacer.php';

class ShopTemplatesReplacer
{
    /**
     * ShopTemplatesReplacer process.
     *
     * @param string $content
     *
     * @return string $content
     */
    public static function process($content)
    {
        $content = CartTemplateReplacer::process($content);
        $content = CheckoutTemplateReplacer::process($content);
        return $content;
    }
}
