<?php

declare(strict_types = 1);

namespace Merck_Scraper\Admin;

use Merck_Scraper\Admin\Traits\MSAdminTrait;

/**
 * Registers the custom post type for the plugin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSCustomPT
{

    use MSAdminTrait;

    /**
     * Registers all necessary post types
     * @link WordPressDasicons https://developer.wordpress.org/resource/dashicons/
     */
    public function registerPostType()
    {
        collect(
            [
                'trials' => $this->postTypeArray(
                    'Trial',
                    'dashicons-analytics',
                    __("This post type is used to store trials scraped from the govt api.", 'merck-scraper'),
                    'post',
                    [
                        'rewrite' => [
                            'slug'       => 'trial',
                            'with_front' => true,
                            'pages'      => true,
                            'feeds'      => true,
                        ],
                    ]
                ),
                'locations' => $this->postTypeArray(
                    'Location',
                    'dashicons-admin-site',
                    __("This post type is used to store trial locations scraped from the govt api.", 'merck-scraper'),
                    'post',
                    [
                        'rewrite' => [
                            'slug'       => 'location',
                            'with_front' => false,
                            'pages'      => false,
                            'feeds'      => false,
                        ],
                        'supports' => ['title', 'editor', 'revisions']
                    ],
                ),
            ]
        )
            ->each(function ($pt_args, $post_type) {
                register_post_type($post_type, $pt_args);
            });
    }
}
