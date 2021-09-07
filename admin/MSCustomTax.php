<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

use Merck_Scraper\Traits\MSAdminTrait;

/**
 * Registers the custom taxonomy for the custom post type
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSCustomTax
{

    use MSAdminTrait;

    /**
     * Sets up the taxonomies used on the site. The key for each array item is the taxonomy name
     * and all are linked to the trials post type.
     * Order of the item added to $tax_array indicates the menu order.
     */
    public function registerTaxonomy()
    {
        $tax_array = [];

        // Trial Category Taxonomy
        $tax_array["trial_category"] = self::taxonomyArray("Trial Category", "Trial Categories", 'trial-category');

        // Recruiting Taxonomy
        // $tax_array["recruiting"] = self::taxonomyArray("Recruiting Type", "Recruiting Types", 'recruiting');

        // Study Keyword Taxonomy
        $tax_array["study_keyword"] = self::taxonomyArray("Study Keyword", "Study Keywords", 'study-keyword');

        // Trial Studies Taxonomy
        $tax_array["conditions"] = self::taxonomyArray("Condition", "Conditions", 'condition');

        // Trial Status
        $tax_array["trial_status"] = self::taxonomyArray("Trial Status", "Trial Status", 'trial-status');

        // Loops through each tax array item and registers them.
        foreach ($tax_array as $taxonomy => $tax_args) {
            register_taxonomy($taxonomy, ["trials"], $tax_args);
        }
    }
}
