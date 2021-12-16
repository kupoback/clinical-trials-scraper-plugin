<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

/**
 * Adds and registers the Custom Options pages for the scraper
 *
 * Defines the plugin name, version, registers the options page
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSOptionsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * A default ACF Options page
     */
    public function acfOptionsPage()
    {
        acf_add_options_page(
            [
                'page_title' => 'Merck Scraper Settings',
                'menu_title' => 'Merck Scraper',
                'menu_slug'  => 'merck-scraper',
                'capability' => 'manage_options',
                'redirect'   => false,
                'post_id'    => 'merck_settings',
                'position'   => 85,
                'icon_url'   => 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzNjggMzY4IiBmaWxsPSIjZmZmIj4NCgk8cGF0aCBkPSJNODggMTI4LjAwMUg1NmMtMTMuMjMyIDAtMjQgMTAuNzY4LTI0IDI0djgwYzAgNC40MTYgMy41NzYgOCA4IDhzOC0zLjU4NCA4LTh2LTI0aDQ4djI0YzAgNC40MTYgMy41NzYgOCA4IDhzOC0zLjU4NCA4LTh2LTgwYzAtMTMuMjMyLTEwLjc2OC0yNC0yNC0yNHptOCA2NEg0OHYtNDBjMC00LjQwOCAzLjU4NC04IDgtOGgzMmM0LjQxNiAwIDggMy41OTIgOCA4djQwek0xOTIgMTI4LjAwMWgtMzJjLTQuNDI0IDAtOCAzLjU4NC04IDh2OTZjMCA0LjQxNiAzLjU3NiA4IDggOHM4LTMuNTg0IDgtOHYtMjRoMjRjMjIuMDU2IDAgNDAtMTcuOTQ0IDQwLTQwcy0xNy45NDQtNDAtNDAtNDB6bTAgNjRoLTI0di00OGgyNGMxMy4yMzIgMCAyNCAxMC43NjggMjQgMjRzLTEwLjc2OCAyNC0yNCAyNHpNMzI4IDIyNC4wMDFoLTI0di04MGgyNGM0LjQyNCAwIDgtMy41ODQgOC04cy0zLjU3Ni04LTgtOGgtNjRjLTQuNDI0IDAtOCAzLjU4NC04IDhzMy41NzYgOCA4IDhoMjR2ODBoLTI0Yy00LjQyNCAwLTggMy41ODQtOCA4czMuNTc2IDggOCA4aDY0YzQuNDI0IDAgOC0zLjU4NCA4LThzLTMuNTc2LTgtOC04ek0zNDQgNDguMDAxSDIxOS4zMTJsLTI5LjY1Ni0yOS42NTZjLTMuMTI4LTMuMTI4LTguMTg0LTMuMTI4LTExLjMxMiAwbC0yOS42NTYgMjkuNjU2SDI0Yy0xMy4yMzIgMC0yNCAxMC43NjgtMjQgMjR2MjRjMCA0LjQxNiAzLjU3NiA4IDggOHM4LTMuNTg0IDgtOHYtMjRjMC00LjQwOCAzLjU4NC04IDgtOGgxMjhjMi4xMjggMCA0LjE2LS44NCA1LjY1Ni0yLjM0NEwxODQgMzUuMzEzbDI2LjM0NCAyNi4zNDRjMS40OTYgMS41MDQgMy41MjggMi4zNDQgNS42NTYgMi4zNDRoMTI4YzQuNDE2IDAgOCAzLjU5MiA4IDh2MjRjMCA0LjQxNiAzLjU3NiA4IDggOHM4LTMuNTg0IDgtOHYtMjRjMC0xMy4yMzItMTAuNzY4LTI0LTI0LTI0ek0zNjAgMjY0LjAwMWMtNC40MjQgMC04IDMuNTg0LTggOHYyNGMwIDQuNDA4LTMuNTg0IDgtOCA4SDIxNmMtMi4xMjggMC00LjE2Ljg0LTUuNjU2IDIuMzQ0TDE4NCAzMzIuNjg5bC0yNi4zNDQtMjYuMzQ0Yy0xLjQ5Ni0xLjUwNC0zLjUyOC0yLjM0NC01LjY1Ni0yLjM0NEgyNGMtNC40MTYgMC04LTMuNTkyLTgtOHYtMjRjMC00LjQxNi0zLjU3Ni04LTgtOHMtOCAzLjU4NC04IDh2MjRjMCAxMy4yMzIgMTAuNzY4IDI0IDI0IDI0aDEyNC42ODhsMjkuNjU2IDI5LjY1NmMxLjU2IDEuNTYgMy42MDggMi4zNDQgNS42NTYgMi4zNDQgMi4wNDggMCA0LjA5Ni0uNzg0IDUuNjU2LTIuMzQ0bDI5LjY1Ni0yOS42NTZIMzQ0YzEzLjIzMiAwIDI0LTEwLjc2OCAyNC0yNHYtMjRjMC00LjQxNi0zLjU3Ni04LTgtOHoiLz4NCjwvc3ZnPg0K', // Source https://www.flaticon.com/free-icon/api_929853 SVG Converted to base64
            ]
        );
    }


    /**
     * Registers the Trials Options Page
     *
     * @return void
     */
    public function setTrialOptsPage()
    {
        if (function_exists('acf_add_options_page')) {
            // Trials Settings
            acf_add_options_sub_page(
                [
                    'page_title'  => 'Trials Settings',
                    'menu_title'  => 'Trials Settings',
                    'parent_slug' => 'edit.php?post_type=trials',
                    'menu_slug'   => 'trials-settings',
                    'capability'  => 'edit_posts',
                    'redirect'    => false,
                    'post_id'     => 'trials_opts',
                ]
            );
        }
    }


    /** Fields to import
     * NCTId;
    BriefTitle;
    OfficialTitle;
    MaximumAge;
    MinimumAge;
    StartDate;
    CompletionDate;
    PrimaryCompletionDate;
    Gender;
    EnrollmentType;
    StudyType;
    Condition;
     */

    /**
     * Custom Sub Options pages under the ACF Options page. These are just used to mount Vue components
     */
    public function customOptsPage()
    {

        /**
         * The sub options page to show the API Scraper
         */
        add_submenu_page(
            'merck-scraper',
            'API Scraper',
            'API Scraper',
            'edit_posts',
            'merck-api-scraper',
            [$this, 'apiScraperPage']
        );

        /**
         * The sub options page to show the API Logs
         */
        add_submenu_page(
            'merck-scraper',
            'API Logs',
            'API Logs',
            'edit_posts',
            'merck-logs-scraper',
            [$this, 'logsScraperPage']
        );

    }

    /**
     * This is the Scraper Plugin API Import page
     */
    public function apiScraperPage()
    {
        // Set class property
        $this->options = get_option('merck_import');
        ?>
		<div class="wrap merck-scraper-settings merck-scraper-import" id="merck-scraper-settings">
			<h1><?= __('API Scrapper Import', 'merck-scraper'); ?></h1>
			<div id="merck-scraper-api"></div>
		</div>
        <?php
    }

    /**
     * This is the Scraper Plugin Logs page
     */
    public function logsScraperPage()
    {
        // Set class property
        $this->options = get_option('merck_import');
        ?>
		<div class="wrap merck-scraper-settings merck-scraper-log" id="merck-scraper-settings">
			<h1><?= __('API Logs', 'merck-scraper'); ?></h1>
			<div id="merck-scraper-log"></div>
		</div>
        <?php
    }



    /**
     * A textarea for the fields we're to import
     */
    public function importFields()
    {
        $helper = sprintf(
            '<p class="description">%s</p><p class="description"><a href="%s" target="_blank" rel="noopener">%s</p>',
            __(
                "Please enter in one field name per line, or use a semi-colon to separate the fields out. Upon saving, the entries will be filtered for duplicates, and return with a one item per line, followed by a semi-colon.",
                "merck-scraper"
            ),
            esc_url('https://clinicaltrials.gov/api/info/study_fields_list'),
            __('Those fields can be found here.', 'merck-scraper')
        );

        $defaults = 'NCTId;OfficialTitle;BriefTitle;BriefSummary;CompletionDate;StartDate;PrimaryCompletionDate;MaximumAge;MinimumAge;LocationFacility;LocationCity;LocationState;LocationZip;Phase;StudyType;LeadSponsorName;';

        printf(
            '<textarea class="merck-scraper-settings__textarea" type="textarea" rows="8" id="%2$s" name="merck_import[%2$s]">%1$s</textarea>%3$s',
            $this->options['import_fields'] ?? $defaults,
            'import_fields',
            $helper,
        );
    }
}
