<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, registers the options page
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSAdmin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $pluginName The ID of this plugin.
     */
    private string $pluginName;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private string $version;

    /**
     * An array of screens that things should be added to
     *
     * @var string[]
     */
    private array $screens = [];

    /**
     * An array of screens that things should be added to
     *
     * @var string[]
     */
    private array $optsScreens = [];

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version     The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->pluginName = $plugin_name;
        $this->version    = $version;

        $this->screens = ['trials',];

        $this->optsScreens = [
            'toplevel_page_merck-scraper',
            'merck-scraper_page_merck-api-scraper',
            'merck-scraper_page_merck-logs-scraper',
        ];
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    {
        if (is_admin() && (in_array(get_current_screen()->id, $this->screens) || in_array(get_current_screen()->id, $this->optsScreens))) {
            wp_enqueue_style($this->pluginName, plugin_dir_url(__FILE__) . 'dist/merck-scraper-admin.css', [], $this->version, 'all');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    {
        $current_screen  = get_current_screen()->id;
        $api_path        = "merck-scraper/v1";
        $js_script_name  = "{$this->pluginName}-js";
        $vue_script_name = "{$this->pluginName}-vue";

        if (is_admin() && in_array($current_screen, $this->screens)) {
            wp_enqueue_script($js_script_name, plugin_dir_url(__FILE__) . "dist/merck-scraper-admin.js", [], $this->version, true);
        }

        if (is_admin() && in_array($current_screen, $this->optsScreens)) {
            wp_enqueue_script($vue_script_name, plugin_dir_url(__FILE__) . 'dist/merck-scraper-vue.js', [], $this->version, true);
        }

        /**
         * Merck API Localized Args
         */
        if (is_admin() && $current_screen === 'merck-scraper_page_merck-api-scraper') {
            wp_localize_script(
                $vue_script_name,
                'MERCK_API',
                [
                    'apiUrl'      => rest_url("{$api_path}/api-scraper"),
                    'apiSingle'   => rest_url("{$api_path}/api-scraper"),
                    'apiPosition' => rest_url("{$api_path}/api-position"),
                    'apiClearPosition' => rest_url("{$api_path}/api-clear-position"),
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
                    'apiLog'        => rest_url("{$api_path}/api-log"),
                    'apiGetLogDirs' => rest_url("{$api_path}/api-directories"),
                    'apiGetLogUrl'  => rest_url("{$api_path}/api-get-log-file"),
                    'apiDeleteFile' => rest_url("{$api_path}/api-delete-file"),
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
    {
        // list of field groups that should be saved to merck-scraper/admin/acf-json
        $groups = [
            'group_60fae8b82087d', // Trails Single Post
            'group_610964a2e214b', // Trial Clone Fields
            'group_60fed83c786ed', // Merck Settings
        ];

        if (in_array($group['key'], $groups)) {
            add_filter('acf/settings/save_json', function () {
                return dirname(__FILE__) . '/acf-json';
            });
        }
    }

    /**
     * A filter to allow ACF to load json field groups from within the plugin
     *
     * @param array $paths The json file paths
     *
     * @return mixed
     */
    public function loadACFJson(array $paths)
    {
        $paths[] = dirname(__FILE__) . '/acf-json';
        return $paths;
    }
}
