<?php

declare(strict_types = 1);

namespace Merck_Scraper\Includes;

use Merck_Scraper\Admin\MSAdmin;
use Merck_Scraper\Admin\MSApiLogger;
use Merck_Scraper\Admin\MSApiScraper;
use Merck_Scraper\Admin\MSCustomPostStatus;
use Merck_Scraper\Admin\MSCustomPT;
use Merck_Scraper\Admin\MSCustomTax;
use Merck_Scraper\Admin\MSLocationMetaBox;
use Merck_Scraper\Admin\MSOptionsPage;
use Merck_Scraper\Frontend\MSFrontEndAPI;
use Merck_Scraper\Frontend\MSPublic;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, Admin-specific hooks, and
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
    protected MSLoader $loader;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the Admin area and
     * the frontend-facing side of the site.
     *
     * @param  string  $pluginName  The current name of the plugin.
     * @param  string  $version     The current version of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct(protected string $pluginName = 'merck-scraper', protected string $version = MERCK_SCRAPER_VERSION)
    {
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
     * - Merck_Scraper_Admin. Defines all hooks for the Admin area.
     * - Merck_Scraper_Public. Defines all hooks for the frontend side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function loadDependencies()
    :void
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
    :void
    {
        $plugin_i18n = new MSI18n();

        $this->loader->addAction('plugins_loaded', $plugin_i18n, 'loadMSTextdomain');
    }

    /**
     * Register all the hooks related to the Admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function adminHooks()
    :void
    {
        $plugin_admin = new MSAdmin($this->pluginName, $this->version);

        $this->loader->addAction('admin_enqueue_scripts', $plugin_admin, 'enqueueStyles');
        $this->loader->addAction('admin_enqueue_scripts', $plugin_admin, 'enqueueScripts');
        $this->loader->addAction('manage_trials_posts_columns', $plugin_admin, 'addColumns');
        $this->loader->addAction(
            'manage_trials_posts_custom_column',
            $plugin_admin,
            'showCustomCol',
            10,
            2
        );
        $this->loader->addFilter('cron_schedules', $plugin_admin, 'customSchedule');


        /**
         * Filters and Actions to expand the Admin Columns for the Trials Post type,
         * including filters and searching for custom ACF field
         */
        $this->loader->addAction('pre_get_posts', $plugin_admin, 'trialsAdminQuery');
        $this->loader->addAction('before_delete_post', $plugin_admin, 'removeTrialLocations', 99, 2);
        $this->loader->addFilter('manage_edit-trials_sortable_columns', $plugin_admin, 'filterCustomCol');
        $this->loader->addFilter('posts_join', $plugin_admin, 'trialsAdminJoin');
        $this->loader->addFilter('posts_where', $plugin_admin, 'trialsAdminWhere');
        $this->loader->addFilter('posts_distinct', $plugin_admin, 'trialsAdminDistinct');

        // ACF JSON related
        $this->loader->addAction('acf/update_field_group', $plugin_admin, 'saveACFJson', 1, 1);
        $this->loader->addFilter('acf/settings/load_json', $plugin_admin, 'loadACFJson');

        $admin_options = new MSOptionsPage();
        // Options Page
        $this->loader->addAction('acf/init', $admin_options, 'acfOptionsPage');
        $this->loader->addAction('admin_menu', $admin_options, 'customOptsPage', 105);
        $this->loader->addAction('admin_init', $admin_options, 'settingsInit');
        $this->loader->addFilter('acf/load_field/key=field_63cff7fab5aca', $admin_options, 'customPostStatusOptions');

        // Registers the Logger API
        $logger_api = new MSApiLogger();
        $this->loader->addAction('rest_api_init', $logger_api, 'registerEndpoint');

        // Register the custom post types
        $admin_cpt = new MSCustomPT();

        $this->loader->addAction('init', $admin_cpt, 'registerPostType');

        // Register the custom taxonomies
        $admin_taxonomy = new MSCustomTax();

        $this->loader->addAction('init', $admin_taxonomy, 'registerTaxonomy');

        // Registers the Scraper API
        $scraper_api = new MSApiScraper();
        $this->loader->addAction('rest_api_init', $scraper_api, 'registerEndpoint');
        // $this->loader->addAction('init', $scraper_api, 'registerCronType');

        $location_meta_box = new MSLocationMetaBox;
        $this->loader->addAction('add_meta_boxes', $location_meta_box, 'addMetaBoxes');
        // $this->loader->addAction('save_post', $location_meta_box, 'savePost');

        /**
         * Custom post Status
         */
        $plugin_post_status = new MSCustomPostStatus($this->pluginName, $this->version);
        $this->loader->addAction('init', $plugin_post_status, 'registerPostStatus');
        $this->loader->addAction('admin_init', $plugin_post_status, 'overrideAdminPostListInit');
        $this->loader->addAction('admin_init', $plugin_post_status, 'adminRedirects');
        $this->loader->addAction('admin_footer-post.php', $plugin_post_status, 'appendPostStatusList');
        $this->loader->addAction('admin_footer-post-new.php', $plugin_post_status, 'appendPostStatusList');
        $this->loader->addAction('admin_footer-edit.php', $plugin_post_status, 'appendPostStatusListQuickedit');
        $this->loader->addAction('admin_print_footer_scripts', $plugin_post_status, 'changePublishButtonGutenberg');
        $this->loader->addAction('display_post_states', $plugin_post_status, 'appendPostStatusPostOverview');
        $this->loader->addAction('custom_trial_publication_status_add_form_fields', $plugin_post_status, 'statusTaxonomyCustomFields', 10, 2);
        $this->loader->addAction('created_custom_trial_publication_status', $plugin_post_status, 'saveStatusTaxonomyCustomFields', 10, 2);
        $this->loader->addAction('custom_trial_publication_status_edit_form_fields', $plugin_post_status, 'statusTaxonomyCustomFields', 10, 2);
        $this->loader->addAction('delete_term_taxonomy', $plugin_post_status, 'deletedPostStatusTerm', 10, 1);
        $this->loader->addAction('edited_custom_trial_publication_status', $plugin_post_status, 'saveStatusTaxonomyCustomFields', 10, 2);
        $this->loader->addAction('manage_edit-custom_trial_publication_status_columns', $plugin_post_status, 'editStatusTaxonomyColumns');
        $this->loader->addAction('add_meta_boxes', $plugin_post_status, 'addStatusMetabox');
        $this->loader->addAction('enqueue_block_editor_assets', $plugin_post_status, 'removePublishingSidebarGutenberg');

        $this->loader->addFilter('parentFile', $plugin_post_status, 'parentFile');
        $this->loader->addFilter('submenuFile', $plugin_post_status, 'submenuFile');
        $this->loader->addFilter('wp_update_term_data', $plugin_post_status, 'overrideStatusTaxonomyOnSave', 10, 4);
        $this->loader->addFilter('manage_custom_trial_publication_status_custom_column', $plugin_post_status, 'addStatusTaxonomyColumnsContent', 10, 3);
        $this->loader->addFilter('wpInsertPostData', $plugin_post_status, 'wpInsertPostData', 99, 2);
        $this->loader->addFilter('gettext', $plugin_post_status, 'gettextOverride', 10, 3);
    }

    /**
     * Register all the hooks related to the frontend-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function publicHooks()
    :void
    {
        $plugin_public = new MSPublic($this->pluginName, $this->version);

        // $this->loader->addAction('wp_enqueue_scripts', $plugin_public, 'enqueueStyles');
        // $this->loader->addAction('wp_enqueue_scripts', $plugin_public, 'enqueueScripts');

        $public_api = new MSFrontEndAPI();
        $this->loader->addRestRoute($public_api, 'registerEndpoint');
    }

    /**
     * Run the loader to execute all the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function executePlugin()
    :void
    {
        $this->loader->executeUtility();
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    MSLoader    Orchestrates the hooks of the plugin.
     * @since     1.0.0
     */
    public function getLoader()
    :MSLoader
    {
        return $this->loader;
    }
}
