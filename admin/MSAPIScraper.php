<?php

declare(strict_types = 1);

namespace Merck_Scraper\admin;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Merck_Scraper\Helper\MSMailer;
use Merck_Scraper\Traits\MSAcf;
use Merck_Scraper\Traits\MSApiFieldTrait;
use Merck_Scraper\Traits\MSApiTrait;
use Merck_Scraper\Traits\MSDBCallbacks;
use Merck_Scraper\Traits\MSHttpCallback;
use Merck_Scraper\Traits\MSLogger;
use Monolog\Logger;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class MSAPIScraper
{

    use MSApiTrait;
    use MSAcf;
    use MSLogger;
    use MSHttpCallback;
    use MSApiFieldTrait;
    use MSDBCallbacks;

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

        $timestamp      = Carbon::now()->timestamp;
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
    public function apiImport(WP_REST_Request $request)
    {
        // Callback to the frontend to let them know we're starting the import
        self::updatePosition("Starting Import");
        set_time_limit(300);
        ini_set('memory_limit', '2048M');
        ini_set('post_max_size', '512M');

        $nctid_field = $request->get_param('nctidField') ?? '';
        // $search_keywords  = self::acfOptionField('search_keywords');
        // $fields_to_import = self::acfOptionField('fields_to_import');
        $arr_data = true;

        $email_body = [
            'subject'   => 'Email Test',
            'body_text' => 'Body Text',
        ];

        $starting_rank = self::acfOptionField('min_import_rank') ?: 1;
        $max_rank = self::acfOptionField('max_import_rank') ?: 30;

        /**
         * Grab the data from the gov't site
         */
        $client_args = [
            // 'expr'    => 'AREA[LeadSponsorName]"Merck Sharp & Dohme Corp."',
            'expr'    => 'keynote AND AREA[LeadSponsorName]"Merck Sharp & Dohme Corp." AND AREA[LocationCountry]"United States"',
            'min_rnk' => $starting_rank,
            'max_rnk' => $max_rank,
        ];

        if ($nctid_field) {
            $client_args['expr'] = "AREA[NCTId]{$nctid_field}";
        }

        /**
         * Parse and organize each field and single-level sub field
         */
        $this->acfFields = self::trialsFieldGroup();

        $client_http = self::httpCallback('/api/query/full_studies', "GET", $client_args, ['delay' => 120]);

        // Check that our HTTP request was successful
        if (!is_wp_error($client_http)) {
            $api_data = json_decode($client_http->getBody()->getContents());

            // Set data root to first object key
            $api_data = $api_data->FullStudiesResponse ?? null;

            // Grab the total number of studies found
            $total_found = $api_data->NStudiesFound ?? 0;
            // Number of items we're grabbing
            $max_grabbed = $max_rank;

            /**
             * Determine how many times we need to loop through the items based on the amount found
             * versus the max number of item's we're getting
             */
            $loop_number = round($total_found / $max_grabbed);

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
            for ($iteration = 1; $iteration <= $loop_number; $iteration++) {
                // Increase the min_rnk and max_rnk for each loop above the first
                if ($iteration > 1) {
                    $client_args['min_rnk'] = $client_args['min_rnk'] + $max_rank;
                    $client_args['max_rnk'] = $client_args['max_rnk'] + $max_rank;
                    $client_http = self::httpCallback('/api/query/full_studies', "GET", $client_args, ['delay' => 120]);

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
                    $position = self::studyImportLoop($studies, $current_position, $total_found);
                    $current_position = $current_position + $position;
                }

            }
        } else {
            $this->errorLog->error(json_decode($client_http->getBody()->getContents()));
        }

        // Email notification on completion
        // MSMailer::mailer($this->sendTo, $email_body);

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
                    'position' => 1,
                    'total_import' => $total_found,
                ]
            );
            $studies = $studies
                ->map(function ($study, $index) use ($current_position, $total_found) {
                    $study_data = collect($study);
                    $position = ($current_position + $index) + 1;
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

            return $studies->count();
        }
        return 0;
    }

    /**
     * Setups the post creation or update based on the data imported.
     *
     * @param object $field_data Data retrieved from the API
     *
     * @return false|mixed
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
        $do_not_import = false;
        $trial_status = sanitize_title($status_module->get('trial_status'));
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
                return self::doNotImportTrial(0, $post_args->get('post_title'), $nct_id, 'Did not create new trial post.');
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
            $this->errorLog->error("Error importing post", [
                'PostTitle' => $post_args['post_title'],
                'NCTId'     => $nct_id,
                'error'     => $post_id->get_error_message(),
            ]);

            return false;
        }

        $position_index = $position_index++;
        self::updatePosition(
            "Trials Import",
            [
                'position' => $position_index !== $total_count ? $position_index : $total_count,
                'total_count' => $total_count,
                'helper' => "Importing {$post_args->get('post_title')}",
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
                    // ->put('interventions', $arms_module->get('interventions'))
                    ->put('phase', $design_module->get('phase'))
                    // ->put('other_ids', '')
                    ->put('locations', $contact_module->get('locations'));

                // Map through our fields and update their values
                $acf_fields
                    ->map(function ($field) use ($field_data, $post_id, $return) {
                        $data_name = $field['data_name'] ?? '';
                        if ($field['type'] === 'repeater') {
                            $sub_fields = $field['sub_fields'] ?? false;
                            if ($sub_fields && $sub_fields->isNotEmpty()) {
                                // Retrieve the data based on the parent data_name
                                $arr_data = $field_data->get($data_name) ?? false;

                                if ($arr_data) {
                                    return self::updateACF($field['name'], $arr_data, $post_id);
                                }
                                return false;
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

    protected function doNotImportTrial(int $post_id = 0, $post_title = '', $nct_id = '', $message = '')
    {
        return collect(
            [
                'ID' => $post_id,
                'NAME' => $post_title,
                'NCDID' => $nct_id,
                'MESSAGE' => $message
            ]
        );
    }

}
