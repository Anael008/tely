<?php
defined('ABSPATH') or die;

class NpAdminActions {

    public static $cmsUserRoleOption = false;
    public static $showFullMenu = true;
    public static $editorShow = true;

    private static $_editorPageTypes = array(
        '' => 'theme-template',
        'html' => 'np-template-header-footer-from-plugin',
        'html-header-footer' => 'np-template-header-footer-from-theme'
    );

    /**
     * Defines site is https and localhost
     *
     * @return bool
     */
    public static function siteIsSecureAndLocalhost() {
        return NpAdminActions::isSSL() && NpAdminActions::isLocalhost();
    }

    /**
     * Defines site is ssl
     *
     * @return bool
     */
    public static function isSSL()
    {
        $isSSL = false;

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $_SERVER['HTTPS'] = 'on';
        }

        if (isset($_SERVER['HTTPS'])) {
            if ('on' == strtolower($_SERVER['HTTPS'])) {
                $isSSL = true;
            }
            if ('1' == $_SERVER['HTTPS']) {
                $isSSL = true;
            }
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            $isSSL = true;
        }
        return $isSSL;
    }

    /**
     * Defines site is localhost
     *
     * @return bool
     */
    public static function isLocalhost()
    {
        $whitelist = array(
            // IPv4 address
            '127.0.0.1',
            // IPv6 address
            '::1'
        );

        if (filter_has_var(INPUT_SERVER, 'REMOTE_ADDR')) {
            $ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        } else if (filter_has_var(INPUT_ENV, 'REMOTE_ADDR')) {
            $ip = filter_input(INPUT_ENV, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        } else {
            $ip = null;
        }
        return $ip && in_array($ip, $whitelist);
    }

    /**
     * Filter on get_edit_post_link
     * Add domain parameter for debug
     *
     * @param string $link
     *
     * @return string
     */
    public static function editPostLinkFilter($link) {
        $domain = NpEditor::getDomain();
        if ($domain) {
            $link = add_query_arg(array('domain' => urlencode($domain)), $link);
        }
        return $link;
    }

    /**
     * Action on edit_form_top
     * Add domain hidden field to editor form
     */
    public static function editFormTopFilter() {
        $domain = NpEditor::getDomain();
        if ($domain) {
            printf('<input type="hidden" name="domain" value="%s" />', $domain);
        }
    }

    /**
     * Action on themler_edit_form_buttons
     * Add "Edit with Nicepage" button
     *
     * @param WP_Post $post
     */
    public static function addNicepageButtonAction($post) {
        if (!self::$editorShow) {
            return;
        }
        if (!NpEditor::isAllowedForEditor($post)) {
            return;
        }
        if (np_data_provider($post->ID)->isConvertRequired()) {
            ?>
            <a href="#" id="convert-in-nicepage" class="button nicepage-editor"><?php _e('Turn to Nicepage', 'nicepage'); ?></a>
            <?php
        } else {
            ?>
            <a href="#" id="edit-in-nicepage" class="button nicepage-editor"><?php _e('Edit with Nicepage', 'nicepage'); ?></a>
            <?php $autoSaveChanges = !!get_post_meta($post->ID, '_np_html_auto_save', true);
            if ($autoSaveChanges) { ?>
                <p><b><?php echo __('The page has unpublished changes.', 'nicepage');?></b> <?php echo __('Click \'Edit\' with Nicepage\' button and then click \'Publish\' to make them visible on your website.', 'nicepage');?></p>
            <?php }
        }
    }

    /**
     * Action on admin_menu
     * Add Nicepage menus and register page capabilities
     */
    public static function addEditorPageAction() {
        if (!self::$editorShow) {
            return;
        }
        $capability = 'edit_pages';
        $menu_slug = 'np_app';
        add_pages_page(__('Nicepage', 'nicepage'), __('Nicepage', 'nicepage'), $capability, $menu_slug, 'np_start');
        add_pages_page(__('Nicepage', 'nicepage'), __('Nicepage', 'nicepage'), $capability, 'np_editor', 'NpAdminActions::editorAction');

        add_submenu_page($menu_slug, __('Import', 'nicepage'), __('Import', 'nicepage'), $capability, 'np_import', 'NpImport::importPage');
        add_submenu_page($menu_slug, __('Plugin Wizard', 'nicepage'), __('Plugin Wizard', 'nicepage'), $capability, 'np_wizard', 'NpImport::wizardPage');
        if (current_user_can('manage_options')) {
            add_submenu_page($menu_slug, __('Role Manager', 'nicepage'), __('Role Manager', 'nicepage'), $capability, 'np_role_manager', 'NpRoleManager::roleManagerPage');
        }
        add_submenu_page($menu_slug, __('Settings', 'nicepage'), __('Settings', 'nicepage'), $capability, 'np_settings', 'NpSettings::settingsPage');

        // remove submenu from Pages
        global $submenu;
        $pages_slug = 'edit.php?post_type=page';
        if (isset($submenu[$pages_slug]) && is_array($submenu[$pages_slug])) {
            foreach ($submenu[$pages_slug] as $key => $value) {
                if (in_array('np_editor', $value) || in_array('np_app', $value)) {
                    unset($submenu[$pages_slug][$key]);
                }
            }
        }
    }

    /**
     * Action on custom_menu_order
     * Add Nicepage menus and register page capabilities
     *
     * @return bool
     */
    public static function addSubmenusAction() {
        if (!self::$editorShow) {
            return true;
        }
        global $submenu;
        $menu_slug = NpAdminActions::adminUrlFilter(admin_url('post-new.php?post_type=page&np_new=1'));
        $menu_slug_2 = 'Colors and Fonts';
        $menu_slug_2_url = NpAdminActions::adminUrlFilter(admin_url('admin.php?page=np_settings&np_new=1'));
        $capability = 'edit_pages';
        $submenu[$menu_slug][10] = array(__('Settings', 'nicepage'), $capability, admin_url('admin.php?page=np_settings'));
        $submenu[$menu_slug][11] = array(__('New Page', 'nicepage'), $capability, $menu_slug);
        $submenu[$menu_slug][12] = array(__('Import', 'nicepage'), $capability, admin_url('admin.php?page=np_import'));
        if (self::$showFullMenu) {
            $submenu[$menu_slug_2][13] = array(__('Colors', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=colors');
            $submenu[$menu_slug_2][14] = array(__('Fonts', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=fonts');
            $submenu[$menu_slug_2][15] = array(__('Typography', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=typography');
            $submenu[$menu_slug_2][16] = array(__('Headings and Text', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=customize');
            $submenu[$menu_slug_2][17] = array(__('Header', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=Header');
            $submenu[$menu_slug_2][18] = array(__('Menu Style', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=Menu');
            $submenu[$menu_slug_2][19] = array(__('Footer', 'nicepage'), $capability, $menu_slug_2_url . '&np_page=Footer');
        }
        $submenu[$menu_slug][20] = array(__('Plugin Wizard', 'nicepage'), $capability, admin_url('admin.php?page=np_wizard'));
        if (current_user_can('manage_options')) {
            $submenu[$menu_slug][21] = array(__('Role Manager', 'nicepage'), $capability, admin_url('admin.php?page=np_role_manager'));
        }
        return true;
    }

    /**
     * Action on
     *  _network_admin_menu,
     *  _user_admin_menu,
     *  _admin_menu
     *
     * Add Nicepage menus and register page capabilities
     */
    public static function addMenuAction() {
        if (!self::$editorShow) {
            return;
        }
        global $menu;
        $menu_slug = NpAdminActions::adminUrlFilter(admin_url('post-new.php?post_type=page&np_new=1'));
        $menu_slug_2 = 'Colors and Fonts';
        $capability = 'edit_pages';
        $menu['56'] = array(__('Nicepage', 'nicepage'), $capability, $menu_slug, '', 'menu-top menu-icon-nicepage', 'menu-nicepage', 'div');
        if (self::$showFullMenu) {
            $menu['58'] = array(__('Colors and Fonts', 'nicepage'), $capability, $menu_slug_2, '', 'menu-top menu-icon-nicepage-2', 'menu-nicepage-2', 'div');
        }
    }

    public static $thumbnailScale = 0.3;

    /**
     * Action on admin_print_styles
     */
    public static function printMenuStylesAction() {
        ?>
        <style>
            #adminmenu .menu-icon-nicepage div.wp-menu-image {
                background-image: url('<?php echo APP_PLUGIN_URL; ?>editor/assets/images/menu-icon.png');
                background-position:50% 55%;
                background-repeat: no-repeat;
                background-size: 16px;
            }

            #adminmenu .menu-icon-nicepage-2 div.wp-menu-image {
                background-image: url('<?php echo APP_PLUGIN_URL; ?>editor/assets/images/colors-and-fonts.png');
                background-position:50% 55%;
                background-repeat: no-repeat;
                background-size: 15px;
            }

            #adminmenu .menu-icon-nicepage:hover div.wp-menu-image,
            #adminmenu .menu-icon-nicepage.wp-has-current-submenu div.wp-menu-image,
            #adminmenu .menu-icon-nicepage.current div.wp-menu-image {
                opacity: 0.85;
            }

            .wp-core-ui .button.nicepage-editor {
                background: #4082f3;
                color: #fff;
                font-size: 14px;
                height: 46px;
                line-height: 44px;
                padding: 0 41px 0 36px;
            }

            .nicepage-editor:before {
                background-image: url('<?php echo APP_PLUGIN_URL; ?>editor/assets/images/edit-menu-icon.png');
                display: inline-block;
                margin-right: 5px;
                content: "";
                background-size: 19px;
                width: 19px;
                height: 19px;
                background-repeat: no-repeat;
                vertical-align: middle;
                margin-bottom: 3px;
            }

            .editor-header__toolbar>.components-button.nicepage-editor {
                display: inline-flex;
                background: #3f82f4;
                color: #fff;
                padding: 5px 13px 8px 33px;
                font-size: 14px;
                height: 48px;
                padding-left: 16px;
                padding-right: 16px;
            }

            .editor-header__toolbar>.components-button.nicepage-editor::before {
                top: 13px;
                box-shadow: none!important;
            }

            .components-icon-button.nicepage-editor:not(:disabled):not([aria-disabled=true]):not(.is-default):hover,
            .components-icon-button.nicepage-editor:not(:disabled):not([aria-disabled=true]):not(.is-default):active,
            .components-icon-button.nicepage-editor:not(:disabled):not([aria-disabled=true]):not(.is-default):focus,
            .wp-core-ui .button.nicepage-editor:hover,
            .wp-core-ui .button.nicepage-editor:active,
            .wp-core-ui .button.nicepage-editor:focus {
                background: #2b7aff;
                box-shadow: none;
                color: #fafafa;
            }

            .components-button.nicepage-editor:before {
                margin-bottom: 0;
                width: 19px;
                height: 19px;
                background-size: 19px;
            }

            #nicepage-preview-frame {
                transform: scale(<?php echo self::$thumbnailScale; ?>);
                transform-origin: 0 0;
                height: <?php echo 100 / self::$thumbnailScale; ?>%;
            }

            #nicepage-preview {
                margin-left: auto;
                margin-right: auto;
                cursor: pointer;
                overflow: hidden;
                height: 0;
            }

            #nicepage-preview-frame {
                pointer-events: none;
            }
            #np-loader {
                position: absolute;
                visibility: hidden;
                width: 1800px;
            }
            body.editor-fullscreen #wpadminbar,
            body.editor-fullscreen #adminmenuwrap {
                display: none;
            }
            body.editor-fullscreen #wpcontent,
            body.editor-fullscreen #wpfooter {
                margin-left: 0;
            }
        </style>
        <?php
    }


    /**
     * Get Nicepage editor iframe html
     *
     * @param array $args
     *
     * @return string
     */
    public static function getEditorContainerHtml($args = array()) {
        ob_start();
        ?>
        <div id="nicepage-editor-container" style="display: block; z-index: 100; position: relative;">
            <style>
                html {
                    overflow: hidden !important;
                }
                #wpcontent {
                    padding-left: 0 !important;
                }
                #nicepage-editor-frame {
                    width: 100%;
                    height: calc(100vh);
                    background: #fff;
                }
            </style>
            <script>
                jQuery(document).scroll(function() {
                    if (jQuery('#nicepage-editor-container').length) {
                        jQuery(this).scrollTop(0);
                    }
                });
            </script>
            <iframe id="nicepage-editor-frame" src="<?php echo NpEditor::getAppLink($args); ?>"></iframe>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Action on load-appearance_page_np_editor
     * Print Nicepage editor iframe
     *
     * @deprecated
     */
    public static function editorAction() {
        throw new Exception("Deprecated usage");
    }

    /**
     * Print Nicepage editor page
     */
    public static function appAction() {
        include_once dirname(__FILE__) . '/nicepage-editor.php';
        die();
    }

    /**
     * Action on edit_form_after_title
     * Print page preview (first 4 sections)
     *
     * @param WP_Post $post
     */
    public static function showScreenShotsAction($post) {
        $data_provider = np_data_provider($post->ID);
        if ($data_provider->isNp()) {
            ob_start();
            ?>
            <head>
                <link rel="stylesheet" type="text/css" media="all" href="<?php echo APP_PLUGIN_URL; ?>assets/css/nicepage.css">
                <link rel="stylesheet" type="text/css" media="all" href="<?php echo APP_PLUGIN_URL; ?>assets/css/media.css">

                <?php echo $data_provider->getPageFonts(); ?>
                <style>
                    <?php echo $data_provider->getStyleCss(); ?>
                    <?php echo $data_provider->getPageHead(); ?>
                </style>
            </head>
            <?php
            $frame_head = ob_get_clean();
            $body_lazy_load_background = $data_provider->getPageBodydataBg();
            $body_lazy_load_background = $body_lazy_load_background !== '' ? 'background-image: ' . $data_provider->getPageBodydataBg() : '';
            ob_start();
            ?>
            <body class="<?php echo $data_provider->getPageBodyClass(); ?>" style="<?php echo $data_provider->getPageBodyStyle(); ?> <?php echo $body_lazy_load_background; ?>">
            <?php
            self::$_previewSectionIdx = 0;
            echo preg_replace_callback(
                '#<section [\s\S]*?</section>#',
                'NpAdminActions::_screenshotSectionsReplacer',
                $data_provider->getPagePublishHtml()
            );
            ?>
            </body>
            <?php
            $frame_body = ob_get_clean();
            ?>
            <div id="nicepage-preview">
                <iframe id="nicepage-preview-frame">
                </iframe>
            </div>
            <style>
                #postdivrich {
                    display: none;
                }
            </style>
            <script>
                jQuery(function ($) {
                    var doc = $('#nicepage-preview-frame')[0].contentDocument;
                    doc.open();
                    doc.write("<!DOCTYPE html>\n<html>\n" + <?php echo wp_json_encode($frame_head); ?> + <?php echo wp_json_encode($frame_body); ?> + "</html>");
                    doc.close();

                    $("#nicepage-preview-frame").load(function() {
                        var totalHeight = 0,
                            width = 1600;

                        $('#nicepage-preview-frame').css('width', width + 'px');
                        $('#nicepage-preview').css('width', width * <?php echo self::$thumbnailScale; ?> + 'px');
                        $('#nicepage-preview-frame').contents().find('section').each(function () {
                            totalHeight += $(this).height();
                        });
                        $('#nicepage-preview').css('height', totalHeight * <?php echo self::$thumbnailScale; ?>);
                    });
                });

            </script>
            <?php
        }
    }

    /**
     * @param array $m
     *
     * @return string
     */
    public static function _screenshotSectionsReplacer($m) {
        if (self::$_previewSectionIdx >= 4) {
            return '';
        }
        self::$_previewSectionIdx++;
        return $m[0];
    }

    private static $_previewSectionIdx;


    /**
     * Action on admin_head
     * Add page content to seo analytics scripts
     */
    public static function addSeoAnalytics() {
        global $post; $id = isset($post->ID) ? $post->ID : false; ?>
        <script>
            var seoContent = <?php echo wp_json_encode(np_data_provider($id)->getPagePublishHtml()); ?>;
        </script>
        <script src="<?php echo APP_PLUGIN_URL; ?>/editor/assets/js/transfer-data-to-seo.js?v=<?php echo APP_PLUGIN_VERSION; ?>"></script>
        <?php
    }
    /**
     * Action on admin_head
     * Print page editor scripts and styles
     */
    public static function printPreviewStylesAction() {
        global $post;

        if ($post && $post->ID) {
            if (!NpEditor::isAllowedForEditor($post)) {
                return;
            }
            np_data_provider($post->ID, true)->clear(false);
        }

        $settingsPage = array('post_id' => $post ? $post->ID : 0);
        if (_arr($_GET, 'np_page')) {
            $settingsPage['theme_typography_page'] = _arr($_GET, 'np_page');
        }
        if (_arr($_GET, 'np_new')) {
            $settingsPage['new_page'] = true;
        } ?>
        <style>
            #nicepage-preview {
                font-size: 0;
                line-height: 0;
            }
            #nicepage-preview a img {
                max-width: 100%;
            }
        </style>
        <script src="<?php echo APP_PLUGIN_URL; ?>/editor/assets/js/link-dialog.js?v=<?php echo APP_PLUGIN_VERSION; ?>"></script>
        <script src="<?php echo APP_PLUGIN_URL; ?>/editor/assets/js/leave-editor.js?v=<?php echo APP_PLUGIN_VERSION; ?>"></script>
        <script src="<?php echo APP_PLUGIN_URL; ?>/editor/assets/js/meta-boxes.js?v=<?php echo APP_PLUGIN_VERSION; ?>"></script>
        <script>
            var editorFullScreen;

            function collapseMenu() {
                jQuery('body').addClass('editor-fullscreen');
                jQuery('.editor-fullscreen').closest('html.wp-toolbar').css('padding-top', 0);
            }
            function startEditor(t) {
                function runNicepage() {
                    if (window.dataBridge) {
                        editorFullScreen = jQuery('body').hasClass('editor-fullscreen');
                        jQuery(window).scrollTop(0);
                        var editorContainer = <?php echo wp_json_encode(self::getEditorContainerHtml($settingsPage)); ?>;
                        jQuery('#wpbody-content').prepend(editorContainer);
                        collapseMenu();
                    } else {
                        alert('Unable to start the Editor. Please contact the Support.');
                    }
                }

                function redirectWhenSave()
                {
                    setTimeout(function() {
                            wp.data.select("core/editor").isSavingPost() ? redirectWhenSave() : runNicepage()}
                        , 300);
                }
                t.preventDefault();
                //for old wp version
                if(wp.data === undefined) {
                    runNicepage();
                } else { //for wp version 5.0 and more
                    var isNewPage = '<?php echo (isset($_SERVER['SCRIPT_FILENAME']) && strpos($_SERVER['SCRIPT_FILENAME'], 'post-new.php') !== false) ? '1' : '0'; ?>';
                    if(isNewPage === '1') {
                        wp.data.select("core/editor").getEditedPostAttribute("title") || wp.data.dispatch("core/editor").editPost({
                            title: "Page #" + jQuery("#post_ID").val()
                        });
                        wp.data.dispatch("core/editor").savePost();
                        redirectWhenSave();
                    } else {
                        runNicepage();
                    }
                }
            }
            function closeEditor(data) {
                if (data.needRefresh) {
                    if (!data.closeUrl || window.location.href === data.closeUrl) {
                        window.location.reload();
                    } else {
                        window.location.href = data.closeUrl;
                    }
                } else {
                    jQuery('#nicepage-editor-container').remove();
                    jQuery('html.wp-toolbar').css('padding-top', '');
                    jQuery('body').toggleClass('editor-fullscreen', editorFullScreen);
                }
            }

            jQuery(function ($) {
                editorFullScreen = $('body').hasClass('editor-fullscreen');

                var buttonHtml = '<button type="button" class="components-button components-icon-button nicepage-editor">' +
                    '<?php echo __('Turn to Nicepage', 'nicepage') ?>' +
                    '</button>';

                <?php if (version_compare(get_bloginfo('version'), '5.0-beta5', '>=')) : ?>
                $(function () {
                    waitFor('.editor-header__toolbar', function (toolbar) {
                        toolbar
                            .on('click', '.nicepage-editor', startEditor)
                            .children().last().before(buttonHtml);
                    });
                });
                <?php endif ?>

                $('#edit-in-nicepage, #convert-in-nicepage, #nicepage-preview').click(startEditor);

                if (location.search.indexOf('np_new=') !== -1 || location.search.indexOf('np_edit=') !== -1) {
                    collapseMenu();
                }

                <?php if (_arr($_GET, 'np_new')) : ?>
                $('.wp-menu-open').removeClass('wp-has-current-submenu wp-menu-open').addClass('wp-not-current-submenu');
                $('#menu-nicepage, #menu-nicepage > a').removeClass('wp-not-current-submenu').addClass('wp-has-current-submenu wp-menu-open');
                <?php endif; ?>
            });

            if (window.addEventListener) {
                window.addEventListener("message", postMessageListener);
            } else {
                window.attachEvent("onmessage", postMessageListener); // IE8
            }

        </script>
        <?php
    }

    /**
     * Get posts by categories for blog and products control
     *
     * @param string $type of control
     *
     * @return array $result
     */
    public static function getPostsByType($type) {
        $sources = array();
        $productSources = array();
        $postSources = array();
        if ($type === 'blog') {
            $sources = self::getPostsSources('blog');
            $postSources = self::getPostsSources('post_details');
        } else if ($type === 'product') {
            $sources = self::getPostsSources('products');
            $productSources = self::getPostsSources('product');
            if (!function_exists('wc_get_product') || !isset($productSources[0]) || !wc_get_product($productSources[0])) {
                $productSources = array();
            }
        }

        $result = array();

        if ($type == 'blog') {
            $controlPostsName = 'posts';
            $defaultCatList = get_categories(array('type' => 'post', 'hide_empty' => 0));
        } else {
            $controlPostsName = 'products';
            $defaultCatList = get_categories(array('taxonomy' => 'product_cat', 'hide_empty' => 0));
        }
        if (count($defaultCatList) < 1) {
            return $result;
        }
        array_push($sources, $defaultCatList[0]->name);

        foreach ($sources as $key => $source) {
            $source = count($sources) === $key + 1 ? 'Recent ' . $controlPostsName : $source;
            $posts = self::getPostsByCategory($source, $type);
            if (count($posts) > 0) {
                if (preg_match('/^tags:/', $source)) {
                    array_push($result, array('tags' => str_replace('tags:', '', $source), 'id' => null, $controlPostsName => $posts));
                } else {
                    $cat_id = getCatIdByType($source, $type);
                    array_push($result, array('category' => $source, 'id' => $cat_id, $controlPostsName => $posts));
                }
            }
            if ($type == 'product') {
                $featuredPosts = self::getPostsByCategory('featured', $type);
                if (count($featuredPosts) > 0) {
                    array_push($result, array('category' => 'Featured products', 'id' => 0, $controlPostsName => $featuredPosts));
                }
            }
        }
        foreach ($productSources as $source) {
            $current = array(
                'productId' => $source,
                'id' => null,
                'products' => array(),
            );
            $item = get_post($source);
            if ($item) {
                $current['products'] = array(self::getProductsPost($item));
            }
            array_push($result, $current);
        }
        foreach ($postSources as $source) {
            $current = array(
                'postId' => $source,
                'id' => null,
                'posts' => array(),
            );
            $item = get_post($source);
            if ($item) {
                $current['posts'] = array(self::getBlogPost($item, 'full'));
            }
            array_push($result, $current);
        }
        return $result;
    }

    /**
     * Get video files
     *
     * @return array $files
     *
     * @throws Exception
     */
    public static function getVideoFiles() {
        $files = self::getFiles('video');
        $result = array();
        if (isset($files['data'])) {
            foreach ($files['data'] as $file) {
                array_push($result, array ('fileName' => $file['title'], 'id' => $file['title'], 'publicUrl' => $file['url']));
            }
        }
        return $result;
    }

    /**
     * Get font files
     *
     * @return array $files
     *
     * @throws Exception
     */
    public static function getCustomFonts() {
        $files = self::getFilesFromDisk('fonts');
        $result = array();
        if (isset($files['data'])) {
            foreach ($files['data'] as $file) {
                array_push($result, array ('fileName' => $file['title'], 'id' => 'user-file-' . $file['title'], 'name'=> $file['name'], 'publicUrl' => $file['url']));
            }
        }
        return $result;
    }

    /**
     * Get isCmsClientMode - if true - content edit only
     *
     * @return bool $isCmsClientMode
     */
    public static function isCmsClientMode() {
        $isCmsClientMode = false;
        $roleOption = self::getCmsUserRoleOption();
        if ($roleOption && $roleOption === 'contentEditOnly') {
            $isCmsClientMode = true;
        }
        return $isCmsClientMode;
    }

    /**
     * Get current cms user role slug
     *
     * @return string $roleOption
     */
    public static function getCmsUserRoleOption() {
        $roleOption = false;
        $user = wp_get_current_user();
        $role = isset($user->roles[0]) ? $user->roles[0] : false;
        if ($role && $role !== 'administrator') {
            $roleOption = get_option('np_' . $role . '_options');
        }
        return $roleOption;
    }

    /**
     * Get files from disk - json in Nicepage-editor format
     *
     * @param bool|string $type
     *
     * @return array
     * @throws Exception
     */
    public static function getFilesFromDisk($type = false) {
        $base_upload_dir = wp_upload_dir();
        $base_dir = $base_upload_dir['basedir'];
        $base_url = $base_upload_dir['baseurl'];
        $filesDir = false;
        $filesDirUrl = false;
        if ($type === 'fonts') {
            $filesDir = $base_dir . '/' . 'nicepage-fonts/fonts';
            $filesDirUrl = $base_url . '/' . 'nicepage-fonts/fonts';
        }

        $result = array();
        if (!$filesDir || !file_exists($filesDir)) {
            return array(
                'result' => 'done',
                'data' => $result,
            );
        }
        if ($handle = opendir($filesDir)) {
            while (false !== ($file = readdir($handle))) {
                $fileSource = $filesDir . '/' . $file;
                if ('.' == $file || '..' == $file || is_dir($fileSource)) {
                    continue;
                }
                $fileOption = pathinfo($fileSource);
                $result[] = array(
                    'title' => $file,
                    'url' => $filesDirUrl . '/' . $fileOption['basename'],
                    'name' => $fileOption['filename']
                );
            }
            closedir($handle);
        }
        return array(
            'result' => 'done',
            'data' => $result,
        );
    }

    /**
     * Get posts categories name for blog and products control
     *
     * @param string $type = blog OR products
     *
     * @return array
     */
    public static function getPostsSources($type = 'blog') {
        global $post;
        $post_id = isset($post->ID) ? $post->ID : get_the_ID();
        $data_provider = np_data_provider($post_id);

        $sources = array();
        $pagePublishHtml = $data_provider->getPagePublishHtml();
        if (!$pagePublishHtml) {
            return $sources;
        }

        if (preg_match_all('/<\!--'. $type .'-->([\s\S]+?)<\!--\/' . $type . '-->/', $pagePublishHtml, $blogMatches, PREG_SET_ORDER)) {
            foreach ($blogMatches as $blogMatch) {
                if (preg_match('/<\!--' . $type . '_options_json--><\!--([\s\S]+?)--><\!--\/' . $type . '_options_json-->/', $blogMatch[1], $optionsMatches)) {
                    $options = json_decode($optionsMatches[1], true);
                    $sourceType = isset($options['type']) ? $options['type'] : '';
                    if ($sourceType === 'Tags') {
                        $source = 'tags:' . (isset($options['tags']) && $options['tags'] ? $options['tags'] : '');
                    } else {
                        $source = isset($options['source']) && $options['source'] ? $options['source'] : '';
                    }
                    if ($source) {
                        array_push($sources, $source);
                    }
                }
            }
        }
        return $sources;
    }

    /**
     * Get post object
     *
     * @param int $id Post id
     *
     * @return array
     */
    public static function getPost($id) {
        $result = array();
        $post = get_post($id);
        if ($post) {
            array_push($result, $post);
        }
        return $result;
    }

    /**
     * Get 20 posts by category or 20 last posts
     *
     * @param string|bool $source      Source
     * @param int         $numberposts Post count
     * @param string      $type        Post type
     *
     * @return array $result
     */
    public static function getPosts($source = false, $numberposts = 20, $type = 'post') {
        $posts = array();
        if ($source && $numberposts === 1) {
            $onePost = NpAdminActions::getPost($source);
            if ($onePost) {
                return $onePost;
            }
        }
        $cat_id = 0;
        $tags = '';
        if (preg_match('/^tags:/', $source)) {
            $tags = str_replace('tags:', '', $source);
        } else {
            $cat_id = getCatIdByType($source, $type);
            if ($source && $cat_id < 1) {
                return $posts;
            }
        }
        $params = array(
            'post_type' => $type,
            'numberposts' => $numberposts,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => true
        );
        if ($cat_id && $type == 'post') {
            $params['category'] = $cat_id;
        }
        if ($type == 'product' && $source != 'featured') {
            $params['product_cat'] = $source;
            if ($source && function_exists('wc_get_products')) {
                $catTerm = get_term_by('id', $cat_id, 'product_cat', 'ARRAY_A');
                $items = wc_get_products(array('category' => array($source)));
                if (!$items && isset($catTerm['slug'])) {
                    $items = wc_get_products(array('category' => array($catTerm['slug'])));
                }
                return $items;
            }
        }
        if ($source == 'featured') {
            // The tax query
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'terms'    => $source,
                'field'    => 'name',
                'operator' => 'IN',
            );
            $params['tax_query'] = $tax_query;
        }
        if ($tags) {
            $params['tag'] = array_map('trim', explode(',', $tags));
        }
        return get_posts($params);
    }

    /**
     * Get posts for blog and products control
     *
     * @param string $category
     * @param string $type     of control
     *
     * @return array
     */
    public static function getPostsByCategory($category, $type = 'blog') {
        $posts = array();
        $category = $category === 'Recent posts' || $category === 'Recent products' ? false : $category;
        $postType = $type == 'blog' ? 'post' : 'product';
        $limit = 25;
        // if recent posts - get last posts
        $items = self::getPosts($category, $limit, $postType);
        foreach ($items as $key => $item) {
            if ($type == 'blog') {
                $post = self::getBlogPost($item, 'intro');
            } else {
                $post = self::getProductsPost($item);
            }
            array_push($posts, $post);
        }
        return $posts;
    }

    /**
     * Get post for blog control
     *
     * @param WP_Post $item
     * @param string  $type
     *
     * @return array $data
     */
    public static function getBlogPost($item, $type='full') {
        // post image
        $thumb_id = get_post_thumbnail_id($item->ID);
        if ($thumb_id) {
            $url = get_attached_file($thumb_id);
            if ($url) {
                $uploads = wp_upload_dir();
                $postImageUrl = str_replace($uploads['basedir'], $uploads['baseurl'], $url);
            }
        } else {
            preg_match('/<img[\s\S]+?src=[\'"]([\s\S]+?)[\'"] [\s\S]+?>/', $item->post_content, $regexResult);
            if (count($regexResult) > 0) {
                $postImageUrl = $regexResult[1];
            }
        }
        // post category
        $postCategories = str_replace(
            '<a',
            '<a class="u-textlink"',
            get_the_category_list(_x(', ', 'Used between list items, there is a space after the comma.', 'nicepage'), '', $item->ID)
        );
        // post edit link
        $postEditLink = '<a href="' . get_edit_post_link($item->ID) . '">' . __('Edit', 'nicepage') . '</a>';
        // post tags
        $tags = get_the_tag_list('', _x(', ', 'Used between list items, there is a space after the comma.', 'nicepage'), '', $item->ID);
        // post link
        $postLink = get_permalink($item->ID);
        /* translators: %d: Number of comments */
        $comments_text = __('Comments (%d)', 'nicepage');
        // all post data
        $data = array(
            'post-header' => $item->post_title,
            'post-header-link' => $postLink ? $postLink : '',
            'post-content' => $type === 'full' ? $item->post_content : plugin_trim_long_str(self::getTheExcerpt($item->ID), 150),
            'post-image' => isset($postImageUrl) ? $postImageUrl : '',
            'post-readmore-text' => __('Read more', 'nicepage'),
            'post-readmore-link' => $postLink ? $postLink : '',
            'post-metadata-author' => get_the_author_meta('display_name', $item->post_author),
            'post-metadata-date' => get_the_date('', $item->ID),
            'post-metadata-category' => $postCategories,
            'post-metadata-comments' => sprintf($comments_text, $item->comment_count),
            'post-metadata-edit' => $postEditLink,
            'post-tags' => $tags,
        );
        return $data;
    }

    /**
     * Get post for products control
     *
     * @param WP_Post $item
     *
     * @return array $data
     */
    public static function getProductsPost($item) {
        $galleryData = array();
        $variations = array();
        if (function_exists('wc_get_product')) {
            if (isset($item->ID)) {
                $id = $item->ID;
            } else {
                $id = method_exists($item, 'get_id') ? $item->get_id() : $item->id;
            }
            $product_data = np_data_product($id, true);
            $product = $product_data['product'];

            $attachment_ids = $product_data['gallery_images_ids'];
            foreach ($attachment_ids as $attachment_id) {
                array_push($galleryData, wp_get_attachment_url($attachment_id));
            }

            $product_type = $product_data['type'];
            if (method_exists($product, 'get_variation_attributes') && $product_type == 'variable') {
                $productAttributes = $product_data['attributes'];
                $variation_attributes = $product_data['variations_attributes'];
                if (is_array($variation_attributes)) {
                    foreach ($variation_attributes as $name => $variation_attribute) {
                        $productAttribute = $productAttributes[strtolower($name)] ? $productAttributes[strtolower($name)] : $productAttributes[wc_attribute_taxonomy_slug($name)];
                        $variation_title = $productAttribute['name'];
                        $variation_options = $productAttribute['options'];
                        if (isset($productAttribute['id']) && $productAttribute['id'] > 0) {
                            $attribute = NpDataProduct::getProductAttribute($productAttribute['id']);
                            $variation_options = $productAttribute->get_terms();
                            $variation_title = NpDataProduct::getProductVariationTitle($attribute, $productAttribute);
                        }

                        $product_options = array();
                        if (is_array($variation_options)) {
                            foreach ($variation_options as $variation_option) {
                                $variation_option_title = NpDataProduct::getProductVariationOptionTitle($variation_option);
                                array_push($product_options, array('value' => $variation_option_title, 'text' => $variation_option_title));
                            }
                        }

                        $variations[] = array(
                            'title' =>  $variation_title,
                            'options' => $product_options
                        );
                    }
                }
            }
        }

        // all post data
        $data = array(
            'product-title'       => isset($product_data['title']) ? $product_data['title'] : '',
            'product-desc'        => isset($product_data['shortDesc']) ? $product_data['shortDesc'] : '',
            'product-full-desc'   => isset($product_data['fullDesc']) ? $product_data['fullDesc'] : '',
            'product-image'       => isset($product_data['image_url']) ? $product_data['image_url'] : '',
            'product-price'       => isset($product_data['price']) ? $product_data['price'] : '',
            'product-old-price'   => isset($product_data['price_old']) ? $product_data['price_old'] : '',
            'product-button-text' => $product_data['add_to_cart_text'] ?: np_translate('Add to cart'),
            'product-gallery'     => $galleryData,
            'product-variations'  => $variations,
            'product-tabs'        => isset($product_data['tabs']) ? $product_data['tabs'] : '',
            'product-categories'  => isset($product_data['categories']) ? $product_data['categories'] : array(),
        );
        return $data;
    }

    /**
     * Get excerpt for blog control
     *
     * @param int $post_id
     *
     * @return string $output
     */
    public static function getTheExcerpt($post_id) {
        global $post;
        $save_post = $post;
        $post = get_post($post_id);
        if (has_excerpt($post_id)) {
            $output = get_the_excerpt();
        } else {
            $output = self::getExcerptById($post_id, 150);
        }
        $post = $save_post;
        return $output;
    }

    /**
     * Get excerpt for blog control when excerpt is empty
     *
     * @param int $post_id
     * @param int $length
     *
     * @return string $the_excerpt
     */
    public static function getExcerptById($post_id, $length) {
        $the_post = get_post($post_id); //Gets post ID
        $the_excerpt = $the_post->post_content; //Gets post_content to be used as a basis for the excerpt
        $excerpt_length = $length; //Sets excerpt length by word count
        $the_excerpt = strip_tags(strip_shortcodes($the_excerpt)); //Strips tags and images
        $words = explode(' ', $the_excerpt, $excerpt_length + 1);

        if(count($words) > $excerpt_length) :
            array_pop($words);
            array_push($words, '&hellip;');
            $the_excerpt = implode(' ', $words);
        endif;

        $the_excerpt = '<p>' . $the_excerpt . '</p>';

        return $the_excerpt;
    }

    /**
     * Get active menu id from wp
     *
     * @param bool|string $location
     *
     * @return int|string
     */
    public static function getMenuId($location=false) {
        $menu_id = 0;
        $locations = get_nav_menu_locations();
        $location = $location ?: 'primary-navigation-1';
        if ($location) {
            $menu_id = isset($locations[$location]) ? $locations[$location] : 0;
        }
        if (!$menu_id && count($locations) > 0) {
            for ($i = 0; $i < count($locations); $i++) {
                $menu_id = array_pop($locations);
                if ($menu_id) {
                    break;
                }
            }
        }
        return $menu_id;
    }

    public static $_menuIds = array();
    public static $_extraMenuIds = array();

    /**
     * Get menu items by menu id
     *
     * @param bool|int $menu_id
     *
     * @return array
     */
    public static function getMenuItemsById($menu_id=false) {
        $items = array();
        if (!$menu_id) {
            $menu_id = self::getMenuId();
        }
        if ($menu_id) {
            $items = wp_get_nav_menu_items($menu_id);
        }
        return $items;
    }

    /**
     * Fix href for file link in menu
     *
     * @param WP_Post $el menu item
     *
     * @return WP_Post
     */
    public static function processFileLink($el) {
        if (isset($el->url) && strpos($el->url, '[[site_path_live]]') !== false) {
            $linkParts = explode('[[site_path_live]]', $el->url);
            $url = isset($linkParts[1]) ? get_site_url() . $linkParts[1] : $el->url;
            $el->url = $url;
        }
        return $el;
    }

    public static $langs_items;

    /**
     * Get menu items from wp to editor
     *
     * @return array
     */
    public static function getMenuItems() {
        $result = array();
        self::$langs_items = array();
        $menu_ids = self::getMenuIds();
        foreach ($menu_ids as $key => $menu_id) {
            if ($key === 'siteMenuId') {
                $default_lang_items = self::getMenuItemsById($menu_id);
            } else {
                foreach ($menu_id as $lang => $translation_menu_id) {
                    self::$langs_items[$lang] = self::getMenuItemsById($translation_menu_id);
                }
            }
        }
        if (isset($default_lang_items) && $default_lang_items) {
            $result = self::buildMenuItems($default_lang_items);
        }
        return count($result) > 0 ? $result : null;
    }

    /**
     * Build menu items structure with items ids
     *
     * @param array $items
     *
     * @return array $result
     */
    public static function buildMenuItems($items=array()) {
        $maxMenuItems = 20;
        foreach ($items as $i => $item) {
            $item = self::processFileLink($item);
            if ($item->menu_item_parent > 0) {
                $maxMenuItems ++;
            }
            if ($i >= $maxMenuItems) {
                self::$_extraMenuIds[] = $item->ID;
            } else {
                self::$_menuIds[] = $item->ID;
            }
        }
        return self::buildHierarchicalMenu($items);
    }

    /**
     * Get menu ids for all languages - need to editor save
     *
     * @return array
     */
    public static function getMenuIds() {
        $result = array();
        $menu_translations = self::getMenuTranslations();
        if ($menu_translations && count($menu_translations) > 0) {
            if (!isset($result['translationsMenuId'])) {
                $result['translationsMenuId'] = array();
            }
            $firstMenu = array_shift($menu_translations);
            foreach ($firstMenu as $lang => $location) {
                $key = $lang === 'default' ? 'siteMenuId' : $lang;
                if ($lang === 'default') {
                    $result[$key] = self::getMenuId($location);
                } else {
                    $result['translationsMenuId'][$key] = self::getMenuId($location);
                }
            }
        } else {
            $result['siteMenuId'] = self::getMenuId();
        }
        return $result;
    }

    /**
     * Get menu translations for header and footer
     *
     * @return array $menu_translations
     */
    public static function getMenuTranslations() {
        $menuTranslations = array();
        if (get_option('np_menu_translations')) {
            $menuTranslations = json_decode(get_option('np_menu_translations'), true);
        }
        return $menuTranslations;
    }

    /**
     * Set menu translations for header and footer
     *
     * @param array $menuTranslations
     */
    public static function setMenuTranslations($menuTranslations) {
        if ($menuTranslations) {
            update_option('np_menu_translations', json_encode($menuTranslations));
        }
    }

    /**
     * Build menu items in hierarchical array
     *
     * @param array $items
     * @param int   $parentId
     *
     * @return array
     */
    public static function buildHierarchicalMenu(array $items, $parentId = 0) {
        $branch = array();
        $translations = array();
        $level = 1;
        $stack = array('0');
        foreach ($items as $key => $item) {
            while ($item->menu_item_parent != array_pop($stack) && $level >= 1) {
                $level--;
            }
            $level++;
            $stack[] = $item->menu_item_parent;
            $stack[] = $item->ID;
            $link_title = $item->post_title ? $item->post_title : $item->title;
            $active_item_id = (get_the_ID() === (int) $item->object_id) ? (int) $item->object_id : false;
            $translations = self::buildTranslations($key, $translations);
            $result = array(
                'title' => strip_tags($link_title),
                'id' => $active_item_id,
                'publishUrl' => $item->url,
                'blank' => isset($item->target) && $item->target === '_blank' ? true : false,
                'translations' => json_encode($translations),
                'level' => ($level - 1),
            );

            if ($item->menu_item_parent == $parentId) {
                $children = self::buildHierarchicalMenu($items, $item->ID);
                if ($children) {
                    $result['items'] = $children;
                }
                $branch[] = $result;
            }
        }
        return $branch;
    }

    /**
     * Build menu items translation
     *
     * @param string $key
     * @param array  $translations
     *
     * @return array $translations
     */
    public static function buildTranslations($key, $translations) {
        if (isset(self::$langs_items) && self::$langs_items) {
            foreach (self::$langs_items as $lang => $lang_items) {
                if (!isset($translations[$lang])) {
                    $translations[$lang] = array();
                }
                if (isset($lang_items[$key])) {
                    $translation_item = $lang_items[$key];
                    $translations[$lang]['content'] = isset($translation_item->post_title) && $translation_item->post_title ? $translation_item->post_title : $translation_item->title;
                    $translations[$lang]['href'] = $translation_item->url;
                }
            }
        }
        return $translations;
    }

    /**
     * Get files json in Nicepage-editor format
     *
     * @param bool|string $type
     *
     * @return array
     * @throws Exception
     */
    public static function getFiles($type = false) {
        $allowed_types_attachment = get_allowed_mime_types();
        $allowed_types_without_images = array();
        $allowed_video_types = array();
        foreach ($allowed_types_attachment as $allowed_type) {
            if ($allowed_type !== 'image/jpeg' && $allowed_type !== 'image/png' && $allowed_type !== 'image/bmp' && $allowed_type !== 'image/tiff' && $allowed_type !== 'image/x-icon' && $allowed_type !== 'image/gif') {
                $allowed_types_without_images[] = $allowed_type;
                if (strpos($allowed_type, 'video') === 0) {
                    $allowed_video_types[] = $allowed_type;
                }
            }
        }
        $file_types = $type == 'video' ? $allowed_video_types : $allowed_types_without_images;

        $query_options = array(
            'post_type' => 'attachment',
            'post_mime_type' => $file_types,
            'posts_per_page' => -1,
            'order' => 'ASC',
            'orderby' => 'modified',
            'post_status' => 'any',
        );

        $result = array();

        $files = get_posts($query_options);

        foreach ($files as $file) {
            $current_file = self::getFile($file);
            $result[] = $current_file;
        }

        return array(
            'result' => 'done',
            'data' => $result,
        );
    }

    /**
     * Get file json in Nicepage-editor format
     *
     * @param WP_Post $post
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getFile($post) {
        if (is_int($post)) {
            $post = get_post($post);
        }
        if ($post === null) {
            throw new Exception('post is undefined');
        }

        $result = array(
            'title' => $post->post_name,
            'url' => $post->guid
        );
        return $result;
    }

    /**
     * Action on admin_head
     * Print prepared data for Nicepage-editor
     *
     * @throws Exception
     */
    public static function printBridgeAction() {
        $theme_typography_page = _arr($_GET, 'np_page') ? true : false;
        global $post;
        if (!$theme_typography_page) {
            if (!$post || !NpEditor::isAllowedForEditor($post)) {
                return;
            }
        }

        $post_id = $theme_typography_page ? 0 : $post->ID;
        $post_title = $theme_typography_page ? 'Theme Typography' : $post->post_title;

        $user = wp_get_current_user();
        $uid = (int)$user->ID;
        $ajax_nonce = wp_create_nonce('np-upload');
        $edit_post_url = NpAdminActions::adminUrlFilter(admin_url('post.php?action=edit')) . '&post={id}';
        $autoSaveValue = get_option('np_include_auto_save');
        $previous_plugin_info = NpMeta::get('site_settings');
        if ($previous_plugin_info === null && $autoSaveValue === false) {
            $autoSaveValue = '1';
        }
        $autoSave = $autoSaveValue === '1' ? true : false;

        $settings = array(
            'actions' => array(
                'uploadFile'             => NpAction::getActionUrl('np_upload_file'),
                'uploadImage'            => NpAction::getActionUrl('np_upload_image'),
                'savePage'               => NpAction::getActionUrl('np_save_page'),
                'publishPage'            => NpAction::getActionUrl('np_publish_page'),
                'clearChunks'            => NpAction::getActionUrl('np_clear_chunks'),
                'saveSiteSettings'       => NpAction::getActionUrl('np_save_site_settings'),
                'saveLocalStorageKey'    => NpAction::getActionUrl('np_save_local_storage_key'),
                'getSite'                => NpAction::getActionUrl('np_get_site'),
                'getSitePosts'           => NpAction::getActionUrl('np_get_site_posts'),
                'savePreferences'        => NpAction::getActionUrl('np_save_preferences'),
                'saveMenuItems'          => NpAction::getActionUrl('np_save_menu_items'),
                'removeFont'             => NpAction::getActionUrl('np_remove_font'),
                'getPosts'               => add_query_arg(array('type' => 'blog'), NpAction::getActionUrl('np_get_posts_by_type')),
                'getProducts'            => add_query_arg(array('type' => 'products'), NpAction::getActionUrl('np_get_posts_by_type')),
                'getCategories'          => NpAction::getActionUrl('np_get_categories'),
                'saveProductsJson'       => NpAction::getActionUrl('np_save_products_json'),
            ),
            'ajaxData' => array(
                'uid' => $uid,
                '_ajax_nonce' => $ajax_nonce,
            ),
            'uploadFileOptions' => array(
                'formFileName' => 'async-upload',
                'params' => array(
                    'html-upload' => 'Upload',
                    '_wpnonce' => wp_create_nonce('media-form'),
                    'uid' => $uid,
                    '_ajax_nonce' => $ajax_nonce,
                ),
            ),
            'editPostUrl' => $edit_post_url,
            'dashboardUrl' => NpAdminActions::adminUrlFilter(admin_url()),
            'loginUrl' => wp_login_url($edit_post_url),
            'pageId' => np_data_provider($post_id)->isConvertRequired() ? '' : $post_id,
            'startPageId' => $post_id,
            'startPageTitle' => $post_title,
        );

        $cms_settings = array(
            'defaultImageUrl' => NpAttachments::getDefaultImageUrl(),
            'defaultLogoUrl' => NpAttachments::getDefaultLogoUrl(),
            'isFirstStart' => !!_arr($_GET, 'np_new'),
            'maxRequestSize' => min(
                wp_convert_hr_to_bytes(ini_get('post_max_size')),
                wp_convert_hr_to_bytes(ini_get('upload_max_filesize')),
                wp_convert_hr_to_bytes(ini_get('memory_limit'))
            ),
            'isWhiteLabelPlugin' => pathinfo(dirname(dirname(__FILE__)), PATHINFO_BASENAME) != ('n' . 'i' . 'c' . 'e' . 'p' . 'a' . 'g' . 'e'),
            'disableAutosave' => !$autoSave
        );
        $data_provider_auto_save = np_data_provider($post_id, null, false);
        $data_provider = np_data_provider($post_id);

        $page_obj = $theme_typography_page ? array() : NpAction::getPost($post);
        $page_html_auto_save = $data_provider_auto_save->getPageHtml();
        $page_html = $data_provider->getPageHtml();
        $page_html = ($page_html_auto_save && $page_html_auto_save !== '') ? $page_html_auto_save : $page_html;
        $page_html = $theme_typography_page ? '' : self::_restorePageType($page_html, $post);
        $data = array(
            'site' => NpGetSiteAction::getSite(),
            'pageHtml' => $page_html,
            'nicePageCss' => file_get_contents(APP_PLUGIN_PATH . 'assets/css/nicepage-dynamic.css'),
            'downloadedFonts' => self::getDownloadedFonts(),
            'customFonts' => self::getCustomFonts(),
            'productsJson' => getProductsJson(),
            'getProjectProductsFiles' => $data_provider->getProjectProductsFiles(),
            'isCmsClientMode' => self::isCmsClientMode(),
        );
        $page_found = false;
        foreach ($data['site']['items'] as $page) {
            if (isset($page_obj['id']) && $page_obj['id'] === $page['id']) {
                $page_found = true;
                break;
            }
        }
        $headerNp_auto_save = get_option('headerNp_auto_save');
        $headerNp = get_option('headerNp');
        $headerNp = ($headerNp_auto_save && $headerNp_auto_save !== '') ? $headerNp_auto_save : $headerNp;
        $headerNp = fixImagePaths($headerNp);
        $footerNp_auto_save = get_option('footerNp_auto_save');
        $footerNp = get_option('footerNp');
        $footerNp = ($footerNp_auto_save && $footerNp_auto_save !== '') ? $footerNp_auto_save : $footerNp;
        $footerNp = fixImagePaths($footerNp);
        if (isset($headerNp)) {
            $headerItem = json_decode($headerNp, true);
            $data['site']['header'] = $headerItem ? $headerItem['html'] : '';
        }
        if (isset($footerNp)) {
            $footerItem = json_decode($footerNp, true);
            $data['site']['footer'] = $footerItem ? $footerItem['html'] : '';
        }

        if (!$page_found && !$theme_typography_page) {
            array_push($data['site']['items'], $page_obj);
        }

        $newPageTitle = '';
        if (isset($page_obj['title']) && !$page_obj['title']) {
            $uniqueTitle = self::createUniqueTitle('Page');
            $page_obj['title'] = $uniqueTitle;
            $newPageTitle = $uniqueTitle;
        }

        $data['info'] = array(
            'taxonomies' => array(),
            'menus' => array(),
            'productsExists' => is_woocommerce_active(),
            'newPageUrl' => NpAdminActions::adminUrlFilter(admin_url('post-new.php?post_type=page&np_new=1')),
            'forceModified' => !get_post_meta($post_id, '_np_site_style_css_used_ids', true),
            'generalSettingsUrl' => NpAdminActions::adminUrlFilter(admin_url('options-general.php')),
            'typographyPageHtmlUrl' => add_query_arg(array('np_html' => '1', 'preview' => 'true'), get_permalink($post_id)),
            'siteIsSecureAndLocalhost' => NpAdminActions::siteIsSecureAndLocalhost(),
            'newPageTitle' => $newPageTitle,
            'fontsInfo' => self::getFontsInfo(),
            'menuItems' => self::getMenuItems(),
            'menuOptions' => self::getMenuIds(),
            'blogInfo' => self::getPostsByType('blog'),
            'videoFiles' => self::getVideoFiles(),
            'productsInfo' => self::getPostsByType('product'),
            'localStorageKey' => get_option('np_local_storage_key', null),
            'protectedWithNp' => $data_provider->isNp(),
        );
        foreach (get_taxonomies(array('show_tagcloud' => true), 'object') as $taxonomy) {
            $data['info']['taxonomies'][] = array('name' => $taxonomy->name, 'label' => $taxonomy->label);
        }
        foreach (wp_get_nav_menus() as $menu) {
            $data['info']['menus'][] = array('id' => $menu->term_id . '', 'label' => $menu->name);
        }
        //if true editor can start
        $dataJson = wp_json_encode($data, JSON_PRETTY_PRINT);
        if ($settings && $cms_settings && $dataJson) {
            update_option('npDataBridge', 1);
        } else {
            update_option('npDataBridge', 0);
        }
        $mediaFiles = json_encode(self::getFiles());
        $uploadFileLink = $settings['actions']['uploadFile'];?>
        <script>
            window.phpVars = {
                'uploadFileLink': '<?php echo $uploadFileLink; ?>',
                'mediaFiles': <?php echo $mediaFiles; ?>,
                'maxRequestSize': '<?php echo $cms_settings['maxRequestSize']; ?>',
            }
        </script>
        <script>
            var dataBridgeData = <?php echo $dataJson; ?>,
                callbacks = [],
                attemptsCount = 0;

            window.dataBridge = {
                getSite: function getSite() {
                    return dataBridgeData.site;
                },
                setSite: function setSite(site) {
                    dataBridgeData.site = site;
                },
                getPageHtml: function getPageHtml() {
                    return dataBridgeData.pageHtml;
                },
                getNPCss: function getNPCss() {
                    return dataBridgeData.nicePageCss;
                },
                getDownloadedFonts: function getDownloadedFonts() {
                    return dataBridgeData.downloadedFonts;
                },
                setDownloadedFonts: function setDownloadedFonts(downloadedFonts) {
                    dataBridgeData.downloadedFonts = downloadedFonts;
                },
                getCustomFonts: function getCustomFonts() {
                    return dataBridgeData.customFonts;
                },
                setCustomFonts: function setCustomFonts(customFonts) {
                    dataBridgeData.customFonts = customFonts;
                },
                getStartTerm: function getStartTerm() {
                    return "<?php echo $data_provider->isConvertRequired() ? 'site:wordpress:' . $post_id : ''; ?>";
                },
                getDefaultPageType: function getDefaultPageType() {
                    return "<?php echo self::$_editorPageTypes[NpSettings::getOption('np_template')]; ?>";
                },
                getInfo: function getInfo() {
                    return dataBridgeData.info;
                },
                getProductsJson: function getProductsJson() {
                    return dataBridgeData.productsJson;
                },
                isCmsClientMode: function isCmsClientMode() {
                    return dataBridgeData.isCmsClientMode;
                },
                doLoggedIn: function doLoggedIn(func) {
                    callbacks.push(func);

                    // show login dialog
                    if (jQuery('#wp-auth-check-wrap').hasClass('hidden')) {
                        if (attemptsCount > 0) {
                            // login dialog will not be shown a second time
                            // see  "$(document).off( 'heartbeat-tick.wp-auth-check' );" in wp-auth-check.js
                            callbacks.forEach(function (cb) {
                                cb(false);
                            });
                            callbacks = [];
                            return;
                        }
                        jQuery(document).trigger('heartbeat-tick', [{'wp-auth-check': false}]);
                    }
                },
                settings: <?php echo wp_json_encode($settings, JSON_PRETTY_PRINT); ?>,
                cmsSettings: <?php echo wp_json_encode($cms_settings, JSON_PRETTY_PRINT); ?>
            };

            jQuery(document).on("heartbeat-tick.wp-refresh-nonces", function(c, d) {
                if (d['wp-refresh-post-nonces'] && typeof dataBridge === 'object') {
                    dataBridge.settings.ajaxData._ajax_nonce = d['wp-refresh-post-nonces'].replace.np_ajax_nonce;
                    dataBridge.settings.uploadFileOptions.params._ajax_nonce = d['wp-refresh-post-nonces'].replace.np_ajax_nonce;
                    dataBridge.settings.uploadFileOptions.params._wpnonce = d['wp-refresh-post-nonces'].replace.np_upload_image_nonce;

                    attemptsCount++;
                    callbacks.forEach(function (cb) {
                        cb(true);
                    });
                    callbacks = [];
                }
            });
            jQuery(document).on('click', '#wp-auth-check-wrap .wp-auth-check-close', function() {
                attemptsCount++;
                callbacks.forEach(function (cb) {
                    cb(false);
                });
            });



        </script>
        <?php
    }

    /**
     * Create unique page title based on specified string
     *
     * @param string $title
     *
     * @return string
     */
    public static function createUniqueTitle($title) {
        while (self::_pageWithExactTitleExists($title)) {
            if (preg_match('#(.*\s)(\d+)$#', $title, $match)) {
                $new_title = $match[1] . ($match[2] + 1);
                if ($title === $new_title) {
                    break;
                }
                $title = $new_title;
            } else {
                $title = $title . ' 1';
            }
        }
        return $title;
    }

    /**
     * Find page by title
     *
     * @param string $title
     *
     * @return bool
     */
    private static function _pageWithExactTitleExists($title)
    {
        $query = new WP_Query(
            [
                'post_type' => 'page',
                'post_status' => 'any',
                'title' => $title,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ]
        );

        if (!empty($query->posts)) {
            $page_id = $query->posts[0];
            $page = get_post($page_id);
            return $page && $page->post_title === $title;
        }

        return false;
    }

    /**
     * Action on in_admin_header
     * Prepare theme typography scripts
     */
    public static function printLoaderFrameScripts() {
        $theme_typography_page = _arr($_GET, 'np_page') ? true : false;
        global $post;
        if (!$theme_typography_page) {
            if (!$post || !NpEditor::isAllowedForEditor($post)) {
                return;
            }
        }

        $defined_settings = apply_filters('np_theme_settings', array());
        if ($defined_settings) {
            ?>
            <script>
                dataBridgeData.info.themeTypography = <?php echo wp_json_encode($defined_settings['typography']); ?>;
                dataBridgeData.info.themeFontScheme = <?php echo wp_json_encode($defined_settings['fontScheme']); ?>;
                dataBridgeData.info.themeColorScheme = <?php echo wp_json_encode($defined_settings['colorScheme']); ?>;
            </script>
            <?php
            return;
        }
        ?>
        <script src="<?php echo APP_PLUGIN_URL; ?>/editor/assets/js/typography-parser.js?v=<?php echo APP_PLUGIN_VERSION; ?>"></script>
        <iframe id="np-loader"></iframe>

        <script>
            var loadCallback;

            var needResetCache = !localStorage.np_theme_typography_cache ||
                document.cookie.indexOf('np_theme_typography_cache_force_refresh=1') !== -1;

            if (needResetCache) {
                delete localStorage.np_theme_typography_cache;
            }

            window.loadAppHook = function (load) {
                if (localStorage.np_theme_typography_cache) {
                    jQuery.extend(dataBridgeData.info, JSON.parse(localStorage.np_theme_typography_cache));
                    console.log('Regular load app.js');
                    load();
                    return;
                }
                loadCallback = load;
            };

            var loaderIframe = document.getElementById('np-loader');
            loaderIframe.addEventListener("load", function() {
                localStorage.np_theme_typography_cache = JSON.stringify(NpTypographyParser.parse(loaderIframe));
                document.cookie = 'np_theme_typography_cache_force_refresh=';
                jQuery(loaderIframe).remove();
                console.log('Typography cache updated');
                jQuery.extend(dataBridgeData.info, JSON.parse(localStorage.np_theme_typography_cache));
                if (loadCallback) {
                    console.log('Deferred load app.js');
                    loadCallback();
                }
            });

            if (location.protocol === "https:" && dataBridgeData.info.typographyPageHtmlUrl.indexOf('http://') !== -1) {
                console.log('Regular load app.js due to CORS');
                delete window.loadAppHook;
            } else {
                loaderIframe.src = dataBridgeData.info.typographyPageHtmlUrl;
            }
        </script>
        <?php
    }

    /**
     * Filter on the_posts
     * Create fake post for testing typography
     *
     * @param array $posts
     *
     * @return stdClass[]
     */
    public static function fakePostsFilter($posts) {
        global $wp_query;

        $post = new stdClass;
        $post->ID = -1;
        $post->post_author = 1;
        $post->post_date = current_time('mysql');
        $post->post_date_gmt = current_time('mysql', 1);
        $post->post_content = '<div id="np-test-container"></div>';
        $post->post_title = 'TEST';
        $post->post_excerpt = '';
        $post->post_status = 'publish';
        $post->comment_status = 'closed';
        $post->ping_status = 'closed';
        $post->post_password = '';
        $post->post_name = 'test-theme-page';
        $post->to_ping = '';
        $post->pinged = '';
        $post->modified = $post->post_date;
        $post->modified_gmt = $post->post_date_gmt;
        $post->post_content_filtered = '';
        $post->post_parent = 0;
        $post->guid = get_home_url('/' . $post->post_name);
        $post->menu_order = 0;
        $post->post_tyle = 'page';
        $post->post_mime_type = '';
        $post->comment_count = 0;

        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        unset($wp_query->query['error']);
        $wp_query->query_vars['error'] = '';
        $wp_query->is_404 = false;

        return array($post);
    }


    /**
     * Filter on admin_url
     * Add domain parameter if need
     *
     * @param string $url
     *
     * @return string
     */
    public static function adminUrlFilter($url) {
        if (isset($_GET['domain'])) {
            $url = add_query_arg(array('domain' => urlencode($_GET['domain'])), $url);
        }
        return $url;
    }

    /**
     * Get fonts info
     *
     * @return array
     */
    public static function getFontsInfo() {
        $info = array(
            'path' => '',
            'canSave' => true,
        );
        $base_upload_dir = wp_upload_dir();
        $fonts = $base_upload_dir['basedir'] . '/nicepage-gfonts/';
        if (!file_exists($fonts)) {
            if (false === @mkdir($fonts, 0777, true)) {
                return array_merge($info, array('path' => $fonts, 'canSave' => false));
            }
        } else {
            $error = self::checkWritable($fonts);
            if (count($error) > 0) {
                return array_merge($info, $error);
            }
        }
        return $info;
    }

    /**
     * Check path writable
     *
     * @param string $path Path
     *
     * @return string
     */
    public static function checkWritable($path) {
        if (function_exists('get_current_user') && function_exists('chown') && function_exists('chmod')) {
            $user = get_current_user();
            @chown($path, $user);
            @chmod($path, 0777);
        }
        $result = array();
        if (!is_writable($path)) {
            $result = array(
                'path' => $path,
                'canSave' => false,
            );
        }
        return $result;
    }

    /**
     * Get downloaded fonts
     *
     * @return false|string
     */
    public static function getDownloadedFonts() {
        $base_upload_dir = wp_upload_dir();
        $fontsDir = $base_upload_dir['basedir'] . '/nicepage-gfonts/';
        $downloadedFontsFile = $fontsDir . 'downloadedFonts.json';

        $fontsJson = file_exists($downloadedFontsFile) ? file_get_contents($downloadedFontsFile) : '[]';
        $fontsList = json_decode($fontsJson, true);

        if (!is_array($fontsList)) {
            $fontsList = [];
        }

        $allFilesExist = true;

        foreach ($fontsList as $fontFile) {
            $fontPath = $fontsDir . $fontFile;
            if (!file_exists($fontPath)) {
                $allFilesExist = false;
                break;
            }
        }

        return $allFilesExist ? $fontsJson : '';
    }

    /**
     * Action on in_admin_header
     * Print Nicepage-editor iframe at the top
     */
    public static function printEditorAction() {
        global $post;
        if (_arr($_GET, 'np_new') || _arr($_GET, 'np_edit')) {
            if ($post && $post->ID && !_arr($_GET, 'np_new')) {
                np_data_provider($post->ID, true)->clear();
            }
            $can_start_editor = get_option('npDataBridge');
            if ($can_start_editor) {
                $settingsPage = array('post_id' => $post ? $post->ID : 0);
                if (_arr($_GET, 'np_page')) {
                    $settingsPage['theme_typography_page'] = _arr($_GET, 'np_page');
                }
                if (_arr($_GET, 'np_new')) {
                    $settingsPage['new_page'] = true;
                }
                echo NpAdminActions::getEditorContainerHtml($settingsPage);
            } else {
                ?>
                <script>
                    window.location.href = `<?php echo admin_url().'edit.php?post_type=page';?>`;
                    alert('Unable to start the Editor. Please contact the Support.');
                </script>
                <?php
            }
        }
    }

    /**
     * Action in init
     */
    public static function init() {
        // cms user role option
        self::$cmsUserRoleOption = self::getCmsUserRoleOption();
        if (self::$cmsUserRoleOption) {
            if (self::$cmsUserRoleOption === 'editorNoAccess') {
                self::$editorShow = false;
            }
            if (self::$cmsUserRoleOption === 'contentEditOnly') {
                self::$showFullMenu = false;
            }
        }
        add_filter('page_row_actions', 'NpAdminActions::pageRowAction');
    }

    /**
     * Action on page_row_actions
     * Add "Edit with Nicepage" links to pages list
     *
     * @param array $actions
     *
     * @return array
     */
    public static function pageRowAction($actions) {
        if (!self::$editorShow) {
            return $actions;
        }
        $post = get_post();
        if (!NpEditor::isAllowedForEditor($post)) {
            return $actions;
        }
        if (np_data_provider($post->ID)->isConvertRequired()) {
            $actions['edit_in_nicepage'] = '<a href="' . add_query_arg(array('np_edit' => '1'), get_edit_post_link($post->ID)) . '">' . __('Turn to Nicepage', 'nicepage') . '</a>';
        } else {
            $actions['edit_in_nicepage'] = '<a href="' . add_query_arg(array('np_edit' => '1'), get_edit_post_link($post->ID)) . '">' . __('Edit with Nicepage', 'nicepage') . '</a>';
        }
        return $actions;
    }

    /**
     * Action on wp_refresh_nonces
     * Add new nonces to replace them in nicepageSettings
     *
     * @param array  $response
     * @param array  $data
     * @param string $screen_id
     *
     * @return array
     */
    public static function refreshNonsesFilter($response, $data, $screen_id) {
        if (array_key_exists('wp-refresh-post-nonces', $data)) {
            if (!$post_id = absint($data['wp-refresh-post-nonces']['post_id'])) {
                return $response;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return $response;
            }

            $response['wp-refresh-post-nonces']['replace']['np_ajax_nonce'] = wp_create_nonce('np-upload');
            $response['wp-refresh-post-nonces']['replace']['np_upload_image_nonce'] = wp_create_nonce('media-form');
        }

        return $response;
    }

    /**
     * Action on after_switch_theme
     * Set cookie for refreshing typography cache
     */
    public static function forceRefreshTypographyCache() {
        setcookie('np_theme_typography_cache_force_refresh', '1', time() + 3*YEAR_IN_SECONDS);
    }

    /**
     * @param array   $post_states
     * @param WP_Post $post
     *
     * @return array $post_states
     */
    public static function addPostState($post_states, $post) {
        if (!self::$editorShow) {
            return $post_states;
        }
        $data_provider = np_data_provider($post->ID);
        if ($post->post_type === "page" && $data_provider->isNp()) {
            $post_states[] = 'Nicepage';
        }
        return $post_states;
    }

    /**
     * Restore page type for editor
     *
     * @param string  $pageHtml Page html
     * @param WP_Post $post
     *
     * @return mixed
     */
    private static function _restorePageType($pageHtml, $post) {
        if ($post->post_type === 'page') {
            $post_type = get_post_meta($post->ID, '_np_template', true);
            $pageView = isset($post_type) ? $post_type : NpSettings::getOption('np_template');
            $rePageType = '/<meta name="page_type" content="[^"]+?">/';
            if (preg_match($rePageType, $pageHtml)) {
                $pageHtml = preg_replace($rePageType, '<meta name="page_type" content="' . self::$_editorPageTypes[$pageView] . '">', $pageHtml);
            } else {
                $pageHtml = str_replace('<head>', '<head><meta name="page_type" content="' . self::$_editorPageTypes[$pageView] . '">', $pageHtml);
            }
        }
        return $pageHtml;
    }
}

