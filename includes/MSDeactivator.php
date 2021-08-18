<?php

declare(strict_types = 1);

namespace Merck_Scraper\includes;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/includes
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSDeactivator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        /**
         * Since we've registered custom post types and  custom taxonomies, we'll want to flush the permalinks
         */
        flush_rewrite_rules();
    }
}
