<?php
defined('ABSPATH') or die;

class NpMultiLanguages
{
    /**
     * Pre init
     */
    public static function pre_init()
    {
        if (!is_admin()) {
            add_action('wp_head', array(__CLASS__, 'set_language_cookie'));
            add_action('wp_head', array(__CLASS__, 'disable_server_cache_for_links_script'));
            add_action('template_redirect', array(__CLASS__, 'language_redirect'));
            add_filter('locale', array(__CLASS__, 'set_my_locale'));
        }
    }

    /**
     * Disable server cache for links
     */
    public static function disable_server_cache_for_links_script()
    {
        $language = self::get_np_default_lang();
        if (isset($_COOKIE['np_lang']) && $_COOKIE['np_lang']) {
            $language = $_COOKIE['np_lang'];
        }
        if (isset($_GET['lang']) && $_GET['lang']) {
            $language = $_GET['lang'];
        }
        $domain = get_site_url();
        ?>
        <script>
            function addLangToUrls(selector, attr) {
                jQuery(selector).each(function() {
                    var url = jQuery(this).attr(attr);
                    if (url) {
                        if (url === '#' ||
                            url.startsWith('#') ||
                            url.indexOf('lang=') > -1 ||
                            url.indexOf('tel:') > -1 ||
                            url.indexOf('mailto:') > -1 ||
                            url.indexOf('<?php echo $domain; ?>') === -1
                        ) {
                            return;
                        }
                        var urlParams = url.split('#');
                        var anchor = urlParams[1] ? '#' + urlParams[1] : '';
                        var concat = url.indexOf('?') === -1 ? '?' : '&';
                        url = urlParams[0] + concat + `lang=<?php echo $language; ?>` + anchor
                        jQuery(this).attr(attr, url);
                    }
                });
            }
            jQuery(document).ready(function () {
                addLangToUrls('a:not([type="submit"]', 'href');
                addLangToUrls('[data-href]', 'data-href');
            });
        </script>
    <?php }

    /**
     * Get supported languages
     *
     * @return array $supported_langs
     */
    public static function get_supported_langs()
    {
        $supported_langs = array('en');
        if (get_option('np_supported_langs')) {
            $supported_langs = json_decode(get_option('np_supported_langs'));
        }
        return $supported_langs;
    }

    /**
     * Get default np language
     *
     * @return string $lang
     */
    public static function get_np_default_lang()
    {
        $lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';
        if (get_option('np_default_lang')) {
            $lang = get_option('np_default_lang');
        }
        return $lang;
    }

