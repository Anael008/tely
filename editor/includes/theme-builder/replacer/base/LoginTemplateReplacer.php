<?php
defined('ABSPATH') or die;

class LoginTemplateReplacer
{

    public static $loginType;

    /**
     * TemplatesReplacer process.
     *
     * @param string $content
     *
     * @return string $content
     */
    public static function process($content)
    {
        $content = preg_replace_callback('/<\!--login_form-->([\s\S]+?)<\!--\/login_form-->/', 'self::_processLoginForm', $content);
        $content = preg_replace_callback('/<\!--login_form_forgot_password-->([\s\S]+?)<\!--\/login_form_forgot_password-->/', 'self::_processLoginFormForgotPassword', $content);
        $content = preg_replace_callback('/<\!--login_form_create_account-->([\s\S]+?)<\!--\/login_form_create_account-->/', 'self::_processLoginFormCreateAccount', $content);
        $content = preg_replace_callback('/<\!--login_form_forgot_name-->([\s\S]+?)<\!--\/login_form_forgot_name-->/', 'self::_processLoginFormForgotName', $content);
        return $content;
    }

    /**
     * Process form
     *
     * @param array $loginFormMatch Matches
     *
     * @return string
     */
    private static function _processLoginForm($loginFormMatch)
    {
        $loginFormHtml = $loginFormMatch[1];
        if (strpos($loginFormHtml, '<form') === false) {
            return '';
        }
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        if (is_page('login') || $action === '') {
            self::$loginType = 'login';
            $actionUrl = wp_login_url();
        } else if (is_page('register') || $action === 'register') {
            self::$loginType = 'register';
            $actionUrl = wp_registration_url();
        } else {
            self::$loginType = 'lostpassword';
            $actionUrl = wp_lostpassword_url();
        }
        $loginFormHtml = str_replace('<form', self::_getThemeMyLoginAlerts() . '<form', $loginFormHtml);
        $loginFormHtml = str_replace('action="#"', 'action="' . $actionUrl . '"', $loginFormHtml);
        $loginFormHtml = preg_replace_callback('/<\!--login_form_usernameGroup-->([\s\S]+?)<\!--\/login_form_usernameGroup-->/', 'self::_processLoginFormUsernameGroup', $loginFormHtml);
        $loginFormHtml = preg_replace_callback('/<\!--login_form_passwordGroup-->([\s\S]+?)<\!--\/login_form_passwordGroup-->/', 'self::_processLoginFormPasswordGroup', $loginFormHtml);
        $loginFormHtml = preg_replace_callback('/<\!--login_form_rememberGroup-->([\s\S]+?)<\!--\/login_form_rememberGroup-->/', 'self::_processLoginFormRememberGroup', $loginFormHtml);
        $loginFormHtml = preg_replace_callback('/<\!--login_form_submit-->([\s\S]+?)<\!--\/login_form_submit-->/', 'self::_processLoginFormSubmit', $loginFormHtml);
        return $loginFormHtml;
    }

