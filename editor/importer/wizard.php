<?php
defined('ABSPATH') or die;

/**
 * Pwizard
 */
class Pwizard {

    protected $options_steps = array();
    protected $page_slug;
    protected $page_title;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct($options) {
        $this->enqueueScripts();
        $this->setOptions($options);
        $this->init();
    }

    /**
     * Set options
     *
     * @param array $options
     */
    public function setOptions($options) {
        if (isset($options['page_slug'])) {
            $this->page_slug = esc_attr($options['page_slug']);
        }
        if (isset($options['page_title'])) {
            $this->page_title = esc_attr($options['page_title']);
        }
    }

    /**
     * Print the content for the widgets step
     */
    public function getStepContent() {
        $content = array();
        // Check if the content imported
        $hideImport = get_option('themler_hide_import_notice');
        if ($hideImport) {
            $content['summary'] = sprintf(
                '<p>%s</p>',
                __('Content has already been imported. Please skip this step', 'nicepage')
            );
        } else {
            $content['summary'] = sprintf(
                '<p>%s</p>',
                __('Nicepage plugin has Pages, Images, Menu, Header, and Footer. </br></br>Do you want to import the Content?', 'nicepage')
            );
        }
        $content['import_options'] = sprintf(
            '<p style="margin: 20px 0 0 0;" class="import-options"><input type="checkbox" id="importSidebarsContent" name="importSidebarsContent" checked="checked"><label for="importSidebarsContent">%s</label></p>',
            __('Import Sidebars Content', 'nicepage')
        );
        if (is_woocommerce_active()) {
            $content['import_options'] .= sprintf(
                '<p style="margin: 5px 0 0 0;" class="import-options"><input type="checkbox" id="importProductsContent" name="importProductsContent" checked="checked"><label for="importProductsContent">%s</label></p>',
                __('Import Products Content to Woocommerce', 'nicepage')
            );
        }
        $content = apply_filters('pwizard_filter_content', $content);
        return $content;
    }

    /**
     * Print the content for the widgets step
     */
    public function getStepProducts() {
        $content = array();
        $options = '';

        $contentPath = dirname(dirname(__FILE__)) . '/content/content.json';
        if (file_exists($contentPath)) {
            $jsonContent = file_get_contents($contentPath);
            $data = json_decode($jsonContent, true);
            if (!empty($data['Parameters']['productsJson'])) {
                $productsData = json_decode($data['Parameters']['productsJson'], true);
                if (!empty($productsData['products'])) {
                    $options .= '<option value="content-source" ' . selected(get_option('np_products_source'), 'content-source', false) . '>' . __('Content', 'nicepage') . '</option>';
                }
            }
        }

        $themeProductsPath = get_template_directory() . '/shop/products.json';
        if (file_exists($themeProductsPath)) {
            $jsonContent = file_get_contents($themeProductsPath);
            $data = json_decode($jsonContent, true);
            if (!empty($data['products'])) {
                $options .= '<option value="theme-source" ' . selected(get_option('np_products_source'), 'theme-source', false) . '>' . __('Theme', 'nicepage') . '</option>';
            }
        }

        $content['detail'] = '
    <div class="products-options">
        <label for="np_products_source">' . __('We have found Products that you can import to your site. </br></br>Do you want to continue with importing?', 'nicepage') . '</label>
        </br></br>
        <select name="np_products_source" id="np_products_source">
            ' . $options . '
        </select>
    </div>
    <p>';

        // Check if the products imported
        $old_products_data = getProductsJson();
        if (isset($old_products_data['products']) && count($old_products_data['products']) > 0) {
            $content['detail'] .= sprintf(
                '<p><b>%s</b></p>',
                __('Please note that some Products already exist in your database, and the import will overwrite the current ones.', 'nicepage')
            );
        }
        return $content;
    }


