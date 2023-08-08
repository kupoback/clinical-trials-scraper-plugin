=== Merck Scraper - WPML ===
Contributors: Nick Makris
Donate link: https://cliquestudios.com
Tags:
Requires at least: 6.0
Tested up to: 6.1.1
Requires PHP: 7.4
Stable tag: 1.8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used to scrape data from clinicaltrials.gov website with WPML integrations.

== Description ==

== Changelog ==

= 1.8.1 =

Fixed an issue where the parseMesh method would cause the script to fail due to null not being accepted

= 1.8 =

Added ability to capture MeSH Condition modules

Added in Search Keywords taxonomy

Mapped MeSH Condition Modules to Search Keywords and Study Keywords

Added capturing of additional EudraCT numbers

Capturing EudraCT numbers first, then falling back to EU CT before defaulting the value

= 1.7.1 =

Fixed issue with left behind error_log

Updated php dependencies

= 1.6 =

Added new field for capturing the Merck Protocol Number from the ID Module

Added mapping in the MSApiField for fetching the value and returning it

Added mapping to save that value to the db as `api_data_mk_id`

Updated all `composer` dependencies

Updated all `npm` packages

= 1.5.2 =

Added conditional logic to the trial notes as it apparently was reading some earlier integrations as an array instead of a string

Added FE API to fix the trials by the language passed

= 1.5.1 =

Fix to the Maximum Age range

= 1.5 =

* Updated the logic for calculating the minimum and maximum age range so that they display correctly in years as 0 if they are set as such in their string text

= 1.4 =

* Added field for allowing to override or not the CT.gov WYSIWYG content

= 1.3 =

* Added new field for Eudract Number from the SecondaryInfo fields

* Added mapping to capture the Eudract Number during the API call

= 1.2.1 =

* Fixed an issue with the Locations not importing the street or other address fields due to the match mapping

* Added in a new way to fetch missing Location data if any of the fields are empty, even if the location is already imported

= 1.2.0 =

**Reworks**

* Worked on adjusting how the import expression is built.
    * No longer using locations for the expression, but instead to filter out the return results of trials based on the location fields
* Reworked instantiation of log files and moved to Class methods instead of __construct so that it saves a bit on memory usage
* Fixed parenthesis string filtering leaving white spaces
* Adjusted the API Logs admin to showcase Change Logs
    * Added ability to download the file

**New Integrations**
* Integrated functionality to delete and remove log files that are at least 2 months old through a cron job at the start of the month every 2 months
* Added new ACF field for adding of notes for a Trial
    * Added new notes column and field for trials on the Trial Listing Page
* Added ability to add Custom Post Status'
    * Allowed the user to create and add new Post Status
    * When a Post Status is deleted, the Trial will get reset to Draft so that it's not lost
* Added ability to map WordPress Post Status to Trail Status for already imported trials, and not new trials
* Added in new functionality to track changes of content on Trials, but not Locations
    * Only works if there was content initially to compare to
    * Returns the new data, unfiltered for consumption
    * Support for input field strings
    * Support for array based data
    * Support for Taxonomy changes
    * Support for textarea data changes

= 1.1.5 =

Fix for frontend IP grabber

= 1.1.4.1 =

Worked out issues with the NCT ID additional textarea not actually importing trials

= 1.1.4 =

Working out additional expression bugs for the import process

Updated composer packages

= 1.1.3.2 =

Fixed issue with API import not processing countries correctly

Fixed the expression again for the query builder omitting expand and fullmatch phrasing

= 1.1.3.1 =

Removed error log from the plugin

= 1.1.3 =

• Fixed issue where not including a semicolon in a textarea under Merck Scraper Settings would return a single string, instead of a properly formatted array
    • Replaced explode option for a preg_split allowing the use of a regex for a return or new line character

• Removed the if/elseif condition statement for Locations, allowing the user to set an Allowed Location, as well as a Disallowed Location for the query expression
• Added in Expand[Concept] statement to the LocationCountry in the expression builder
• Fixed issue supporting the use of Allowed Locations and Disallowed Locations to skip over countries that should or shouldn't be imported
• Updated composer package dependencies to the latest versions

= 1.1.2 =

Fixed issue with ACF array data not saving

= 1.1.1 =

Added in fix for study protocol not mapping correctly due to dashes

= 1.1 =

Fixed major logic issue with the API request from Clinical Trials due to the improper query string being dropped

Added new progress bar for import to showcase the position of locations being imported without affecting the status of the trials being imported

= 1.0.9.4 =

Fixed logger return error for API Scraper

= 1.0.9.3 =

Added new ability to enter in NCT ID's and a second portion of the import will process those

Will remove the manually entered NCT ID's if they appear in the initial API response and save the remaining values

Code cleanup and redundancy adjustment

= 1.0.9.2 =

Various code optimizations for the API import

Adjusted a lot of the query returns and reintroduced the filtering by the location either omitted or included

Adjusted the location mapping for a trial to filter out ones that don't exist in the allowed or disallowed filters

= 1.0.9.1 =

Fix to composer.json PSR-4 path

= 1.0.9 =

This is a fork of the WPML version for use of MCT specifically

= 1.0.8 =

Updated illuminate/support to 8.x for more Collection availability and support on filtering and eliminating conflicts with Sage 9 theme

= 1.0.7.12 =

Reverted ->get_param to array check which caused cron event to fail

Refined the Get Locations Sync to just pull in locations missing lat/lng

= 1.0.7.11 =

Fix for admin CSS and JS not loading

= 1.0.7.10 =

Added a manual call to from the admin, so that emails aren't sent out when ever the scraper is run manually

Increased to Geolocate Callback time to 150ms

Code cleanup and organization

Trimed the import of the Facility name to remove ending spaces

Fixed the location data bug that was occurring on import

Adjusted to merge the initial grabbed data and override the existing location data, but retaining the existing lat/lng, which before was being removed entirely

Updated package dependencies

Added a button to the API page to get all imported locations' data in case any are missing

Added method to handle getting locations' data from Google Maps

= 1.0.7.9 =

Fixes to locations not all being pulled in

Added omission of email from manual calls

= 1.0.7.8 =

Removed Trial Settings to be theme dependent

= 1.0.7.7 =

Removed duplicate Trial Email fields

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
