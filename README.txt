=== Plugin Name ===
Contributors: Nick Makris
Donate link: https://cliquestudios.com
Tags:
Requires at least: 5.8.0
Tested up to: 5.8.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used to scrape data from clinicaltrials.gov website.

== Description ==

== Changelog ==

= 1.2.0 =

Added in a field for collecting allowed conditions.

Cleaned up some methods.

Adjusted some spelling errors.

= 1.1.8.1 =

Added a default if no location found to use city, state, zip and country to get the location for a trial spot

= 1.1.8 =

Added filter for Location Facility title to omit parenthesis which were causing google to be unable to locate area

Added in adjustment to use API location info when returning the location, then deferring to Google's location data, then empty string for content.

= 1.1.7.5 =

Variable Text fix

= 1.1.7.4 =

Fixed issue with emailer not sending out the final data

= 1.1.7.3 =

Added padding 0 to container-fluid for the admin option pages

Uncommented the MailJet integration

= 1.1.7.2 =

Adjustment to the API call to use keywords as Condition omissions.

= 1.1.7.1 =

Added in commented out code to update the post title

= 1.1.7 =

Added Protocol Number to import and removed study results from ACF since it's not being mapped anywhere

Added regex for the BriefTitle to remove items in parentheses.

= 1.1.6 =

Added new Trial Age taxonomy

Setup taxonomy with min and max age ranges

Hooked up the taxonomy terms and parsing of the min and max age of the trial, and setting the trial with the right term

= 1.1.5.7 =

Fixed another styling issue hiding fields completely.

= 1.1.5.6 =

Removed return type of update_acf_field as it was returning an int instead of a bool

= 1.1.5.5 =

Another minor tweak to CSS rule

= 1.1.5.4 =

Fixed styling issue stopping a user from unpublishing a Trials post.

= 1.1.5.3 =

Removed auto-draft from the Antidote API options

= 1.1.5.2 =

Removed message from Frontend Antidote API

= 1.1.5.1 =

Fix to default values on Merck Scraper Settings

= 1.1.5 =

Added Endpoint for Antidote to grab trials from

Added ACF Fields for the endpoint. Can select which taxonomy for the categories array, and select the post status types to return

Moved the Trial Scraper API call query params to ACF to better control the query.

Ran into issue where the scraper wasn't running, and it was due to fields being depreciated in the Clinical Trials API.

Refactoring code and some tidying up of methods.

= 1.1.4.2 =

Upped the progress timeout from 3 mins to 5 mins

= 1.1.4.1 =

Another adjustment to the encoding of strings

= 1.1.4 =

Fixed by encoding the strings sent out for getting the lat/lng of a location

= 1.1.3 =

Fixed conditional in the MSAPIScraper file

Updated Guzzle version

= 1.1.2.3 =

Fixed issue with return on MSUserLocation

= 1.1.2.2 =

Removed error_log in MSUserLocation

= 1.1.2.1 =

Added missing composer update dependency

= 1.1.2 =

Added Google Maps provider for Geocoder.

Updated ACF name for Google Sever Side API from Geocoder API key

= 1.1.1 =

Fixed issue where phone was not being mapped

Updated composer packages

= 1.1.0 =

Added IP lookup using Geocoder composer package with free-geoip provider. Added frontend class for a return of the zipcode and coordinates, full locations, and first location.

Class allows dev to use an alternative service provider other than free-geoip, but must provide an array with the provider or providers they wish to use

= 1.0.10.1 =

Trying to debug the Google Import not fully finishing on server, compared to local. Increased max execution times

= 1.0.10 =

Hadded new Google Geocode API Key field in settings due to it needing a key that is not domain locked.

= 1.0.9 =

Changed Trial Category to Thereaputic Area

= 1.0.8 =

Added import for phone numbers

= 1.0.7 =

Added Options field for base url for the Clinical Trials show page and added to import

= 1.0.6 =

Adjusted Import to not override the `post_title` or `post_content` on imported trials
Adjusted ACF to have a field for the brief title, and brief summary

= 1.0.5 =

CSS fix to ACF not closing boxes

= 1.0.4 =

Added trial_drugs taxonomy and mapped the drugs there for front-end querying

= 1.0.0 =

Initial build of the plugin. Scrapes the clinicaltrials.gov website for trials that are related to Merck