    /**
     * Print the theme appearance for the widgets step
     */
    public function getStepThemeAppearance() {
        $content['detail'] = '
    <div class="theme-appearance-options">
        <label for="np_theme_appearance">' . __('Choose the source for your site\'s Colors, Fonts, Header, and Footer, as well as for the Blog, E-Commerce, and other Templates.', 'nicepage') . '</label>
        </br></br>
        <select name="np_theme_appearance" id="np_theme_appearance">
            <option value="theme-option" ' . selected(get_option('np_theme_appearance'), 'theme-option', false) . '>' . __('Theme', 'nicepage') . '</option>
            <option value="plugin-option" ' . selected(get_option('np_theme_appearance'), 'plugin-option', false) . '>' . __('Plugin', 'nicepage') . '</option>
        </select>
    </div>
    <p>' . sprintf(
            // translators: %s is the URL to Nicepage settings page.
                __('You can change it later in the <a href="%s">Nicepage Settings</a>', 'nicepage'),
                esc_url(admin_url('admin.php?page=np_settings'))
            ) . '</p>';
        return $content;
    }

    /**
     * Print the content for the final step
     */
    public function getStepDone() {
        $content = array();
        $content['summary'] = sprintf(
            '<p>%s</p>',
            __('Congratulations! The Nicepage plugin has been activated and your website is ready.', 'nicepage')
        );
        $content['summary'] .= sprintf('<p>%s</p>', 'Create a new page with the Nicepage Editor.', 'nicepage');
        $content['buttons'] = '<br><a href="' . admin_url('post-new.php?post_type=page&np_new=1') . '" class="button button-primary">Create Page</a>';
        $content['buttons'] .= '<a href="' . get_site_url() . '" style="margin-left: 5px;" id="visit-site" class="button button-secondary">Visit Site</a>';
        $content['buttons'] .= '<a href="' . get_admin_url() . '" style="margin-left: 5px;" id="visit-site" class="button button-secondary">Close</a>';
        return $content;
    }

    /**
     * Set options for the steps
     *
     * @return array
     */
    public function getSteps() {
        $steps = array(
            'done' => array(
                'id' => 'done',
                'title' => __('Your website is ready!', 'nicepage'),
                'icon' => 'yes',
                'view' => 'getStepDone',
                'callback' => ''
            )
        );
        $theme_appearance_step = array(
            'theme_appearance' => array(
                'id' => 'theme_appearance',
                'title' => __('Select Site Style', 'nicepage'),
                'icon' => 'welcome-content-menus',
                'view' => 'getStepThemeAppearance',
                'callback3' => 'theme_appearance_update',
                'button3_text' => __('Apply', 'nicepage'),
                'can_skip' => true,
                'can_replace' => true
            ),
        );
        if (get_option('whiteLabelName') && !get_option('np_theme_appearance')) {
            $steps = $theme_appearance_step + $steps;
        }
        $import_content_step = array(
            'content' => array(
                'id' => 'content',
                'title' => __('Import Content', 'nicepage'),
                'icon' => 'welcome-content-menus',
                'view' => 'getStepContent',
                'callback' => 'import_content',
                'callback2' => 'replace_content',
                'button_text' => __('Import Content', 'nicepage'),
                'button2_text' => __('Replace previously imported Content', 'nicepage'),
                'can_skip' => true,
                'can_replace' => true
            ),
        );
        if (file_exists(dirname(dirname(__FILE__)) . '/content/content.json')) {
            $steps = $import_content_step + $steps;
        }
        $import_products_step = array(
            'products' => array(
                'id' => 'products',
                'title' => __('Import Products', 'nicepage'),
                'icon' => 'welcome-content-menus',
                'view' => 'getStepProducts',
                'callback_products_import' => 'import_products',
                'callback_products_replace' => 'replace_products',
                'button_products_import_text' => __('Import Products', 'nicepage'),
                'button_products_replace_text' => __('Replace previously imported Products', 'nicepage'),
                'can_skip' => true,
                'can_replace' => true
            ),
        );
        $productsJsonPath = get_template_directory() . '/shop/products.json';
        $contentJsonPath = dirname(dirname(__FILE__)) . '/content/content.json';
        if (file_exists($productsJsonPath)) {
            $steps = $import_products_step + $steps;
        } else if (file_exists($contentJsonPath)) {
            $jsonContent = file_get_contents($contentJsonPath);
            $data = json_decode($jsonContent, true);

            if (!empty($data['Parameters']['productsJson'])) {
                $steps = $import_products_step + $steps;
            }
        }
        return $steps;
    }

    /**
     * Make an interface for the wizard
     */
    public function wizardPage() {
        ?>
        <div class="wrap pwizard-wrap-perent">
            <?php
            echo '<div class="card pwizard-wrap">';
            $steps = $this->getSteps();
            echo '<ul class="pwizard-menu">';
            foreach ($steps as $step) {
                $class = 'step step-' . esc_attr($step['id']);
                echo '<li data-step="' . esc_attr($step['id']) . '" class="' . esc_attr($class) . '">';
                printf('<h2>%s</h2>', esc_html($step['title']));
                // $content split
                $content = call_user_func(array($this, $step['view']));
                if (isset($content['summary'])) {
                    $importOptions = '';
                    if (isset($content['import_options'])) {
                        $importOptions = $content['import_options'];
                    }
                    printf(
                        '<div class="summary">%s</div>',
                        wp_kses_post($content['summary']) . $importOptions
                    );
                }
                if (isset($content['buttons'])) {
                    echo $content['buttons'];
                }
                if (isset($content['detail'])) {
                    printf(
                        '<div class="detail">%s</div>',
                        $content['detail'] // Need to escape this
                    );
                }
                // Next button
                if (isset($step['button_text']) && $step['button_text']) {
                    printf(
                        '<div class="button-wrap"><a href="#" class="button button-primary p-do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback']),
                        esc_attr($step['id']),
                        esc_html($step['button_text'])
                    );
                }
                // Replace button
                if (isset($step['button2_text']) && $step['button2_text']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-secondary p-do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback2']),
                        esc_attr($step['id']),
                        esc_html($step['button2_text'])
                    );
                }
                // Theme appearance button
                if (isset($step['button3_text']) && $step['button3_text']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-primary p-do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback3']),
                        esc_attr($step['id']),
                        esc_html($step['button3_text'])
                    );
                }
                // Products import button
                if (isset($step['button_products_import_text']) && $step['button_products_import_text']) {
                    printf(
                        '<div class="button-wrap"><a href="#" class="button button-primary p-do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback_products_import']),
                        esc_attr($step['id']),
                        esc_html($step['button_products_import_text'])
                    );
                }
                // Products replace button
                if (isset($step['button_products_replace_text']) && $step['button_products_replace_text']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-secondary p-do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        esc_attr($step['callback_products_replace']),
                        esc_attr($step['id']),
                        esc_html($step['button_products_replace_text'])
                    );
                }
                // Skip button
                if (isset($step['can_skip']) && $step['can_skip']) {
                    printf(
                        '<div class="button-wrap" style="margin-left: 0.5em;"><a href="#" class="button button-secondary p-do-it" data-callback="%s" data-step="%s">%s</a></div>',
                        'do_next_step',
                        esc_attr($step['id']),
                        __('Skip', 'nicepage')
                    );
                }

                echo '</li>';
            }
            echo '</ul>';
            ?>
            <div class="step-loading"><span class="spinner"></span></div>
            <?php
            if (isset($GLOBALS['npThemeVersion']) && (float)APP_PLUGIN_VERSION > (float)$GLOBALS['npThemeVersion']) {
                // if our theme older then plugin
                echo sprintf('<div class="pwizard-warning"><p>%s</p></div>', 'The active theme has a version lower than the plugin version. Please update the theme too.', 'nicepage');
            }
            ?>
        </div><!-- .pwizard-wrap -->

        </div><!-- .wrap -->
    <?php }

    /**
     * Add styles and scripts
     */
    public function enqueueScripts() {
        wp_register_script('pwizard', APP_PLUGIN_URL . 'importer/assets/js/pwizard.js', array('jquery'), time());
        wp_localize_script(
            'pwizard',
            'pwizard_params',
            array(
                'urlContent'     => admin_url("admin-ajax.php"),
                'settingsUrl'    => admin_url('admin.php?page=np_settings'),
                'wpnonceContent' => wp_create_nonce('np-importer'),
                'wpnonceThemeAppearance' => wp_create_nonce('np-theme-appearance'),
                'actionImportContent'  => 'np_import_content',
                'actionReplaceContent'  => 'np_replace_content',
                'actionImportProducts'  => 'np_import_products',
                'actionReplaceProducts'  => 'np_replace_products',
            )
        );
        wp_enqueue_script('pwizard');
    }

    /**
     * Hooks and filters
     */
    public function init() {
        $this->wizardPage();
        add_action('wp_ajax_setup_content', array($this, 'setup_content'));
    }
}
?>