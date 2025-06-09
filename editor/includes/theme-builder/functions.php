<?php
require_once 'actions.php';
require_once 'breadcrumbs.php';

add_action('template_redirect', 'templates_replacer');

/**
 * Replace header/footer templates
 *
 * @param $location_manager
 */
function templates_replacer($location_manager)
{
    $headerFooterFromPlugin = get_option('np_theme_appearance') === 'plugin-option';
    Nicepage::$isWooShopProductTemplate = function_exists('wc_get_product') && (is_shop() || is_product_category() || is_product());
    Nicepage::$isBlogPostTemplate = is_singular('post') || is_home() && (!isset($_GET['productsList']) && !isset($_GET['productId'])) || (is_archive() && !Nicepage::$isWooShopProductTemplate);
    if ($headerFooterFromPlugin && Nicepage::isNpTheme()) {
        remove_action('wp_body_open', 'wp_admin_bar_render', 0); // wp-version >= 5.2
        add_action('get_header', 'get_np_header');
        add_action('get_footer', 'get_np_footer');
    }
}

/**
 * Get custom header
 *
 * @param $name
 */
function get_np_header($name) {
    include __DIR__ . '/header.php';

    $templates = [];
    $name = (string)$name;
    if ('' !== $name) {
        $templates[] = "header-{$name}.php";
    }

    $templates[] = 'header.php';

    // Avoid running wp_head hooks again
    remove_all_actions('wp_head');
    ob_start();
    // It cause a `require_once` so, in the get_header it self it will not be required again.
    locate_template($templates, true);
    ob_get_clean();

    $post_id = get_the_ID();
    $data_provider = np_data_provider($post_id);
    $headerNp = $data_provider->getNpHeader();
    $headerItem = '';
    if ($headerNp && !$data_provider->getHideHeader()) {
        $headerItem = json_decode($headerNp, true);
        $publishHeader = $data_provider->getTranslation($headerItem, 'header');
        $publishHeader = Nicepage::processFormCustomPhp($publishHeader, 'header');
        $publishHeader = Nicepage::processContent($publishHeader, array('templateName' => 'header'));
    }
    if ($headerItem) {
        if (false === strpos($headerItem['styles'], '<style>')) {
            $headerItem['styles'] = '<style>' . $headerItem['styles'] . '</style>';
        }
        echo $headerItem['styles'];
        echo $publishHeader;
    }
}

/**
 * Get custom footer
 *
 * @param $name
 */
function get_np_footer($name) {
    include __DIR__ . '/footer.php';

    $templates = [];
    $name = (string)$name;
    if ('' !== $name) {
        $templates[] = "footer-{$name}.php";
    }

    $templates[] = 'footer.php';

    ob_start();
    // It cause a `require_once` so, in the get_footer it self it will not be required again.
    locate_template($templates, true);
    ob_get_clean();

    $post_id = get_the_ID();
    $data_provider = np_data_provider($post_id);
    $footerNp = $data_provider->getNpFooter();
    $footerItem = '';
    if ($footerNp && !$data_provider->getHideFooter()) {
        $footerItem = json_decode($footerNp, true);
        $publishFooter = $data_provider->getTranslation($footerItem, 'footer');
        $publishFooter = Nicepage::processFormCustomPhp($publishFooter, 'footer');
        $publishFooter = Nicepage::processContent($publishFooter, array('templateName' => 'footer'));
    }
    if ($footerItem) {
        if (false === strpos($footerItem['styles'], '<style>')) {
            $footerItem['styles'] = '<style>' . $footerItem['styles'] . '</style>';
        }
        echo $footerItem['styles'];
        echo $publishFooter;
    }
}

