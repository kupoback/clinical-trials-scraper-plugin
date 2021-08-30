<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use Illuminate\Support\Collection;
use Merck_Scraper\Helper\Helper;
use Merck_Scraper\Traits\MsContentFormat;

/**
 * Traits for the Merck Scraper API fields
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSApiFieldTrait
{

    use MsContentFormat;

    /**
     * Parses the IdentificationModule object field, returning the Post Title, NCTID, and Official Title field
     *
     * @param object $id_module The IdentificationModule object from the gov't API data
     *
     * @return Collection
     */
    protected function parseId(object $id_module)
    {
        $other_ids = collect($id_module->SecondaryIdInfoList->SecondaryIdInfo ?? []);
        if ($other_ids->isNotEmpty()) {
            $other_ids = $other_ids
                ->map(function ($second_id) {
                    return $second_id->SecondaryId ?? '';
                })
                ->filter();
        }

        return collect(
            [
                'post_title'     => $id_module->BriefTitle ?? '',
                'nct_id'         => $id_module->NCTId ?? '',
                'official_title' => $id_module->OfficialTitle ?? '',
                'other_ids'      => $other_ids,
            ]
        );
    }

    /**
     * Parses the StatusModule object field, returning the Trial Status taxonomy term, Start Date, Primary Completion Date,
     * Completion Date, Study First Post Date, and Results First Post Date fields
     *
     * @param object $status_module
     *
     * @return Collection
     */
    protected function parseStatus(object $status_module)
    {
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
            ]
        );
    }

    /**
     * Parses the Sponsor Collaborators Module, returning the Lead Sponsor Name field
     *
     * @param object $sponsor_module
     *
     * @return Collection
     */
    protected function parseSponsors(object $sponsor_module)
    {
        return collect(
            [
                'lead_sponsor_name' => $sponsor_module
                        ->LeadSponsor
                        ->LeadSponsorName ?? '',
            ]
        );
    }

    /**
     * Parses the Oversight Module, not currently used
     *
     * @param object $oversight_module
     *
     * @return Collection
     */
    protected function parseOversight($oversight_module)
    {
        return collect(
            [

            ]
        );
    }

    /**
     * Parses the Description Module, returning the Brief Summary as post content
     *
     * @param $description_module
     *
     * @return Collection
     */
    protected function parseDescription(object $description_module)
    {
        return collect(
            [
                'post_content' => $description_module->BriefSummary ?? '',
            ]
        );
    }

    /**
     * Parses the Condition Module, returning a list of Conditions and a list of Keywords for taxonomy terms
     *
     * @param object $condition_module
     *
     * @return Collection
     */
    protected function parseCondition(object $condition_module)
    {
        return collect(
            [
                'conditions' => $condition_module
                        ->ConditionList
                        ->Condition ?? [],
                'keywords'   => $condition_module
                        ->KeywordList
                        ->Keyword ?? [],
            ]
        );
    }

    /**
     * Parses the Design Info Module, returning an array of Phases, the Study Type, and a collection of Study Designs
     *
     * @param object $design_module
     *
     * @return Collection
     */
    protected function parseDesign(object $design_module)
    {
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
                    ->map(function ($phase) {
                        return ['phase' => $phase];
                    }),
                // 'study_type'    => $design_module->StudyType,
                // 'study_designs' => $study_design,
            ]
        );
    }

    /**
     * Parses the Arms Interventions Module, returning a collection of Interventions
     *
     * @param object $arms_module
     *
     * @return Collection
     */
    protected function parseArms(object $arms_module)
    {
        $interventions = $arms_module->InterventionList ?? [];
        if (is_object($interventions) && !empty($interventions->Intervention)) {
            $intervention_arr = collect($interventions->Intervention)
                ->map(function ($arr_item) {
                    return [
                        'type' => $arr_item->InterventionType ?? '',
                        'name' => $arr_item->InterventionName ?? '',
                    ];
                })
                ->filter();
        }

        unset($interventions);

        return collect(
            [
                'interventions' => $intervention_arr ?? [],
            ]
        );
    }

    /**
     * Parses the Outcomes Module, returning a collection of Outcome Measures
     *
     * @param object $outcome_module
     *
     * @return Collection
     */
    protected function parseOutcome(object $outcome_module)
    {
        $outcomes        = collect([]);
        $primary_outcome = $outcome_module
                ->PrimaryOutcomeList
                ->PrimaryOutcome ?? [];
        $second_outcome  = $outcome_module
                ->SecondaryOutcomeList
                ->SecondaryOutcome ?? [];

        if (!empty($primary_outcome)) {
            collect($primary_outcome)
                ->map(function ($outcome) use ($outcomes) {
                    $outcomes->push(['measure' => $outcome->PrimaryOutcomeMeasure]);
                    return false;
                });
        }

        if (!empty($second_outcome)) {
            collect($second_outcome)
                ->map(function ($outcome) use ($outcomes) {
                    $outcomes->push(['measure' => $outcome->SecondaryOutcomeMeasure]);
                    return false;
                });
        }

        unset($primary_outcome, $second_outcome);

        return collect(
            [
                'outcome_measures' => $outcomes,
            ]
        );
    }

    /**
     * Parses the Eligibility Module, returning the Gender, Minimum Age, and Maximum Age fields
     *
     * @param object $eligibility_module
     *
     * @return Collection
     */
    protected function parseEligibility(object $eligibility_module)
    {
        return collect(
            [
                'gender'      => $eligibility_module->Gender ?? '',
                'minimum_age' => self::stripYears($eligibility_module->MinimumAge ?? '') ?: 0,
                'maximum_age' => self::stripYears($eligibility_module->MaximumAge ?? '') ?: 999,
            ]
        );
    }

    /**
     * Parses the Contacts Locations Module, returning a collection for the Location field
     *
     * @param object $location_module
     *
     * @return Collection
     */
    protected function parseLocation(object $location_module)
    {
        /**
         * Map through all the locations, and set them up for import. During this time
         * we will be getting the latitude and longitude from Google Maps
         */
        $locations = collect($location_module->LocationList->Location ?? []);
        if ($locations->isNotEmpty()) {
            $locations = $locations
                ->map(function ($location) {
                    $us_names = ["United States", "United States of America", "USA"];
                    $status = ['Recruiting'];
                    if (in_array($location->LocationCountry, $us_names) && in_array($location->LocationStatus, $status)) {
                        return [
                            'city'              => $location->LocationCity ?? '',
                            'country'           => $location->LocationCountry ?? '',
                            'facility'          => $location->LocationFacility ?? '',
                            'recruiting_status' => $location->LocationStatus ?? '',
                            'state'             => $location->LocationState ?? '',
                            'zip'               => $location->LocationZip ?? '',
                        ];
                    }
                    return false;
                })
                ->filter();
        }

        return collect(
            [
                'locations' => $locations,
            ]
        );
    }

    /**
     * Parses the IPD Sharing Statement Module, not currently used
     *
     * @param object $ipd_module
     *
     * @return Collection
     */
    protected function parseIDP(object $ipd_module)
    {
        return collect(
            [

            ]
        );
    }

    /**
     * Sets up the array needed to create or update a post
     *
     * @param array $post_args The post args to setup for an wp_insert_post or wp_create_post
     *
     * @return array
     */
    protected function parsePostArgs(array $post_args)
    {
        return [
            'post_title'   => $post_args['title'],
            'post_name'    => sanitize_title($post_args['slug']),
            'post_content' => $post_args['content'],
            'post_excerpt' => $post_args['content'] ? Helper::generateExcerpt($post_args['content'], 31) : '',
        ];
    }

    /**
     * Easier method to combine acf data updates
     *
     * @param string $field_name The ACF field name
     * @param mixed  $field_data The data to save
     * @param int    $post_id    The post ID to save to
     *
     * @return bool
     */
    protected function updateACF(string $field_name, $field_data, int $post_id)
    {
        return update_field($field_name, $field_data, $post_id);
    }

    /**
     * Maps through the Trials Field group and parses the data for importing
     *
     * @return Collection|mixed
     */
    protected function trialsFieldGroup()
    {
        $fields = collect(
            acf_get_fields('group_60fae8b82087d')
        );

        $ignored_fields = ['tab', 'message'];

        if ($fields->isNotEmpty()) {
            return $fields
                ->filter(function ($field) use ($ignored_fields) {
                    return $field['type'] && !in_array($field['type'], $ignored_fields);
                })
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
                            ->map(function ($sub_field) use ($ignored_fields) {
                                if ($sub_field['type'] && !in_array($sub_field['type'], $ignored_fields)) {
                                    return [
                                        'default_value' => $sub_field['default_value'] ?? false,
                                        'key'           => $sub_field['key'] ?? false,
                                        'name'          => $sub_field['name'] ?? false,
                                        'type'          => $sub_field['type'] ?? false,
                                    ];
                                }
                                return false;
                            })
                            ->filter()
                            ->values();
                    }
                    return $field_arr;
                })
                ->values();
        }

        return collect([]);
    }
}
