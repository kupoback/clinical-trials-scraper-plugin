<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// use Merck_Scraper\Helper\MSMailer;
use Merck_Scraper\Helper\MSHelper;
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

class MSAPIScraper
{
    //region Class Uses
    use MSApiTrait;
    use MSAcfTrait;
    use MSGoogleMaps;
    use MSLoggerTrait;
    use MSHttpCallback;
    use MSApiField;
    use MSDBCallbacks;
    //endregion

    //region Class Vars
    /**
     * Array for who the email needs to be sent to
     *
     * @var string[][]
     */
    private array $sendTo = [];

    /**
     * The base url for the clinical trials gov't website
     *
     * @var string
     */
    private string $baseUrl = 'https://clinicaltrials.gov/api/query';

    /**
     * Default array of allowed keywords for HTTP Request
     * @var array|string[]
     */
    private Collection $allowedKeywords;

    /**
     * Default array of keywords for HTTP request
     * @var array|string[]
     */
    private Collection $disallowKeywords;

    /**
     * Trial ACF Field Names
     *
     * @var array|string[]
     */
    private Collection $acfFields;

    /**
     * Instantiates the success logger for the API
     *
     * @var Logger|false
     */
    private Logger $apiLog;

    /**
     * Instantiates the error logger for the API
     *
     * @var Logger|false
     */
    private Logger $errorLog;

    /**
     * Sets a global Carbon DateTime for the instantiated class.
     *
     * @var Carbon $nowTime
     */
    private Carbon $nowTime;
    //endregion

    /**
     * MSAPIScraper constructor.
     *
     * @param string $apiLogDirectory The path string of the dir for the API Log
     * @param array  $email_params    An array with the email and the name of who to send the email to
     */
    public function __construct($apiLogDirectory = MERCK_SCRAPER_API_LOG_DIR, array $email_params = [])
    {
        $this->sendTo = [
            [
                'email' => $email_params['email'] ?? 'nmakris@cliquestudios.com',
                'name'  => $email_params['name'] ?? 'Nick',
            ],
        ];

        /**
         * Collection of the allowed keywords
         */
        $this->allowedKeywords = collect(
            MSHelper::textareaToArr(
                self::acfOptionField('allowed_keywords')
            )
        );
        /**
         * Collection of the disallowed keywords
         */
        $this->disallowKeywords = collect(
            MSHelper::textareaToArr(
                self::acfOptionField('disallowed_keywords')
            )
        );

        $this->nowTime = Carbon::now();

        $timestamp      = $this->nowTime->timestamp;
        $this->errorLog = self::initLogger("api-error", "error-{$timestamp}", "{$apiLogDirectory}/error");
        $this->apiLog   = self::initLogger("api-import", "api-{$timestamp}", "{$apiLogDirectory}/log", Logger::INFO);
    }

