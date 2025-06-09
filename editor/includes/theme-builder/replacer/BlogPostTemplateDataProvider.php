<?php
defined('ABSPATH') or die;

class BlogPostTemplateDataProvider
{
    protected $post;

    /**
     * BlogPostTemplateDataProvider constructor.
     *
     * @param WP_Post $post
     */
    public function __construct($post)
    {
        $this->post = $post;
    }

    /**
     * Get Post title with hooks/filters
     *
     * @return string Post title
     */
    public function get_title()
    {
        return apply_filters('the_title', $this->post->post_title, $this->post->ID);
    }

    /**
     * Get Post excerpt with hooks/filters
     *
     * @return string Post excerpt
     */
    public function get_excerpt()
    {
        if (has_excerpt($this->post->ID)) {
            return apply_filters('the_excerpt', $this->post->post_excerpt);
        }
        return '';
    }

    /**
     * Get Post content with hooks/filters
     *
     * @param string $postType
     *
     * @return string Post content
     */
    public function get_content($postType)
    {
        $content = apply_filters('the_content', $this->post->post_content);
        return $postType === 'full' ? $content : plugin_trim_long_str(self::get_excerpt(), 150);
    }
}
