<?php
defined('ABSPATH') or die;

class PasswordTemplateReplacer
{

    /**
     * Process password form
     *
     * @param string $content Content
     *
     * @return string|string[]|null
     */
    public static function process($content)
    {
        $content = preg_replace_callback('/<\!--password_form-->([\s\S]+?)<\!--\/password_form-->/', 'self::_processPasswordForm', $content);
        return $content;
    }

    /**
     * Process password form
     *
     * @param array $passwordFormMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processPasswordForm($passwordFormMatch)
    {
        $passwordFormHtml = $passwordFormMatch[1];
        $action = APP_PLUGIN_URL . 'includes/templates/passwordProtect/action.php';
        $passwordFormHtml = str_replace('action="#"', 'action="' . $action . '"', $passwordFormHtml);
        $passwordFormHtml = preg_replace_callback('/<\!--password_form_input-->([\s\S]+?)<\!--\/password_form_input-->/', 'self::_processPasswordFormInput', $passwordFormHtml);
        return $passwordFormHtml;
    }

    /**
     * Process password form input
     *
     * @param array $inputMatch Matches
     *
     * @return string
     */
    private static function _processPasswordFormInput($inputMatch)
    {
        $inputHtml = $inputMatch[1];
        global $original_post;
        $post_id = isset($original_post->ID) ? $original_post->ID : 0;
        return $inputHtml . '<input type="hidden" name="post_id" value="' . $post_id . '"><input name="password_hash" id="hashbox" type="hidden" size="20">';
    }

}
