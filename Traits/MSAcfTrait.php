<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

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
     * Retruns a field value from the Merck Scraper Options page
     *
     * @param string $field The ACF field name
     *
     * @return mixed
     */
    protected function acfOptionField(string $field)
    {
        return strtolower(get_field($field, 'merck_settings') ?? '');
    }
}