    /**
     * Process form submit
     *
     * @param array $submitMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormSubmit($submitMatch)
    {
        $formSubmitHtml = $submitMatch[1];
        $text = __('Log in', 'nicepage');
        if (self::$loginType === 'register') {
            $text = __('Register', 'nicepage');
        }
        if (self::$loginType === 'lostpassword') {
            $text = __('Get New Password', 'nicepage');
        }
        $formSubmitHtml = preg_replace('/(<a[\s\S]*?>)([\s\S]*?)(<\/a>)/', '$1' . $text . '$3', $formSubmitHtml);
        return $formSubmitHtml;
    }

    /**
     * Process form username group
     *
     * @param array $usernameGroupMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormUsernameGroup($usernameGroupMatch)
    {
        $usernameGroupHtml = $usernameGroupMatch[1];
        $usernameGroupHtml = preg_replace_callback('/<\!--login_form_username-->([\s\S]+?)<\!--\/login_form_username-->/', 'self::_processLoginFormUsername', $usernameGroupHtml);
        $usernameGroupHtml = preg_replace_callback('/<\!--login_form_usernameLabel-->([\s\S]+?)<\!--\/login_form_usernameLabel-->/', 'self::_processLoginFormUsernameLabel', $usernameGroupHtml);
        return $usernameGroupHtml;
    }

    /**
     * Process form input username
     *
     * @param array $usernameMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormUsername($usernameMatch)
    {
        $usernameHtml = $usernameMatch[1];
        $text = __('Username', 'nicepage');
        if (self::$loginType === 'lostpassword') {
            $text = __('Username or Email Address', 'nicepage');
        }
        $name = self::$loginType === 'login' ? 'log' : 'user_login';
        $usernameHtml = preg_replace('/placeholder="[^"]+?"/', 'placeholder="' . $text . '"', $usernameHtml);
        $usernameHtml = preg_replace('/name="[^"]+?"/', 'name="' . $name . '"', $usernameHtml);
        return $usernameHtml;
    }

    /**
     * Process form username label
     *
     * @param array $usernameLabelMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormUsernameLabel($usernameLabelMatch)
    {
        $usernameLabelHtml = $usernameLabelMatch[1];
        $text = __('Username', 'nicepage');
        if (self::$loginType === 'lostpassword') {
            $text = __('Username or Email Address', 'nicepage');
        }
        $usernameLabelHtml = preg_replace('/(<label[^>]+?>)([\s\S]*?)(<\/label>)/', '$1' . $text . '$3', $usernameLabelHtml);
        return $usernameLabelHtml;
    }

    /**
     * Process form password group
     *
     * @param array $passwordGroupMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormPasswordGroup($passwordGroupMatch)
    {
        $passwordGroupHtml = $passwordGroupMatch[1];
        if (self::$loginType === 'lostpassword') {
            return '';
        }
        if (self::$loginType === 'register') {
            $passwordGroupHtml = preg_replace('/for="password([^"]*)"/', 'for="email$1"', $passwordGroupHtml);
            $passwordGroupHtml = preg_replace('/id="password([^"]*)"/', 'id="email$1"', $passwordGroupHtml);
            $passwordGroupHtml = preg_replace('/name="password([^"]*)"/', 'name="email$1"', $passwordGroupHtml);
        }
        $passwordGroupHtml = preg_replace_callback('/<\!--login_form_password-->([\s\S]+?)<\!--\/login_form_password-->/', 'self::_processLoginFormPassword', $passwordGroupHtml);
        $passwordGroupHtml = preg_replace_callback('/<\!--login_form_passwordLabel-->([\s\S]+?)<\!--\/login_form_passwordLabel-->/', 'self::_processLoginFormPasswordLabel', $passwordGroupHtml);
        return $passwordGroupHtml;
    }

    /**
     * Process form input password
     *
     * @param array $passwordMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormPassword($passwordMatch)
    {
        $passwordHtml = $passwordMatch[1];
        $type = 'password';
        $text = __('Password', 'nicepage');
        if (self::$loginType === 'register') {
            $text = __('Email', 'nicepage');
            $type = 'email';
        }
        $name = self::$loginType === 'login' ? 'pwd' : 'user_email';
        $passwordHtml = str_replace('type="text"', 'type="' . $type . '"', $passwordHtml);
        $passwordHtml = preg_replace('/placeholder="[^"]+?"/', 'placeholder="' . $text . '"', $passwordHtml);
        $passwordHtml = preg_replace('/name="[^"]+?"/', 'name="' . $name . '"', $passwordHtml);
        return $passwordHtml;
    }

    /**
     * Process form password label
     *
     * @param array $passwordLabelMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormPasswordLabel($passwordLabelMatch)
    {
        $passwordLabelHtml = $passwordLabelMatch[1];
        $text = __('Password', 'nicepage');
        if (self::$loginType === 'register') {
            $text = __('Email', 'nicepage');
        }
        $passwordLabelHtml = preg_replace('/(<label[^>]+?>)([\s\S]*?)(<\/label>)/', '$1' . $text . '$3', $passwordLabelHtml);
        return $passwordLabelHtml;
    }

    /**
     * Process form remember group
     *
     * @param array $rememberGroupMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormRememberGroup($rememberGroupMatch)
    {
        $rememberGroupHtml = $rememberGroupMatch[1];
        if (self::$loginType === 'register' || self::$loginType === 'lostpassword') {
            return '';
        }
        $rememberGroupHtml = preg_replace_callback('/<\!--login_form_remember-->([\s\S]+?)<\!--\/login_form_remember-->/', 'self::_processLoginFormRemember', $rememberGroupHtml);
        $rememberGroupHtml = preg_replace_callback('/<\!--login_form_rememberLabel-->([\s\S]+?)<\!--\/login_form_rememberLabel-->/', 'self::_processLoginFormRememberLabel', $rememberGroupHtml);
        return $rememberGroupHtml;
    }

    /**
     * Process form checkbox remember
     *
     * @param array $rememberMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormRemember($rememberMatch)
    {
        $rememberHtml = $rememberMatch[1];
        $rememberHtml = preg_replace('/name="[^"]+?"/', 'name="rememberme"', $rememberHtml);
        return $rememberHtml;
    }

    /**
     * Process form remember label
     *
     * @param array $rememberLabelMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormRememberLabel($rememberLabelMatch)
    {
        $rememberLabelHtml = $rememberLabelMatch[1];
        $rememberLabelHtml = preg_replace('/(<label[^>]+?>)([\s\S]*?)(<\/label>)/', '$1' . __('Remember Me', 'nicepage') . '$3', $rememberLabelHtml);
        return $rememberLabelHtml;
    }

    /**
     * Process form forgot password
     *
     * @param array $forgotPasswordMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormForgotPassword($forgotPasswordMatch)
    {
        $forgotPasswordHtml = $forgotPasswordMatch[1];
        $url = wp_lostpassword_url();
        $text = __('Lost Password', 'nicepage');
        if (self::$loginType === 'lostpassword') {
            $url = wp_registration_url();
            $text = __('Register', 'nicepage');
        }
        $forgotPasswordHtml = str_replace('href="#"', 'href="' . $url . '"', $forgotPasswordHtml);
        $forgotPasswordHtml = preg_replace('/(<a[\s\S]*?>)([\s\S]*?)(<\/a>)/', '$1' . $text . '$3', $forgotPasswordHtml);
        return $forgotPasswordHtml;
    }

    /**
     * Process form forgot name
     *
     * @return string
     */
    private static function _processLoginFormForgotName()
    {
        return '';
    }

