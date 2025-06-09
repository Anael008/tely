<?php

/**
 * Add common scripts and styles for np controls
 */
function add_common_scripts() {
    global $post;
    $post_id = isset($post->ID) ? $post->ID : 0;
    if (np_data_provider($post_id)->isNp()) {
        wp_register_script('common-np-scripts', APP_PLUGIN_URL . 'includes/common/js/np-scripts.js', array('jquery'), time());
        wp_enqueue_script('common-np-scripts');
    }
}
add_action('wp_enqueue_scripts', 'add_common_scripts', 1003);

function filter_posts_by_category($query) {
    if ($query->is_main_query() && !is_admin()) {
        $category_id = isset($_GET['postsCategoryId']) ? intval($_GET['postsCategoryId']) : 0;
        if ($category_id > 0) {
            $query->set('cat', $category_id);
        }
    }
}
add_action('pre_get_posts', 'filter_posts_by_category');

function sorting_posts($query) {
    if (!is_admin() && $query->is_main_query()) {
        $sorting = isset($_GET['postsSorting']) ? sanitize_text_field($_GET['postsSorting']) : '';
        if ($sorting) {
            switch ($sorting) {
                case 'name_asc':
                    $query->set('orderby', 'title');
                    $query->set('order', 'ASC');
                    break;
                case 'name_desc':
                    $query->set('orderby', 'title');
                    $query->set('order', 'DESC');
                    break;
                case 'date_asc':
                    $query->set('orderby', 'date');
                    $query->set('order', 'ASC');
                    break;
                case 'date_desc':
                    $query->set('orderby', 'date');
                    $query->set('order', 'DESC');
                    break;
            }
        }
    }
}
add_action('pre_get_posts', 'sorting_posts');

function import_categories_in_posts($categories, $added_terms) {
    $category_old_new_ids = array();
    foreach ($categories as $post_category) {
        $old_id = isset($post_category['id']) ? $post_category['id'] : null;
        $parent_id = isset($post_category['categoryId']) ? $post_category['categoryId'] : null;
        $title = isset($post_category['title']) ? $post_category['title'] : null;

        if ($old_id && $title && !$parent_id) {
            $category_old_new_ids[$old_id] = wp_create_category($title);
        }
        if ($old_id && $title && $parent_id && isset($category_old_new_ids[$parent_id])) {
            $category_old_new_ids[$old_id] = wp_create_category($title, $category_old_new_ids[$parent_id]);
        }
        if (isset($category_old_new_ids[$old_id])) {
            $added_terms[] = array(
                'term_id' => (int)$category_old_new_ids[$old_id],
                'taxonomy' => 'category'
            );
        }
    }

    if ($category_old_new_ids) {
        update_option('post_category_old_new_ids', $category_old_new_ids);
    }

    return $added_terms;
}