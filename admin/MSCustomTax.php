<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin;

use Illuminate\Support\Collection;
use Merck_Scraper\Admin\Traits\MSAdminTrait;

/**
 * Registers the custom taxonomy for the custom post type
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
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
    :void
    {
        /**
         * Register Trials Taxonomies
         */
        self::loopTaxonomy(
            collect(
                [
                    // Trial Category Taxonomy
                    'trial_ta' => static::taxonomyArray("Therapeutic Area", 'trial-ta'),
                    // Study Keyword Taxonomy
                    'study_keyword' => static::taxonomyArray("Study Keyword", 'study-keyword'),
                    // Trial Studies Taxonomy
                    'conditions' => static::taxonomyArray("Condition", 'condition'),
                    // Trial Status Taxonomy
                    'trial_status' => static::taxonomyArray("Trial Status", 'trial-status'),
                    // Trial Drugs Taxonomy
                    'trial_drugs' => static::taxonomyArray("Trial Drug", 'trial-drugs', false),
                    // Trial Age Taxonomy
                    'trial_age' => static::taxonomyArray("Trial Age", 'trial-age'),
                    // Trial Umbrella Protocols
                    'trial_umbrella' => static::taxonomyArray("Umbrella Protocol", 'trial-umbrella'),
                ]
            ),
            ["trials"]
        );

        /**
         * Register Locations Taxonomies
         */
        self::loopTaxonomy(
            collect(
                [
                    // Location NCTID Taxonomy
                    'location_nctid' => static::taxonomyArray("NCTID", 'location-nctid'),
                    // Location Status Taxonomy
                    'location_status' => static::taxonomyArray("Status", 'location-status'),
                ]
            ),
            ['locations'],
        );

        /**
         * Register the Search Keywords for Programs and Trials if they exist
         */
        self::loopTaxonomy(
            collect(
                [
                    'search_keywords' => static::taxonomyArray(
                        "Search Keyword",
                        "",
                        false,
                    )
                ]
            ),
            ['trials', 'programs']
        );

        /**
         * Register Trials and Locations taxonomies
         */
        self::loopTaxonomy(
            collect(
                [
                    // Trial Language Taxonomy
                    'trial_language' => static::taxonomyArray("Language", 'trial-language'),
                ]
            ),
            ['trials', 'locations']
        );

        self::loopTaxonomy(
            collect(
                [
                    'custom_trial_publication_status' => static::taxonomyArray(
                        'Custom Trial Publication Status',
                        'custom-trial-publication-status',
                        false,
                    )
                ]
            )
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
    :void
    {
        $tax_collection
            ->each(fn ($tax_args, $taxonomy) => register_taxonomy($taxonomy, $post_types, $tax_args));
    }
}
