<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
} ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php if (!current_theme_supports('title-tag')) : ?>
        <title>
            <?php
            // PHPCS - already escaped by WordPress.
            echo wp_get_document_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </title>
    <?php endif; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php global $wp_version; if (version_compare($wp_version, '5.2', '>=')) {
    wp_body_open();
} ?>
