<?php
defined('ABSPATH') or die;

class NpGetSiteAction extends NpAction {

    /**
     * Get pages json + templates json in Nicepage-editor format
     *
     * @return mixed
     * @throws Exception
     */
    private static function _getItems() {
        $items = self::_getPages();
        return self::_getTemplates($items);
    }

    /**
     * Get templates json in Nicepage-editor format
     *
     * @param array $items
     *
     * @return mixed
     * @throws Exception
     */
    private static function _getTemplates($items) {
        $query_options = array(
            'post_type' => 'template',
            'posts_per_page' => -1,
            'order' => 'DESC',
            'orderby' => 'modified',
            'post_status' => 'any',
            'meta_key' => '_np_html',
        );

        $query = new WP_Query;
        $posts = $query->query($query_options);

        foreach ($posts as $post) {
            if (NpEditor::isAllowedForEditor($post)) {
                $current_page = self::getPost($post);
                $items[] = $current_page;
            }
        }

        return $items;
    }

    /**
     * Get pages json in Nicepage-editor format
     *
     * @return array
     * @throws Exception
     */
    private static function _getPages() {
        // all post from db : posts_per_page => -1
        $query_options = array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'order' => 'DESC',
            'orderby' => 'modified',
            'post_status' => 'any',
            'meta_key' => '_np_html',
        );

        $query = new WP_Query;
        $posts = $query->query($query_options);

        $result = array();

        foreach ($posts as $post) {
            if (NpEditor::isAllowedForEditor($post)) {
                $current_page = self::getPost($post);
                $result[] = $current_page;
            }
        }

        return $result;
    }

    /**
     * Get site
     *
     * @return array
     */
    public static function getSite() {

        $site_settings = NpMeta::get('site_settings');

        // backeard for broken back to top and captcha script.
        $s = json_decode($site_settings);
        if ($s) {
            $i = 0;
            while ($i < 100) {
                $original = clone $s;
                $s1 = stripslashes_deep($s);
                if (json_encode($original) == json_encode($s1)) {
                    break;
                }
                $s = $s1;
                $i++;
            }
            $s->title = get_bloginfo('name');
            $site_settings = json_encode($s);
        }

        if (!$site_settings) {
            $site_settings = '{}';
        }
        $site_settings = fixImagePaths($site_settings);

        return array(
            'title' => get_bloginfo('name'),
            'publicUrl' => get_home_url(),
            'id' => 1,
            'order' => 1,
            'status' => 2,
            'items' => self::_getItems(),
            'settings' => $site_settings,
            'isFullLoaded' => true,
            'blogUrl' => get_option('show_on_front') === 'posts' ? get_home_url() : home_url('/?post_type=post'),
            'homePageId' => get_option('page_on_front'),
        );
    }

    /**
     * Process action entrypoint
     *
     * @return array
     */
    public static function process() {

        return array(
            'result' => 'done',
            'data' => self::getSite(),
        );
    }
}
NpAction::add('np_get_site', 'NpGetSiteAction');