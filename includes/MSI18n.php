<?php

declare(strict_types = 1);

namespace Merck_Scraper\Includes;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/includes
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSI18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function loadMSTextdomain()
    :void
    {
        load_plugin_textdomain(
            'merck-scraper',
            false,
            dirname(plugin_basename(__FILE__), 2) . '/languages/'
        );
    }
}
