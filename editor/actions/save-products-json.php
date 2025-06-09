<?php
defined('ABSPATH') or die;

class NpSaveProductsJsonAction extends NpAction {

    /**
     * Process action entrypoint
     *
     * @return array
     *
     * @throws Exception
     */
    public static function process()
    {

        include_once dirname(__FILE__) . '/chunk.php';

        $saveType = isset($_REQUEST['saveType']) ? $_REQUEST['saveType'] : '';
        $request = array();
        switch ($saveType) {
        case 'base64':
            $request = array_merge($_REQUEST, json_decode(base64_decode($_REQUEST['data']), true));
            break;
        case 'chunks':
            $chunk = new NpChunk();
            $ret = $chunk->save(NpSavePageAction::getChunkInfo($_REQUEST));
            if (is_array($ret)) {
                return NpSavePageAction::response(array($ret));
            }
            if ($chunk->last()) {
                $result = $chunk->complete();
                if ($result['status'] === 'done') {
                    $request = array_merge($_REQUEST, json_decode(base64_decode($result['data']), true));
                } else {
                    $result['result'] = 'error';
                    return NpSavePageAction::response(array($result));
                }
            } else {
                return NpSavePageAction::response('processed');
            }
            break;
        default:
            $request = stripslashes_deep($_REQUEST);
        }

        $jsonData = json_decode($request['productsData'], true);

        $productsJson = array();
        if (isset($jsonData['products'])) {
            $productsJson['products'] = $jsonData['products'];
        }
        if (isset($jsonData['categories'])) {
            $productsJson['categories'] = $jsonData['categories'];
        }
        $data_provider = np_data_provider();
        $data_provider->saveProductsJson($productsJson);
        return array(
            'result' => 'done',
            'data' => $productsJson,
        );
    }
}
NpAction::add('np_save_products_json', 'NpSaveProductsJsonAction');