<?php
defined('ABSPATH') or die;

class NpGetHtmlAction extends NpAction {

    /**
     * Get page html
     *
     * @param string $id
     *
     * @return string
     */
    public static function getHtml($id) {
        return np_data_provider($id)->getPageHtml();
    }

    /**
     * Process action entrypoint
     */
    public static function process() {
        $page_id = isset($_REQUEST['pageId']) ? sanitize_text_field(wp_unslash($_REQUEST['pageId'])) : 0;
        echo self::getHtml($page_id);
    }
}

NpAction::add('np_get_html', 'NpGetHtmlAction');