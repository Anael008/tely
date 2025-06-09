<?php
defined('ABSPATH') or die;

class NpSavePageAction extends NpAction {

    /**
     * Process action entrypoint
     *
     * @return array
     *
     * @throws Exception
     */
    public static function process() {

        include_once dirname(__FILE__) . '/chunk.php';

        $saveType = isset($_REQUEST['saveType']) ? $_REQUEST['saveType'] : '';
        switch($saveType) {
        case 'base64':
            $_REQUEST = array_merge($_REQUEST, json_decode(base64_decode($_REQUEST['data']), true));
            break;
        case 'chunks':
            $chunk = new NpChunk();
            $ret = $chunk->save(self::getChunkInfo($_REQUEST));
            if (is_array($ret)) {
                return self::response(array($ret));
            }
            if ($chunk->last()) {
                $result = $chunk->complete();
                if ($result['status'] === 'done') {
                    $_REQUEST = array_merge($_REQUEST, json_decode(base64_decode($result['data']), true));
                } else {
                    $result['result'] = 'error';
                    return self::response(array($result));
                }
            } else {
                return self::response('processed');
            }
            break;
        default:
        }

        if (!isset($_REQUEST['id']) || !isset($_REQUEST['data'])) {
            return array(
                'status' => 'error',
                'type' => 'CmsSaveServerError',
                'message' => 'post parameter missing',
            );
        }
        if (!isset($_REQUEST['data']['publishNicePageCss']) || $_REQUEST['data']['publishNicePageCss'] === '') {
            return array(
                'status' => 'error',
                'type' => 'CmsSaveServerError',
                'message' => 'publishNicePageCss parameter missing',
            );
        }

        $request = $_REQUEST;

        if (!$saveType) {
            foreach ($request as $key => $value) {
                $request[$key] = stripslashes_deep($value);
            }
        }

        $post_id = $request['id'];
        $templateKey = isset($request['templateKey']) ? $request['templateKey'] : '';
        $title = _arr($request, 'title', '');

        if (!$title) {
            return array(
                'result' => 'error',
                'type' => 'CmsSaveServerError',
                'message' => 'Page title missing',
            );
        }

        $data = &$request['data'];
        $fullRequest = &$request;

        if ($post_id === '404' && $templateKey === '404') {
            $post_id = null;
        }
        if ($templateKey && !is_numeric($post_id)) {
            $insert_data = array();
            $insert_data['post_type'] = 'template';
            $insert_data['post_status'] = 'publish';
            $insert_data['post_name'] = $templateKey;

            $post_id = wp_insert_post($insert_data);
            if (is_wp_error($post_id)) {
                //TODO: process error
            }
            update_post_meta($post_id, '_original_template_name', $templateKey);
        }

        if ($post_id <= 0) {
            $insert_data = array();

            $insert_data['post_type'] = 'page';
            $insert_data['post_status'] = 'publish';

            $post_id = wp_insert_post($insert_data);
            if (is_wp_error($post_id)) {
                //TODO: process error
            }
        }

        $post = get_post($post_id);

        if (!$post) {
            return array(
                'result' => 'error',
                'type' => 'CmsSaveServerError',
                'message' => ($templateKey ? 'Template' : 'Page') . ' not found'
            );
        }

        if (isset($request['pageType'])) {
            $getCmsValue = array(
                'theme-template' => '',
                'np-template-header-footer-from-plugin' => 'html',
                'np-template-header-footer-from-theme' => 'html-header-footer'
            );
            $pageType = $getCmsValue[$request['pageType']];
        } else {
            $pageType = 'html';
        }
        NpMetaOptions::update($post_id, 'np_template', $pageType);

        $customFontsCss = isset($request['customFontsCss']) ? $request['customFontsCss'] : '';
        $parts = explode('/* page-custom-fonts */', $customFontsCss);
        $headerFooterCustomFontsCss = '';
        if (count($parts) > 1) {
            $headerFooterCustomFontsCss = $parts[0];
            $customFontsCss = $parts[1];
        }
        if ($customFontsCss) {
            $base_upload_dir = wp_upload_dir();
            $customFontsPath = $base_upload_dir['basedir'] . '/nicepage-fonts/';
            if (!file_exists($customFontsPath)) {
                mkdir($customFontsPath);
            }
            $customFontsFilePath = $customFontsPath . 'fonts_' . $post_id . '.css';
            file_put_contents($customFontsFilePath, $customFontsCss);
            if ($headerFooterCustomFontsCss) {
                $headerFooterCustomFontsFilePath = $customFontsPath . 'header-footer-custom-fonts.css';
                file_put_contents($headerFooterCustomFontsFilePath, $headerFooterCustomFontsCss);
            }
        }

        $saveAndPublish = isset($request['saveAndPublish']) ? $request['saveAndPublish'] : null;
        $preview = isset($request['isPreview']) ? $request['isPreview'] : null;
        $data_provider = np_data_provider($post_id, $preview, $saveAndPublish);
        $data_provider->setSiteSettings(_arr($request, 'settings', ''));

        if ($title !== $post->post_title) {
            $title = NpAdminActions::createUniqueTitle($title);
            wp_update_post(
                array(
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_status' => $post->post_status === 'auto-draft' ? 'draft' : $post->post_status,
                )
            );
            $post = get_post($post_id);
        }

        $publishHeaderFooter = NpSavePageAction::saveHeaderFooter($data_provider, $fullRequest);

        $publish_html = _arr($data, 'publishHtml', '');
        $publish_html_translations = _arr($data, 'publishHtmlTranslations', array());
        $data_provider->setPagePublishHtml($publish_html);
        $data_provider->setPagePublishHtmlTranslations($publish_html_translations, $post_id);
        $data_provider->setPageHtml(_arr($data, 'html', ''));
        $data_provider->setPageHead(_arr($data, 'head', ''));
        $data_provider->setPageBodyClass(_arr($data, 'bodyClass', ''));
        $data_provider->setPageBodyStyle(_arr($data, 'bodyStyle', ''));
        $data_provider->setPageBodyDataBg(_arr($data, 'bodyDataBg', ''));

        $data_provider->setHideHeader(_arr($data, 'hideHeader', 'false'));
        $data_provider->setHideFooter(_arr($data, 'hideFooter', 'false'));
        $data_provider->setPasswordProtection(_arr($data, 'passwordProtection', ''));
        $data_provider->setHideBackToTop(_arr($data, 'hideBackToTop', 'false'));
        $data_provider->setPageOgTags(_arr($fullRequest, 'ogTags', ''));
        $data_provider->setPageSeoTranslations(_arr($data, 'seoTranslations', array()));

        $fonts = _arr($data, 'fonts', '');
        $base_upload_dir = wp_upload_dir();
        $fontsUrl = $base_upload_dir['baseurl'] . '/nicepage-gfonts/';
        if ($fonts) {
            $fonts = preg_replace('/[\"\']fonts.css[\"\']/',  $fontsUrl . 'fonts.css', $fonts);
            $fonts = preg_replace('/[\"\']page-fonts.css[\"\']/', $fontsUrl . 'page-' . $post_id . '-fonts.css', $fonts);
        }
        $headerFooterFonts = isset($request['headerFooterFonts']) ? $request['headerFooterFonts'] : '';
        if ($headerFooterFonts) {
            $fullHeaderFooterFontsPath = preg_replace('/[\"\']header-footer-fonts.css[\"\']/', $fontsUrl . 'header-footer-fonts.css', $headerFooterFonts);
            NpMeta::update('headerFooterFonts', $fullHeaderFooterFontsPath);
        }

        $data_provider->setPageFonts($fonts);
        self::saveLocalGoogleFonts(_arr($request, 'fontsData', ''), $post_id);

        $backlinkHtml = _arr($data, 'backlink', '');
        $data_provider->setPageBacklink($backlinkHtml);
        if ($backlinkHtml) {
            $publish_html .= $backlinkHtml;
        }

        $dialogsData = _arr($request, 'dialogs', '');
        if ($dialogsData) {
            $dialogs = json_decode($dialogsData, true);
            $data_provider->setDialogsData($dialogs);
        }
        $data_provider->setPublishDialogs(_arr($request, 'publishDialogs', ''));
        if (isset($dialogs) && $dialogs) {
            foreach ($dialogs as $dialog) {
                $publish_html .= $dialog['publishHtml'];
            }
        }

        $passwordProtectionItem = $data_provider->getPasswordProtectionData();
        if ($passwordProtectionItem) {
            $publish_html .= $passwordProtectionItem['php'];
        }

        $backToTopPublishHtml = isset($request['backToTopPublishHtml']) ? $request['backToTopPublishHtml'] : '';
        if ($backToTopPublishHtml) {
            NpMeta::update('backToTop', $backToTopPublishHtml);
            $publish_html .= $backToTopPublishHtml;
        }

        $templateHtml = $templateKey ? $publish_html : '';
        $templateCss = $templateKey ? true : false;
        $data_provider->setStyleCss(_arr($data, 'publishNicePageCss', ''), $publish_html, $publishHeaderFooter, '', $templateHtml, $templateCss);
        $data_provider->setPageKeywords(_arr($request, 'keywords', ''));
        $data_provider->setPageDescription(_arr($request, 'description', ''));
        $data_provider->setPageCanonical(_arr($request, 'canonical', ''));
        $data_provider->setPageMetaTags(_arr($request, 'metaTags', ''));
        $data_provider->setPageMetaGenerator(_arr($request, 'metaGeneratorContent', ''));
        $data_provider->setPageMetaReferrer(_arr($request, 'metaReferrer', ''));
        $data_provider->setPageCustomHeadHtml(_arr($request, 'customHeadHtml', ''));
        $data_provider->setPageTitleInBrowser(_arr($request, 'titleInBrowser', ''));
        $data_provider->setFormsData(_arr($request, 'pageFormsData', ''));

        NpForms::updateForms($post_id);

        if ($data_provider->saveAndPublish) {
            np_data_provider($post_id, null, true)->clear();
            // create post_content for page indexing in search
            wp_update_post(array('ID' => $post_id, 'post_content' => apply_filters('np_create_excerpt', $data_provider->getPagePublishHtml())));
            $post = get_post($post_id);
        }
        if (!$data_provider->preview) {
            np_data_provider($post_id, true)->clear();
        }

        $result = self::getPost($post);
        return array(
            'result' => 'done',
            'data' => $result,
        );
    }

