<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use Illuminate\Support\Str;

/**
 * Traits for the MS Admin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSAdminTrait
{
    /**
     * Creates the array needed to register the post type
     *
     * @param string $singular    The single name for the post type
     * @param string $plural      The plural name for the post type
     * @param string $dashicon    The dashicon name for the post type, an image/svg or url
     * @param string $description The description of the post type
     * @param string $type        Determines whether it's a post or a page style hierarchy
     *
     * @link Dashicon https://developer.wordpress.org/resource/dashicons
     *
     * @return array
     */
    protected function postTypeArray(string $singular, string $dashicon, string $description = '', string $type = "post", array $editor_args = [])
    :array
    {
        $default_args = ["title", "editor", "thumbnail", "excerpt", "revisions", "post-formats"];

        $plural = Str::plural($singular);
        $single_lower = strtolower($singular);

        return [
            "label"               => __($plural, "merck-scraper"),
            "description"         => __($description, "merck-scraper"),
            "labels"              => [
                "name"                  => _x($singular, "Post Type General Name", "merck-scraper"),
                "singular_name"         => _x($singular, "Post Type Singular Name", "merck-scraper"),
                "menu_name"             => __($plural, "merck-scraper"),
                "name_admin_bar"        => __($singular, "merck-scraper"),
                "archives"              => __($plural, "merck-scraper"),
                "attributes"            => __("$singular Attributes", "merck-scraper"),
                "parent_item_colon"     => __("Parent $singular:", "merck-scraper"),
                "all_items"             => __("All {$plural}", "merck-scraper"),
                "add_new_item"          => __("Add New $singular", "merck-scraper"),
                "add_new"               => __("Add New $singular", "merck-scraper"),
                "new_item"              => __("New $singular", "merck-scraper"),
                "edit_item"             => __("Edit $singular", "merck-scraper"),
                "update_item"           => __("Update $singular", "merck-scraper"),
                "view_item"             => __("View $singular", "merck-scraper"),
                "view_items"            => __("View $plural", "merck-scraper"),
                "search_items"          => __("Search $plural", "merck-scraper"),
                "not_found"             => __("Not found", "merck-scraper"),
                "not_found_in_trash"    => __("Not found in Trash", "merck-scraper"),
                "featured_image"        => __("$singular Image", "merck-scraper"),
                "set_featured_image"    => __("Set $single_lower image", "merck-scraper"),
                "remove_featured_image" => __("Remove $single_lower image", "merck-scraper"),
                "use_featured_image"    => __("Use as $single_lower image", "merck-scraper"),
                "insert_into_item"      => __("Place into $single_lower", "merck-scraper"),
                "uploaded_to_this_item" => __("Uploaded to this $singular", "merck-scraper"),
                "items_list"            => __("$singular list", "merck-scraper"),
                "items_list_navigation" => __("$singular list navigation", "merck-scraper"),
                "filter_items_list"     => __("Filter $singular list", "merck-scraper"),
            ],
            "supports"            => $editor_args['supports'] ?? $default_args,
            "hierarchical"        => false,
            "public"              => true,
            "show_ui"             => true,
            "show_in_menu"        => true,
            "menu_position"       => 25,
            "menu_icon"           => $dashicon,
            "show_in_admin_bar"   => true,
            "show_in_nav_menus"   => false,
            "can_export"          => true,
            "has_archive"         => false,
            "exclude_from_search" => false,
            "publicly_queryable"  => true,
            "capability_type"     => $type,
            'rewrite'             => $editor_args['rewrite'] ?? true,
            "show_in_rest"        => false,
        ];
    }

    /**
     * Creates an array return for registering a taxonomy
     *
     * @param string      $singular     The taxonomy singular name
     * @param string      $plural       The taxonomy plural name
     * @param null|string $tax_slug     The taxonomy archive slug
     * @param bool        $hierarchical Whether this mimics a category or a tag
     */
    protected function taxonomyArray(string $singular, string $plural, string $tax_slug = null, bool $hierarchical = true)
    :array
    {
        $single_lower = strtolower($singular);
        $plural_lower = strtolower($plural);
        return [
            "labels"            => [
                "name"                       => _x($singular, "Taxonomy General Name", "merck-scraper"),
                "singular_name"              => _x($singular, "Taxonomy Singular Name", "merck-scraper"),
                "menu_name"                  => __($plural, "merck-scraper"),
                "all_items"                  => __("All $plural", "merck-scraper"),
                "parent_item"                => __("Parent $singular", "merck-scraper"),
                "parent_item_colon"          => __("Parent $singular:", "merck-scraper"),
                "new_item_name"              => __("New $singular", "merck-scraper"),
                "add_new_item"               => __("Add $singular", "merck-scraper"),
                "edit_item"                  => __("Edit $singular", "merck-scraper"),
                "update_item"                => __("Update $singular", "merck-scraper"),
                "view_item"                  => __("View $singular", "merck-scraper"),
                "separate_items_with_commas" => __("Separate $single_lower with commas", "merck-scraper"),
                "add_or_remove_items"        => __("Add or remove $plural_lower", "merck-scraper"),
                "choose_from_most_used"      => __("Choose from the most used", "merck-scraper"),
                "popular_items"              => __("Popular $plural", "merck-scraper"),
                "search_items"               => __("Search $plural", "merck-scraper"),
                "not_found"                  => __("Not Found", "merck-scraper"),
                "no_terms"                   => __("No $plural_lower", "merck-scraper"),
                "items_list"                 => __("$plural list", "merck-scraper"),
                "items_list_navigation"      => __("$plural list navigation", "merck-scraper"),
            ],
            "hierarchical"      => $hierarchical,
            "public"            => true,
            "show_ui"           => true,
            "show_admin_column" => true,
            "show_in_nav_menus" => false,
            "show_tagcloud"     => false,
            "rewrite"           => [
                "slug"         => $tax_slug,
                "with_front"   => false,
                "hierarchical" => false,
            ],
            "show_in_rest"      => false,
        ];
    }
}
