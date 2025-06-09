<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/BaseTemplatesReplacer.php';
require_once dirname(__FILE__) . '/ShopTemplatesReplacer.php';

class TemplatesReplacer
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
        if (Nicepage::$override_with_plugin) {
            $content = BaseTemplatesReplacer::process($content);
            $content = ShopTemplatesReplacer::process($content);
        }
        return $content;
    }
}
