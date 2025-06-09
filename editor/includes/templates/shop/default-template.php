<?php
defined('ABSPATH') or die;
/**
 * Default template with raw html
 */
global $template_name;
$data_provider = np_data_provider();
ob_start();
?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php wp_head(); ?>
    </head>
    <body class="<?php echo $data_provider->getPageBodyClass(); ?>"
          style="<?php echo $data_provider->getPageBodyStyle(); ?>"
          data-bg="<?php echo $data_provider->getPageBodyDataBg(); ?>">
    <?php if (version_compare($wp_version, '5.2', '>=')) {
        wp_body_open();
    } ?>

    <?php if ($template_name === 'thankYou') {
        $path = dirname(__FILE__) . "/default/thank-you.php";
        if (file_exists($path)) {
            include $path;
        }
    } ?>

    <?php wp_footer(); ?>
    </body>
    </html>
<?php
$htmlDocument = ob_get_clean();
echo $htmlDocument;