add_filter(
    'template_include', function ($template) {
        if (get_option('np_theme_appearance') !== 'plugin-option' || !Nicepage::isNpTheme()) {
            return $template;
        }
        $isShop = function_exists('wc_get_product') && (is_shop() || is_product_category());
        $isProduct = function_exists('wc_get_product') && is_product();
        if (get_query_var('products-list', null) !== null || isset($_GET['productsList'])) {
            $render = render_plugin_template('products');
            return $render ? null : $template;
        }
        if (get_query_var('product-id', null) !== null || isset($_GET['productId'])) {
            $render = render_plugin_template('product');
            return $render ? null : $template;
        }
        if (get_query_var('thank-you', null) !== null || isset($_GET['thankYou'])) {
            $render = render_plugin_template('thankYou');
            return $render ? null : $template;
        }
        if (is_singular('post')) {
            $render = render_plugin_template('post');
            return $render ? null : $template;
        }
        if (is_home() || (is_archive() && !$isShop)) {
            $render = render_plugin_template('blog');
            return $render ? null : $template;
        }
        if ($isShop) {
            $render = render_plugin_template('products');
            return $render ? exit : $template;
        }
        if ($isProduct) {
            $render = render_plugin_template('product');
            return $render ? exit : $template;
        }
        if (is_password_protected_page()) {
            $render = render_plugin_template('password');
            return $render ? null : $template;
        }
        if (is_login_page()) {
            $render = render_plugin_template('login');
            return $render ? null : $template;
        }
        if (is_cart_page()) {
            WC()->cart->calculate_totals();
            $render = render_plugin_template('cart');
            return $render ? null : $template;
        }
        if (is_checkout_page() && (!isset($_GET['order-received']) && !isset($_GET['key']))) {
            WC()->cart->calculate_totals();
            WC()->checkout;
            $render = render_plugin_template('checkout');
            return $render ? null : $template;
        }
        if (is_404()) {
            $render = render_plugin_template('404');
            return $render ? null : $template;
        }
        if (is_search()) {
            $render = render_plugin_template('search');
            return $render ? null : $template;
        }
        return $template;
    },
    51
);

/**
 * Render base theme template from plugin db template
 *
 * @param string $type
 */
function render_plugin_template($type) {
    $result = null;
    $plugin_templates = get_templates($type);
    if (!empty($plugin_templates)) {
        $GLOBALS['pluginTemplatesExists'] = true;
        $plugin_template = $plugin_templates[0];
        if ($plugin_template && $plugin_template->ID) {

            if ($type) {
                global $post;
                global $original_post;
                $original_post = $post;
                $post = get_post($plugin_template->ID);

                set_template_id($type, $plugin_template->ID);
            }

            $sections_html = Nicepage::html($plugin_template->ID);
            if ($sections_html) {
                if (function_exists('do_blocks') && function_exists('has_blocks') && has_blocks($sections_html)) {
                    $sections_html = do_blocks($sections_html);
                }
                if (function_exists('w123cf_widget_text_filter')) {
                    $sections_html = w123cf_widget_text_filter($sections_html);
                }
            }
            ob_start();
            get_header();
            if ($type === 'product' || $type === 'products' || $type === 'cart' || $type === 'checkout') {
                $sections_html = processWoocommercePlaceholders($sections_html);
            }
            echo $sections_html;

            if ($type === 'post') {
                $post = $original_post;
                if (comments_open() || get_comments_number()) {
                    comments_template();
                }
                do_action('the_rating');
                do_action('after_post_content');
                $post = get_post($plugin_template->ID);
            }

            get_footer();
            wpFooterActions($plugin_template->ID);

            // for dialogs start
            $data_provider = np_data_provider($plugin_template->ID);
            $headerNp = $data_provider->getNpHeader();
            $footerNp = $data_provider->getNpFooter();
            $headerItem = '';
            $footerItem = '';
            if ($headerNp && !$data_provider->getHideHeader()) {
                $headerItem = json_decode($headerNp, true);
            }
            if ($footerNp && !$data_provider->getHideFooter()) {
                $footerItem = json_decode($footerNp, true);
            }
            $htmlDocument = ob_get_clean();
            $htmlDocument = $data_provider->addPublishDialogToBody($htmlDocument, $headerItem, $footerItem);
            echo $htmlDocument;
            // for dialogs end

            if ($type) {
                $post = $original_post;
            }
        }
        $result = true;
    }
    return $result;
}

/**
 * Get new plugin templates
 *
 * @param string $type
 *
 * @return array
 */
