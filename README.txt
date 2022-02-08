=== Merck Scraper - WPML ===
Contributors: Nick Makris
Donate link: https://cliquestudios.com
Tags:
Requires at least: 5.8.0
Tested up to: 5.8.1
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used to scrape data from clinicaltrials.gov website with WPML integrations.

== Description ==

== Changelog ==

= 1.1 =

Reworked a lot of the logic for the locations mapping
	Will no longer get a Google location if the one existing already has a lat/lng defined

Added in Disallow Country list and using that if there are no defined countries to focus on

Adjusted the way the country language and country map works, and integrated it into a new taxonomy

Made a new trial_language taxonomy for setting a trial to specific languages, with a fallback to all

Reworked some methods for cleanup and ease of adjustments

Adjusted the way Keywords and Conditions are defined and cleaning up their text values

Migrated some cleanup and adjustments from the default Merck Scraper plugin

= 1.0.6.1 =

Adjustment to the Post Type method to allow for rewrites array

Working on the scraper to allow for languages.

= 1.0.6 =

Fixed some logic in the import to handle the conditions and locations better

Adjusted the ACF for the Trial Status, and locations to be text areas instead of having the query defined

Edited the default conditions in ACF

Adjusted the MSHelper Textarea to Array to omit some lines of code that was breaking the return formatting for space cased words

= 1.0.5 =

Fix to customizer screen being inaccessible

= 1.0.4 =

Removed has_archive allowance in post type declaration

= 1.0.3 =

Moved Trial Settings to be theme dependent

= 1.0.2 =

Added ACF Trial Settings registration in the plugin

= 1.0.1 =

Added conditional checking for WPML install

Updated ACF groups to use WPML translation options

Added phpdotenv for symfony

= 1.0.0 =

Forked from Merck Scraper for WPML integration
