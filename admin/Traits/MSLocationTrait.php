<?php

namespace Merck_Scraper\Admin\Traits;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use WP_Post;
use WP_Query;

trait MSLocationTrait
{
    /**
     * This method parses through all the locations for the study just imported.
     *
     * @param  Collection  $locations  A collection of locations
     * @param  string      $nct_id     The NCT ID of the location
     *
     * @return Collection
     * @throws Exception
     */
    protected function locationsImport(Collection $locations, string $nct_id)
    :Collection
    {
        $location_ids = collect();

        if ($locations->isNotEmpty()) {
            $import_position = get_option('merck_import_position');
            /**
             * Grab all locations based on its unique ID, so that we aren't importing the same location more than once
             */
            $existing_locations = $locations
                ->mapWithKeys(function ($location) {
                    $post_id = intval($this->dbFetchPostId('meta_value', $location['id']));
                    if ($post_id > 0) {
                        return [
                            $post_id => collect(get_fields($post_id))
                                ->mapWithKeys(fn ($value, $key) => [
                                    str_replace('api_data_', '', $key) => $value,
                                ])
                                ->merge(
                                    [
                                        'latitude'  => get_post_meta($post_id, 'ms_location_latitude', true),
                                        'longitude' => get_post_meta($post_id, 'ms_location_longitude', true),
                                    ],
                                )
                                ->filter()
                                ->toArray(),
                        ];
                    }

                    return [0 => false];
                })
                ->filter()
                ->unique();

            $total_items = $locations
                ->count();

            // Fetch, filter and sanitize the locations' data
            self::locationGeocode($locations, $existing_locations)
                // Map through each location and create or update the location post
                ->each(function ($location, $index) use ($import_position, $location_ids, $nct_id, $total_items) {
                    $position = $index + 1;
                    $this->updatePosition(
                        "Import Locations",
                        wp_parse_args(
                            [
                                'total_count' => $this->totalFound,
                                'sub_data'    => [
                                    'helper'     => "Importing Locations for $nct_id",
                                    'position'   => $position,
                                    'title'      => "Importing Locations",
                                    'totalCount' => $total_items,
                                ],
                            ],
                            $import_position,
                        ),
                    );

                    if (!is_wp_error($location)) {
                        $location_data = collect($location);
                        if ($location_data->filter()
                                          ->isNotEmpty()) {
                            $location_post_id = intval($this->dbFetchPostId('meta_value', $location['id']));
                            $latitude         = $location_data->get('latitude');
                            $longitude        = $location_data->get('longitude');
                            $language         = $location_data->get('location_language');
                            $status           = $location_data->get('recruiting_status');

                            $location_data
                                ->forget(
                                    [
                                        'latitude',
                                        'longitude',
                                        'location_language',
                                        'recruiting_status',
                                    ],
                                );

                            // Set up the post data
                            $location_args = collect(
                                wp_parse_args(
                                    $this->parsePostArgs(
                                        [
                                            'title' => $location_data->get('post_title'),
                                            'slug'  => $location_data->get('facility'),
                                        ],
                                    ),
                                    $this->locationPostDefault,
                                ),
                            );

                            if ($location_post_id === 0) {
                                $location_post_id = wp_insert_post(
                                    $location_args
                                        ->toArray(),
                                    "Failed to create location post.",
                                );
                            } else {
                                $location_args
                                    ->put('ID', $location_post_id);
                                $location_args
                                    ->forget(['post_title', 'post_name']);
                                wp_update_post(
                                    $location_args
                                        ->toArray(),
                                    "Failed to update post.",
                                );
                            }

                            /**
                             * Update the ACF Fields for this location, and set the latitude and longitude grabbed to the custom meta field
                             */
                            if ($location_post_id) {
                                $acf_fields = $this->locationFields;
                                $acf_fields
                                    ->map(fn ($field) => Str::contains($field['name'], 'api')
                                        ? $this->updateACF(
                                            $field['name'],
                                            $location_data
                                                ->get($field['data_name'] ?? ''),
                                            $location_post_id,
                                        )
                                        : false);

                                collect(
                                    [
                                        'location_nctid'  => $nct_id,
                                        'location_status' => $status,
                                        'trial_language'  => explode(';', $language),
                                    ],
                                )
                                    ->each(fn ($terms, $taxonomy) => wp_set_object_terms($location_post_id, $terms, $taxonomy, true));

                                update_post_meta($location_post_id, 'ms_location_latitude', $latitude);
                                update_post_meta($location_post_id, 'ms_location_longitude', $longitude);
                                $location_ids->push($location_post_id);
                            }
                        }
                    }
                });
        }

        return $location_ids;
    }

