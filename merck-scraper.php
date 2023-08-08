<?php

declare(strict_types=1);

namespace Merck_Scraper;

use Carbon\CarbonInterface;
use Exception;
use Illuminate\Support\Carbon;
use Merck_Scraper\Admin\MSAdminLoggerDelete;
use Merck_Scraper\Admin\MSApiScraper;
use Merck_Scraper\Includes\MSMainClass;
use Merck_Scraper\Includes\MSActivator;
use Merck_Scraper\Includes\MSDeactivator;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Admin area. This file also includes all the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin. This comes coupled with WPML and used for MCT
 *
 * @link              https://cliquestudios.com
 * @since             1.0.0
 * @package           Merck_Scraper
 *
 * @wordpress-plugin
 * Plugin Name:       Merck Scrapper - WPML
 * Description:       This plugin is used to scrape data from clinicaltrials.gov website.
 * Version:           1.8.1
 * Author:            Clique Studios (Nick Makris)
 * Author URI:        https://cliquestudios.com
 * Requires at least: 6.0
 * Tested up to:      6.1.1
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       merck-scraper
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

require plugin_dir_path(__FILE__) . 'vendor/autoload.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('MERCK_SCRAPER_VERSION', '1.8.1');

/**
 * This constant is used to save the logs to a specific directory
 */
define("MERCK_SCRAPER_LOG_DIR", WP_CONTENT_DIR . '/ms-logs');

/**
 * This constant is used to save API logs to the API directory
 */
define("MERCK_SCRAPER_API_LOG_DIR", WP_CONTENT_DIR . "/ms-logs/api");

/**
 * This constant defines the location of the text files that contain the trial changes
 */
define("MERCK_SCRAPER_API_CHANGES_DIR", WP_CONTENT_DIR . "/uploads/ms-api-changes");

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-merck-scraper-activator.php
 */
register_activation_hook(__FILE__, function () {
    MSActivator::activate();
    ms_register_cron_jobs();
});

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-merck-scraper-deactivator.php
 */
register_deactivation_hook(__FILE__, function () {
    MSDeactivator::deactivate();
    ms_remove_cron_jobs();
});

/**
 * This checks if ACF and SitePress are activated, and warns the user that it's required to function properly
 */
if (!class_exists('ACF') || !class_exists('SitePress')) {
    add_action('admin_notices', function () {
        function printError($plugin_name)
        :string
        {
            return sprintf(
                '<div class="error"><h3>%s</h3>%s%s%s</div>',
                __('Error', 'merck-scraper'),
                sprintf(
                    __("The plugin %s requires $plugin_name to be installed.", 'merck-scraper'),
                    '<strong>Merck Scraper - WPML</strong>',
                ),
                sprintf(
                    '<p>%s</p>',
                    __(
                        "Please install or activate $plugin_name and try again.",
                        'merck-scraper',
                    ),
                ),
                $plugin_name === 'WordPress Multi-language' ?
                    sprintf(
                        '<p>%s</p>',
                        __("Consider using the other version of Merck Scraper plugin, which doesn't rely on WPML", 'merck-scraper'),
                    )
                    : '',
            );
        }

        if (!class_exists('ACF')) {
            printf('%s', printError('Advanced Custom Fields'));
        }
        if (!class_exists('SitePress')) {
            printf('%s', printError('WordPress Multi-language'));
        }
    }, 20);
    MSDeactivator::deactivate();
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ms()
:void
{
    if (!is_dir(MERCK_SCRAPER_LOG_DIR)) {
        mkdir(MERCK_SCRAPER_LOG_DIR);
    }

    if (!is_dir(MERCK_SCRAPER_API_CHANGES_DIR)) {
        mkdir(MERCK_SCRAPER_API_CHANGES_DIR);
    }

    $plugin = new MSMainClass();
    $plugin->executePlugin();
}

run_ms();

/**
 * Sets up the cron to delete 2 months worth of log files
 */
add_action('ms_scrape_log_cleanup', function () {
    (new MSAdminLoggerDelete())
        ->deleteTwoMonthsOfFiles();
});

/**
 * This is the cron job setup function
 */
add_action('ms_govt_scrape_cron', function () {
    $scraper_class = new MSApiScraper();

    $logger = $scraper_class->setLogger('cron-job', 'cron', MERCK_SCRAPER_LOG_DIR . '/cron');

    try {
        $scraper_class->apiImport();
    } catch (Exception $exception) {
        $logger->error(__("Error executing the cron job", 'merck-scraper'), $exception->getTrace());
    }
});

/**
 * Event to register all cron jobs
 *
 * @return void
 */
function ms_register_cron_jobs()
:void
{
    $now = Carbon::now('America/New_York');

    /**
     * If the cron job isn't scheduled to run, we'll set it up to run
     */
    if (! wp_next_scheduled('ms_govt_scrape_cron')) {
        // Grab the next day, and set it up
        wp_schedule_event(
            $now
                ->next(CarbonInterface::FRIDAY)
                ->timestamp,
            'ms_fridays',
            'ms_govt_scrape_cron',
        );
    }

    /**
     * Cron job for deleting logs from two months ago, every 2 months
     */
    if (! wp_next_scheduled('ms_scrape_log_cleanup')) {
        wp_schedule_event(
            $now
                ->addMonths(2)
                ->startOfMonth()
                ->timestamp,
            'ms_two_months',
            'ms_scrape_log_cleanup',
        );
    }
}

/**
 * Removes the two set cron jobs
 *
 * @return void
 */
function ms_remove_cron_jobs()
:void
{
    wp_clear_scheduled_hook('ms_govt_scrape_cron');
    wp_clear_scheduled_hook('ms_scrape_log_cleanup');
}
