<?php
defined('ABSPATH') or die;
if (!function_exists('getProductsJson')) {
    /**
     * Get products json
     * This json used in the payment products buttons
     *
     * @return array[]
     */
    function getProductsJson() {
        $return = json_encode(get_option('_products_json'));
        $return = json_decode(fixImagePaths($return), true);
        $return = prepareProductsJson($return);
        return $return ? $return : array('products' => array ());
    }
}
if (!function_exists('prepareProductsJson')) {
    /**
     * Add products data from cms
     *
     * @param array $data
     *
     * @return array $data
     */
    function prepareProductsJson($data) {
        if ($data) {
            $products = isset($data['products']) ? $data['products'] : array();
            $allCategories = isset($data['categories']) ? $data['categories'] : array();
            foreach ($products as $index => $product) {
                if (!isset($data['products'][$index]['categories'])) {
                    $data['products'][$index]['categories'] = array();
                }
                $data['products'][$index]['categoriesData'] = getNpCategoriesData($data['products'][$index]['categories'], $allCategories);
                $prefix = isset($_GET['products-list']) || isset($_GET['product-id']) || isset($_GET['product']) ? 'product-id' : 'productId';
                $data['products'][$index]['link'] = home_url('?'. $prefix . '=' . $product['id']);
            }
        }
        return $data;
    }
}
if (!function_exists('fixImagePaths')) {
    /**
     * Fix image paths
     *
     * @param string $content Content
     *
     * @return mixed
     */
    function fixImagePaths($content) {
        return str_replace('[[site_path_live]]', get_site_url(), $content);
    }
}
if (!function_exists('getNpCategoriesData')) {
    /**
     * Get CategoriesData
     *
     * @param array $productCategories
     * @param array $allCategories
     *
     * @return array $categoriesData
     */
    function getNpCategoriesData($productCategories, $allCategories) {
        $prefix = isset($_GET['products-list']) || isset($_GET['product-id']) || isset($_GET['product']) ? 'products-list' : 'productsList';
        $categoryLinkFormat = isset($_GET['np_from']) && $_GET['np_from'] == 'theme' ? '?products-list#/1///' : '?' . $prefix . '#/1///';
        $categoriesData = array();
        if ($productCategories) {
            foreach ($productCategories as $id) {
                $category = array_filter(
                    $allCategories, function ($category) use ($id) {
                        return $category['id'] === $id;
                    }
                );

                if (empty($category)) {
                    continue;
                }
                $category = array_shift($category);
                if ($category) {
                    $categoriesData[] = array(
                        'title' => isset($category['title']) ? $category['title'] : 'Uncategorized',
                        'link'  => home_url($categoryLinkFormat . $id),
                    );
                }
                if (empty($categoriesData)) {
                    $categoriesData[] = array('id' => 0, 'title' => 'Uncategorized');
                }
            }
        }
        return $categoriesData;
    }
}
if (!function_exists('getWpQuery')) {
    /**
     * Get WP_QUERY with posts by category or with all posts
     *
     * @param array $params Params for request
     *
     * @return object|WP_Query $result
     */
    function getWpQuery($params) {
        if (!isset($params['orderby'])) {
            $params['orderby'] = 'date';
        }
        if (!isset($params['order'])) {
            $params['order'] = 'DESC';
        }
        if (!isset($params['count'])) {
            $params['count'] = 999999999;
        }
        if ($params['count'] === '') {
            $post_per_page = -1;
        } else {
            $post_per_page = $params['count'];
        }
        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        $control_query = new stdClass();
        $cat_id = 0;
        $tags = '';
        if (preg_match('/^tags:/', $params['source'])) {
            $tags = str_replace('tags:', '', $params['source']);
        } else {
            $params['source'] = html_entity_decode($params['source'], ENT_QUOTES, 'UTF-8');
            $cat_id = getCatIdByType($params['source'], $params['entity_type']);
            if (isset($params['id']) && $params['id']) {
                $cat_id = $params['id'];
            }
            if ($params['source'] && $params['source'] !== 'featured' && $params['source'] !== 'relative' && $cat_id < 1) {
                return $control_query;
            }
        }
        $request_params = array(
            'post_type' => $params['entity_type'],
            'numberposts' => $params['count'],
            'orderby' => $params['orderby'],
            'order' => $params['order'],
            'suppress_filters' => false,
            'paged' => $paged,
        );
        if ($post_per_page && $post_per_page !== '') {
            $request_params['posts_per_page'] = $post_per_page;
        }
        if ($cat_id && $params['entity_type'] == 'post') {
            $request_params['cat'] = $cat_id;
        }
        if ($params['entity_type'] == 'product' && $params['source'] && $params['source'] != 'featured') {
            if ($params['source'] === 'relative') {
                $id = get_the_ID();
                if (!empty($GLOBALS['pluginTemplatesExists'])) {
                    global $original_post;
                    if (isset($original_post->ID)) {
                        $id = $original_post->ID;
                    }
                }
                $current_product = wc_get_product($id);
                if ($current_product) {
                    $categories = $current_product->get_category_ids();
                    $tagsIds = $current_product->get_tag_ids();
                    $request_params['post__not_in'] = [$id];
                    $request_params['orderby'] = 'rand';
                    $request_params['tax_query'] = [
                        'relation' => 'OR',
                        [
                            'taxonomy' => 'product_cat',
                            'field'    => 'id',
                            'terms'    => $categories,
                            'operator' => 'IN',
                        ],
                        [
                            'taxonomy' => 'product_tag',
                            'field'    => 'id',
                            'terms'    => $tagsIds,
                            'operator' => 'IN',
                        ],
                    ];
                }
            } else {
                $request_params['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'name',
                        'terms'    => $params['source'],
                    ),
                );
            }
        }
        if ($params['source'] == 'featured') {
            // The tax query
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'terms'    => $params['source'],
                'field'    => 'name',
                'operator' => 'IN',
            );
            $request_params['tax_query'] = $tax_query;
        }
        if ($tags) {
            $request_params['tag'] = array_map('trim', explode(',', $tags));
        }
        if (isset($params['meta_key']) && $params['meta_key']) {
            $request_params['meta_key'] = $params['meta_key'];
        }
        if (isset($params['meta_value']) && $params['meta_value']) {
            $request_params['meta_value'] = $params['meta_value'];
        }
        if (isset($params['meta_query']) && $params['meta_query']) {
            $request_params['meta_query'] = $params['meta_query'];
        }
        if (isset($params['tax_query']) && $params['tax_query']) {
            $request_params['tax_query'] = $params['tax_query'];
        }
        if (isset($params['productId']) && $params['productId']) {
            $request_params['p'] = $params['productId'];
        }
        $control_query = new WP_Query($request_params);
        return $control_query;
    }
}
if (!function_exists('getCatIdByType')) {
    /**
     * Get id category for post or product
     *
     * @param string $categoryName
     * @param string $type         of control
     *
     * @return int
     */
    function getCatIdByType($categoryName, $type = 'blog') {
        $taxonomy = $categoryName === 'featured' ? 'product_visibility' : 'product_cat';
        $product_cat = get_term_by('name', $categoryName, $taxonomy);
        $product_cat_id = 0;
        if (isset($product_cat->term_id)) {
            $product_cat_id = $product_cat->term_id;
        }
        $cat_id = $type == 'blog' || $type == 'post' ? get_cat_ID($categoryName) : $product_cat_id;
        return $cat_id = $cat_id ? $cat_id : 0;
    }
}
if (!function_exists('getHookOutput')) {
    /**
     * Get result of hook
     *
     * @param string $hook Hook Name.
     *
     * @return string Hook result.
     */
    function getHookOutput( $hook ) {
        ob_start();
        do_action($hook);
        return ob_get_clean();
    }
}