    /**
     * Save local google fonts
     *
     * @param array  $fontsData Data parameters
     * @param string $pageId    Page id
     *
     * @return array|void
     */
    public static function saveLocalGoogleFonts($fontsData, $pageId) {
        if (!$fontsData) {
            return;
        }


        $base_upload_dir = wp_upload_dir();
        $fontsFolder = $base_upload_dir['basedir'] . '/nicepage-gfonts';
        if (!file_exists($fontsFolder)) {
            if (false === @mkdir($fontsFolder, 0777, true)) {
                return;
            }
        }
        $fontsFiles = isset($fontsData['files']) ? $fontsData['files'] : array();
        foreach ($fontsFiles as $fontFile) {
            $fontData = json_decode($fontFile, true);
            if (!$fontData) {
                continue;
            }
            switch($fontData['fileName']) {
            case 'fonts.css':
                file_put_contents($fontsFolder . '/fonts.css', str_replace('fonts/', '', $fontData['content']));
                break;
            case 'page-fonts.css':
                file_put_contents($fontsFolder . '/page-' . $pageId . '-fonts.css', str_replace('fonts/', '', $fontData['content']));
                break;
            case 'header-footer-fonts.css':
                file_put_contents($fontsFolder . '/header-footer-fonts.css', str_replace('fonts/', '', $fontData['content']));
                break;
            case 'downloadedFonts.json':
                file_put_contents($fontsFolder . '/downloadedFonts.json', $fontData['content']);
                break;
            default:
                $content = '';
                $bytes = $fontData['content'];
                foreach ($bytes as $chr) {
                    $content .= chr($chr);
                }
                file_put_contents($fontsFolder . '/' . $fontData['fileName'], $content);
            }
        }
    }

