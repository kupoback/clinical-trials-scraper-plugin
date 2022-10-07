<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use Illuminate\Support\Str;
use function get_field;

/**
 * Acf Traits for the plugin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSAcfTrait
{
    /**
     * Reruns a field value from the Merck Scraper Options page with a str to lower
     *
     * @param string $field The ACF field name
     *
     * @return string
     */
    protected function acfStrOptionFld(string $field)
    :string
    {
        return Str::lower(get_field($field, 'merck_settings') ?? '');
    }

    /**
     * Reruns a field value from the Merck Scraper Options page
     *
     * @param string $field The ACF field name
     *
     * @return mixed
     */
    protected function acfOptionField(string $field)
    :mixed
    {
        return get_field($field, 'merck_settings') ?? '';
    }
}
