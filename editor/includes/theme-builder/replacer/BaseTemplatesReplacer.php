<?php
defined('ABSPATH') or die;

require_once dirname(__FILE__) . '/base/LoginTemplateReplacer.php';
require_once dirname(__FILE__) . '/base/PasswordTemplateReplacer.php';
require_once dirname(__FILE__) . '/base/SearchTemplateReplacer.php';
require_once dirname(__FILE__) . '/base/Page404TemplateReplacer.php';

class BaseTemplatesReplacer
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
            $content = LoginTemplateReplacer::process($content);
            $content = PasswordTemplateReplacer::process($content);
            $content = SearchTemplateReplacer::process($content);
            $content = Page404TemplateReplacer::process($content);
        }
        return $content;
    }
}
