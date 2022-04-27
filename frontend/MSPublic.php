<?php

declare(strict_types = 1);

namespace Merck_Scraper\Frontend;

/**
 * The frontend-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the frontend-facing stylesheet and JavaScript.
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/frontend
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSPublic
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
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->pluginName = $plugin_name;
        $this->version    = $version;
    }

    /**
     * Register the stylesheets for the frontend-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueStyles()
    {
        wp_enqueue_style(
            $this->pluginName,
            plugin_dir_url(__FILE__) . 'dist/merck-scraper-frontend.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the frontend-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueScripts()
    {
        wp_enqueue_script(
            $this->pluginName,
            plugin_dir_url(__FILE__) . 'dist/merck-scraper-frontend.js',
            [],
            $this->version,
            false
        );
    }
}