    /**
     * @param string|array $result Result
     *
     * @return mixed|string
     */
    public static function response($result)
    {
        if (is_string($result)) {
            $result = array('result' => $result);
        }
        return $result;
    }

    /**
     * Get chunk info
     *
     * @param array $data Chunk data
     *
     * @return array
     */
    public static function getChunkInfo($data)
    {
        return array(
            'id' => $data['id'],
            'content' =>  isset($data['content']) ? $data['content'] : '',
            'current' =>  $data['current'],
            'total' =>  $data['total'],
            'blob' => $data['blob'] == 'true' ? true : false
        );
    }

    /**
     * Save header and footer content
     *
     * @param NpDataProvider $data_provider
     * @param array          $data
     *
     * @return array $result
     */
    public static function saveHeaderFooter($data_provider, $data)
    {
        $result = array();
        $keys = array('header', 'footer');
        $publishHeaderFooter = '';
        foreach ($keys as $key) {
            $html = isset($data[$key]) ? $data[$key] : '';
            $htmlCss = isset($data[$key.'Css']) ? $data[$key.'Css'] : '';
            $htmlPhp =  isset($data['publish'.ucfirst($key)]) ? $data['publish'.ucfirst($key)] : '';
            $formsData = isset($data[$key . 'FormsData']) ? $data[$key . 'FormsData'] : '[]';
            $dialogsData = isset($data[$key . 'Dialogs']) ? $data[$key . 'Dialogs'] : '[]';

            if ($html) {
                $publishPageParts = str_replace(
                    get_site_url(),
                    '[[site_path_live]]',
                    array(
                        'html'    => $html,
                        'htmlPhp' => $htmlPhp,
                        'htmlCss' => $htmlCss
                    )
                );
                $htmlPhp = $data_provider->setHeaderFooterPublishHtml($htmlPhp);
                $result[$key] = json_encode(
                    array(
                        'html'   => $publishPageParts['html'],
                        'php'    => $publishPageParts['htmlPhp'],
                        'styles' => $publishPageParts['htmlCss'],
                        'formsData' => $formsData,
                        'dialogs' => $dialogsData,
                    )
                );
                $publishHeaderFooter .= $htmlPhp;
            } else {
                $result[$key] = "";
                if (get_option($key . 'Np')) {
                    $item = json_decode(get_option($key . 'Np'), true);
                    $publishHeaderFooter .= $item['php'];
                }
            }
            // add footer/header modal popups to $dialogs for set styles - custom colors
            ${$key . 'DialogsData'} = json_decode($dialogsData, true);
            if (${$key . 'DialogsData'}) {
                foreach (${$key . 'DialogsData'} as $dialog) {
                    $publishHeaderFooter .= $dialog['publishHtml'];
                }
            }
        }
        // Save header and footer content data
        if ($result['header'] !== "") {
            $data_provider->setNpHeader($result['header']);
            NpForms::updateForms(0, 'header', $data['publishHeader']);
        }
        if (isset($data['publishHeaderTranslations'])) {
            foreach ($data['publishHeaderTranslations'] as $lang => $translation) {
                $data_provider->setTranslation($translation, 'header', $lang);
                $GLOBALS['np_current_process_lang'] = $lang;
                NpForms::updateForms(0, 'header', $translation);
            }
            $GLOBALS['np_current_process_lang'] = false;
        }
        if ($result['footer'] !== "") {
            $data_provider->setNpFooter($result['footer']);
            NpForms::updateForms(0, 'footer', $data['publishFooter']);
        }
        if (isset($data['publishFooterTranslations'])) {
            foreach ($data['publishFooterTranslations'] as $lang => $translation) {
                $data_provider->setTranslation($translation, 'footer', $lang);
                $GLOBALS['np_current_process_lang'] = $lang;
                NpForms::updateForms(0, 'footer', $translation);
            }
            $GLOBALS['np_current_process_lang'] = false;
        }
        return $publishHeaderFooter;
    }
}

NpAction::add('np_save_page', 'NpSavePageAction');
add_filter('np_create_excerpt', 'wp_strip_all_tags');