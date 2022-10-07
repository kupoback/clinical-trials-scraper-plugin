<?php

declare(strict_types=1);

namespace Merck_Scraper\Frontend;

use Merck_Scraper\Traits\MSApiTrait;
use Merck_Scraper\Traits\MSGoogleMaps;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The accessible API for the site
 *
 * Defines the plugin name, version.
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/frontend
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSFrontEndAPI
{

    use MSApiTrait;
    use MSGoogleMaps;

    /**
     * @var string The taxonomy name for mapping
     */
    private string $taxName;

    /**
     * @var string|mixed The Google Maps API key from the Database
     */
    private string $gmApiKey;

    public function __construct()
    {
    }

    public function registerEndpoint()
    :void
    {
        /**
         * Registers the endpoint to grab the Lat/Lng from Google Maps
         */
        $this->registerRoute(
            'get-geolocation',
            WP_REST_Server::READABLE,
            [$this, 'getGeolocation'],
            '',
            [
                'id'         => [
                    'required'          => true,
                    'validate_callback' => fn ($param) => is_numeric($param),
                ],
                'secret_key' => [
                    'required'          => true,
                    'validate_callback' => fn ($param) => $param === $_ENV['SECRET_KEY'],
                ],

            ],
        );

        /**
         * Serves the data for Antidote to grab data from
         */
        $this->registerRoute(
            'trials',
            WP_REST_Server::READABLE,
            [$this, 'getTrials'],
        );
    }

    /**
     * Method to grab the latitude and longitude from Google Maps. Requires
     * a POST ID and the Secret key from env
     *
     * @param  WP_REST_Request  $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     * @link EndpointExample https://merck.test/wp-json/merck-scraper/v1/get-geolocation/
     *
     */
    public function getGeolocation(WP_REST_Request $request)
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        self::instantiateClass();
        $the_post      = get_post($request['id']);
        $location_type = $request->get_param('location_type') ?? 'api_data_locations';

        if (!empty($the_post)) {
            return rest_ensure_response(
                collect(get_field($location_type, $the_post->ID))
                    ->map(fn ($location, $key) => rest_ensure_response(
                        self::getLatLng(
                            [
                                $location['facility'] ?: '',
                                $location['city'] ?: '',
                                $location['state'] ?: '',
                                $location['zipcode'] ?: '',
                                $location['country'] ?: '',
                            ]
                        )
                    )
                    )
            );
        }

        return rest_ensure_response(new WP_Error(300, __('Error with the API call')));
    }

    /**
     * Frontend API for Antidote that queries all the trials and returns
     * the nct_id and the "Conditions" category for each trial.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function getTrials()
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        self::instantiateClass();
        $return = collect();

        $trials = new WP_Query(
            [
                'post_type'      => 'trials',
                'posts_per_page' => -1,
                'post_status'    => $this->acfOptionField('post_status'),
                'fields'         => 'ids',
            ]
        );

        if (!is_wp_error($trials) && $trials->found_posts > 0) {
            return rest_ensure_response(
                $return
                    ->put('count', $trials->found_posts)
                    ->put(
                        'trials',
                        collect($trials->posts)
                            ->map(function ($trial) {
                                $categories = wp_get_post_terms($trial, $this->taxName);

                                return [
                                    'nct_id'     => get_field('api_data_nct_id', $trial),
                                    'categories' => (!is_wp_error($categories) && count($categories) > 0)
                                        ? collect($categories)
                                            ->map(fn ($category) => [
                                                'name' => $category->name,
                                                'slug' => $category->slug,
                                            ])
                                        : [],
                                ];
                            }),
                    )
                    ->toArray());
        }

        return rest_ensure_response(['count' => 0, 'trials' => []]);
    }

    /**
     * Instantiates the Class variables from ACF, but only when the method is called
     *
     * @return void
     */
    protected function instantiateClass()
    :void
    {
        $this->taxName  = $this->acfOptionField('category_type') ?: 'conditions';
        $this->gmApiKey = $this->acfOptionField('google_maps_server_side_api_key');
    }
}