    /**
     * Process create account
     *
     * @param array $createAccountMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processLoginFormCreateAccount($createAccountMatch)
    {
        if (!get_option('users_can_register')) {
            return '';
        }
        $createAccountHtml = $createAccountMatch[1];
        $url = wp_registration_url();
        $text = __('Register', 'nicepage');
        if (self::$loginType === 'register' || self::$loginType === 'lostpassword') {
            $url = wp_login_url();
            $text = __('Log in', 'nicepage');
        }
        $createAccountHtml = str_replace('href="#"', 'href="' . $url . '"', $createAccountHtml);
        $createAccountHtml = preg_replace('/(<a[\s\S]*?>)([\s\S]*?)(<\/a>)/', '$1' . $text . '$3', $createAccountHtml);
        return $createAccountHtml;
    }

    /**
     * Get all alerts from html template of login/register/lostpassword
     */
    private static function _getThemeMyLoginAlerts()
    {
        $themeMyLoginTemplateShortCode = '[theme-my-login action="' . self::$loginType . '"]';
        $html = do_shortcode($themeMyLoginTemplateShortCode);
        if ($html && strpos($html, 'tml-alerts') !== false) {
            preg_match('/<div class="tml-alerts">\s*<\/div>|<div class="tml-alerts">.*?<ul.*?>.*?<\/ul>.*?<\/div>/s', $html, $matches);
            if (isset($matches[0])) {
                return '<div class="tml">' . $matches[0] . '</div>';
            }
        }
        return '';
    }
}