function get_templates($type) {
    $args = [
        'post_type'      => 'template',
        'posts_per_page' => 1,
        'meta_key'       => '_original_template_name',
        'meta_value'     => $type,
    ];

    $plugin_templates = get_posts($args);

    // backward for lowercase slug names
    if (empty($plugin_templates)) {
        $plugin_templates = get_templates_by_slug_name($type);
    }
    // backward for older export/import our products templates
    if (empty($plugin_templates)) {
        $plugin_templates = get_old_our_products_templates($type);
    }
    return $plugin_templates;
}

/**
 * Get plugin templates with previous names format
 *
 * @param string $type
 *
 * @return array
 */
function get_templates_by_slug_name($type) {
    $args = [
        'post_type'      => 'template',
        'posts_per_page' => 1,
        'name' => $type,
    ];

    $plugin_templates = get_posts($args);
    return $plugin_templates;
}

/**
 * Get old plugin templates
 *
 * @param string $type
 *
 * @return array
 */
function get_old_our_products_templates($type) {
    $plugin_templates = array();
    if ($type === 'products') {
        $old_type = 'product-list-template';
    } elseif ($type === 'product') {
        $old_type = 'product-details-template';
    } elseif ($type === 'thankYou') {
        $old_type = 'thank-you-page-template';
    } else {
        $old_type = '';
    }
    if ($old_type) {
        $old_plugin_templates = get_posts(
            [
                'post_type'      => 'np_shop_template',
                'name'           => $old_type,
                'posts_per_page' => 1,
            ]
        );
    }
    if (!empty($old_plugin_templates)) {
        $plugin_templates = $old_plugin_templates;
    }
    return $plugin_templates;
}

/**
 * Set plugin template id in db
 *
 * @param string $type
 * @param int    $id
 */
function set_template_id($type, $id) {
    update_option($type . '_template_id', $id);
}

/**
 * Get plugin template id
 *
 * @param string $type
 */
function get_template_id($type) {
    return get_option($type . '_template_id');
}

/**
 * Get plugin template type*
 */
function get_template_type() {
    $type = '';
    if (is_home() || is_archive()) {
        $type = 'blog';
    }
    if (get_query_var('products-list', null) !== null || function_exists('wc_get_product') && (is_shop() || is_product_category()) || isset($_GET['productsList'])) {
        $type = 'products';
    }
    if (get_query_var('product-id', null) !== null || function_exists('wc_get_product') && is_product() || isset($_GET['productId'])) {
        $type = 'product';
    }
    if (get_query_var('thank-you', null) !== null) {
        $type = 'thankYou';
    }
    if (is_login_page()) {
        $type = 'login';
    }
    if (is_password_protected_page()) {
        $type = 'password';
    }
    if (is_cart_page()) {
        $type = 'cart';
    }
    if (is_checkout_page()) {
        $type = 'checkout';
    }
    if (is_404()) {
        $type = '404';
    }
    if (is_search()) {
        $type = 'search';
    }
    return $type;
}

/**
 * Add plugin templates in admin
 */
function register_template_post_type() {
    register_post_type(
        'template', array(
        'label'               => 'Plugin Templates',
        'public'              => false, // frontend
        'show_ui'             => true,  // edit mode
        'show_in_menu'        => true,  // menu
        'supports'            => array('title', 'editor'), // fields
        '_edit_link'          => 'post.php?post=%d', // structure
        'capability_type'     => 'post',
        'map_meta_cap'        => true, // user rules
        'menu_icon'           => 'dashicons-text',
        )
    );
}
add_action('init', 'register_template_post_type');

/**
 * Hide menu with templates in admin if templates is empty
 */
function hide_empty_template_menu() {
    if (get_post_type_object('template') && current_user_can('edit_posts')) {
        $query = new WP_Query(
            array(
            'post_type'      => 'template',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            )
        );
        if (!$query->have_posts()) {
            remove_menu_page('edit.php?post_type=template');
        }
    }
}
add_action('admin_menu', 'hide_empty_template_menu', 20);

/**
 * Disable remove for templates in admin
 *
 * @param $actions
 * @param $post
 *
 * @return mixed
 */
