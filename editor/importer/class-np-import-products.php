<?php
defined('ABSPATH') or die;

class NpImportProducts
{
    /**
     * Import np products process
     *
     * @param bool $remove_previous_content
     */
    public static function import($remove_previous_content)
    {
        $error_data = '';
        try {
            self::importProducts($remove_previous_content);
        } catch (Exception $e) {
            $error_data = json_encode(
                array(
                    'error' => $e->getMessage()
                )
            );
        }
        echo $error_data;
    }

    public static $importProductFromContent;
    /**
     * Import np products
     *
     * @param bool $remove_previous_content
     *
     * @throws Exception
     */
    public static function importProducts($remove_previous_content)
    {
        $paymentProductsIds = $remove_previous_content ? array() : (get_option('_paymentProductsIds') ?: array());
        $importOptions = isset($_REQUEST['importOptions']) ? $_REQUEST['importOptions'] : array();
        self::$importProductFromContent = isset($importOptions['importProductsSource']) && $importOptions['importProductsSource'] === 'content-source' ? true : false;

        // Get products JSON
        if (self::$importProductFromContent) {
            $productsJson = self::_getProductsFromContent();
        } else {
            $productsJson = self::_getProductsFromTheme();
        }

        if (empty($productsJson)) {
            return;
        }

        // Process product IDs
        if (!$remove_previous_content) {
            $result = self::_processProductIds($productsJson, $paymentProductsIds);
            $productsJson = $result['productsJson'];
            $paymentProductsIds = $result['paymentProductsIds'];

            if (!empty($paymentProductsIds)) {
                update_option('_paymentProductsIds', $paymentProductsIds);
            }
        }

        // Save products
        $new_products_data = json_decode($productsJson, true);
        if (!is_array($new_products_data)) {
            throw new Exception("Invalid products json");
        }
        $new_products_data = self::processContent($new_products_data);

        if (!$remove_previous_content) {
            $old_products_data = getProductsJson();
            $new_products_data = self::_mergeProducts($new_products_data, $old_products_data);
        }

        if (!empty($new_products_data)) {
            np_data_provider()->saveProductsJson($new_products_data);
        }
    }

    /**
     * Process content
     *
     * @param array $jsonData
     *
     * @return array $jsonData
     */
    public static function processContent($jsonData) {
        return self::importProductsImages($jsonData);
    }

    /**
     * Get Np products from content.json
     *
     * @return string $productsJson
     *
     * @throws Exception
     */
    private static function _getProductsFromContent()
    {
        $content_dir = file_exists(APP_PLUGIN_PATH . 'content/content.json') ? APP_PLUGIN_PATH . 'content' : get_template_directory() . '/content';

        if (!file_exists($content_dir . '/content.json')) {
            if (file_exists($content_dir . '/nicepage/content/content.json')) {
                $content_dir .= '/nicepage/content';
            } else if (file_exists($content_dir . '/content/content.json')) {
                $content_dir .= '/content';
            }
        }

        $json_path = $content_dir . '/content.json';
        $content = file_get_contents($json_path);
        $content = json_decode($content, true);

        if (!is_array($content)) {
            throw new Exception("Invalid content json");
        }

        return isset($content['Parameters']['productsJson']) ? $content['Parameters']['productsJson'] : '';
    }

    /**
     * Get Np products from theme products.json
     *
     * @return string $productsJson
     */
    private static function _getProductsFromTheme()
    {
        $products_json_path = file_exists(get_template_directory() . '/shop/products.json') ? get_template_directory() . '/shop/products.json' : '';
        if ($products_json_path) {
            $productsJson = file_get_contents($products_json_path);
            return !empty($productsJson) ? $productsJson : '';
        }
        return '';
    }

    /**
     * Process update product ids
     *
     * @param string $productsJson
     * @param array  $paymentProductsIds
     *
     * @return array $result
     */
    private static function _processProductIds($productsJson, $paymentProductsIds)
    {
        $old_products_data = getProductsJson();
        if (!isset($old_products_data['products']) || count($old_products_data['products']) == 0) {
            return array(
                'productsJson' => $productsJson,
                'paymentProductsIds' => $paymentProductsIds
            );
        }

        $max_product_id = 1;
        foreach ($old_products_data['products'] as $old_product_data) {
            if ($max_product_id < (int)$old_product_data['id']) {
                $max_product_id = (int)$old_product_data['id'];
            }
        }

        if (preg_match_all('/"id":"([\s\S]+?)"/', $productsJson, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $old_id = isset($match[1]) ? $match[1] : false;
                $new_id = $max_product_id + 1;
                if ($old_id && !array_key_exists($old_id, $paymentProductsIds)) {
                    $productsJson = str_replace('"id":"' . $old_id . '"', '"id":"' . $new_id . '"', $productsJson);
                    $paymentProductsIds[$old_id] = $new_id;
                }
                $max_product_id++;
            }
        }

        return array(
            'productsJson' => $productsJson,
            'paymentProductsIds' => $paymentProductsIds
        );
    }

    /**
     * Merge old and new products
     *
     * @param array $new_products_data
     * @param array $old_products_data
     *
     * @return mixed
     */
    private static function _mergeProducts($new_products_data, $old_products_data)
    {
        foreach ($new_products_data as $param_name => $new_param_data) {
            if (isset($old_products_data[$param_name])) {
                $new_products_data[$param_name] = array_merge($new_products_data[$param_name], $old_products_data[$param_name]);
            }
        }
        return $new_products_data;
    }

