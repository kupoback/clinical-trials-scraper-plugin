<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

/**
 * Trait for cleaning data on import from Files
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MsContentFormat
{
    /**
     * Strips out any text and returns an int value for the string
     *
     * @param string $years
     *
     * @return null|array|string|string[]
     */
    protected function stripYears(string $years)
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
    protected function formatTextarea(string $sanitized_field)
    {
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
}
