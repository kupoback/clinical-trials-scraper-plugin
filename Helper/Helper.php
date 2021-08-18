<?php

declare(strict_types = 1);

namespace Merck_Scraper\Helper;

use WP_Error;

/**
 * Methods that can be used throughout the plugin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class Helper
{

    /**
     * A basic method to check if an array is single level or multi-dimensional
     *
     * @param array $array The array to iterate through
     *
     * @return bool
     */
    public static function isMultiArray(array $array)
    {
        rsort($array);
        return isset($array[0]) && is_array($array[0]);
    }

    /**
     * Returns the excerpt if it exists or creates the excerpt
     * based on the $post_override or $post->post_content
     *
     * @param string $post_override The content to truncate and clean to use for the excerpt
     * @param int    $word_max      Pass an integer to override the word count
     *
     * @return string|WP_Error
     */
    public static function generateExcerpt(string $post_content, int $word_max = 25)
    {
        if (!$post_content) {
            return new WP_Error(400, __("Please include the post content.", 'merck-scraper'));
        }

        $excerpt = strip_shortcodes($post_content);
        $excerpt = strip_tags($excerpt);
        $excerpt = wp_trim_words($excerpt, $word_max, '');

        return html_entity_decode($excerpt);
    }
}
