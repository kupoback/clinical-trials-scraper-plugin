<?php

declare(strict_types = 1);

namespace Merck_Scraper\Admin;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Merck_Scraper\Admin\Traits\MSAdminTrial;
use Merck_Scraper\Admin\Traits\MSApiField;
use Merck_Scraper\Admin\Traits\MSEmailTrait;
use Merck_Scraper\Admin\Traits\MSLocationTrait;
use Merck_Scraper\Helper\MSHelper;
use Merck_Scraper\Traits\MSAcfTrait;
use Merck_Scraper\Traits\MSApiTrait;
use Merck_Scraper\Traits\MSDBCallbacks;
use Merck_Scraper\Traits\MSGoogleMaps;
use Merck_Scraper\Traits\MSHttpCallback;
use Merck_Scraper\Traits\MSLoggerTrait;
use Monolog\Logger;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * This class file is used to execute the ClinicalTrials.gov scraper to retrieve Trials based on
 * the allowed and disallowed list of words, countries, status' and main sponsor and import them as posts.
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSApiScraper
{

    //region Class Uses
    use MSAcfTrait;
    use MSAdminTrial;
    use MSApiField;
    use MSApiTrait;
    use MSDBCallbacks;
    use MSEmailTrait;
    use MSGoogleMaps;
    use MSHttpCallback;
    use MSLocationTrait;
    use MSLoggerTrait;
    //endregion

    //region Class Vars
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
     * @var string|mixed The Google Maps API key from the Database
     */
    private string $gmApiKey;

    /**
     * @var Collection Location ACF Field Names
     */
    private Collection $locationFields;

    /**
     * @var array|string[] Default array for \WP_Query
     */
    private array $locationPostDefault = [
        'post_title'  => '',
        'post_name'   => '',
        'post_type'   => 'locations',
        'post_status' => 'publish',
    ];

    /**
     * @var string Current NCT ID in use
     */
    private string $nctId;

    /**
     * @var Carbon $nowTime Sets a global Carbon DateTime for the instantiated class.
     */
    private Carbon $nowTime;

    /**
     * @var Collection A collection of protocol names to search for. Used for the study_protocol field
     */
    private Collection $protocolNames;

    /**
     * @var array|Collection Array for whom the email needs to be sent to
     */
    private $sendTo;

    /**
     * @var int  The total number of trials found
     */
    private int $totalFound;

    /**
     * @var Collection Trial ACF Field Names
     */
    private Collection $trialFields;

    /**
     * @var Collection An array of locations for a trial
     */
    private Collection $trialLocations;

    /**
     * @var array|string[] Default settings for \WP_Query
     */
    private array $trialPostDefault = [
        'post_title' => '',
        'post_name'  => '',
        'post_type'  => 'trials',
    ];

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
         * Now timestamp used to keep the log files in order
         */
        $this->nowTime = Carbon::now();

        /**
         * An array of people who the email notification should be sent out to
         */
        $this->sendTo = collect($email_params);

        $timestamp      = $this->nowTime->timestamp;
        $this->errorLog = $this->initLogger("api-error", "error-$timestamp", "$apiLogDirectory/error");
        $this->apiLog   = $this->initLogger("api-import", "api-$timestamp", "$apiLogDirectory/log", Logger::INFO);
    }

    /**
     * Method to register our API Endpoints
     */
    public function registerEndpoint()
    :void
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
                'nctidField' => [
                    'required'          => false,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
                'manualCall' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_bool($param);
                    }
                ]
            ]
        );

        /**
         * Get Geolocation
         */
        $this->registerRoute(
            'geo-locate',
            WP_REST_Server::CREATABLE,
            [$this, 'geoLocate'],
            '(?P<id>[\d]+)',
            [
                'id' => [
                    'required'          => true,
                    'type'              => 'int',
                    'sanitize_callback' => 'absint',
                ]
            ]
        );

        $this->registerRoute(
            'get-trial-locations',
            WP_REST_Server::CREATABLE,
            [$this, 'getTrialsLocations']
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
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        $this->setData();
        // Callback to the frontend to let them know we're starting the import
        $this->updatePosition("Starting Import");
        set_time_limit(1800);
        $init_memory_limit = ini_get("memory_limit");
        ini_set('memory_limit', '4096M');
        ini_set('post_max_size', '2048M');

        $nct_id_field     = $request['nctidField'] ?? false;
        $manual_call      = $request['manualCall'] ?? '';
        $num_not_imported = 0;
        $starting_rank    = $this->acfOptionField('min_import_rank') ?: 1;
        // $max_rank         = 5;
        $max_rank         = $this->acfOptionField('max_import_rank') ?: 30;

        $this->countryMappedLanguages =  collect($this->acfOptionField('clinical_trials_api_language_locations'))
            ->filter(fn ($location) => collect($location)->filter()->isNotEmpty());

        if ($this->countryMappedLanguages->isNotEmpty()) {
            /**
             * Creates an array of languages, codes and the countries they should be associated to. This is
             * used to split up the API calls and better map the data import.
             */
            $language_codes = collect(apply_filters('wpml_active_languages', null))
                ->mapWithKeys(fn ($language) => [$language['translated_name'] => $language['code']]);

            $this->countryMappedLanguages = $this->countryMappedLanguages
                ->map(fn ($country_language) => [
                    'code'     => $language_codes->get($country_language['language']) ?? 'en',
                    'country'  => collect(MSHelper::textareaToArr($country_language["countries"] ?? ''))
                        ->filter(),
                    'language' => $country_language['language'] ?? '',
                ]);
        }

        /**
         * If pulling in specific trial ID's, ignore the above
         */
        if ($nct_id_field) {
            $expression = collect(MSHelper::textareaToArr($nct_id_field))
                ->filter()
                ->map(fn ($nct_id) => "(AREA[NCTId]$nct_id)")
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
            // if ($this->disallowedTrialLocations->isEmpty()) {
            //     $location = $this->mapImplode($this->allowedTrialLocations);
            //     $expression->push(
            //         "( AREA[LocationCountry] $location )"
            //     );
            // } elseif ($this->disallowedTrialLocations->isNotEmpty()) {
            //     $location = $this->mapImplode($this->disallowedTrialLocations);
            //     $expression->push(
            //         "( AREA[LocationCountry] NOT $location )"
            //     );
            // }

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
            $this->trialFields = $this->getFieldGroup('group_60fae8b82087d');
            if ($this->trialFields->isEmpty()) {
                $this
                    ->errorLog
                    ->error(__("Trial ACF Group is empty or missing.", 'merck-scraper'));
                return new WP_Error(424, __("ACF Group is empty or missing.", 'merck'));
            }

            $this->locationFields = $this->getFieldGroup('group_6220de6da8144');
            if ($this->locationFields->isEmpty()) {
                $this
                    ->errorLog
                    ->error(__("Location ACF Group is empty or missing", 'merck-scraper'));
                return new WP_Error(424, __("ACF Group is empty or missing.", 'merck'));
            }

            // Set data root to first object key
            $api_data = $api_data->FullStudiesResponse ?? null;

            // Number of trials found
            // $this->totalFound = 5;
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
                        ->map(fn ($post) => $this->dbFetchNctId(intval($post->ID)));
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

        /**
         * Send the email only if it was run by the weekly call
         */
        if (!$manual_call) {
            $this->emailSetup($studies_imported, $num_not_imported);
        }

        // Restore the max_execution_time
        ini_restore('post_max_size');
        ini_restore('upload_max_filesize');
        ini_restore('max_execution_time');
        ini_set('memory_limit', $init_memory_limit);

        // Clear position
        $this->clearPosition();

        return rest_ensure_response(true);
    }

    /**
     * A simple method that grabs the locations override data and defaults to the imported data
     * and makes an API request to Google to get the latest latitude and longitude.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function geoLocate(WP_REST_Request $request)
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        $this->setData();
        $post_id = $request->get_param('id');

        $gm_geocoder_data = self::locationPostSetup($post_id);

        if ($gm_geocoder_data->get('latitude')) {
            return rest_ensure_response(
                [
                    'latitude' => $gm_geocoder_data->get('latitude'),
                    'longitude' => $gm_geocoder_data->get('longitude'),
                ]
            );
        }

        return rest_ensure_response([]);
    }

    /**
     * This method handles the manual call of importing all locations and getting the lat/lng primarily
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function getTrialsLocations()
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        set_time_limit(600);
        ini_set('memory_limit', '4096M');
        ini_set('post_max_size', '2048M');

        // The initial query to get the first 100 locations and sets up the for loop
        $locations_array = self::locationsQuery();

        // Check if the locations returned any items
        if ($locations_array['max_pages'] > 0) {
            $total_pages = $locations_array['max_pages'];
            $total_locations = $locations_array['total'];
            $post_count = 1;

            /**
             * Our initial for loop, the iteration will be use also
             * for the pagination of the locationsQuery
             */
            for ($iteration = 1; $iteration <= $total_pages; $iteration++) {
                // Our callback to get the next page
                if ($iteration !== 1) {
                    $locations_array = self::locationsQuery($iteration);
                } else {
                    // Our setup to show that the import has executed
                    $this->updatePosition(
                        "All Locations Import",
                        [
                            'helper' => "Importing $total_locations Locations",
                        ]
                    );
                }
                // Our loop through the returned locations to get the new Google Data
                collect($locations_array['locations'])
                    ->filter(function ($post_id) use (&$total_locations) {
                        $latitude = get_post_meta($post_id, 'ms_location_latitude', true);
                        $longitude = get_post_meta($post_id, 'ms_location_longitude', true);
                        // If there's a lat/lng, decrease the total importing by 1
                        if ($latitude && $longitude) {
                            $total_locations--;
                        }
                        return !$latitude && !$longitude;
                    })
                    ->each(function ($post_id) use (&$post_count, $total_locations) {
                        set_time_limit(1800);
                        ini_set('max_execution_time', '1800');
                        $post_count++;
                        $this
                            ->updatePosition(
                                'All Single Locations',
                                [
                                    'position' => $post_count,
                                    'total_count' => $total_locations,
                                ]
                            );

                        $gm_geocoder_data = self::locationPostSetup($post_id);

                        // If we error, we'll need to capture that error
                        if ($gm_geocoder_data->isEmpty()) {
                            $this
                                ->errorLog
                                ->error("Unable to get lat/lng for location ID: $post_id");
                        }
                    });
            }
        }

        // Clear position
        $this->clearPosition();

        ini_restore('post_max_size');
        ini_restore('upload_max_filesize');
        ini_restore('max_execution_time');

        return rest_ensure_response(true);
    }

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
    )
    :Logger|bool
    {
        return $this->initLogger($name, $file_name, $file_path, $logger_type);
    }

    public function setData()
    :void
    {
        $this->gmApiKey = $this->acfOptionField('google_maps_server_side_api_key');

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
         * Iterates through a textarea of the Study Protocol items
         */
        $this->protocolNames = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('clinical_trials_api_study_protocol_filter')
            )
        )
            ->filter();

        /**
         * Iterates through the status and sanitizes them for comparison as well as query
         */
        $this->trialStatus = collect(
            MSHelper::textareaToArr(
                $this->acfStrOptionFld('clinical_trials_api_status_search')
            )
        )
            ->filter();
    }
}
