<?php
defined('ABSPATH') or die;

class NpGetCategoriesAction extends NpAction {

    /**
     * Process action entrypoint
     *
     * @return array
     */
    public static function process()
    {
        $type = _arr($_REQUEST, 'type', 'productsCategories');
        $categories = array();
        if (!$type || $type === 'productsCategories') {
            $wo_categories = get_categories(array('taxonomy' => 'product_cat', 'hide_empty' => 0));
            foreach ($wo_categories as $wo_category) {
                $categories[] = array(
                    'id' => isset($wo_category->cat_ID) ? $wo_category->cat_ID : 0,
                    'title' => isset($wo_category->name) ? $wo_category->name : '',
                    'categoryId' => isset($wo_category->category_parent) ? $wo_category->category_parent : 0,
                );
            }
        }

        return array(
            'result' => 'done',
            'categories' => $categories,
        );
    }
}
NpAction::add('np_get_categories', 'NpGetCategoriesAction');