    /**
     * Method to register our API Endpoints
     */
    public function registerEndpoint()
    {
        /**
         * Import Trials
         */
        self::registerRoute(
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

        /**
         * Importing a single NCTID
         */
        // self::registerRoute(
        //     'api-scraper',
        //     WP_REST_Server::CREATABLE,
        //     'apiImport',
        //     '(?P<nctid>[[:alnum:]]+)',
        //     [
        //         'nctid' => [
        //             'required'          => false,
        //             'validate_callback' => function ($param) {
        //                 return is_string($param);
        //             },
        //         ],
        //     ]
        // );
    }

    /**
     * This method executes the DB Scrapper making the API call to grab the new contents
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     * @throws Exception
     */
    public function apiImport(WP_REST_Request $request = null)
    {
        // Callback to the frontend to let them know we're starting the import
        self::updatePosition("Starting Import");
        set_time_limit(300);
        ini_set('memory_limit', '2048M');
        ini_set('post_max_size', '512M');

        $nctid_field = $request['nctidField'] ?? false;
        // $fields_to_import = self::acfOptionField('fields_to_import');
        // $search_keywords  = self::acfOptionField('search_keywords');
        $arr_data = true;

        // $email_body = [
        //     'subject'   => 'Email Test',
        //     'body_text' => 'Body Text',
        // ];

        $starting_rank = self::acfOptionField('min_import_rank') ?: 1;
        $max_rank      = self::acfOptionField('max_import_rank') ?: 100;

        $keywords_text = '';
        $sponsor_search = '( AREA[LeadSponsorName] "Merck Sharp & Dohme Corp." )';
        $country_search = 'SEARCH[Location] EXPAND[Term] COVER[FullMatch] ( AREA[LocationPath] "US" AND ( AREA[LocationCountry] "United States" OR CONST[0.95] ) )';
        // $country_search = 'SEARCH[Location](AREA[LocationCountry]"United States")';
        $trial_status = 'AND AREA[OverallStatus] EXPAND[Term] COVER[FullMatch] ( "Recruiting" OR "Not yet recruiting" )';
        if ($this->allowedKeywords->isNotEmpty()) {
            $keywords_text = "({$this->allowedKeywords->implode(' OR ')})";
        }
        if ($this->disallowKeywords->isNotEmpty()) {
            $disallowed_keys = $this->disallowKeywords->implode(' OR ');
            // if ($this->allowedKeywords->isNotEmpty()) {
            //     $keywords_text .= " NOT ({$disallowed_keys})";
            // } else {
            //     $keywords_text = "NOT ({$disallowed_keys})";
            // }
            // $keywords_text = "NOT ({$disallowed_keys})";
        }

        // $expression = 'AREA[LeadSponsorName]"Merck Sharp & Dohme Corp."';
        $expression = "{$keywords_text} AND {$sponsor_search} AND {$country_search}";
        if ($nctid_field) {
            $expression = "AREA[NCTId]{$nctid_field}";
            $total_found = 1;
        } else {
            /**
             * Updates the expr field to get a single ID
             */
            $total_found = self::getTotalToImport(
                [
                    // Use the expression above
                    'expr'    => "{$expression} AND AREA[OverallStatus]\"Recruiting\"",
                    'min_rnk' => 1,
                    'max_rnk' => 1,
                ]
            );
        }

        /**
         * Grab the data from the gov't site
         */
        $client_args = [
            'expr'    => $expression,
            'min_rnk' => $starting_rank,
            'max_rnk' => $max_rank,
        ];

        /**
         * Parse and organize each field and single-level sub field
         */
        $this->acfFields  = self::trialsFieldGroup();
        $studies_imported = collect();

        $client_http = self::scraperHttpCB('/api/query/full_studies', "GET", $client_args, ['delay' => 120]);

        // Check that our HTTP request was successful
        if (!is_wp_error($client_http)) {
            $api_data = json_decode($client_http->getBody()->getContents());

            // Set data root to first object key
            $api_data = $api_data->FullStudiesResponse ?? null;

            // Number of items we're grabbing
            $max_grabbed = $max_rank;

            // $filtered_data = self::filterTrials($api_data);

            /**
             * Determine how many times we need to loop through the items based on the amount found
             * versus the max number of item's we're getting
             */
            $loop_number = intval(round($total_found / $max_grabbed));
            $loop_number = $loop_number === 0 ? 1 : $loop_number;

            // Grab a list of trashed posts that are supposed to be archived
            $trashed_posts = collect(self::dbArchivedPosts());
            if ($trashed_posts->isNotEmpty()) {
                $trashed_posts = $trashed_posts
                    ->map(function ($post) {
                        return self::dbFetchNctId(intval($post->ID));
                    });
            }

            $current_position = 1;
            /**
             * Iterate through the import if the import max count is
             * higher than the max_rnk set.
             */

            for ($iteration = 1; $iteration < $loop_number; $iteration++) :
                // Increase the min_rnk and max_rnk for each loop above the first
                if ($iteration > 1) {
                    $client_args['min_rnk'] = $client_args['min_rnk'] + $max_rank;
                    $client_args['max_rnk'] = $client_args['max_rnk'] + $max_rank;
                    $client_http            = self::scraperHttpCB('/api/query/full_studies', "GET", $client_args, ['delay' => 120]);

                    if (!is_wp_error($client_http)) {
                        // Grab the results
                        $api_data = json_decode($client_http->getBody()->getContents());

                        // Set data root to first object key
                        $api_data = $api_data->FullStudiesResponse ?? null;
                    } else {
                        $this->errorLog->error("Error grabbing items during ranks {$client_args['min_rnk']} - {$client_args['max_rnk']}.");
                        $this->errorLog->error(json_decode($client_http->getBody()->getContents()));
                        // We don't want to stop the import, in case the issues were just at one instance
                        continue;
                    }
                }

                $studies = collect($api_data->FullStudies)
                    ->filter(function ($study) use ($total_found, $trashed_posts) {
                        // Filter the data removing ones that are marked as "trash"
                        $collect_study   = collect($study)
                            ->get('Study')
                            ->ProtocolSection;
                        $study_id_module = collect($collect_study)
                            ->get('IdentificationModule');

                        $study = self::parseId($study_id_module);
                        $found = $trashed_posts->search($study->get('nct_id'));
                        return is_bool($found);
                    })
                    ->values();

                if ($studies->count() > 0) {
                    $studies  = self::studyImportLoop(
                        $studies,
                        $current_position,
                        $total_found
                    );
                    $position = $studies['numOfStudies'];
                    $studies_imported->push($studies['studies']);
                    $current_position = $current_position + $position;
                }
            endfor;
        } else {
            $this->errorLog->error(json_decode($client_http->getBody()->getContents()));
        }

        // if ($studies_imported->isNotEmpty()) {
        //     $studies_imported = $studies_imported
        //         ->flatten(1)
        //         ->map(function ($study) {
        //
        //             return $study;
        //         });
        // Email notification on completion
        // MSMailer::mailer($this->sendTo, $email_body);
        // }

        // Restore the max_execution_time
        ini_restore('post_max_size');
        ini_restore('upload_max_filesize');
        ini_restore('max_execution_time');
        ini_restore('memory_limit');

        // Clear position
        self::clearPosition();

        return rest_ensure_response($arr_data);
    }

    /**
     * API call to get the actual number we'll be importing
     *
     * @param array $client_args An array of args to build the query
     *
     * @return int
     */
    private function getTotalToImport($client_args)
    {
        $client_http = self::scraperHttpCB(
            '/api/query/full_studies',
            "GET",
            $client_args,
            ['delay' => 120]
        );

        if (!is_wp_error($client_http)) {
            $api_data    = json_decode($client_http->getBody()->getContents());
            $api_data    = $api_data->FullStudiesResponse ?? null;
            return $api_data->NStudiesFound ?? 0;
        }
        return 0;
    }

    /**
     * Trial not to import
     *
     * @param int    $post_id
     * @param string $post_title
     * @param string $nct_id
     * @param string $message
     *
     * @return Collection
     */
    protected function doNotImportTrial(int $post_id = 0, $post_title = '', $nct_id = '', $message = '')
    {
        return collect(
            [
                'ID'      => $post_id,
                'NAME'    => $post_title,
                'NCDID'   => $nct_id,
                'MESSAGE' => $message,
            ]
        );
    }

    /**
     * Method that runs through the studies, and filters out the ones that contain a condition
     * that's not allowed
     *
     * @param object $studies An object of studies from the API call
     *
     * @return null|Collection
     */
    protected function filterTrials(object $studies)
    {
        if ($studies) {
            return collect($studies->FullStudies)
                ->filter(function ($study, $key) {
                    // if ($key > 1) {
                    //     return $study;
                    // }
                    $parse_conditions = self::parseCondition($study->Study->ProtocolSection->ConditionsModule);
                    $conditions = collect($parse_conditions->get('conditions'));
                    if ($conditions->isNotEmpty()) {
                        $disallowed_conds = $this->disallowKeywords->toArray();
                        error_log(print_r($conditions, true));
                        $final_conditions = $conditions
                            ->filter(function ($condition) use ($disallowed_conds) {
                                $condition = strtolower($condition);
                                return preg_grep("/^$condition/i", $disallowed_conds);
                            });
                        error_log(print_r($final_conditions, true));
                    }

                    return $study;
                });
        }
        return $studies;
    }

    //region Import Methods
    /**
     * A separated loop to handle pagination of posts
     *
     * @param Collection $api_data         The return data from the API, filtered through a Collection
     * @param int        $current_position The current position of the import
     * @param int        $total_found      The number of items found
     *
     * @throws Exception
     */
    private function studyImportLoop(Collection $studies, int $current_position, int $total_found)
    {
        // Map through our studies and begin assigning data to fields
        if ($studies->count() > 0) {
            self::updatePosition(
                "Trials Found",
                [
                    'position'     => 1,
                    'total_import' => $total_found,
                ]
            );
            $studies = $studies
                ->map(function ($study, $index) use ($current_position, $total_found) {
                    $study_data = collect($study);
                    $position   = ($current_position + $index) + 1;
                    return self::studyImport(
                        collect(
                            $study_data
                                ->get('Study')
                                ->ProtocolSection
                        ),
                        $position,
                        $total_found,
                    );
                })
                ->filter();

            $this->apiLog->info("Imported", $studies->toArray());

            return [
                'numOfStudies' => $studies->count(),
                'studies'      => $studies,
            ];
        }
        return 0;
    }

    /**
     * Setups the post creation or update based on the data imported.
     *
     * @param object $field_data Data retrieved from the API
     *
     * @return false|Collection
     * @throws Exception
     */
    protected function studyImport(object $field_data, int $position_index, int $total_count)
    {
        set_time_limit(180);
        ini_set('max_execution_time', '180');

        $return           = collect([]);
        $id_module        = self::parseId($field_data->get('IdentificationModule'));
        $status_module    = self::parseStatus($field_data->get('StatusModule'));
        $sponsor_module   = self::parseSponsors($field_data->get('SponsorCollaboratorsModule'));
        $desc_module      = self::parseDescription($field_data->get('DescriptionModule'));
        $condition_module = self::parseCondition($field_data->get('ConditionsModule'));
        $design_module    = self::parseDesign($field_data->get('DesignModule'));
        $eligibile_module = self::parseEligibility($field_data->get('EligibilityModule'));
        $contact_module   = self::parseLocation($field_data->get('ContactsLocationsModule'));

        // Not currently used field mappings
        // $arms_module      = self::parseArms($field_data->get('ArmsInterventionsModule'));
        // $oversite_module = $field_data->get('OversightModule');
        // $outcome_module   = self::parseOutcome($field_data->get('OutcomesModule'));
        // $ipd_module = self::parseIDP($field_data->get('IPDSharingStatementModule'));

        $nct_id = $id_module->get('nct_id');
        // Grabs the post_id from the DB based on the NCT ID value
        $post_id = intval(self::dbFetchPostId('meta_value', $nct_id));

        // Default post status
        $do_not_import  = false;
        $trial_status   = sanitize_title($status_module->get('trial_status'));
        $allowed_status = ['recruiting', 'active-not-recruiting'];

        $post_default = [
            'post_title' => '',
            'post_name'  => '',
            'post_type'  => 'trials',
        ];

        // Setup the post data
        $parse_args = self::parsePostArgs(
            [
                'title'   => $id_module->get('post_title'),
                'slug'    => $nct_id,
                'content' => $desc_module->get('post_content'),
            ]
        );

        $post_args = collect(wp_parse_args($parse_args, $post_default));

        // Update some parameters to not import the post OR set it's status to trash
        if (!in_array($trial_status, $allowed_status)) {
            $do_not_import = true;
            $post_args->put('post_status', 'trash');
        }

        if ($post_id === 0) {
            // Don't import the post and dump out of the loop for this item
            if ($do_not_import) {
                return self::doNotImportTrial(
                    0,
                    $post_args->get('post_title'),
                    $nct_id,
                    __('Did not create new trial post.', 'merck-scraper')
                );
            }

            // All new trials are set to draft status
            $post_args->put('post_status', 'draft');
            // Setup the post for creation
            $post_id = wp_insert_post(
                $post_args
                    ->toArray(),
                "Failed to create post."
            );
        } else {
            // Updating our post
            $post_args->put('ID', $post_id);
            wp_update_post(
                $post_args
                    ->toArray(),
                "Failed to update post."
            );
        }

        // Grab the post status
        $status = get_post_status($post_id);

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
                        'NAME'  => $post_args->get('post_title'),
                        'NCDID' => $nct_id,
                        'error' => $post_id->get_error_message(),
                    ]
                );

