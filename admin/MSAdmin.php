<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin;

use Illuminate\Support\Str;
use Merck_Scraper\Admin\Traits\MSAdminTrait;
use WP_Post;

/**
 * The Admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, registers the options page
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSAdmin
{

    use MSAdminTrait;

    /**
     * An array of screens that things should be added to
     *
     * @var string[]
     */
    private array $screens = ['trials', 'locations',];

    /**
     * An array of screens that things should be added to
     *
     * @var string[]
     */
    private array $optsScreens = [
        'toplevel_page_merck-scraper',
        'merck-scraper_page_merck-api-scraper',
        'merck-scraper_page_merck-logs-scraper',
    ];

    /**
     * Initialize the class and set its properties.
     *
     * @param string $pluginName The ID of this plugin.
     * @param string $version     The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct(private string $pluginName, private string $version)
    {
    }

    /**
     * Register the stylesheets for the Admin area.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    :void
    {
        if (is_admin() && (in_array(get_current_screen()->id, $this->screens) || in_array(get_current_screen()->id, $this->optsScreens))) {
            wp_enqueue_style(
                $this->pluginName,
                plugin_dir_url(__FILE__) . 'dist/merck-scraper-admin.css',
                [],
                $this->version
            );
        }
    }

    /**
     * Register the JavaScript for the Admin area.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    :void
    {
        $current_screen  = get_current_screen()->id;
        $api_path        = "merck-scraper/v1";
        $js_script_name  = "$this->pluginName-js";
        $vue_script_name = "$this->pluginName-vue";

        if (is_admin() && in_array($current_screen, $this->screens)) {
            wp_enqueue_script(
                $js_script_name,
                plugin_dir_url(__FILE__) . "dist/merck-scraper-admin.js",
                [],
                $this->version,
                true
            );
        }

        if (is_admin() && in_array($current_screen, $this->optsScreens) || in_array($current_screen, $this->screens)) {
            wp_enqueue_script(
                $vue_script_name,
                plugin_dir_url(__FILE__) . 'dist/merck-scraper-vue.js',
                [],
                $this->version,
                true
            );
        }

        /**
         * Merck API Localized Args
         */
        if (is_admin() && $current_screen === 'merck-scraper_page_merck-api-scraper') {
            wp_localize_script(
                $vue_script_name,
                'MERCK_API',
                [
                    'apiClearPosition' => rest_url("$api_path/api-clear-position"),
                    'apiLocationsUrl'  => rest_url("$api_path/get-trial-locations"),
                    'apiSingle'        => rest_url("$api_path/api-scraper"),
                    'apiPosition'      => rest_url("$api_path/api-position"),
                    'apiUrl'           => rest_url("$api_path/api-scraper"),
                ]
            );
        }

        /**
         * Merck Log Localized Args
         */
        if (is_admin() && $current_screen === 'merck-scraper_page_merck-logs-scraper') {
            wp_localize_script(
                $vue_script_name,
                'MERCK_LOG',
                [
                    'apiDeleteFile' => rest_url("$api_path/api-delete-file"),
                    'apiGetLogDirs' => rest_url("$api_path/api-directories"),
                    'apiGetLogUrl'  => rest_url("$api_path/api-get-log-file"),
                    'apiLog'        => rest_url("$api_path/api-log"),
                ]
            );
        }

        if (is_admin() && $current_screen === 'locations') {
            global $post;
            wp_localize_script(
                $vue_script_name,
                'MERCK_GEO',
                [
                    'apiUrl' => rest_url("$api_path/geo-locate"),
                    'getText' => __('Get Location', 'merck-scraper'),
                    'id' => $post->ID ?? 0,
                ]
            );
        }
    }

    /**
     * Defining specific group field ID's to the $groups array will make sure that those field groups
     * are saved within the plugin. This should only be for fields that are used in the plugin itself.
     *
     * @param array $group
     */
    public function saveACFJson(array $group)
    :void
    {
        // list of field groups that should be saved to merck-scraper/Admin/acf-json
        $groups = [
            'group_60fae8b82087d', // Trails Single Post
            'group_60fed83c786ed', // Merck Settings
            'group_618e88f57b867', // Trial Ages
            'group_6220de6da8144', // Location Single Post
            'group_63b8738bbcc0e', // Trial Notes
            'group_6411f08069922', // Trial Content Override
        ];

        if (in_array($group['key'], $groups)) {
            add_filter('acf/settings/save_json', fn () => dirname(__FILE__) . '/acf-json');
        }
    }

    /**
     * A filter to allow ACF to load json field groups from within the plugin
     *
     * @param array $paths The json file paths
     *
     * @return array
     */
    public function loadACFJson(array $paths)
    :array
    {
        $paths[] = dirname(__FILE__) . '/acf-json';
        return $paths;
    }

    /**
     * Sets up and registers custom cron schedules
     *
     * @param array $schedules An array of current schedules
     *
     * @return array
     */
    public function customSchedule(array $schedules)
    :array
    {
        return collect($schedules)
            ->put(
                'thursdays',
                [
                    'interval' => 604800,
                    'display'  => __("Thursday Once Weekly", 'merck-scraper'),
                ]
            )
            ->put(
                'two_months',
                [
                    'interval' => 5260000,
                    'display' => __('Every 2 months', 'merck-scraper'),
                ]
            )
            ->toArray();
    }

    /**
     * Adds custom columns to the trials post type
     *
     * @param array $columns An array of the existing registered columns
     *
     * @return array
     */
    public function addColumnsTrials(array $columns)
    :array
    {
        // Save the $columns['date'] field
        $post_date = $columns['date'];
        unset($columns['date']);

        // Add any custom columns here before the `date` field
        $custom_columns = [
            'nct_id' => __("NCT ID", 'merck-scraper'),
            'notes'  => __('Trial Notes', 'merck-scraper'),
            'date'   => $post_date,
        ];

        return array_merge(
            $columns,
            $custom_columns
        );
    }

    /**
     * Displays the data for the custom field defined in addAcfColumns for Trials
     *
     * @param string $column_key The column key name
     * @param int    $post_id    The post_id
     */
    public function showCustomTrialsColumn(string $column_key, int $post_id)
    :void
    {
        if ($column_key === 'nct_id') {
            printf(
                '<span>%s</span>',
                get_field('api_data_nct_id', $post_id) ?: '-',
            );
        }

        $trial_notes = get_field('trial_notes', $post_id);

        if (is_array($trial_notes)) {
            $trial_notes = collect($trial_notes)
                ->filter()
                ->implode('');
        }

        if ($column_key === 'notes') {
            printf(
                '<span>%s</span>',
                $trial_notes ?: 'No notes created yet',
            );
        }
    }

    /**
     * Sets up the custom columns to be filterable for Trials
     *
     * @param array $columns An array of registered Admin columns
     *
     * @return array
     */
    public function filterTrialsColumn(array $columns)
    :array
    {
        $columns['nct_id'] = 'nct_id';
        return $columns;
    }

    /**
     * Adds custom columns to the locations post type
     *
     * @param array $columns An array of the existing registered columns
     *
     * @return array
     */
    public function addColumnsLocations(array $columns)
    :array
    {
        // Save the $columns['date'] field
        $post_date = $columns['date'];
        unset($columns['date']);

        // Add any custom columns here before the `date` field
        $custom_columns = [
            'country' => __("Country", 'merck-scraper'),
            'date'   => $post_date,
        ];

        return array_merge(
            $columns,
            $custom_columns
        );
    }

    /**
     * Displays the data for the custom field defined in addAcfColumns for Locations
     *
     * @param string $column_key The column key name
     * @param int    $post_id    The post_id
     */
    public function showCustomLocationsColumn(string $column_key, int $post_id)
    :void
    {
        if ($column_key === 'country') {
            printf(
                '<span>%s</span>',
                get_field('api_data_country', $post_id) ?: '-',
            );
        }
    }

    /**
     * Sets up the custom columns to be filterable for Locations
     *
     * @param array $columns An array of registered Admin columns
     *
     * @return array
     */
    public function filterLocationsColumn(array $columns)
    :array
    {
        $columns['country'] = 'country';
        return $columns;
    }

    /**
     * Currently allows us to filter any custom columns appended to the $query
     *
     * @param $query
     */
    public function trialsAdminQuery($query)
    :void
    {
        if ($query->get('post_type') === 'trials' && is_admin()) {
            if (!$query->is_main_query()) {
                return;
            }

            if ('nct_id' === $query->get('orderby')) {
                $query->set('orderby', 'meta_value');
                $query->set('meta_key', 'api_data_nct_id');
            }
        }

        if ($query->get('post_type') === 'locations' && is_admin()) {
            if (!$query->is_main_query()) {
                return;
            }

            if ('country' === $query->get('orderby')) {
                $query->set('orderby', 'meta_value');
                $query->set('meta_key', 'api_data_country');
            }
        }
    }

    /**
     * This adjusts the join query for the Trials Admin
     * Search capability to look for the post meta table
     *
     * @param string $join The join clause
     *
     * @return string
     */
    public function trialsAdminJoin(string $join)
    :string
    {
        global $wpdb;

        if ($this->isTrialsAdmin()) {
            $join .= " LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id";
        }

        return $join;
    }

    /**
     * This is the where query for the Trials Admin Search capability for the api_data_nct_id
     *
     * @param string $where The where clause
     *
     * @return null|array|string|string[]
     */
    public function trialsAdminWhere(string $where)
    :array|string|null
    {
        global $wpdb;
        if ($this->isTrialsAdmin()) {
            if (get_current_screen()->id === 'edit-locations') {
                $where = preg_replace(
                    "/\(\s*$wpdb->posts.post_title\s+LIKE\s*('[^']+')\s*\)/",
                    "($wpdb->posts.post_title LIKE $1) OR ($wpdb->postmeta.meta_key = 'api_data_country' AND $wpdb->postmeta.meta_value LIKE $1)",
                    $where
                );
            } else {
                /**
                 * Extend the post_title search to search the api_data_nct_id
                 */
                $where = preg_replace(
                    "/\(\s*$wpdb->posts.post_title\s+LIKE\s*('[^']+')\s*\)/",
                    "($wpdb->posts.post_title LIKE $1) OR ($wpdb->postmeta.meta_key = 'api_data_nct_id' AND $wpdb->postmeta.meta_value LIKE $1)",
                    $where
                );
            }

            /**
             * Remove searching for the post_content
             */
            $where = preg_replace(
                "/OR\s+\(\s*$wpdb->posts.post_content\s+LIKE\s*('[^']+')\s*\)/",
                "",
                $where
            );
        }

        return $where;
    }

    /**
     * This is setting the $where distinct clause
     *
     * @param string $where
     *
     * @return string
     */
    public function trialsAdminDistinct(string $where)
    :string
    {
        if ($this->isTrialsAdmin()) {
            return "DISTINCT";
        }
        return $where;
    }

    /**
     * Hook to either remove a location if it is attached to only one location, or
     * delete the NCT ID term from that location.
     *
     * @param  int|string  $post_id  The post ID
     * @param WP_Post      $post     The WP_Post Object
     *
     * @return void
     */
    public function removeTrialLocations(int|string $post_id, WP_Post $post)
    :void
    {
        if ('trials' === $post->post_type) {
            $location_ids = get_field('api_data_location_ids', $post_id);
            $nct_id       = get_field('api_data_nct_id', $post_id);
            if ($location_ids) {
                collect(
                    explode(';', $location_ids)
                )
                    ->each(function ($post) use ($nct_id) {
                        $the_post = get_post($post);
                        $post_id  = $the_post->ID ?? 0;
                        if (!is_wp_error($the_post) && $post_id > 0) {
                            $terms = collect(wp_get_post_terms($post_id, 'location_nctid'));

                            /**
                             * If the location has more than one NCT ID's attached to it,
                             * remove that NCT ID from that location, otherwise it's safe
                             * to delete the location entirely.
                             */
                            if ($terms->count() > 1) {
                                $terms
                                    ->filter(fn ($term) =>  $term->name === $nct_id)
                                    ->each(function ($term) use ($post_id) {
                                        if ($term->term_id ?? false) {
                                            wp_remove_object_terms($post_id, $term->term_id, 'location_nctid');
                                        }
                                    });
                            } else {
                                wp_delete_post($post_id, true);
                            }
                        }
                    });

                // Grab the NCT ID term from the locations and delete it
                $term = get_term_by('name', $nct_id, 'location_nctid');
                if (!is_wp_error($term) && $term->term_id > 0) {
                    wp_delete_term($term->term_id, 'location_nctid');
                }
            }
        }
    }

    /**
     * Checks whether we're on the Admin edit-trials archive page
     *
     * @return bool
     */
    protected function isTrialsAdmin(): bool
    {
        if (is_admin() && function_exists('get_current_screen')) {
            $current_screen   = get_current_screen();
            $post_edit_screen = ['edit-trials', 'edit-locations'];
            return is_object($current_screen) && in_array($current_screen->id, $post_edit_screen) && is_search();
        }
        return false;
    }
}