    private static $_replaceFrom = array();
    private static $_replaceTo = array();

    /**
     * Import all images for products
     *
     * @param array $jsonData Products Data
     *
     * @return array $jsonData Products Data with updated image paths
     */
    public static function importProductsImages($jsonData) {
        if (isset($jsonData['products'])) {
            foreach ($jsonData['products'] as &$product) {
                self::_importProductImages($product);
            }
        }
        return self::_replaceImagePathsInJson($jsonData);
    }

    /**
     * Import and process product images
     *
     * @param array $product Product data
     */
    private static function _importProductImages($product) {
        $content_dir = file_exists(APP_PLUGIN_PATH . 'content/content.json')
            ? APP_PLUGIN_PATH . 'content'
            : get_template_directory() . '/content';
        $imagesDir = self::$importProductFromContent ? $content_dir : get_template_directory();
        $imagesPath = $imagesDir . '/images';

        if (empty($product['images']) || !is_dir($imagesPath)) {
            return;
        }

        $base_upload_dir = wp_upload_dir();
        $images_dir = $base_upload_dir['path'];
        $data_provider = np_data_provider();

        // Load images mapping from content.json if importing from content
        $imagesMapping = array();
        if (self::$importProductFromContent) {
            $content_json_path = $content_dir . '/content.json';
            if (file_exists($content_json_path)) {
                $content = json_decode(file_get_contents($content_json_path), true);
                if (isset($content['Images'])) {
                    $imagesMapping = $content['Images'];
                }
            }
        }

        // Process each product image
        foreach ($product['images'] as $id => &$image) {
            $original_image_path = $image['url'];
            $imageId = null;

            // Handle [image_X] placeholder format
            if (self::$importProductFromContent && preg_match('/^\[image_(\d+)\]$/', $original_image_path, $matches)) {
                $imageId = $matches[1];
                if (!isset($imagesMapping[$imageId]['fileName'])) {
                    continue; // Skip if no filename mapping found
                }
                $original_filename = $imagesMapping[$imageId]['fileName'];
            } else {
                $original_filename = basename($original_image_path);
            }

            // Verify source image exists
            $source_image_path = $imagesPath . '/' . $original_filename;
            if (!file_exists($source_image_path)) {
                continue;
            }

            // Prepare unique filename for WordPress media library
            $filename = wp_unique_filename($images_dir, $original_filename);
            $image_path = $images_dir . '/' . $filename;

            // Copy image to uploads directory
            NpFilesUtility::copyRecursive($source_image_path, $image_path);

            // Create WordPress attachment
            $wp_filetype = wp_check_filetype($filename, null);
            $attachment = array(
                'guid' => $base_upload_dir['url'] . '/' . $filename,
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
            );
            $attach_id = wp_insert_attachment($attachment, $image_path);

            // Optimize image editor selection based on image size
            if (extension_loaded('imagick')) {
                $imageData = getimagesize($image_path);
                $width = isset($imageData[0]) ? $imageData[0] : 0;
                $height = isset($imageData[1]) ? $imageData[1] : 0;

                add_filter(
                    'wp_image_editors', function () use ($width, $height) {
                        return ($width > 1600 || $height > 1600)
                            ? array('WP_Image_Editor_GD', 'WP_Image_Editor_Imagick')
                            : array('WP_Image_Editor_Imagick', 'WP_Image_Editor_GD');
                    }
                );
            }

            // Generate attachment metadata and update
            $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // Get final image URL and store in product data
            $image_url = wp_get_attachment_url($attach_id);
            $image_url = $data_provider->replaceImagePaths($image_url);
            $image['url'] = $image_url;

            // Register replacements for content.json placeholders
            if (self::$importProductFromContent && $imageId !== null) {
                self::$_replaceFrom[] = '[image_' . $imageId . ']';
                self::$_replaceTo[] = $image_url;
            }

            // Register replacement for products.json
            if (!self::$importProductFromContent || $original_image_path !== '[image_' . $imageId . ']') {
                self::$_replaceFrom[] = str_replace('/', '\/', $original_image_path);
                self::$_replaceTo[] = $image_url;
            }
        }
    }

    /**
     * Update image paths in JSON
     *
     * @param array $jsonData Products Json
     *
     * @return array $jsonData Products Json with updated paths
     */
    private static function _replaceImagePathsInJson($jsonData) {
        $jsonString = json_encode($jsonData);
        $jsonString = str_replace(self::$_replaceFrom, self::$_replaceTo, $jsonString);
        $updatedJson = json_decode($jsonString, true);
        return $updatedJson;
    }


    /**
     * Action on wp_ajax_np_import_products
     * Action to import products
     */
    public static function importProductsAction()
    {
        check_ajax_referer('np-importer');
        self::import(false);
        exit;
    }

    /**
     * Action on wp_ajax_np_replace_products
     * Action to import products with replace
     */
    public static function replaceProductsAction()
    {
        check_ajax_referer('np-importer');
        self::import(true);
        exit;
    }
}

add_action('wp_ajax_np_import_products', 'NpImportProducts::importProductsAction', 9);
add_action('wp_ajax_np_replace_products', 'NpImportProducts::replaceProductsAction', 9);