    /**
     * Set cookie language
     */
    public static function set_language_cookie()
    {
        if (!isset($_COOKIE['np_lang']) && isset($_GET['lang']) || isset($_COOKIE['np_lang']) && isset($_GET['lang']) && $_COOKIE['np_lang'] !== $_GET['lang']) {
            if (!in_array($_GET['lang'], self::get_supported_langs())) {
                $_GET['lang'] = self::get_np_default_lang();
            }
            setcookie('np_lang', $_GET['lang'], time() + 3 * YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    /**
     * Redirect by cookie language
     */
    public static function language_redirect() {
        if (isset($_COOKIE['np_lang']) and !isset($_GET['lang'])) {
            $location = self::getLanguageLink($_COOKIE['np_lang']);
            if (isset($_GET['productsList'])) {
                $location = add_query_arg(array('productsList' => ''), $location);
            }
            if (isset($_GET['productId'])) {
                $location = add_query_arg(array('productId' => $_GET['productId']), $location);
            }
            if (isset($_GET['products-list'])) {
                $location = add_query_arg(array('products-list' => ''), $location);
            }
            if (isset($_GET['product-id'])) {
                $location = add_query_arg(array('product-id' => $_GET['product-id']), $location);
            }
            global $post;
            $data_provider = np_data_provider($post->ID);
            $isPreview = $data_provider->isPreview();
            if (!$isPreview) {
                wp_redirect($location);
            }
        }
    }

    /**
     * Change language on the live site
     *
     * @param string $lang
     *
     * @return string $lang
     */
    public static function set_my_locale($lang) {
        if (isset($_COOKIE['np_lang']) && $_COOKIE['np_lang']) {
            return self::get_locale_by_lang($_COOKIE['np_lang']);
        } else {
            return $lang;
        }
    }

    /**
     * Get wordpress locale code by np language code
     *
     * @param string $lang
     *
     * @return string $lang
     */
    public static function get_locale_by_lang($lang) {
        $default_locale = 'en_US';
        $associations = array(
            'aa' => $default_locale,
            'ab' => $default_locale,
            'ae' => $default_locale,
            'af' => 'af',
            'ak' => 'ak',
            'am' => 'am',
            'an' => $default_locale,
            'ar' => 'ar',
            'as' => 'as',
            'av' => $default_locale,
            'ay' => $default_locale,
            'az' => 'az',
            'ba' => 'ba',
            'be' => 'bel',
            'bg' => 'bg_BG',
            'bh' => $default_locale,
            'bi' => $default_locale,
            'bm' => $default_locale,
            'bn' => 'bn_BD',
            'bo' => 'bo',
            'br' => 'bre',
            'bs' => 'bs_BA',
            'ca' => 'ca',
            'ce' => $default_locale,
            'ch' => $default_locale,
            'co' => 'co',
            'cr' => $default_locale,
            'cs' => 'cs_CZ',
            'cv' => $default_locale,
            'cy' => 'cy',
            'da' => 'da_DK',
            'de' => 'de_DE',
            'dz' => 'dzo',
            'ee' => $default_locale,
            'el' => 'el',
            'en' => $default_locale,
            'eo' => 'eo',
            'es' => 'es_ES',
            'et' => 'et',
            'eu' => 'eu',
            'fa' => 'fa_IR',
            'fi' => 'fi',
            'fj' => $default_locale,
            'fo' => 'fo',
            'fr' => 'fr_FR',
            'fy' => 'fy',
            'ga' => 'ga',
            'gd' => 'gd',
            'gl' => 'gl_ES',
            'gn' => 'gn',
            'gu' => 'gu',
            'ha' => $default_locale,
            'he' => 'he_IL',
            'hi' => 'hi_IN',
            'ho' => $default_locale,
            'hr' => 'hr',
            'ht' => $default_locale,
            'hu' => 'hu_HU',
            'hy' => 'hy',
            'hz' => $default_locale,
            'ia' => $default_locale,
            'id' => 'id_ID',
            'ie' => $default_locale,
            'ig' => $default_locale,
            'ii' => $default_locale,
            'ik' => $default_locale,
            'io' => $default_locale,
            'is' => 'is_IS',
            'it' => 'it_IT',
            'iu' => $default_locale,
            'ja' => 'ja',
            'jv' => 'jv_ID',
            'ka' => 'ka_GE',
            'kg' => $default_locale,
            'ki' => $default_locale,
            'kj' => $default_locale,
            'kk' => 'kk',
            'kl' => $default_locale,
            'km' => 'km',
            'kn' => 'kn',
            'ko' => 'ko_KR',
            'kr' => $default_locale,
            'ks' => $default_locale,
            'ku' => $default_locale,
            'kv' => $default_locale,
            'kw' => $default_locale,
            'ky' => 'ky_KY',
            'la' => $default_locale,
            'lb' => 'lb_LU',
            'lg' => $default_locale,
            'li' => 'li',
            'ln' => $default_locale,
            'lo' => 'lo',
            'lt' => 'lt_LT',
            'lv' => 'lv',
            'mg' => 'mg_MG',
            'mh' => $default_locale,
            'mi' => $default_locale,
            'mk' => 'mk_MK',
            'ml' => 'ml_IN',
            'mn' => 'mn',
            'mo' => $default_locale,
            'mr' => 'mr',
            'ms' => 'ms_MY',
            'mt' => $default_locale,
            'my' => 'my_MM',
            'na' => $default_locale,
            'nd' => $default_locale,
            'ne' => 'ne_NP',
            'ng' => $default_locale,
            'nl' => 'nl_NL',
            'no' => $default_locale,
            'nr' => $default_locale,
            'nv' => $default_locale,
            'oc' => 'oci',
            'oj' => $default_locale,
            'or' => 'ory',
            'os' => 'os',
            'pa' => 'pa_IN',
            'pi' => $default_locale,
            'pl' => 'pl_PL',
            'ps' => 'ps',
            'pt' => 'pt_PT',
            'qu' => $default_locale,
            'rm' => $default_locale,
            'rn' => $default_locale,
            'ro' => 'ro_RO',
            'ru' => 'ru_RU',
            'rw' => $default_locale,
            'sa' => 'sa_IN',
            'sd' => $default_locale,
            'se' => $default_locale,
            'sg' => $default_locale,
            'si' => 'si_LK',
            'sk' => 'sk_SK',
            'sl' => 'sl_SI',
            'sm' => $default_locale,
            'sn' => $default_locale,
            'so' => 'so_SO',
            'sq' => 'sq',
            'sr' => 'sr_RS',
            'ss' => $default_locale,
            'st' => $default_locale,
            'su' => 'su_ID',
            'sv' => 'sv_SE',
            'sw' => 'sw',
            'ta' => 'ta_IN',
            'te' => 'te',
            'tg' => 'tg',
            'th' => 'th',
            'ti' => 'tir',
            'tk' => $default_locale,
            'tl' => 'tl',
            'tn' => $default_locale,
            'to' => $default_locale,
            'tr' => 'tr_TR',
            'ts' => $default_locale,
            'tt' => 'tt_RU',
            'tw' => $default_locale,
            'ty' => $default_locale,
            'ug' => 'ug_CN',
            'uk' => 'uk',
            'ur' => 'ur',
            'uz' => 'uz_UZ',
            've' => $default_locale,
            'vi' => 'vi',
            'vo' => $default_locale,
            'wa' => 'wa',
            'wo' => $default_locale,
            'xh' => $default_locale,
            'yi' => $default_locale,
            'yo' => 'yor',
            'za' => $default_locale,
            'zh' => 'zh_CN',
            'zu' => $default_locale,
        );
        $locale = isset($associations[$lang]) ? $associations[$lang] : $default_locale;
        return $locale;
    }

    /**
     * Get language link for switcher/redirect
     *
     * @param string $language
     *
     * @return string $url
     */
    public static function getLanguageLink($language = 'en')
    {
        global $wp;
        global $post;
        $wp->query_vars['lang'] = $language;
        if (is_front_page() && !is_home()) {
            // if home page is np page
            $pagename = isset($post->post_name) ? $post->post_name : 'home';
            $wp->query_vars['pagename'] = $pagename;
        } else {
            unset($wp->query_vars['pagename']);
            unset($wp->query_vars['page']);
        }
        $wp->query_vars = array_unique($wp->query_vars);
        $url = add_query_arg($wp->query_vars, home_url($wp->request));
        if (isset($_GET['productsList'])) {
            $url = add_query_arg(array('productsList' => ''), $url);
        }
        if (isset($_GET['productId'])) {
            $url = add_query_arg(array('productId' => $_GET['productId']), $url);
        }
        if (isset($_GET['products-list'])) {
            $url = add_query_arg(array('products-list' => ''), $url);
        }
        if (isset($_GET['product-id'])) {
            $url = add_query_arg(array('product-id' => $_GET['product-id']), $url);
        }
        return $url;
    }
}

if (count(NpMultiLanguages::get_supported_langs()) > 1) {
    NpMultiLanguages::pre_init();
}


