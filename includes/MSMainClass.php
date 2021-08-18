<?php

declare(strict_types = 1);

namespace Merck_Scraper\includes;

use Merck_Scraper\admin\MSAdmin;
use Merck_Scraper\admin\MSApiLogger;
use Merck_Scraper\admin\MSAPIScraper;
use Merck_Scraper\admin\MSCustomPT;
use Merck_Scraper\admin\MSCustomTax;
use Merck_Scraper\admin\MSOptionsPage;
use Merck_Scraper\frontend\MSPublic;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * frontend-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/includes
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSMainClass
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      MSLoader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $pluginName The string used to uniquely identify this plugin.
     */
    protected $pluginName;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the frontend-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('MERCK_SCRAPER_VERSION')) {
            $this->version = MERCK_SCRAPER_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->pluginName = 'merck-scraper';

        $this->loadDependencies();
        $this->setLocale();
        $this->adminHooks();
        $this->publicHooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Merck_Scraper_Loader. Orchestrates the hooks of the plugin.
     * - Merck_Scraper_i18n. Defines internationalization functionality.
     * - Merck_Scraper_Admin. Defines all hooks for the admin area.
     * - Merck_Scraper_Public. Defines all hooks for the frontend side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function loadDependencies()
    {
        $this->loader = new MSLoader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Merck_Scraper_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function setLocale()
    {
        $plugin_i18n = new MSI18n();

        $this->loader->addAction('plugins_loaded', $plugin_i18n, 'loadMSTextdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function adminHooks()
    {
        $plugin_admin = new MSAdmin($this->getPluginName(), $this->getversion());

        $this->loader->addAction('admin_enqueue_scripts', $plugin_admin, 'enqueueStyles');
        $this->loader->addAction('admin_enqueue_scripts', $plugin_admin, 'enqueueScripts');

        // ACF JSON related
        $this->loader->addAction('acf/update_field_group', $plugin_admin, 'saveACFJson', 1, 1);
        $this->loader->addFilter('acf/settings/load_json', $plugin_admin, 'loadACFJson');

        $admin_options = new MSOptionsPage();
        // Options Page
        $this->loader->addAction('acf/init', $admin_options, 'acfOptionsPage');
        $this->loader->addAction('admin_menu', $admin_options, 'customOptsPage', 105);

        // Registers the Scraper API
        $scraper_api = new MSAPIScraper();
        $this->loader->addAction('rest_api_init', $scraper_api, 'registerEndpoint');

        // Registers the Logger API
        $logger_api = new MSApiLogger();
        $this->loader->addAction('rest_api_init', $logger_api, 'registerEndpoint');

        // Register the custom post types
        $admin_cpt = new MSCustomPT();

        $this->loader->addAction('init', $admin_cpt, 'registerPostType');

        // Register the custom taxonomies
        $admin_taxonomy = new MSCustomTax();

        $this->loader->addAction('init', $admin_taxonomy, 'registerTaxonomy');
    }

    /**
     * Register all of the hooks related to the frontend-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function publicHooks()
    {
        $plugin_public = new MSPublic($this->getPluginName(), $this->getversion());

        $this->loader->addAction('wp_enqueue_scripts', $plugin_public, 'enqueueStyles');
        $this->loader->addAction('wp_enqueue_scripts', $plugin_public, 'enqueueScripts');

        // $public_api = new MSAPI();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function executePlugin()
    {
        $this->loader->executeUtility();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     * @since     1.0.0
     */
    public function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    MSLoader    Orchestrates the hooks of the plugin.
     * @since     1.0.0
     */
    public function getLoader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     * @since     1.0.0
     */
    public function getversion()
    {
        return $this->version;
    }
}
