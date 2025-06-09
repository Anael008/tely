<?php
defined('ABSPATH') or die;

class Page404TemplateReplacer
{

    /**
     * Process 404
     *
     * @param string $content Content
     *
     * @return string|string[]|null
     */
    public static function process($content)
    {
        $content = preg_replace_callback('/<\!--text_404-->([\s\S]+?)<\!--\/text_404-->/', 'self::_processText404', $content);
        return $content;
    }

    /**
     * Process text 404
     *
     * @param array $text404Match Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processText404($text404Match)
    {
        $text404Html = $text404Match[1];
        $text404Html = str_replace('<p', '<div', $text404Html);
        $text404Html = str_replace('/p>', '/div>', $text404Html);
        $text404Html = preg_replace('/<\!--text_404_content-->([\s\S]+?)<\!--\/text_404_content-->/', plugin_404_content(), $text404Html);
        return $text404Html;
    }
}
