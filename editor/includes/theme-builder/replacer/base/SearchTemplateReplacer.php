<?php
defined('ABSPATH') or die;

class SearchTemplateReplacer
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
        $content = preg_replace_callback('/<\!--search_template_form-->([\s\S]+?)<\!--\/search_template_form-->/', 'self::_processSearchTemplateForm', $content);
        return $content;
    }

    /**
     * Process search form
     *
     * @param array $searchTemplateFormMatch Matches
     *
     * @return array|string|string[]|null
     */
    private static function _processSearchTemplateForm($searchTemplateFormMatch)
    {
        $searchTemplateFormHtml = $searchTemplateFormMatch[1];
        $searchTemplateFormHtml = preg_replace('/action="[^"]*?"/', 'action="' . esc_url(home_url('/')) .'"', $searchTemplateFormHtml);
        $passwordFormHtml = preg_replace_callback('/<\!--search_template_form_input-->([\s\S]+?)<\!--\/search_template_form_input-->/', 'self::_processSearchTemplateFormInput', $searchTemplateFormHtml);
        $no_results = '';
        if (is_search() && !have_posts()) {
            $no_results = '<br><div class="u-search-not-found-results u-text u-align-center">' . __('No search result found', 'nicepage') . '</div><br>';
        }
        return $passwordFormHtml . $no_results;
    }

    /**
     * Process search form input
     *
     * @param array $searchTemplateFormInputMatch Form input match
     *
     * @return string
     */
    private static function _processSearchTemplateFormInput($searchTemplateFormInputMatch) {
        $searchTemplateFormInputHtml = $searchTemplateFormInputMatch[1];
        $searchTemplateFormInputHtml = preg_replace('/value="[^"]*?"/', 'value="' . get_search_query() .'"', $searchTemplateFormInputHtml);
        $searchTemplateFormInputHtml = preg_replace('/name="[^"]+?"/', 'name="s"', $searchTemplateFormInputHtml);
        $searchTemplateFormInputHtml = preg_replace('/placeholder="[^"]+?"/', 'placeholder="' . __('Search', 'nicepage') . '"', $searchTemplateFormInputHtml);
        return $searchTemplateFormInputHtml;
    }
}
