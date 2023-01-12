<?php

namespace Merck_Scraper\Admin\Traits;

use Exception;
use Illuminate\Support\Collection;
use function get_all_custom_field_meta;

trait MSAdminTrial
{
    use MSApiField;

    /**
     * Filters each study, and checks if it has locations or not.
     *
     * If the trial has locations, it will parse and check against
     * the allowed and disallowed countries to filter out those locations,
     * then if there are any still, we will allow the trial to be imported
     * otherwise if there are no leftover trials, it's removed from the import
     *
     * If there are no trial locations at all, we'll still import the trial
     *
     * @param  Collection  $studies A collection of studies from the API
     *
     * @return Collection
     */
    private function filterImportLocations(Collection $studies)
    :Collection
    {
        return $studies
            ->filter(function ($study) {
                $study_protocol = $study->Study->ProtocolSection;
                if ($study_protocol ?? false) {
                    if (count($study_protocol->ContactsLocationsModule->LocationList->Location ?? []) > 0) {
                        return $this->parseLocation(
                            ($study_protocol->ContactsLocationsModule ?? null),
                            ($study_protocol->StatusModule ?? null),
                        )->get('locations')
                         ->count() > 0;
                    }
                    return true;
                }
                return false;
            });
    }

    /**
     * A separated loop to handle pagination of posts
     *
     * @param  Collection  $studies
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
                    'position'    => 1,
                    'total_count' => $this->totalFound,
                ],
            );

            return $studies
                ->map(function ($study) {
                    $study_import = $this->studyImport(
                        collect(
                            collect($study)
                                ->get('Study')
                                ->ProtocolSection,
                        ),
                        $study
                            ->Study
                            ->ProtocolSection
                            ->Rank,
                    );

                    if ($study_import->get('locations', false)) {
                        $location_ids = $this->locationsImport(
                            $study_import->get('locations'),
                            $study_import->get('NCT_ID'),
                        );
                        $study_import = $study_import->forget('locations');
                        // Update the imported trial with the location IDs we just imported
                        update_field('api_data_location_ids', $location_ids->implode(';'), $study_import->get('ID'));

                        // Update the imported trial with the languages for the locations imported
                        wp_set_object_terms(
                            $study_import->get('ID'),
                            $location_ids
                                ->map(fn ($id) => get_field('api_data_country', $id))
                                ->filter()
                                ->map(fn ($country) => $this->mapLanguage($country))
                                ->flatten(1)
                                ->values()
                                ->filter()
                                ->unique()
                                ->toArray(),
                            'trial_language',
                        );
                    }

                    return $study_import;
                });
        }

        return collect();
    }

    /**
     * Setups the post creation or update based on the data imported.
     *
     * @param  object  $field_data  Data retrieved from the API
     *
     * @return false|Collection
     * @throws Exception
     */
    protected function studyImport(object $field_data, int $position_index)
    :bool|Collection
    {
        set_time_limit(600);
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '4096M');
        ini_set('post_max_size', '2048M');

        $return        = collect();
        $trial_changes = collect(); // Container for the field data if there are existing data
        $new_changes   = collect(); // Container for holding any new changed data

        //region Modules
        $arms_module      = $this->parseArms($field_data->get('ArmsInterventionsModule', null));
        $condition_module = $this->parseCondition($field_data->get('ConditionsModule', null));
        $contact_module   = $this->parseLocation($field_data->get('ContactsLocationsModule', null), $field_data->get('StatusModule', null));
        $desc_module      = $this->parseDescription($field_data->get('DescriptionModule', null));
        $design_module    = $this->parseDesign($field_data->get('DesignModule', null));
        $eligible_module  = $this->parseEligibility($field_data->get('EligibilityModule', null));
        $id_module        = $this->parseId($field_data->get('IdentificationModule', null));
        $status_module    = $this->parseStatus($field_data->get('StatusModule', null));
        $sponsor_module   = $this->parseSponsors($field_data->get('SponsorCollaboratorsModule', null));
        //endregion

