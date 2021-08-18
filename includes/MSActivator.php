<?php

declare(strict_types = 1);

namespace Merck_Scraper\includes;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/includes
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSActivator
{

    protected static string $name = 'Merck Scraper';
    protected static string $textdomain = 'merck-scraper';

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        /**
         * Create a .env file if it's never made
         */
        if (!file_exists(plugin_dir_path(__FILE__) . '.env')) {
            touch(plugin_dir_path(__FILE__) . '.env');
        }

        /**
         * Since we've registered custom post types and  custom taxonomies, we'll want to flush the permalinks
         */
        flush_rewrite_rules();
    }
}