            return false;
        }

        $update_index = $position_index + 1;
        self::updatePosition(
            "Trials Import",
            [
                'position'    => $update_index !== $total_count ? $update_index : $total_count,
                'total_count' => $total_count,
                'helper'      => "Importing {$post_args->get('post_title')}",
            ],
        );

        $message = "Imported with post status set to {$status}";

        // Update the post meta if the trial is marked as allowed by it's trial status
        if (!$do_not_import) {
            $acf_fields = $this->acfFields;

            /**
             * Check to make sure we're still able to grab out ACF values
             */
            if ($acf_fields->isNotEmpty()) {
                // Setup our collection to pull data from
                $field_data = collect([])
                    ->put('nct_id', $nct_id)
                    ->put('official_title', $id_module->get('official_title'))
                    ->put('start_date', $status_module->get('start_date'))
                    ->put('primary_completion_date', $status_module->get('primary_completion_date'))
                    ->put('completion_date', $status_module->get('completion_date'))
                    ->put('lead_sponsor_name', $sponsor_module->get('lead_sponsor_name'))
                    ->put('gender', $eligibile_module->get('gender'))
                    ->put('minimum_age', $eligibile_module->get('minimum_age'))
                    ->put('maximum_age', $eligibile_module->get('maximum_age'))
                    ->put('other_ids', $id_module->get('other_ids'))
                    // ->put('interventions', $arms_module->get('interventions'))
                    ->put('phase', $design_module->get('phase'))
                    ->put('locations', $contact_module->get('locations'));

                // Map through our fields and update their values
                $acf_fields
                    ->map(function ($field) use ($field_data, $nct_id, $post_id, $return) {
                        $data_name = $field['data_name'] ?? '';
                        if ($field['type'] === 'repeater') {
                            $sub_fields = $field['sub_fields'] ?? false;
                            if ($sub_fields && $sub_fields->isNotEmpty()) {
                                // Retrieve the data based on the parent data_name
                                $arr_data = $field_data->get($data_name) ?? false;

                                /**
                                 * Since we're dealing with locations, we'll need to get the geolocation for each location
                                 * To help save on API calls made for each import, we only need to update if the latitude
                                 * or longitude doesn't exist.
                                 *
                                 * @TODO Look into finding a better way to check if location change or what not
                                 */
                                if ($data_name === 'locations') {
                                    /**
                                     * Skip the grabbing of the geocoding if there is no Google Maps API key set
                                     */
                                    if (self::acfOptionField('google_maps_api_key')) {
                                        // We'll want to reset the existing data as some trials be now be considered "Complete"
                                        self::updateACF($field['name'], [], $post_id);
                                        $arr_data = self::locationGeocode($arr_data, $nct_id);
                                    } else {
                                        $this->errorLog->error("Skipping geocode setup as there's no Google Maps Key set");
                                    }
                                }

                                if ($arr_data) {
                                    return self::updateACF($field['name'], $arr_data, $post_id);
                                }
                                return false;
                            }
                            return false;
                        }

                        // Setup name escaping for textareas
                        if ($field['type'] === 'textarea') {
                            if ($field_data->isNotEmpty()) {
                                return self::updateACF(
                                    $field['name'],
                                    $field_data
                                        ->get($data_name)
                                        ->implode(PHP_EOL),
                                    $post_id
                                );
                            }
                            return false;
                        }

                        return self::updateACF($field['name'], $field_data->get($data_name), $post_id);
                    });
            }

            /**
             * Setup the taxonomy terms
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
        }

        $return->put('ID', $post_id);
        $return->put('NAME', $post_args->get('post_title'));
        $return->put('NCDID', $nct_id);
        $return->put('MESSAGE', $message);

        ini_restore('post_max_size');
        ini_restore('max_execution_time');

        return $return;
    }

    /**
     * Grabs the geocode location data from Google Maps
     *
     * @param Collection $arr_data The array data
     * @param string     $nct_id   The NCT ID, used for errorLog print
     *
     * @return array
     */
    protected function locationGeocode(Collection $arr_data, string $nct_id)
    {
        return $arr_data
            ->map(function ($location) use ($nct_id) {
                $facility         = $location['facility'] ?? '';
                $gm_geocoder_data = self::getFullLocation(
                    collect(
                        [
                            $facility,
                            $location['city'] ?? '',
                            $location['state'] ?? '',
                            $location['zipcode'] ?? '',
                            $location['country'] ?? '',
                        ]
                    )
                        ->filter()
                        ->toArray()
                );
                /**
                 * If the geolocation was successful, then retun the data as an array
                 */
                if (!is_wp_error($gm_geocoder_data)) {
                    $gm_geocoder_data
                        ->put('facility', $facility)
                        ->put('recruiting_status', ($location['recruiting_status'] ?? ''));

                    return $gm_geocoder_data
                        ->toArray();
                }

                $this->errorLog
                    ->error(
                        "Unable to get geocode for {$nct_id}\r\n",
                        (array) $gm_geocoder_data->errors
                    );
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
    public function setLogger(string $name, string $file_name, string $file_path = MERCK_SCRAPER_LOG_DIR, int $logger_type = Logger::ERROR)
    {
        return self::initLogger($name, $file_name, $file_path, $logger_type);
    }
}