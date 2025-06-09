<?php
defined('ABSPATH') or die;

if (isset($_GET['action']) && $_GET['action'] === 'np_route_products_json') {
    $source = isset($_GET['np_from']) && $_GET['np_from'] == 'theme' ? $_GET['np_from'] : 'plugin';
    if ($source == 'theme') {
        if (file_exists(get_template_directory() . '/shop/products.json')) {
            $data = file_get_contents(get_template_directory() . '/shop/products.json');
            $data = json_decode($data, true);
        }
    } else {
        if (function_exists('np_data_provider')) {
            $data = getProductsJson();
        }
    }
    $products = isset($data['products']) ? $data['products'] : array();
    $allCategories = isset($data['categories']) ? $data['categories'] : array();
    foreach ($products as $index => $product) {
        if (isset($_GET['np_from']) && $_GET['np_from'] == 'theme') {
            if (isset($products[$index]['images'])) {
                foreach ($products[$index]['images'] as $i => $image) {
                    if (isset($products[$index]['images'][$i]['url']) && $products[$index]['images'][$i]['url']) {
                        $products[$index]['images'][$i]['url'] = get_template_directory_uri() . '/' . $image['url'];
                    }
                }
            }
        }
        $products[$index]['categoriesData'] = getNpCategoriesData($products[$index]['categories'], $allCategories);
        $products[$index]['link'] = $source == 'theme' ? home_url('?product-id=' . $product['id']) : home_url('?productId=' . $product['id']);
    }
    $data['products'] = $products;
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}