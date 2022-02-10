<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Merck_Scraper\Helper\MSHelper;
use Merck_Scraper\Helper\MSMailer;
use Merck_Scraper\Traits\MSAcfTrait;
use Merck_Scraper\Traits\MSApiField;
use Merck_Scraper\Traits\MSApiTrait;
use Merck_Scraper\Traits\MSDBCallbacks;
use Merck_Scraper\Traits\MSGoogleMaps;
use Merck_Scraper\Traits\MSHttpCallback;
use Merck_Scraper\Traits\MSLoggerTrait;
use Monolog\Logger;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * This class file is used to execute the ClinicalTrials.gov scraper to retrieve Trials based on
 * the allowed and disallowed list of words, countries, status' and main sponsor and import them as posts.
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSApiScraper
{

    //region Class Uses
    use MSApiField;
    use MSAcfTrait;
    use MSApiTrait;
    use MSDBCallbacks;
    use MSGoogleMaps;
    use MSHttpCallback;
    use MSLoggerTrait;
    //endregion

    //region Class Vars
    /**
     * @var Collection Trial ACF Field Names
     */
    private Collection $acfFields;

    /**
     * @var Collection Sets a collection of age ranges defined in the WP-Admin
     */
    private Collection $ageRanges;

    /**
     * @var Collection Default array of keywords to search for via HTTP request
     */
    private Collection $allowedConditions;

    /**
     * @var Collection Default array of locations that are allowing for importing
     */
    private Collection $allowedTrialLocations;

    /**
     * @var Logger|false Instantiates the success logger for the API
     */
    private Logger $apiLog;

    /**
     * @var string The base url for the clinical trial government website
     */
    private string $baseUrl = 'https://clinicaltrials.gov/api/query';

    /**
     * @var Collection A collection of languages and countries defined and used for trial_languages mapping
     */
    private Collection $countryMappedLanguages;

    /**
     * @var Collection  Default array of keywords to omit from searching for via HTTP request
     */
    private Collection $disallowedConditions;

    /**
     * @var Collection Default array of locations that are disallowed for importing
     */
    private Collection $disallowedTrialLocations;

    /**
     * @var Logger|false Instantiates the error logger for the API
     */
    private Logger $errorLog;

    /**
     * @var Collection ACF Values of existing trial locations
     */
    private Collection $existingLocations;

    /**
     * @var string Current NCT ID in use
     */
    private string $nctId;

    /**
     * @var Carbon $nowTime Sets a global Carbon DateTime for the instantiated class.
     */
    private Carbon $nowTime;

    /**
     * @var array|string[] Default settings for \WP_Query
     */
    private array $postDefault = [
        'post_title' => '',
        'post_name'  => '',
        'post_type'  => 'trials',
    ];

    /**
     * @var array|Collection Array for whom the email needs to be sent to
     */
    private $sendTo;

    /**
     * @var int  The total number of trials found
     */
    private int $totalFound;

    /**
     * @var Collection Default array of status that are allowed for importing
     */
    private Collection $trialStatus;
    //endregion

    /**
     * MSAPIScraper constructor.
     *
     * @param array  $email_params    An array with the email and the name of whom to send the email to
     * @param string $apiLogDirectory The path string of the dir for the API Log
     */
    public function __construct(array $email_params = [], string $apiLogDirectory = MERCK_SCRAPER_API_LOG_DIR)
    {
        /**
         * Collection of the allowed conditions
         */
        $this->allowedConditions = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('allowed_conditions')
            )
        )
            ->filter();

        /**
         * Our list of allowed Trial Locations
         */
        $this->allowedTrialLocations = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('clinical_trials_api_location_search')
            )
        )
            ->filter();

        /**
         * Collection of the disallowed conditions
         */
        $this->disallowedConditions = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('disallowed_conditions')
            )
        )
            ->filter();

        /**
         * Our list of disallowed Trial Locations
         */
        $this->disallowedTrialLocations = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('clinical_trials_api_omit_location_search')
            )
        )
            ->filter();

        /**
         * Now timestamp used to keep the log files in order
         */
        $this->nowTime = Carbon::now();

        /**
         * An array of people who the email notification should be sent out to
         */
        $this->sendTo = collect($email_params);

        /**
         * Iterates through the status and sanitizes them for comparison as well as query
         */
        $this->trialStatus = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('clinical_trials_api_status_search')
            )
        )
            ->filter();

        $timestamp      = $this->nowTime->timestamp;
        $this->errorLog = $this->initLogger("api-error", "error-$timestamp", "$apiLogDirectory/error");
        $this->apiLog   = $this->initLogger("api-import", "api-$timestamp", "$apiLogDirectory/log", Logger::INFO);
    }

    /**
     * Method to register our API Endpoints
     */
    public function registerEndpoint()
    {
        /**
         * Import Trials
         */
        $this->registerRoute(
            'api-scraper',
            WP_REST_Server::CREATABLE,
            [$this, 'apiImport'],
            '',
            [
                'nctid' => [
                    'required'          => false,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
            ]
        );
    }

    /**
     * This method executes the DB Scrapper making the API call to grab the new contents
     *
     * @param null|WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     * @throws Exception
     */
    public function apiImport(WP_REST_Request $request = null)
    {
        // Callback to the frontend to let them know we're starting the import
        $this->updatePosition("Starting Import");
        set_time_limit(600);
        ini_set('memory_limit', '4096M');
        ini_set('post_max_size', '2048M');

        $nct_id_field     = $request['nctidField'] ?? false;
        $num_not_imported = 0;
        $starting_rank    = $this->acfOptionField('min_import_rank') ?: 1;
        $max_rank         = $this->acfOptionField('max_import_rank') ?: 30;

        /**
         * Creates an array of languages, codes and the countries they should be associated to. This is
         * used to split up the API calls and better map the data import.
         */
        $language_codes               = collect(apply_filters('wpml_active_languages', null))
            ->mapWithKeys(function ($language) {
                return [$language['translated_name'] => $language['code']];
            });
        $this->countryMappedLanguages = collect($this->acfOptionField('clinical_trials_api_language_locations'))
            ->map(function ($country_language) use ($language_codes) {
                return [
                    'code'     => $language_codes->get($country_language['language']) ?? 'en',
                    'country'  => collect(MSHelper::textareaToArr($country_language["countries"] ?? ''))
                        ->filter(),
                    'language' => $country_language['language'] ?? '',
                ];
            });

        /**
         * If pulling in specific trial ID's, ignore the above
         */
        if ($nct_id_field) {
            $expression = collect(MSHelper::textareaToArr($nct_id_field))
                ->filter()
                ->map(function ($nct_id) {
                    return "(AREA[NCTId]$nct_id)";
                })
                ->implode(' OR ');
        } else {
            /**
             * The default search query.
             *
             * @uses Status OverallStatus of the Trial, Recruiting and Not yet recruiting
             * @uses Country The default country is the United States
             * @uses Sponsor Searches for Merck Sharp & Dohme Corp as the sponsor
             */
            $expression = collect();

            // Keywords that are not allowed
            if ($this->disallowedConditions->isNotEmpty()) {
                $expression->push(
                    "(AREA[ConditionSearch] NOT ({$this->disallowedConditions->implode(' OR ')}))"
                );
            }

            // Keywords that are allowed
            if ($this->allowedConditions->isNotEmpty()) {
                $expression->push(
                    "(AREA[ConditionSearch] ({$this->allowedConditions->implode(' OR ')}))"
                );
            }

            // Trial Status Search type
            if ($this->trialStatus->isNotEmpty()) {
                $recruiting_status = $this->mapImplode($this->trialStatus);
                $expression->push(
                    "(AREA[OverallStatus] EXPAND[Term] COVER[FullMatch] ( $recruiting_status ))"
                );
            }

            // Trial Location search
            if ($this->disallowedTrialLocations->isEmpty()) {
                $location = $this->mapImplode($this->allowedTrialLocations);
                $expression->push(
                    "( AREA[LocationCountry] $location )"
                );
            } elseif ($this->disallowedTrialLocations->isNotEmpty()) {
                $location = $this->mapImplode($this->disallowedTrialLocations);
                $expression->push(
                    "( AREA[LocationCountry] NOT $location )"
                );
            }

            // Trial sponsor search name
            $sponsor_name = $this->acfOptionField('clinical_trials_api_sponsor_search') ?: "Merck Sharp &amp; Dohme Corp.";
            $expression->push(
                "( AREA[LeadSponsorName] \"$sponsor_name\" )"
            );

            // Expression builder
            $expression = $expression
                ->filter()
                ->implode(' AND ');
        }

        /**
         * Grab the data from the govt site
         */
        $client_args = [
            'expr'    => $expression,
            'min_rnk' => $starting_rank,
            'max_rnk' => $max_rank,
        ];

        $studies_imported = collect();

        $client_http = $this->scraperHttpCB(
            '/api/query/full_studies',
            "GET",
            $client_args,
            ['delay' => 120,]
        );

        // Check that our HTTP request was successful
        if (!is_wp_error($client_http)) {
            $api_data = json_decode($client_http->getBody()->getContents());

            // Parse and organize each field and single-level subfield
            $this->acfFields = $this->getTrialsFieldGroups();
            if ($this->acfFields->isEmpty()) {
                $this
                    ->errorLog
                    ->error(__("ACF Group is empty or missing.", 'merck-scraper'));
                return new WP_Error(424, __("ACF Group is empty or missing.", 'merck'));
            }

            // Set data root to first object key
            $api_data = $api_data->FullStudiesResponse ?? null;

            // Number of trials found
            $this->totalFound = $api_data->NStudiesFound ?: 0;

            /**
             * Determine how many times we need to loop through the items based on the amount found
             * versus the max number of item's we're getting
             */
            $loop_number = intval(round($this->totalFound / $max_rank));
            // Commented out for now as it's not looping properly
            $loop_number = $loop_number === 0 ? 1 : $loop_number;

            /**
             * Grab all the ages set in the trial_age, and loop through them grabbing
             * the minimum_age and maximum_age from ACF.
             */
            $this->ageRanges = collect(get_terms(['taxonomy' => 'trial_age', 'hide_empty' => false]));
            if ($this->ageRanges->isNotEmpty()) {
                $this->ageRanges = $this->ageRanges
                    ->map(function ($term) {
                        $age_ranges = get_field('age_range', $term);
                        if (!empty($age_ranges)) {
                            $min_age = $age_ranges['minimum_age'] ?: 0;
                            $max_age = $age_ranges['maximum_age'] ?: 999;
                        } else {
                            // Log an error if there is no age range set for the term
                            $this
                                ->errorLog
                                ->error(__("Please ensure you set an age range", 'merck-scraper'));
                        }

                        return [
                            'name'    => $term->name,
                            'slug'    => $term->slug,
                            'min_age' => isset($min_age) ? intval($min_age) : 0,
                            'max_age' => isset($max_age) ? intval($max_age) : 999,
                        ];
                    });
            }

            if ((int) $api_data->NStudiesFound > 0) {
                // Grab a list of trashed posts that are supposed to be archived
                $trashed_posts = collect($this->dbArchivedPosts());
                if ($trashed_posts->isNotEmpty()) {
                    $trashed_posts = $trashed_posts
                        ->map(function ($post) {
                            return $this->dbFetchNctId(intval($post->ID));
                        });
                }

                /**
                 * Iterate through the import if the import max count is
                 * higher than the max_rnk set.
                 */
                for ($iteration = 0; $iteration < $loop_number; $iteration++) :
                    // Increase the min_rnk and max_rnk for each loop above the first
                    if ($iteration > 0) {
                        $client_args['min_rnk'] = $client_args['min_rnk'] + $max_rank;
                        $client_args['max_rnk'] = $client_args['max_rnk'] + $max_rank;

                        $client_http = $this->scraperHttpCB(
                            '/api/query/full_studies',
                            "GET",
                            $client_args,
                            ['delay' => 120,]
                        );

                        if (!is_wp_error($client_http)) {
                            // Grab the results
                            $api_data = json_decode($client_http->getBody()->getContents());

                            // Set data root to first object key
                            $api_data = $api_data->FullStudiesResponse ?? null;
                        } else {
                            $this
                                ->errorLog
                                ->error(
                                    sprintf(
                                        '%s %s - %s',
                                        __("Error grabbing items during ranks", 'merck-scraper'),
                                        $client_args['min_rnk'],
                                        $client_args['max_rnk']
                                    )
                                );

                            $this
                                ->errorLog
                                ->error(json_decode($client_http->getBody()->getContents()));
                            // We don't want to stop the import, in case the issues were just at one instance
                            continue;
                        }
                    }

                    $studies       = collect($api_data->FullStudies);
                    $initial_count = $studies->count();
                    $studies       = $studies
                        ->map(function ($study) {
                            $study->Study->ProtocolSection->Rank = $study->Rank;
                            return $study;
                        })
                        ->filter(function ($study) use ($trashed_posts) {
                            // Filter the data removing ones that are marked as "trash"
                            $collect_study   = collect($study)
                                ->get('Study')
                                ->ProtocolSection;
                            $study_id_module = collect($collect_study)
                                ->get('IdentificationModule');

                            $study = $this->parseId($study_id_module);
                            return !$trashed_posts->search($study->get('nct_id'));
                        })
                        ->values();

                    $num_not_imported = $num_not_imported + ($initial_count - $studies->count());

                    if ($studies->count() > 0) {
                        $studies_imported
                            ->push($this->studyImportLoop($studies));
                    }
                endfor;
            } else {
                $this
                    ->errorLog
                    ->error(__("No studies were found", 'merck-scraper'));
            }
        } else {
            $this
                ->errorLog
                ->error($client_http
                            ->get_error_message());
        }

        if ($studies_imported->isNotEmpty()) {
            $studies_imported = $studies_imported
                ->flatten(1)
                ->values();
            $this
                ->apiLog
                ->info("Imported {$studies_imported->count()} Studies", $studies_imported->toArray());
        }

        $this->emailSetup($studies_imported, $num_not_imported);

        // Restore the max_execution_time
        ini_restore('post_max_size');
        ini_restore('upload_max_filesize');
        ini_restore('max_execution_time');
        ini_restore('memory_limit');

        // Clear position
        $this->clearPosition();

        return rest_ensure_response(true);
    }

    //region Import Methods

    /**
     * A separated loop to handle pagination of posts
     *
     * @param Collection $studies
     *
     * @return Collection
     */
    private function studyImportLoop(Collection $studies)
    :Collection
    {
        // Map through our studies and begin assigning data to fields
        if ($studies->count() > 0) {
            $this->updatePosition(
                "Trials Found",
                [
                    'position'     => 1,
                    'total_import' => $this->totalFound,
                ]
            );

            return $studies
                ->map(function ($study) {
                    $study_data = collect($study);
                    return $this->studyImport(
                        collect(
                            $study_data
                                ->get('Study')
                                ->ProtocolSection
                        ),
                        $study
                            ->Study
                            ->ProtocolSection
                            ->Rank
                    );
                })
                ->filter();
        }
        return collect();
    }

    /**
     * Setups the post creation or update based on the data imported.
     *
     * @param object $field_data Data retrieved from the API
     *
     * @return false|Collection
     * @throws Exception
     */
    protected function studyImport(object $field_data, int $position_index)
    {
        set_time_limit(600);
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '4096M');
        ini_set('post_max_size', '2048M');

        $return = collect([]);

        //region Modules
        $arms_module      = $this->parseArms($field_data->get('ArmsInterventionsModule'));
        $condition_module = $this->parseCondition($field_data->get('ConditionsModule'));
        $contact_module   = $this->parseLocation($field_data->get('ContactsLocationsModule'));
        $desc_module      = $this->parseDescription($field_data->get('DescriptionModule'));
        $design_module    = $this->parseDesign($field_data->get('DesignModule'));
        $eligible_module  = $this->parseEligibility($field_data->get('EligibilityModule'));
        $id_module        = $this->parseId($field_data->get('IdentificationModule'));
        $status_module    = $this->parseStatus($field_data->get('StatusModule'));
        $sponsor_module   = $this->parseSponsors($field_data->get('SponsorCollaboratorsModule'));
        //endregion

        // Not currently used field mappings
        // $oversight_module = $field_data->get('OversightModule');
        // $outcome_module   = $this->parseOutcome($field_data->get('OutcomesModule'));
        // $ipd_module = $this->parseIDP($field_data->get('IPDSharingStatementModule'));

        $this->nctId = $id_module->get('nct_id');
        $nct_id      = $this->nctId;
        // Grabs the post_id from the DB based on the NCT ID value
        $post_id = intval($this->dbFetchPostId('meta_value', $nct_id));

        // Default post status
        $do_not_import  = false;
        $trial_status   = sanitize_title($status_module->get('trial_status'));
        $allowed_status = $this->trialStatus
            ->map(function ($status) {
                return sanitize_title($status);
            })
            ->toArray();

        // Set up the post data
        $parse_args = $this->parsePostArgs(
            [
                'title'   => $id_module
                    ->get('post_title'),
                'slug'    => $nct_id,
                'content' => $desc_module
                    ->get('post_content'),
            ]
        );

        $post_args = collect(wp_parse_args($parse_args, $this->postDefault));

        // Update some parameters to not import the post OR set its status to trash
        if (!in_array($trial_status, $allowed_status)) {
            $do_not_import = true;
            $post_args->put('post_status', 'trash');
        }

        if ($post_id === 0) {
            // Don't import the post and dump out of the loop for this item
            if ($do_not_import) {
                return $this->doNotImportTrial(
                    0,
                    $post_args->get('post_title'),
                    $nct_id,
                    __('Did not create new trial post.', 'merck-scraper')
                );
            }

            // All new trials are set to draft status
            $post_args
                ->put('post_status', 'draft');
            // Set up the post for creation
            $post_id                 = wp_insert_post(
                $post_args
                    ->toArray(),
                "Failed to create post."
            );
            $this->existingLocations = collect();
        } else {
            // Updating our post
            $post_args
                ->put('ID', $post_id);
            $post_args
                ->forget(['post_title', 'post_content',]);
            wp_update_post(
                $post_args
                    ->toArray(),
                "Failed to update post."
            );

            $this->existingLocations = $this->getTrialLocations($post_id);
        }

        // Grab the post status
        $post_status = get_post_status($post_id);

        /**
         * Bail out if we don't have a post_id
         */
        if (is_wp_error($post_id)) {
            $this
                ->errorLog
                ->error(
                    "Error importing post",
                    [
                        'ID'    => $post_id ?? 0,
                        'NAME'  => $post_args
                            ->get('post_title'),
                        'NCTID' => $nct_id,
                        'error' => $post_id
                            ->get_error_message(),
                    ]
                );

            return false;
        }

        $this->updatePosition(
            "Trials Import",
            [
                'position'    => $position_index,
                'total_count' => $this->totalFound,
                'helper'      => "Importing {$id_module->get('brief_title')}",
            ],
        );

        $message = "Imported with post status set to $post_status";

        // Update the post meta if the trial is marked as allowed by its trial status
        if (!$do_not_import) {
            $acf_fields = $this->acfFields;

            /**
             * Check to make sure we're still able to grab out ACF values
             */
            $google_api_key = $this->acfOptionField('google_maps_api_key');
            // Set up our collection to pull data from
            //region Field Data Setup
            $field_data = collect([])
                ->put('nct_id', $nct_id)
                ->put('url', $id_module->get('url'))
                ->put('brief_title', $id_module->get('brief_title'))
                ->put('official_title', $id_module->get('official_title'))
                ->put('trial_purpose', $desc_module->get('trial_purpose'))
                ->put('study_keyword', $id_module->get('study_keyword'))
                ->put('start_date', $status_module->get('start_date'))
                ->put('primary_completion_date', $status_module->get('primary_completion_date'))
                ->put('completion_date', $status_module->get('completion_date'))
                ->put('lead_sponsor_name', $sponsor_module->get('lead_sponsor_name'))
                ->put('gender', $eligible_module->get('gender'))
                ->put('minimum_age', $eligible_module->get('minimum_age'))
                ->put('maximum_age', $eligible_module->get('maximum_age'))
                ->put('other_ids', $id_module->get('other_ids'))
                // ->put('interventions', $arms_module->get('interventions'))
                ->put('phase', $design_module->get('phase'))
                ->put('locations', $contact_module->get('locations'));
            //endregion

            //region Field Data Import
            // Map through our fields and update their values
            $acf_fields
                ->map(function ($field) use ($field_data, $google_api_key, $post_id, $return) {
                    $data_name = $field['data_name'] ?? '';
                    if ($field['type'] === 'repeater') {
                        $sub_fields = $field['sub_fields'] ?? false;
                        if ($sub_fields && $sub_fields->isNotEmpty()) {
                            // Retrieve the data based on the parent data_name
                            $arr_data = $field_data->get($data_name) ?? collect();
                            if ($arr_data->isEmpty()) {
                                return false;
                            }

                            //region Locations
                            /**
                             * Since we're dealing with locations, we'll need to get the geolocation for each
                             * location to help save on API calls made for each import, we only need to update
                             * if the latitude or longitude doesn't exist.
                             */
                            if ($data_name === 'locations') {
                                /**
                                 * Skip the grabbing of the geocoding if there is no Google Maps API key set
                                 */
                                if ($google_api_key) {
                                    $arr_data = $this->locationGeocode($arr_data);
                                } else {
                                    $this
                                        ->errorLog
                                        ->error("Skipping geocode setup as there's no Google Maps Key set");
                                }
                            }
                            //endregion

                            if ($arr_data) {
                                return $this->updateACF($field['name'], $arr_data, $post_id);
                            }
                            return false;
                        }
                        return false;
                    }

                    // Setup name escaping for textarea
                    if ($field['type'] === 'textarea') {
                        if ($field_data->isNotEmpty()) {
                            if ($field['name'] === 'api_data_other_ids') {
                                $field_data = $field_data
                                    ->get($data_name)
                                    ->implode(PHP_EOL);
                            } else {
                                $field_data = $field_data
                                    ->get($data_name);
                            }
                            return $this->updateACF(
                                $field['name'],
                                $field_data,
                                $post_id
                            );
                        }
                        return false;
                    }

                    return $this->updateACF($field['name'], $field_data->get($data_name), $post_id);
                });
            //endregion

            //region Taxonomy Setup
            /**
             * Set up the taxonomy terms
             */
            collect()
                // Set the key as the taxonomy name
                ->put('study_keyword', $condition_module->get('keywords'))
                ->put('conditions', $condition_module->get('conditions'))
                ->put('trial_status', $status_module->get('trial_status'))
                // ->put('trial_category', [])
                ->each(function ($terms, $taxonomy) use ($post_id) {
                    return wp_set_object_terms($post_id, $terms, $taxonomy);
                });

            /**
             * Set up the taxonomy terms for Trial Drugs
             */
            if ($arms_module->get('drugs') && $arms_module->get('drugs')->isNotEmpty()) {
                collect()
                    ->put('trial_drugs', $arms_module->get('drugs'))
                    ->map(function ($terms, $taxonomy) use ($post_id) {
                        $tax_terms = [];
                        if ($terms instanceof Collection) {
                            $tax_terms = $terms->toArray();
                        } elseif (is_object($terms)) {
                            $tax_terms = (array) $terms;
                        }

                        wp_set_object_terms($post_id, $tax_terms, $taxonomy);
                    });
            }

            if ($eligible_module->get('minimum_age') || $eligible_module->get('maximum_age')) {
                // Reset the Trial Age terms, in case the ages previously imported have changed.
                wp_delete_object_term_relationships($post_id, 'trial_age');

                $min_age = intval($eligible_module->get('minimum_age'));
                $max_age = intval($eligible_module->get('maximum_age'));

                /**
                 * Loop through the Trial Age Ranges set, and match the trial with
                 * the right term, based on the terms min and max age.
                 *
                 * @returns array
                 */
                if ($this->ageRanges->isNotEmpty()) {
                    $this->ageRanges
                        ->each(function ($term) use ($min_age, $max_age, $post_id) {
                            $term_min_age = intval($term['min_age']);
                            $term_max_age = intval($term['max_age']);
                            if ($this->inBetween($term_min_age, $min_age, $max_age)
                                || $this->inBetween($term_max_age, $min_age, $max_age)
                            ) {
                                /**
                                 * For whatever reason, the comparison has to be if set equal
                                 * instead of the opposite. Might be due to type comparison
                                 * as well as value comparison.
                                 */
                                return ($min_age === 0 && $max_age === 999)
                                    ? []
                                    : wp_set_object_terms($post_id, $term['slug'], 'trial_age', true);
                            }
                            return [];
                        });
                }
            }

            /**
             * Maps the trial to a language tax term for filtering on the front end. This map is based on if
             * the country found in the trial exists in the defined ACF settings field
             */
            if ($this->existingLocations->isEmpty()) {
                $this->existingLocations = $this->getTrialLocations();
            }
            wp_set_object_terms(
                $post_id,
                $this->existingLocations
                    ->map(function ($location) use ($post_id) {
                        return $location['country'] ?? false;
                    })
                    ->filter()
                    ->map(function ($country) use ($post_id) {
                        return $this->mapLanguage($country);
                    })
                    ->flatten(1)
                    ->values()
                    ->filter()
                    ->unique()
                    ->toArray(),
                'trial_language'
            );
            //endregion
        }

        $return->put('ID', $post_id);
        $return->put('NAME', $post_args->get('post_title'));
        $return->put('NCT_ID', $nct_id);
        $return->put('MESSAGE', $message);
        $return->put('POST_STATUS', $post_status);

        ini_restore('post_max_size');
        ini_restore('max_execution_time');

        return $return;
    }

    /**
     * Grabs the geocode location data from Google Maps, but only if the location doesn't
     * exist in the first place
     *
     * @param Collection $arr_data The array data
     *
     * @return array
     */
    protected function locationGeocode(Collection $arr_data)
    :array
    {
        set_time_limit(600);
        ini_set('max_execution_time', '600');
        return $arr_data
            ->map(function ($location) {
                // Grab and filter the facility
                $facility = $this->filterParenthesis($location['facility'] ?? '');
                // Check if the location is already imported, so we don't grab the lat/lng again
                $data_grabbed = $this->existingLocations
                    ->filter(function ($location) use ($facility) {
                        return $location['facility'] === $facility;
                    });

                if (!isset($location['location_language'])) {
                    $location_language             = $this->mapLanguage($location['country']);
                    $location['location_language'] = $location_language->isNotEmpty() ? $location_language->first() : "All";
                }

                if ($data_grabbed->isEmpty()) {
                    $gm_geocoder_data = $this->getFullLocation(
                        collect(
                            [
                                $facility,
                                $location['city'] ?? '',
                                $location['state'] ?? '',
                                $location['zip'] ?? ($location['zipcode'] ?? ''),
                                $location['country'] ?? '',
                            ]
                        )
                            ->filter()
                            ->toArray()
                    );

                    /**
                     * If a location isn't located by its facility name,
                     * use the city, state, zip and country to get a lat/lng
                     */
                    if (is_wp_error($gm_geocoder_data)) {
                        $gm_geocoder_data = $this->getFullLocation(
                            collect(
                                [
                                    $location['city'] ?? '',
                                    $location['state'] ?? '',
                                    $location['zip'] ?? ($location['zipcode'] ?? ''),
                                    $location['country'] ?? '',
                                ]
                            )
                                ->filter()
                                ->toArray()
                        );
                    }

                    /**
                     * If the geolocation was successful, then return the data as an array
                     */
                    if (!is_wp_error($gm_geocoder_data)) {
                        $gm_geocoder_data
                            // Use default grabbed City/State from API, otherwise default to what google grabbed
                            ->put('city', $location['city'] ?? ($gm_geocoder_data->city ?? ''))
                            ->put('state', $location['state'] ?? ($gm_geocoder_data->city ?? ''))
                            ->put('country', $location['country'] ?? ($gm_geocoder_data->country ?? ''))
                            // Add facility, recruiting status and phone number to the collection
                            ->put('facility', $facility)
                            ->put('recruiting_status', ($location['recruiting_status'] ?? ''))
                            ->put('phone', ($location['phone'] ?? ''));

                        // Sets the location_language field data so that the trials can be filtered by language
                        $location_language = $this->mapLanguage($gm_geocoder_data->get('country'));
                        $gm_geocoder_data->put(
                            'location_language',
                            $location_language->isNotEmpty()
                                ? $location_language->first()
                                : "All"
                        );

                        return $gm_geocoder_data
                            ->toArray();
                    }

                    $this
                        ->errorLog
                        ->error(
                            "Unable to get geocode for $this->nctId;\r\n",
                            (array) $gm_geocoder_data->errors
                        );

                    return $gm_geocoder_data;
                }

                return $location;
            })
            ->filter()
            ->toArray();
    }
    //endregion

    /**
     * A public accessible logger setup
     *
     * @param string $name
     * @param string $file_name
     * @param string $file_path
     * @param int    $logger_type
     *
     * @return false|Logger
     */
    public function setLogger(
        string $name,
        string $file_name,
        string $file_path = MERCK_SCRAPER_LOG_DIR,
        int    $logger_type = Logger::ERROR
    ) {
        return $this->initLogger($name, $file_name, $file_path, $logger_type);
    }

    /**
     * Trial not to import
     *
     * @param int    $post_id
     * @param string $title
     * @param string $nct_id
     * @param string $msg
     *
     * @return Collection
     */
    protected function doNotImportTrial(int $post_id = 0, string $title = '', string $nct_id = '', string $msg = '')
    :Collection
    {
        return collect(
            [
                'ID'      => $post_id,
                'NAME'    => $title,
                'NCTID'   => $nct_id,
                'MESSAGE' => $msg,
            ]
        );
    }

    /**
     * Method that setups and sends out the email after the import has been run
     *
     * @param Collection $studies_imported A Collection of studies that were imported
     * @param int        $num_not_imported The number of studies not imported as they're filtered out
     */
    protected function emailSetup(Collection $studies_imported, int $num_not_imported = 0)
    {
        /**
         * Merck Email Client
         */
        if ($this->sendTo->isEmpty()) {
            $this->sendTo = collect($this->acfOptionField('api_logger_email_to'));
        }

        /**
         * Check if AIO is installed and setup
         */
        $login_url = wp_login_url();
        global $aio_wp_security;
        if ($aio_wp_security && $aio_wp_security->configs->get_value('aiowps_enable_rename_login_page') === '1') {
            $home_url  = trailingslashit(home_url()) . (!get_option('permalink_structure') ? '?' : '');
            $login_url = "$home_url{$aio_wp_security->configs->get_value('aiowps_login_page_slug')}";
        }

        /**
         * A list of array fields for the MailJet Email Client
         */
        $email_args = [
            'TemplateLanguage' => true,
            'TemplateID'       => (int) ($this->acfOptionField('api_email_template_id') ?? 0),
            'Variables'        => [
                'timestamp' => $this->nowTime
                    ->format("l F j, Y h:i A"),
                'trials'    => '',
                'wplogin'   => $login_url,
            ],
        ];

        /**
         * Adds the Trails' data to Variables
         */
        if ($studies_imported->isNotEmpty()) {
            $new_posts     = collect();
            $trashed_posts = collect();
            $updated_posts = collect();

            $studies_imported
                ->map(function ($study) use ($new_posts, $trashed_posts, $updated_posts) {
                    $status = $study
                            ->get('POST_STATUS') ?? '';
                    if (is_string($status)) {
                        switch (strtolower($status)) {
                            case "draft":
                            case "pending":
                                $new_posts
                                    ->push($study);
                                break;
                            case "trash":
                                $trashed_posts
                                    ->push($study);
                                break;
                            case "publish":
                                $updated_posts
                                    ->push($study);
                                break;
                            default:
                                break;
                        }
                    }
                    return $study;
                });

            $total_found      = sprintf('<li>Total Found: %s</li>', $studies_imported->count());
            $new_posts        = sprintf('<li>New Trials: %s</li>', $new_posts->count());
            $trashed_posts    = sprintf('<li>Removed Trials: %s</li>', $trashed_posts->count());
            $num_not_imported = sprintf('<li>Trials Not Scraped: %s</li>', $num_not_imported ?? 0);
            $updated_posts    = sprintf('<li>Updated Trials: %s</li>', $updated_posts->count());

            $email_args['Variables']['trials'] = sprintf(
                '<ul>%s</ul>',
                $total_found . $new_posts . $trashed_posts . $num_not_imported . $updated_posts
            );
        }

        // Email notification on completion
        return (new MSMailer())
            ->mailer($this->sendTo, $email_args);
    }

    /**
     * Quick adjustment call to setting up the map and implode for a Collection
     *
     * @param Collection $collection Collection
     * @param string     $operator
     *
     * @return string
     */
    protected function mapImplode(Collection $collection, string $operator = "OR")
    :string
    {
        return $collection
            ->map(function ($location) {
                return "\"$location\"";
            })
            ->implode(" $operator ");
    }

    /**
     * Maps through the location data and returns a filtered collection
     *
     * @param string $country The country to search and map for
     *
     * @return Collection
     */
    protected function mapLanguage(string $country)
    :Collection
    {
        return $this->countryMappedLanguages
            ->map(function ($location) use ($country) {
                // $location['country'] is a Collection
                if (is_int($location['country']->search($country, true))) {
                    return $location['language'];
                }
                return false;
            })
            ->filter();
    }
}
