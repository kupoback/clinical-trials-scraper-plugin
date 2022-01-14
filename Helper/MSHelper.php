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
class MSHelper
{

    /**
     * A basic method to check if an array is single level or multidimensional
     *
     * @param array $array The array to iterate through
     *
     * @return bool
     */
    public static function isMultiArray(array $array)
    :bool
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

    /**
     * Strips out any text and returns an int value for the string
     *
     * @param string $years
     *
     * @return null|array|string|string[]
     */
    public static function stripYears(string $years)
    {
        return preg_replace('/[^0-9]/', '', $years);
    }

    /**
     * Formats the text needed for the textarea to omit any HTML and anything unacceptable for input
     *
     * @param string $sanitized_field The text we're sanitizing
     *
     * @return string
     */
    public static function formatTextarea(string $sanitized_field)
    :string
    {
        if ($sanitized_field) {
            $sanitized_field = sanitize_textarea_field($sanitized_field);
            $sanitized_field = preg_replace('/\W/', ' ', $sanitized_field);
            $sanitized_field = preg_replace('/\s+/', ' ', $sanitized_field);
            $sanitized_field = trim($sanitized_field);
            $sanitized_field = explode(' ', $sanitized_field);
            $sanitized_field = collect($sanitized_field);
            return $sanitized_field
                ->unique()
                ->implode(';' . PHP_EOL);
        }
        return '';
    }

    /**
     * Converts the content in a textarea to strip out semi-colors, replacing them with \n's
     * then splitting the \n's into an array
     *
     * @param string $field The ACF textarea string
     *
     * @return array
     */
    public static function textareaToArr(string $field)
    :array
    {
        if ($field) {
            $field = str_replace(';', '\n', $field);
            $field = explode('\n', $field);
            return array_map('trim', $field);
        }
        return [];
    }
}
