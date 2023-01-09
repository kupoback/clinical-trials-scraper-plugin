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
        self::loopTaxonomy(
            collect(
                [
                    // Trial Category Taxonomy
                    'trial_ta' => self::taxonomyArray("Therapeutic Area", "Therapeutic Area", 'trial-ta'),
                    // Study Keyword Taxonomy
                    'study_keyword' => self::taxonomyArray("Study Keyword", "Study Keywords", 'study-keyword'),
                    // Trial Studies Taxonomy
                    'conditions' => self::taxonomyArray("Condition", "Conditions", 'condition'),
                    // Trial Status Taxonomy
                    'trial_status' => self::taxonomyArray("Trial Status", "Trial Status", 'trial-status'),
                    // Trial Drugs Taxonomy
                    'trial_drugs' => self::taxonomyArray("Trial Drug", "Trial Drugs", 'trial-drugs', false),
                    // Trial Age Taxonomy
                    'trial_age' => self::taxonomyArray("Trial Age", "Trial Ages", 'trial-age'),
                    // Trial Umbrella Protocols
                    'trial_umbrella' => self::taxonomyArray("Umbrella Protocol", "Umbrella Protocols", 'trial-umbrella'),
                ]
            ),
            ["trials"]
        );

        self::loopTaxonomy(
            collect(
                [
                    // Location NCTID Taxonomy
                    'location_nctid' => self::taxonomyArray("NCTID", "NCTIDs", 'location-nctid'),
                    // Location Status Taxonomy
                    'location_status' => self::taxonomyArray("Status", "Status", 'location-status'),
                ]
            ),
            ['locations'],
        );

        self::loopTaxonomy(
            collect(
                [
                    // Trial Language Taxonomy
                    'trial_language' => self::taxonomyArray("Language", "Languages", 'trial-language'),
                ]
            ),
            ['trials', 'locations']
        );

        self::loopTaxonomy(
            collect(
                [
                    'custom_trial_publication_status' => self::taxonomyArray(
                        'Custom Trial Publication Status',
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