function modify_template_post_row_actions($actions, $post) {
    if ($post->post_type === 'template') {
        unset($actions['inline hide-if-no-js']);
        unset($actions['trash']);
    }
    return $actions;
}
add_filter('post_row_actions', 'modify_template_post_row_actions', 10, 2);

/**
 * Disable right panel with remove and update buttons in the edit mode for template
 */
function remove_template_edit_sidebar() {
    global $post;
    if ($post && $post->post_type === 'template') {
        remove_meta_box('submitdiv', 'template', 'side');
        remove_meta_box('postimagediv', 'template', 'side');
        remove_meta_box('tagsdiv-post_tag', 'template', 'side');
        remove_meta_box('categorydiv', 'template', 'side');
        remove_meta_box('formatdiv', 'template', 'side');
    }
}
add_action('add_meta_boxes', 'remove_template_edit_sidebar', 10, 2);

/**
 * Disable add new template in admin menu
 */
function remove_template_add_new_button() {
    global $submenu;
    if (isset($submenu['edit.php?post_type=template'])) {
        foreach ($submenu['edit.php?post_type=template'] as $key => $item) {
            if (in_array('post-new.php?post_type=template', $item)) {
                unset($submenu['edit.php?post_type=template'][$key]);
            }
        }
    }
}
add_action('admin_menu', 'remove_template_add_new_button', 999);

/**
 * Disable add new template in admin bar
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function remove_template_add_new_from_admin_bar($wp_admin_bar) {
    $wp_admin_bar->remove_node('new-template');
}
add_action('admin_bar_menu', 'remove_template_add_new_from_admin_bar', 999);

/**
 * Hide add new template in edit mode for template
 */
function hide_template_add_new_button_css() {
    global $post_type;
    if ($post_type === 'template') {
        echo '<style>
            .wrap > .page-title-action { display: none !important; }
        </style>';
    }
}
add_action('admin_head-edit.php', 'hide_template_add_new_button_css');
add_action('admin_head-post.php', 'hide_template_add_new_button_css');

/**
 * Disable multi templates operations in admin
 *
 * @param $bulk_actions
 *
 * @return array|mixed
 */
function remove_bulk_actions_for_template($bulk_actions) {
    if ('template' === get_post_type()) {
        return [];
    }
    return $bulk_actions;
}
add_filter('bulk_actions-edit-template', 'remove_bulk_actions_for_template');

/**
 * Disable change template title in edit mode
 */
function disable_title_input() {
    global $post;
    if ($post->post_type === 'template') {
        echo '<style>
            #titlewrap input#title { 
                pointer-events: none;
            }
        </style>';
    }
}
add_action('admin_head-post.php', 'disable_title_input');
add_action('admin_head-post-new.php', 'disable_title_input');

/**
 * Hooks replacer in plugin woo templates
 *
 * @param string $html Plugin Template HTML with placeholders.
 *
 * @return string HTML with result of hooks.
 */
function processWoocommercePlaceholders($html) {
    // Hooks list.
    $hooks = [
        'woocommerce_before_single_product',         // before product details
        'woocommerce_single_product_summary',        // after last control - product button
        'woocommerce_after_single_product',          // after product details
        'woocommerce_before_add_to_cart_button',     // before add to cart button
        'woocommerce_after_add_to_cart_button',      // after add to cart button
        'woocommerce_after_main_content',            // before woocommerce_sidebar
        'woocommerce_sidebar',                       // before footer
    ];

    if (function_exists('is_woocommerce') && is_woocommerce()) {
        //disable not needed hooks
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
    }

    foreach ($hooks as $hook) {
        $replacerTo = function_exists('is_woocommerce') && is_woocommerce() ? getHookOutput($hook) : '';
        $html = str_replace('<!-- {{' . $hook . '}} -->', $replacerTo, $html);
    }
    $html = preg_replace('/<!--\s*\{\{add_to_cart_button_classes:.*?\}\}\s*-->/', '', $html);
    return $html;
}

/**
 * Check is page login/register/lostpassword
 *
 * @return bool
 */
