<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

use Merck_Scraper\Traits\MSAdminTrait;

/**
 * Registers the custom post type for the plugin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSCustomPT
{

    use MSAdminTrait;

    /**
     * Registers all necessary post types
     */
    public function registerPostType()
    {
        $post_args = [];

        // CPT Trials
        $post_args["trials"] = self::postTypeArray(
            'Trial',
            'dashicons-analytics',
            __("This post type is used to store trials scraped from the gov't api.", 'merck-scraper'),
            'post',
            [
                'rewrite' => [
                    'slug'       => 'trial',
                    'with_front' => true,
                    'pages'      => true,
                    'feeds'      => true,
                ],
            ]
        );

        foreach ($post_args as $post_type => $pt_args) {
            register_post_type($post_type, $pt_args);
        }
    }
}
