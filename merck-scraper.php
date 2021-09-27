<?php

declare(strict_types = 1);

namespace Merck_Scraper;

use Exception;
use Illuminate\Support\Carbon;
use Merck_Scraper\admin\MSAPIScraper;
use Merck_Scraper\includes\MSMainClass;
use Merck_Scraper\includes\MSActivator;
use Merck_Scraper\includes\MSDeactivator;
use Merck_Scraper\Traits\MSAcfTrait;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://cliquestudios.com
 * @since             1.0.0
 * @package           Merck_Scraper
 *
 * @wordpress-plugin
 * Plugin Name:       Merck Scrapper
 * Plugin URI:        #
 * Description:       This plugin is used to scrape data from clinicaltrials.gov website
 * Version:           1.0.4
 * Author:            Clique Studios
 * Author URI:        https://cliquestudios.com
 * Requires at least: 5.8.1
 * Tested up to:      5.8.1
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
// require plugin_dir_path(__FILE__) . 'build/vendor/autoload.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('MERCK_SCRAPER_VERSION', '1.0.4');

/**
 * This constant is used to save the logs to a specific directory
 */
define("MERCK_SCRAPER_LOG_DIR", WP_CONTENT_DIR . '/ms-logs');

define("MERCK_SCRAPER_API_LOG_DIR", MERCK_SCRAPER_LOG_DIR . "/api");

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-merck-scraper-activator.php
 */
register_activation_hook(__FILE__, function () {
    MSActivator::activate();
});

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-merck-scraper-deactivator.php
 */
register_deactivation_hook(__FILE__, function () {
    MSDeactivator::deactivate();
});

/**
 * This checks if ACF is activated, and warns the user that it's required to function properly
 */
if (!class_exists('ACF')) {
    add_action('admin_notices', function () {
        if (!class_exists('ACF')) {
            printf(
                '<div class="error"><h3>%s</h3><p>%s</p><p>%s</p></div>',
                __('Warning', 'merck-scraper'),
                sprintf(
                    __('The plugin %s requires Advanced Custom Fields to be installed.', 'merck-scraper'),
                    '<strong>Merck Scraper</strong>'
                ),
                sprintf(
                    __(
                        'Please install or activate Advanced Custom Fields and try again.',
                        'merck-scraper'
                    )
                )
            );
        }
    },
               20);
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
{
    $plugin = new MSMainClass();
    $plugin->executePlugin();
}

run_ms();

/**
 * This is the cron job setup function
 */
add_action('ms_govt_scrape_cron', function () {
    $scaper_class = new MSAPIScraper();

    $logger = $scaper_class->setLogger('cron-job', 'cron', MERCK_SCRAPER_LOG_DIR . '/cron');

    try {
        $scaper_class->apiImport();
    } catch (Exception $exception) {
        $logger->error(__("Erorr executing the cron job", 'merck-scraper'), $exception->getTrace());
    }
});

/**
 * If the cron job isn't scheduled to run, we'll set it up to run
 */
if (!wp_next_scheduled('ms_govt_scrape_cron')) {
    // Grab the next day, and set it up
    $next_thursday = Carbon::now('America/New_York')
                           ->next(Carbon::THURSDAY);
    wp_schedule_event(
        $next_thursday->timestamp,
        'thursday_api',
        'ms_govt_scrape_cron'
    );
}