function is_login_page() {
    $objectQuery = get_queried_object();
    if ($objectQuery && isset($objectQuery->post_name) && in_array($objectQuery->post_name, array('login', 'lostpassword', 'register'))) {
        return true;
    }
    return false;
}

/**
 * Check is page protected
 *
 * @return bool
 */
function is_password_protected_page() {
    global $original_post;
    $post_id = isset($original_post) && isset($original_post->ID) ? $original_post->ID : get_the_ID();
    $data_provider = np_data_provider($post_id);
    if (post_password_required($post_id) || $data_provider->isNp() && isset($post->post_password) && $post->post_password) {
        return true;
    }
    return false;
}

/**
 * Check is page cart template
 *
 * @return bool
 */
function is_cart_page() {
    if (is_woocommerce_active() && is_cart()) {
        return true;
    }
    return false;
}

/**
 * Check is page checkout template
 *
 * @return bool
 */
function is_checkout_page() {
    if (is_woocommerce_active() && is_checkout()) {
        return true;
    }
    return false;
}

/**
 * Render checkout billing / shipping fields
 *
 * @param $checkout
 * @param $name
 */
function plugin_render_checkout_fields($checkout, $name)
{
    if ($name === 'billing') {
        $fields = array_merge($checkout->get_checkout_fields('billing'), $checkout->get_checkout_fields('order'));
    } else {
        $fields = array_merge($checkout->get_checkout_fields('shipping'));
    }

    $checkout_field_class = get_option('checkout_billing_field_classes', '');
    $checkout_label_class = get_option('checkout_billing_label_classes', '');
    $checkout_input_class = get_option('checkout_billing_input_classes', '');

    foreach ($fields as $key => $field) {
        $label = isset($field['label']) ? $field['label'] : '';
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : $label;
        $class = [$checkout_field_class];
        if (isset($field['class'])) {
            $index = array_search('update_totals_on_change', $field['class']);
            if ($index !== false) {
                $class[] = isset($field['class'][$index]) ? $field['class'][$index] : '';
            }
        }
        $label_class = [$checkout_label_class];
        $input_class = [$checkout_input_class];
        $required = isset($field['required']) ? $field['required'] : false;
        np_woocommerce_form_field(
            $key,
            [
                'type' => isset($field['type']) ? $field['type'] : 'text',
                'options' => isset($field['options']) ? $field['options'] : array(),
                'label' => $label,
                'placeholder' => $placeholder,
                'class' => $class,
                'label_class' => $label_class,
                'input_class' => $input_class,
                'rules_action' => isset($field['rules_action']) ? $field['rules_action'] : '',
                'rules' => isset($field['rules']) ? $field['rules'] : '',
                'required' => $required,
            ],
            $checkout->get_value($key)
        );
    }
}

/**
 * Render text 404
 *
 * @param string $args
 *
 * @return string
 */
function plugin_404_content($args = '')
{
    $args = wp_parse_args(
        $args, array(
            'error_title' => __('Nothing here', 'nicepage'),
            'error_message' => __('It looks like nothing was found at this location. Maybe try a search?', 'nicepage')
        )
    );
    extract($args);
    ob_start();
    echo '<p>' . $args['error_title'] . '</p>'; ?>
    <div class="page404text" style="font-size: 1rem;">
    <?php echo '<p class="center">' . $args['error_message'] . '</p>';
    if (plugin_get_option('theme_show_random_posts_on_404_page')) {
        echo '<h4 class="box-title">' . plugin_get_option('theme_show_random_posts_title_on_404_page') . '</h4>';
        ?>
        <ul>
            <?php
            global $post;
            $rand_posts = get_posts('numberposts=5&orderby=rand');
            foreach ($rand_posts as $post) : ?>
                <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
    if (plugin_get_option('theme_show_tags_on_404_page')) {
        echo '<h4 class="box-title">' . plugin_get_option('theme_show_tags_title_on_404_page') . '</h4>';
        wp_tag_cloud('smallest=9&largest=22&unit=pt&number=200&format=flat&orderby=name&order=ASC');
    }
    get_search_form(); ?>
    </div>
    <?php
    $output = ob_get_clean();
    return $output;
}