if (!function_exists('np_translate')) {
    /**
     * Light migration translation from woocommerce domain to nicepage domain
     *
     * @param string $text
     * @param string $context
     *
     * @return string|void
     */
    function np_translate($text, $context = '') {
        if (!is_string($text) || empty($text)) {
            return '';
        }

        $has_translation = np_translate_text_with_context($text, $context, 'nicepage') !== $text;
        if ($has_translation) {
            return $context
                ? np_translate_text_with_context($text, $context, 'nicepage')
                : np_translate_text($text, 'nicepage');
        }

        // Fallback WooCommerce
        return $context
            ? np_translate_text_with_context($text, $context, 'woocommerce')
            : np_translate_text($text, 'woocommerce');
    }
}

if (!function_exists('np_translate_text')) {
    /**
     * Safe alternative to __() without text-domain warnings
     *
     * @param string $text
     * @param string $domain
     *
     * @return string
     */
    function np_translate_text($text, $domain = 'default') {
        if (!is_string($text)) {
            return $text;
        }
        $translations = get_translations_for_domain($domain);
        return $translations->translate($text);
    }
}

if (!function_exists('np_translate_text_with_context')) {
    /**
     * Safe alternative to _x() without warnings
     *
     * @param string $text
     * @param string $context
     * @param string $domain
     *
     * @return string
     */
    function np_translate_text_with_context($text, $context, $domain = 'default') {
        if (!is_string($text)) {
            return $text;
        }
        $translations = get_translations_for_domain($domain);
        $key = $context . "\004" . $text;
        return $translations->translate($key);
    }
}

if (!function_exists('is_woocommerce_active')) {
    /**
     * Check active woocommerce plugin
     *
     * @return bool
     */
    function is_woocommerce_active() {
        return class_exists('Woocommerce');
    }
}
