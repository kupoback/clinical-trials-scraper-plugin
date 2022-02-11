=== Merck Scraper - WPML ===
Contributors: Nick Makris
Donate link: https://cliquestudios.com
Tags:
Requires at least: 5.9.0
Tested up to: 5.9.0
Requires PHP: 7.4
Stable tag: 1.0.6.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used to scrape data from clinicaltrials.gov website with WPML integrations.

== Description ==

== Changelog ==

= 1.0.6.4 =

Minor tweak to the import logic for the locations, checking if the lat/lng is set for a location

= 1.0.6.3 =

Fixed issue in ApiLog.vue for file undefined causing the inability to delete the files.

= 1.0.6.2 =

Added in integration for proper language mapping and taxonomy terms based on the locations countries

Adjusted a few pieces of integrations to optimize code and use as little memory as possible

Fixed issue with locations parsing after grabbing API data, removing countries not to import

Minor tweaks to the geocode to skip entries that already have a lat/lng entered to save on API calls

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
