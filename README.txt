=== Plugin Name ===
Contributors: Nick Makris
Donate link: https://cliquestudios.com
Tags:
Requires at least: 5.8.0
Tested up to: 5.8.1
Requires PHP: 7.4
Stable tag: 1.1.2.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is used to scrape data from clinicaltrials.gov website.

== Description ==

== Changelog ==

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
