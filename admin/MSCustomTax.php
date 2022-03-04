<?php

declare(strict_types=1);

namespace Merck_Scraper\admin;

use Illuminate\Support\Collection;
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
        self::loopTaxonomy(
            collect(
                [
                    // Trial Category Taxonomy
                    'trial_ta' => $this->taxonomyArray("Therapeutic Area", "Therapeutic Area", 'trial-ta'),
                    // Study Keyword Taxonomy
                    'study_keyword' => $this->taxonomyArray("Study Keyword", "Study Keywords", 'study-keyword'),
                    // Trial Studies Taxonomy
                    'conditions' => $this->taxonomyArray("Condition", "Conditions", 'condition'),
                    // Trial Status Taxonomy
                    'trial_status' => $this->taxonomyArray("Trial Status", "Trial Status", 'trial-status'),
                    // Trial Drugs Taxonomy
                    'trial_drugs' => $this->taxonomyArray("Trial Drug", "Trial Drugs", 'trial-drugs', false),
                    // Trial Age Taxonomy
                    'trial_age' => $this->taxonomyArray("Trial Age", "Trial Ages", 'trial-age'),
                ]
            ),
            ["trials"]
        );

        self::loopTaxonomy(
            collect(
                [
                    // Location NCTID Taxonomy
                    'location_nctid' => $this->taxonomyArray("NCTID", "NCTIDs", 'location-nctid'),
                    // Location Status Taxonomy
                    'location_status' => $this->taxonomyArray("Status", "Status", 'location-status'),
                ]
            ),
            ['locations'],
        );

        self::loopTaxonomy(
            collect(
                [
                    // Trial Language Taxonomy
                    'trial_language' => $this->taxonomyArray("Language", "Languages", 'trial-language'),
                ]
            ),
            ['trials', 'locations']
        );
    }

    /**
     * Loops through a collection of taxonomies to register
     *
     * @param Collection $tax_collection The Collection of Taxonomies to register
     * @param array      $post_types     An array of post types to register this to.
     *
     * @return void
     */
    private function loopTaxonomy(Collection $tax_collection, array $post_types = [])
    {
        $tax_collection
            ->each(function ($tax_args, $taxonomy) use ($post_types) {
                register_taxonomy($taxonomy, $post_types, $tax_args);
            });
    }
}