// @codingStandardsIgnoreStart
/**
 * Action on edit_form_after_title
 * NOTE: For backward compatibility with themler-core do not rename this function
 *
 * @param WP_Post $post
 */
function upage_edit_form_buttons($post) {
    ob_start();
    do_action('themler_edit_form_buttons', $post);
    $html = ob_get_clean();

    if ($html) {
        ?>
        <div style="margin-top: 5px; margin-bottom: 10px;">
            <?php echo $html; ?>
        </div>
        <?php
    }
}
// @codingStandardsIgnoreEnd
if (!has_action('edit_form_after_title', 'themler_edit_form_buttons')) {
    add_action('edit_form_after_title', 'upage_edit_form_buttons');
}

/**
 * Fix blog control pagination for static homepage with augmented query
 *
 * @param WP_QUERY $query
 */
function modify_query_for_pagination($query) {
    if ($query->is_main_query()) {
        if (!get_query_var('paged') && get_query_var('page')) {
            $paged = (get_query_var('page')) ? get_query_var('page') : 1;
            $query->set('paged', $paged);
        }
    }
}
add_action('pre_get_posts', 'modify_query_for_pagination');

add_filter('display_post_states', 'NpAdminActions::addPostState', 10, 2);
add_filter('get_edit_post_link', 'NpAdminActions::editPostLinkFilter');
add_action('edit_form_top', 'NpAdminActions::editFormTopFilter');
add_action('themler_edit_form_buttons', 'NpAdminActions::addNicepageButtonAction');
add_action('admin_menu', 'NpAdminActions::addEditorPageAction');
add_action('_network_admin_menu', 'NpAdminActions::addMenuAction');
add_action('_user_admin_menu', 'NpAdminActions::addMenuAction');
add_action('_admin_menu', 'NpAdminActions::addMenuAction');
add_action('admin_print_styles', 'NpAdminActions::printMenuStylesAction');
add_action('load-pages_page_np_app', 'NpAdminActions::appAction');
add_action('edit_form_after_title', 'NpAdminActions::showScreenShotsAction', 100);
add_action('admin_head', 'NpAdminActions::printPreviewStylesAction');
add_action('admin_head', 'NpAdminActions::addSeoAnalytics');
add_action('admin_head', 'NpAdminActions::printBridgeAction');
add_action('custom_menu_order', 'NpAdminActions::addSubmenusAction');
add_action('in_admin_header', 'NpAdminActions::printEditorAction');
add_action("in_admin_header", 'NpAdminActions::printLoaderFrameScripts');
add_action('init', 'NpAdminActions::init');

// after wp_refresh_post_nonces
add_filter('wp_refresh_nonces', 'NpAdminActions::refreshNonsesFilter', 11, 3);

add_action("after_switch_theme", 'NpAdminActions::forceRefreshTypographyCache');

if (Nicepage::isHtmlQuery()) {
    add_filter('the_posts', 'NpAdminActions::fakePostsFilter', 1003);
}