    /**
     * Grabs the geocode location data from Google Maps, but only if the location doesn't
     * exist in the first place
     *
     * @param  Collection  $arr_data            The array data imported
     * @param  Collection  $existing_locations  The existing locations
     *
     * @return Collection
     */
    protected function locationGeocode(Collection $arr_data, Collection $existing_locations)
    :Collection
    {
        set_time_limit(1800);
        ini_set('max_execution_time', '1800');

        return $arr_data
            ->map(function ($location) use ($existing_locations) {
                // Grab and filter the facility
                $facility = trim($this->filterParenthesis($location['facility'] ?? ''));
                // Check if the location is already imported, so we don't grab the lat/lng again
                $data_grabbed = $existing_locations
                    ->filter(fn ($location) => ($location['latitude'] ?? false) && $location['facility'] === $facility);

                if (!isset($location['location_language'])) {
                    $location_language             = $this->mapLanguage($location['country']);
                    $location['location_language'] = $location_language->isNotEmpty()
                        ? $location_language->implode(';')
                        : "All";
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
                            ],
                        )
                            ->filter()
                            ->toArray(),
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
                                ],
                            )
                                ->filter()
                                ->toArray(),
                        );
                    }

                    /**
                     * If the geolocation was successful, then return the data as an array
                     */
                    if (!is_wp_error($gm_geocoder_data) && $gm_geocoder_data->isNotEmpty()) {
                        // Sets the location_language field data so that the trials can be filtered by language
                        $location_language = $this->mapLanguage($location['country'] ?? '');

                        // $gm_geocoder_data = $gm_geocoder_data
                        return $gm_geocoder_data
                            // Use default grabbed City/State from API, otherwise default to what google grabbed
                            ->put('city', $location['city'] ?? ($gm_geocoder_data->city ?? ''))
                            ->put('country', $location['country'] ?? ($gm_geocoder_data->country ?? ''))
                            // Add facility, recruiting status and phone number to the collection
                            ->put('facility', $facility)
                            ->put('id', $location['id'] ?? '')
                            ->put(
                                'location_language',
                                $location_language->isNotEmpty()
                                    ? $location_language->implode(';')
                                    : "All",
                            )
                            ->put('phone', ($location['phone'] ?? ''))
                            ->put('post_title', ($location['post_title'] ?? ''))
                            ->put('recruiting_status', ($location['recruiting_status'] ?? ''))
                            ->put('state', $location['state'] ?? ($gm_geocoder_data->city ?? ''))
                            ->toArray();

                        // return $gm_geocoder_data
                        //     ->toArray();
                    }

                    $this
                        ->errorLog
                        ->error(
                            "Unable to get geocode for $this->nctId;\r\n",
                            (array) $gm_geocoder_data->errors,
                        );

                    return $gm_geocoder_data;
                }

                return wp_parse_args($location, $data_grabbed->first());
            })
            ->filter();
    }

    /**
     * A Locations query setup with pagination
     *
     * @param  int  $paged  The current page to return
     *
     * @return int[]|WP_Post[]
     */
    protected function locationsQuery(int $paged = 1)
    :array
    {
        $locations = new WP_Query(
            [
                'post_type'      => 'locations',
                'posts_per_page' => 100,
                'paged'          => $paged,
                'fields'         => 'ids',
            ]
        );

        if (!is_wp_error($locations) && $locations->found_posts > 0) {
            return [
                'locations' => $locations->posts,
                'max_pages' => $locations->max_num_pages,
                'total'     => $locations->found_posts,
            ];
        }

        return [
            'locations' => [],
            'max_pages' => 0,
            'total'     => 0,
        ];
    }

    /**
     * This runs through the location and grabs all the necessary information
     * to update and import the locations' information, including lat/lng
     *
     * @param  int  $post_id
     *
     * @return Collection
     */
    protected function locationPostSetup(int $post_id)
    :Collection
    {
        $location = collect(
            [
                'street',
                'city',
                'state',
                'zipcode',
                'country',
            ],
        )
            ->mapWithKeys(fn ($field) => [
                $field => get_field("override_$field", $post_id)
                    ?: get_field("api_data_$field", $post_id),
            ]);

        $gm_geocoder_data = $this->getFullLocation(
            collect(
                [
                    get_the_title($post_id),
                    $location->get('city', ''),
                    $location->get('state', ''),
                    $location->get('zip', ($location->get('zipcode',  ''))),
                    $location->get('country', ''),
                ],
            )
                ->filter()
                ->toArray(),
        );

        if (!is_wp_error($gm_geocoder_data) && $gm_geocoder_data->get('latitude')) {
            update_post_meta($post_id, 'ms_location_latitude', $gm_geocoder_data->get('latitude'));
            update_post_meta($post_id, 'ms_location_longitude', $gm_geocoder_data->get('longitude'));

            return collect(
                [
                    'latitude'  => $gm_geocoder_data->get('latitude'),
                    'longitude' => $gm_geocoder_data->get('longitude'),
                ],
            );
        }

        return collect();
    }

    /**
     * Quick adjustment call to setting up the map and implode for a Collection
     *
     * @param  Collection  $collection  Collection
     * @param  string      $operator
     *
     * @return string
     */
    protected function mapImplode(Collection $collection, string $operator = "OR")
    :string
    {
        return $collection
            ->map(fn ($location) => "\"$location\"")
            ->implode(" $operator ");
    }

    /**
     * Maps through the location data and returns a filtered collection
     *
     * @param  string  $country  The country to search and map for
     *
     * @return Collection
     */
    protected function mapLanguage(string $country)
    :Collection
    {
        return $this->countryMappedLanguages
            ->map(fn ($location) => is_int($location['country']->search($country, true))
                ? $location['language']
                : false,
            )
            ->filter();
    }
}
