<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin\Traits;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Merck_Scraper\Helper\MSHelper as Helper;

/**
 * Traits for the Merck Scraper API fields
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSApiField
{

    /**
     * Parses the IdentificationModule object field, returning the Post Title, NCTID, and Official Title field
     *
     * @param  null|object  $id_module  The IdentificationModule object from the govt API data
     *
     * @return Collection
     */
    protected function parseId(null|object $id_module)
    :Collection
    {
        try {
            $other_ids      = collect($id_module->SecondaryIdInfoList->SecondaryIdInfo ?? []);
            $study_protocol = collect();

            if ($other_ids->isNotEmpty()) {
                $eudract_number = $other_ids
                    ->filter(fn ($second_id) => ($second_id->SecondaryIdType ?? false)
                                                && $second_id->SecondaryIdType === 'EudraCT Number')
                    ->values();

                if ($eudract_number->isNotEmpty()) {
                    $eudract_number = $eudract_number
                        ->map(fn ($id) => $id->SecondaryId ?? false)
                        ->filter()
                        ->first();
                } else {
                    $eudract_number = '';
                }

                $other_ids = $other_ids
                    ->map(fn ($second_id) => $second_id->SecondaryId ?? '')
                    ->filter();
            }

            $base_url = $this->acfOptionField('clinical_trials_show_page');

            $title = '';
            if ($id_module->BriefTitle !== null) {
                $title          = trim($this->filterParenthesis($id_module->BriefTitle));
                $study_keywords = self::extractParenthesis($id_module->BriefTitle);
                if (!empty($study_keywords)) {
                    $study_protocol = collect($study_keywords)
                        ->map(function ($keywords) {
                            $keywords = explode('/', $keywords);
                            if (!empty($keywords)) {
                                return collect($keywords)
                                    ->filter(fn ($keyword) => $this
                                        ->protocolNames
                                        ->search(
                                            Str::lower(
                                                preg_replace(
                                                    "/[^[:alpha:]]/u",
                                                    '',
                                                    // Need to get just the first part of the word
                                                    explode('-', $keyword)[0] ?? '',
                                                ),
                                            ),
                                        ))
                                    ->map(fn ($keyword) => Str::title(
                                        preg_replace(
                                            "/[^[:alnum:]]/u",
                                            ' ',
                                            $keyword,
                                        ),
                                    ))
                                    ->first();
                            }

                            return false;
                        })
                        ->filter()
                        ->values();
                }
            }

            return collect(
                [
                    'post_title'     => $title,
                    'brief_title'    => $id_module->BriefTitle ?? '',
                    'eudract_number' => $eudract_number ?? '',
                    'nct_id'         => $id_module->NCTId ?? '',
                    'official_title' => $id_module->OfficialTitle ?? '',
                    'other_ids'      => $other_ids,
                    'study_keyword'  => $id_module
                                            ->OrgStudyIdInfo
                                            ->OrgStudyId ?? '',
                    'study_protocol' => $study_protocol
                        ->first(),
                    'url'            => $base_url . $id_module->NCTId,
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'id module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the StatusModule object field, returning the Trial Status taxonomy term, Start Date,
     * Primary Completion Date, Completion Date, Study First Post Date, and Results First Post Date fields
     *
     * @param  null|object  $status_module
     *
     * @return Collection
     */
    protected function parseStatus(null|object $status_module)
    :Collection
    {
        try {
            return collect(
                [
                    'trial_status'            => $status_module->OverallStatus,
                    'start_date'              => $status_module
                                                     ->StartDateStruct
                                                     ->StartDate ?? '',
                    'primary_completion_date' => $status_module
                                                     ->PrimaryCompletionDateStruct
                                                     ->PrimaryCompletionDate ?? '',
                    'completion_date'         => $status_module
                                                     ->CompletionDateStruct
                                                     ->CompletionDate ?? '',
                    // 'study_first_post_date'   => $status_module->StudyFirstPostDateStruct->CompletionDate ?? '',
                    // 'results_first_post_date' => $status_module->ResultsFirstPostDateStruct->ResultsFirstPostDate ?? '',
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'status module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Sponsor Collaborators Module, returning the Lead Sponsor Name field
     *
     * @param  null|object  $sponsor_module
     *
     * @return Collection
     */
    protected function parseSponsors(null|object $sponsor_module)
    :Collection
    {
        try {
            return collect(
                [
                    'lead_sponsor_name' => $sponsor_module
                                               ->LeadSponsor
                                               ->LeadSponsorName ?? '',
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'sponsor module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Oversight Module, not currently used
     *
     * @param  null|object  $oversight_module
     *
     * @return Collection
     */
    protected function parseOversight(null|object $oversight_module)
    :Collection
    {
        return collect(
            [],
        );
    }

    /**
     * Parses the Description Module, returning the Brief Summary as post content
     *
     * @param  null|object  $description_module
     *
     * @return Collection
     */
    protected function parseDescription(null|object $description_module)
    :Collection
    {
        try {
            return collect(
                [
                    'post_content'  => $description_module->BriefSummary ?? '',
                    'trial_purpose' => $description_module->BriefSummary ?? '',
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'description module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Condition Module, returning a list of Conditions and a list of Keywords for taxonomy terms
     *
     * @param  null|object  $condition_module
     *
     * @return Collection
     */
    protected function parseCondition(null|object $condition_module)
    :Collection
    {
        try {
            return collect(
                [
                    'conditions' => $this
                        ->standardizeArrayWords($condition_module->ConditionList->Condition ?? []),
                    'keywords'   => $this
                        ->standardizeArrayWords($condition_module->KeywordList->Keyword ?? []),
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'condition module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Design Info Module, returning an array of Phases, the Study Type, and a collection of Study Designs
     *
     * @param  null|object  $design_module
     *
     * @return Collection
     */
    protected function parseDesign(null|object $design_module)
    :Collection
    {
        try {
            /**
             * Unsure if needed
             */
            // $study_design = $design_module->DesignInfo ?? collect([]);
            // if (is_object($study_design)) {
            //     $design_masking = $study_design->DesignMaskingInfo ?? [];
            //     if (is_object($design_masking)) {
            //         $design_mask     = $design_masking->DesignMasking ?? '';
            //         $design_mask_arr = collect([
            //                                        $design_masking
            //                                            ->DesignWhoMaskedList
            //                                            ->DesignWhoMasked ?? '',
            //                                    ])
            //             ->filter();
            //         $design_mask_arr->isNotEmpty() ? $design_mask .= " ({$design_mask_arr->implode(' ')})" : null;
            //     }
            //
            //     $study_design = collect(
            //         [
            //             'allocation'   => $study_design->DesignAllocation ?? '',
            //             'intervention' => $study_design->DesignInterventionModel ?? '',
            //             'masking'      => $design_mask ?? '',
            //             'purpose'      => $study_design->DesignPrimaryPurpose ?? '',
            //         ]
            //     );
            // }
            //
            // unset($design_mask, $design_masking, $design_mask_arr);

            return collect(
                [
                    'phase' => collect($design_module->PhaseList->Phase ?? [])
                        ->map(fn ($phase) => ['phase' => $phase]),
                    // 'study_type'    => $design_module->StudyType,
                    // 'study_designs' => $study_design,
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'design module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Arms Interventions Module, returning a collection of Interventions
     *
     * @param  null|object  $arms_module
     *
     * @return Collection
     */
    protected function parseArms(null|object $arms_module)
    :Collection
    {
        try {
            $intervention_arr = collect([]);
            $interventions    = $arms_module->InterventionList ?? [];
            if (is_object($interventions) && !empty($interventions->Intervention)) {
                $intervention_arr = collect($interventions->Intervention)
                    ->map(fn ($arr_item) => [
                        'type' => $arr_item->InterventionType ?? '',
                        'name' => $arr_item->InterventionName ?? '',
                    ])
                    ->filter();
            }

            unset($interventions);

            return collect(
                [
                    'interventions' => $intervention_arr ?? [],
                    'drugs'         => $intervention_arr->isNotEmpty()
                        ? $intervention_arr
                            ->map(fn ($arr_item) => strtolower($arr_item['type']) === 'drug'
                                ? ucwords(rtrim($arr_item['name'], ','))
                                : false)
                            ->filter()
                        : collect(),
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'arms/interventions module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Outcomes Module, returning a collection of Outcome Measures
     *
     * @param  null|object  $outcome_module
     *
     * @return Collection
     */
    protected function parseOutcome(null|object $outcome_module)
    :Collection
    {
        try {
            $outcomes        = collect([]);
            $primary_outcome = $outcome_module
                                   ->PrimaryOutcomeList
                                   ->PrimaryOutcome ?? [];
            $second_outcome  = $outcome_module
                                   ->SecondaryOutcomeList
                                   ->SecondaryOutcome ?? [];

            if (!empty($primary_outcome)) {
                collect($primary_outcome)
                    ->each(fn ($outcome) => $outcomes->push(['measure' => $outcome->PrimaryOutcomeMeasure]));
            }

            if (!empty($second_outcome)) {
                collect($second_outcome)
                    ->each(fn ($outcome) => $outcomes->push(['measure' => $outcome->SecondaryOutcomeMeasure]));
            }

            unset($primary_outcome, $second_outcome);

            return collect(
                [
                    'outcome_measures' => $outcomes,
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'outcome module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Eligibility Module, returning the Gender, Minimum Age, and Maximum Age fields
     *
     * @param  null|object  $eligibility_module
     *
     * @return Collection
     */
    protected function parseEligibility(null|object $eligibility_module)
    :Collection
    {
        try {
            return collect(
                [
                    'gender'      => $eligibility_module->Gender ?? '',
                    'minimum_age' => intval(
                        Helper::stripYears(
                            $eligibility_module->MinimumAge ?? '',
                        )
                            ?: 0 // Definitive minimum age
                    ),
                    'maximum_age' => intval(
                        Helper::stripYears(
                            $eligibility_module->MaximumAge ?? '',
                        )
                            ?: 999 // Definitive maximum age
                    ),
                ],
            );
        } catch (Exception $exception) {
            $this->returnException(
                'eligibility module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the Contacts Locations Module, returning a collection for the Location field
     *
     * @param  null|object  $location_module  The location array data grabbed
     * @param  null|object  $trial_status     The status of the trial
     *
     * @return Collection
     */
    protected function parseLocation(null|object $location_module, null|object $trial_status, null|object $id_module)
    :Collection
    {
        try {
            $allowed_status = $this->trialStatus
                ->toArray();
            /**
             * Map through all the locations, and set them up for import. During this time
             * we will be getting the latitude and longitude from Google Maps
             */
            $locations = collect($location_module->LocationList->Location ?? [])
                ->filter();

            if ($locations->isNotEmpty()) {
                $trial_status = $trial_status->OverallStatus ?? '';
                return $locations
                    ->map(function ($location) use ($allowed_status, $trial_status) {
                        $location_status = $location->LocationStatus ?? '';
                        $country         = $location->LocationCountry ?? '';
                        $has_status      = false;
                        $in_array        = false;

                        /**
                         * Grab the phone number for the contact
                         */
                        $contact_list = $location->LocationContactList ?? false;
                        if ($contact_list && property_exists($contact_list, 'LocationContact')) {
                            $phone = $contact_list->LocationContact[0]->LocationContactPhone ?? '';
                        }

                        if ($this->allowedTrialLocations->isNotEmpty()) {
                            $in_array = $this->allowedTrialLocations->contains(Str::lower($country));
                        }

                        if (!$in_array && $this->disallowedTrialLocations->isNotEmpty()) {
                            $in_array = !$this->disallowedTrialLocations->contains(Str::lower($country));
                        }

                        if (in_array(Str::lower($location_status), $allowed_status) || $trial_status) {
                            $has_status = true;
                        }

                        $languages = collect();
                        if ($this->countryMappedLanguages->isNotEmpty()) {
                            $languages = $this->mapLanguage($country);
                        }

                        if ($in_array && $has_status) {
                            return [
                                'city'              => $location->LocationCity ?? '',
                                'country'           => $country,
                                'facility'          => trim($this->filterParenthesis($location->LocationFacility ?? '')),
                                'id'                => Str::camel(sanitize_title($location->LocationFacility ?? '')),
                                'phone'             => $phone ?? '',
                                'post_title'        => strtr(
                                    $location->LocationFacility ?? '',
                                    [
                                        '( ' => '(',
                                        ' )' => ')',
                                    ],
                                ),
                                'location_language' => $languages->isNotEmpty() ? $languages->implode(';') : "All",
                                'recruiting_status' => $location_status ?: $trial_status,
                                'state'             => $location->LocationState ?? '',
                                'zip'               => $location->LocationZip ?? '',
                            ];
                        }

                        return [];
                    })
                    ->filter()
                    ->values();
            }

            return collect();
        } catch (Exception $exception) {
            $this->returnException(
                'location module',
                $exception->getMessage(),
            );
        }

        return collect();
    }

    /**
     * Parses the IPD Sharing Statement Module, not currently used
     *
     * @param  object  $ipd_module
     *
     * @return Collection
     */
    protected function parseIDP(object $ipd_module)
    :Collection
    {
        return collect(
            [],
        );
    }

    /**
     * Sets up the array needed to create or update a post
     *
     * @param  array  $post_args  The post args to set up for a wp_insert_post or wp_create_post
     *
     * @return array
     */
    protected function parsePostArgs(array $post_args)
    :array
    {
        return [
            'post_title'   => $post_args['title'] ?? '',
            'post_name'    => sanitize_title($post_args['slug'] ?? ($post_args['title'] ?? '')),
            'post_content' => $post_args['content'] ?? '',
            'post_excerpt' => isset($post_args['content']) ? Helper::generateExcerpt($post_args['content'], 31) : '',
        ];
    }

    /**
     * Filters through all the locations to determine whether the trial
     * should be imported or skipped
     *
     * @param  object|null  $location_module
     * @param  object|null  $trial_status
     * @param  object|null  $id_module
     *
     * @return bool
     */
    protected function hasImportableLocations(null|object $location_module, null|object $trial_status, null|object $id_module)
    :bool
    {
        try {
            $allowed_status = $this->trialStatus
                ->toArray();
            /**
             * Map through all the locations, and set them up for import. During this time
             * we will be getting the latitude and longitude from Google Maps
             */
            $locations = collect($location_module->LocationList->Location ?? [])
                ->filter();

            if ($locations->isNotEmpty()) {
                $trial_status = $trial_status->OverallStatus ?? '';
                return $locations
                    ->map(function ($location) use ($allowed_status, $trial_status) {
                        $location_status      = $location->LocationStatus ?? '';
                        $country              = $location->LocationCountry ?? '';
                        $lower_country        = Str::lower($country);
                        $allowed_locations    = $this->allowedTrialLocations;
                        $disallowed_locations = $this->disallowedTrialLocations;
                        $has_status           = false;
                        $in_array             = false;

                        if ($allowed_locations->isNotEmpty()) {
                            $in_array = $allowed_locations->contains($lower_country);
                        }

                        if (!$in_array && $disallowed_locations->isNotEmpty()) {
                            $in_array = !$disallowed_locations->contains($lower_country);
                        }

                        if ($in_array && (in_array(Str::lower($location_status), $allowed_status) || $trial_status)) {
                            $has_status = true;
                        }

                        return $in_array && $has_status;
                    })
                    ->filter()
                    ->values()
                    ->isNotEmpty();
            }

            return false;
        } catch (Exception $exception) {
            $this->returnException(
                'location module',
                $exception->getMessage(),
            );
            return false;
        }
    }

    /**
     * Checks if a numerical value is between the min_value and max_value
     *
     * @param  int  $value      The value
     * @param  int  $min_value  The minimum value
     * @param  int  $max_value  The maximum value
     *
     * @return bool
     */
    protected function inBetween(int $value, int $min_value, int $max_value)
    :bool
    {
        if ($value < $min_value) {
            return false;
        }
        if ($value > $max_value) {
            return false;
        }

        return true;
    }

    /**
     * Quick filter to remove items with parenthesis in them
     *
     * @param  string  $text  The text to filter parenthesis out of
     *
     * @return null|array|string|string[]
     */
    protected function filterParenthesis(string $text)
    :array|string|null
    {
        return preg_replace('#\([^)]+\)#i', '', $text);
    }

    /**
     * Quick filter to extract only the items in parentheses
     *
     * @param  string  $text
     *
     * @return array|false|int
     */
    protected function extractParenthesis(string $text)
    :bool|int|array
    {
        preg_match_all('#\((.*?)\)#', $text, $parenthesis_text);

        return $parenthesis_text[1] ?? [];
    }

    /**
     * Easier method to combine acf data updates
     *
     * @param  string      $field_name  The ACF field name
     * @param  mixed       $field_data  The data to save
     * @param  string|int  $post_id     The post ID to save to
     *
     * @return bool|int
     */
    protected function updateACF(string $field_name, mixed $field_data, string|int $post_id)
    :bool|int
    {
        return update_field($field_name, $field_data, $post_id);
    }

    /**
     * Maps through the ACF group ID to use for import
     *
     * @param  string  $acf_field  The ACF Group ID
     *
     * @return Collection
     */
    protected function getFieldGroup(string $acf_field)
    :Collection
    {
        $fields = collect(acf_get_fields($acf_field));

        return $fields->isNotEmpty() ? self::mapAcfGroup($fields) : collect();
    }

    /**
     * This function will take a single item array and loop through it, removing any parenthesis
     * and return the capitalization of each first word
     *
     * @param  array  $collection
     *
     * @return array
     */
    protected function standardizeArrayWords(array $collection)
    :array
    {
        return collect($collection)
            ->filter()
            ->map(function ($keyword) {
                if ($keyword) {
                    return ucwords(
                        trim(
                            rtrim($this->filterParenthesis($keyword), ',')
                        )
                    );
                }

                return false;
            })
            ->filter()
            ->toArray();
    }

    /**
     * Maps through a Collection of ACF fields provided, ignoring any defined ones.
     *
     * @param  Collection  $fields          A collection of the ACF Group ID
     * @param  array       $ignored_fields  Any field types to ignore. Defaults are tab and message
     *
     * @return Collection
     */
    protected function mapAcfGroup(Collection $fields, array $ignored_fields = [])
    :Collection
    {
        $default_ignore = ['tab', 'message'];

        $ignored_fields = wp_parse_args($ignored_fields, $default_ignore);

        return $fields
            ->filter(fn ($field) => $field['type'] && !in_array($field['type'], $ignored_fields))
            ->map(function ($field) use ($ignored_fields) {
                $field_name = $field['name'] ?? '';
                $field_arr  = [
                    'data_name'     => str_replace('api_data_', '', $field_name),
                    'default_value' => $field['default_value'] ?? false,
                    'key'           => $field['key'] ?? false,
                    'name'          => $field_name ?: false,
                    'type'          => $field['type'] ?? false,
                ];

                $sub_fields = $field['sub_fields'] ?? false;
                if ($sub_fields) {
                    $field_arr['sub_fields'] = collect($sub_fields)
                        ->map(fn ($sub_field) => $sub_field['type'] && !in_array($sub_field['type'], $ignored_fields)
                            ? [
                                'default_value' => $sub_field['default_value'] ?? false,
                                'key'           => $sub_field['key'] ?? false,
                                'name'          => $sub_field['name'] ?? false,
                                'type'          => $sub_field['type'],
                            ]
                            : false)
                        ->filter()
                        ->values();
                }

                return $field_arr;
            })
            ->values();
    }

    /**
     * Quick call to an error logging
     *
     * @param  string  $module  The module the error occurred
     * @param  string  $error   The error message
     *
     * @return void
     */
    private function returnException(string $module, string $error)
    :void
    {
        $this
            ->errorLog
            ->error("Error with parsing the $module for $this->nctId.\n\r $error");
    }
}