        // Not currently used field mappings
        // $oversight_module = $field_data->get('OversightModule');
        // $outcome_module   = $this->parseOutcome($field_data->get('OutcomesModule'));
        // $ipd_module = $this->parseIDP($field_data->get('IPDSharingStatementModule'));

        $this->nctId = $id_module->get('nct_id', '');
        $nct_id      = $this->nctId;
        // Grabs the post_id from the DB based on the NCT ID value
        $post_id = intval($this->dbFetchPostId('meta_value', $nct_id));

        // Default post status
        $do_not_import  = false;
        $trial_status   = sanitize_title($status_module->get('trial_status', ''));
        $allowed_status = $this->trialStatus
            ->map(fn ($status) => sanitize_title($status))
            ->toArray();

        // Set up the post data
        $parse_args = $this->parsePostArgs(
            [
                'title'   => $id_module
                    ->get('post_title'),
                'slug'    => $nct_id,
                'content' => $desc_module
                    ->get('post_content'),
            ],
        );

        $post_args = collect(wp_parse_args($parse_args, $this->trialPostDefault));

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
                    __('Did not create new trial post.', 'merck-scraper'),
                );
            }

            // All new trials are set to draft status
            $post_args
                ->put('post_status', 'draft');

            // Set up the post for creation
            $post_id = wp_insert_post(
                $post_args
                    ->toArray(),
                "Failed to create trial post.",
            );
        } else {
            $trial_changes = collect(get_all_custom_field_meta($post_id, $this->acfJsonContents))
                ->filter();
            // Updating our post
            $post_args
                ->put('ID', $post_id);
            $post_args
                ->forget(['post_title', 'post_content', 'post_name']);
            wp_update_post(
                $post_args
                    ->toArray(),
                "Failed to update post.",
            );
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
                    ],
                );

            return false;
        }

        $this->updatePosition(
            "Trials Import",
            [
                'position'    => $position_index,
                'total_count' => $this->totalFound,
                'helper'      => "Importing $nct_id",
            ],
        );

        $message = "Imported with post status set to $post_status";

        // Update the post meta if the trial is marked as allowed by its trial status
        if (!$do_not_import) {
            $acf_fields = $this->trialFields;

            // Set up our collection to pull data from
            //region Field Data Setup
            $field_data = collect(
                [
                    'nct_id'                  => $nct_id,
                    'url'                     => $id_module->get('url', ''),
                    'brief_title'             => $id_module->get('brief_title', ''),
                    'official_title'          => $id_module->get('official_title', ''),
                    'trial_purpose'           => $desc_module->get('trial_purpose', ''),
                    'study_keyword'           => $id_module->get('study_keyword', ''),
                    'study_protocol'          => $id_module->get('study_protocol', ''),
                    'start_date'              => $status_module->get('start_date', ''),
                    'primary_completion_date' => $status_module->get('primary_completion_date', ''),
                    'completion_date'         => $status_module->get('completion_date', ''),
                    'lead_sponsor_name'       => $sponsor_module->get('lead_sponsor_name', ''),
                    'gender'                  => $eligible_module->get('gender', ''),
                    'minimum_age'             => $eligible_module->get('minimum_age', ''),
                    'maximum_age'             => $eligible_module->get('maximum_age', ''),
                    'other_ids'               => $id_module->get('other_ids', ''),
                    // 'interventions' => $arms_module->get('interventions'),
                    'phase'                   => $design_module->get('phase', ''),
                ],
            );
            //endregion

            //region Field Data Import
            // Map through our fields and update their values
            // @TODO Uncomment out ACF saving before deploying
            $acf_fields
                ->map(function ($field) use ($field_data, $new_changes, $post_id, $return, $trial_changes) {
                    $data_name = $field['data_name'] ?? '';
                    if ($field['type'] === 'repeater') {
                        $sub_fields = $field['sub_fields'] ?? false;
                        if ($sub_fields && $sub_fields->isNotEmpty()) {
                            // Retrieve the data based on the parent data_name
                            $arr_data    = $field_data->get($data_name) ?? collect();
                            $arr_changes = collect();
                            if ($arr_data->isEmpty()) {
                                return false;
                            }

                            // Compare existing data with new import data
                            $original_data = collect($trial_changes->get($field['name'], ''))
                                ->flatten();
                            $flat_arr_data = $arr_data
                                ->flatten()
                                ->diff($original_data);

                            // If there is new data, merge it in
                            if ($original_data->isNotEmpty() && $flat_arr_data->isNotEmpty()) {
                                $new_changes
                                    ->put(
                                        $data_name,
                                        $flat_arr_data
                                            ->each(fn ($value, $key) => $arr_changes
                                                ->push($arr_data->get($key))),
                                    );
                            }

                            // return $this
                            //     ->updateACF(
                            //         $field['name'],
                            //         $arr_data
                            //             ->toArray(),
                            //         $post_id,
                            //     );
                        }

                        return false;
                    }

                    $original_data = $trial_changes->pull("api_data_$data_name", '');

                    // Setup name escaping for textarea
                    if ($field['type'] === 'textarea') {
                        if ($field_data->isNotEmpty()) {
                            $field_data = $field_data->get($data_name);
                            if ($field['name'] === 'api_data_other_ids') {
                                $field_data = $field_data
                                    ->implode(PHP_EOL);
                            }

                            // Merge in new data if it has changed
                            if ($original_data !== $field_data) {
                                $new_changes->put($data_name, $field_data);
                            }

                            // return $this->updateACF(
                            //     $field['name'],
                            //     $field_data,
                            //     $post_id,
                            // );
                        }

                        return false;
                    }

                    $field_data = $field_data->get($data_name);

                    // Check if the value is an integer for like Age for comparison
                    if (intval($field_data)) {
                        $original_data = intval($original_data);
                    }

                    // If there is new string data, merge it in
                    if ($original_data !== $field_data && $field_data && $original_data) {
                        $new_changes->put($data_name, $field_data);
                    }

                    // return $this->updateACF($field['name'], $field_data, $post_id);
                });
            //endregion

            //region Taxonomy Setup
            /**
             * Set up the taxonomy terms
             */
            collect(
                [
                    // Set the key as the taxonomy name
                    'study_keyword' => $condition_module->get('keywords'),
                    'conditions'    => $condition_module->get('conditions'),
                    'trial_status'  => $status_module->get('trial_status'),
                    // 'trial_category'  => [],
                ],
            )
                ->each(function ($terms, $taxonomy) use ($post_id) {
                    wp_set_object_terms($post_id, $terms, $taxonomy);
                });

            /**
             * Set up the taxonomy terms for Trial Drugs
             */
            if ($arms_module->get('drugs')
                && $arms_module->get('drugs')
                               ->isNotEmpty()) {
                collect()
                    ->put('trial_drugs', $arms_module->get('drugs'))
                    ->map(function ($terms, $taxonomy) use ($post_id) {
                        if ($terms instanceof Collection) {
                            $tax_terms = $terms->toArray();
                        } elseif (is_object($terms)) {
                            $tax_terms = (array) $terms;
                        }

                        wp_set_object_terms($post_id, $tax_terms ?? [], $taxonomy);
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
            //endregion

            // if ($contact_module->get('locations') && $contact_module->get('import')) {
            //     $return->put('locations', collect($contact_module->get('locations')));
            // }
        }

        $return->put('ID', $post_id);
        $return->put('NAME', $id_module->get('post_title'));
        $return->put('NCT_ID', $nct_id);
        $return->put('MESSAGE', $message);
        $return->put('POST_STATUS', $post_status);

        ini_restore('post_max_size');
        ini_restore('max_execution_time');

        return $return;
    }

    /**
     * Trial not to import
     *
     * @param  int     $post_id
     * @param  string  $title
     * @param  string  $nct_id
     * @param  string  $msg
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
            ],
        );
    }
}
