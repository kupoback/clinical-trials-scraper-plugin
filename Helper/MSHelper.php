<?php

declare(strict_types=1);

namespace Merck_Scraper\Helper;

use Illuminate\Support\Str;
use WP_Error;

/**
 * Methods that can be used throughout the plugin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSHelper
{

    /**
     * Returns the excerpt if it exists or creates the excerpt
     * based on the $post_override or $post->post_content
     *
     * @param  string  $post_content  The WordPress post content
     * @param  int     $word_max      Pass an integer to override the word count
     *
     * @return string|WP_Error
     */
    public static function generateExcerpt(string $post_content, int $word_max = 25)
    :WP_Error|string
    {
        if (!$post_content) {
            return new WP_Error(400, __("Please include the post content.", 'merck-scraper'));
        }

        $excerpt = strip_shortcodes($post_content);
        $excerpt = strip_tags($excerpt);
        $excerpt = wp_trim_words($excerpt, $word_max, '');

        return html_entity_decode($excerpt);
    }

    /**
     * Strips out any text and returns an int value for the string
     *
     * @param  string  $years
     *
     * @return null|array|string|string[]
     */
    public static function stripYears(string $years)
    :array|string|null
    {
        return preg_replace('/[^0-9]/', '', $years);
    }

    /**
     * Converts the content in a textarea to strip out semi-colors, replacing them with \n's
     * then splitting the \n's into an array
     *
     * @param  string  $field  The ACF textarea string
     *
     * @return array
     */
    public static function textareaToArr(string $field)
    :array
    {
        if ($field) {
            $field = Str::replace('<br />', ';', $field);
            $field = Str::replace(';', '\n', $field);
            $field = preg_split('/\n|\r\n?/', $field);

            return array_map('trim', $field);
        }

        return [];
    }
}
