=== Merck Scraper - WPML ===
Contributors: Nick Makris
Donate link: https://cliquestudios.com
Tags:
Requires at least: 5.9.0
Tested up to: 5.9.2
Requires PHP: 7.4
Stable tag: 1.0.7.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used to scrape data from clinicaltrials.gov website with WPML integrations.

== Description ==

== Changelog ==

= 1.0.7.6 =

Added global fields for no ages and max ages.

= 1.0.7.5 =

Fixed issue with the trial locations causing an error if not set and skipped.

Reworked the import logic for study protocols to parse the BriefTitle instead of the SecondaryIds as they won't always be populated.

= 1.0.7.4 =

Fixed issue if Mailer has no Send To addresses

Added in mapping for the Trial's Sponsor Protocol Secondary ID to match with ACF field in Settings

= 1.0.7.3 =

Adjusted Trial Settings for breadcrumbs fallback

= 1.0.7.2 =

Changed import process to use the countries to allow or omit to filter out the locations imported instead of limiting the trials

Changed import delay from Google API from 200 to 150

Increase locations import execution time to 30min to prevent hangups

Removed no active locations ACF field

= 1.0.7.1 =

Fixed issue with locations import not skipping

= 1.0.7 =

Added new Locations Post type

Added new Location NCTID Taxonomy for Locations PT

Added new Location Language Taxonomy for Locations PT

Added in new meta-box with Vue element to fetch the location's Latitude/Longitude from the post itself and save the returned data

Added new Locations ACF for trial data and override

Added in before_delete_post hook to remove the NCT ID from the locations' term list, OR delete the location entirely if it only exists to one Trial

= 1.0.6.6 =

Added field for Single Trial Settings for directions.

Code cleanup in MSGoogleMaps

Changed the way location languages are grabbed by going about using ->implode() instead of ->first()

= 1.0.6.5 =

Adjustments to how the location is parsed and assigns the language

Semi-colon separated the locations as before was just using the first item

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
