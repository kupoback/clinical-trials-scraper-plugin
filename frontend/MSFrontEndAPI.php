<?php

declare(strict_types=1);

namespace Merck_Scraper\frontend;

use Merck_Scraper\Traits\MSApiTrait;
use Merck_Scraper\Traits\MSGoogleMaps;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The acessible API for the site
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

    private string $taxName;

    public function __construct()
    {
        $this->taxName = $this->acfOptionField('category_type') ?: 'conditions';
    }

    public function registerEndpoint()
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
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
                'secret_key' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return $param === $_ENV['SECRET_KEY'];
                    },
                ],

            ]
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
     * @param WP_REST_Request $request
     *
     * @link EndpointExample https://merck.test/wp-json/merck-scraper/v1/get-geolocation/
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function getGeolocation(WP_REST_Request $request)
    {
        $the_post      = get_post($request['id']);
        $location_type = $request->get_param('location_type') ?? 'api_data_locations';

        if (!empty($the_post)) {
            return rest_ensure_response(
                collect(get_field($location_type, $the_post->ID))
                    ->map(function ($location, $key) {

                        $get_location = [
                            $location['facility'] ?: '',
                            $location['city'] ?: '',
                            $location['state'] ?: '',
                            $location['zipcode'] ?: '',
                            $location['country'] ?: '',
                        ];
                        return rest_ensure_response($this->getLatLng($get_location));
                    })
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
    {
        $return = collect();

        $trials = new WP_Query(
            [
                'post_type' => 'trials',
                'posts_per_page' => -1,
                'post_status' => $this->acfOptionField('post_status'),
                'fields' => 'ids',
            ]
        );

        if (!is_wp_error($trials) && $trials->found_posts > 0) {
            $return->put('count', $trials->found_posts);
            $return->put(
                'trials',
                collect($trials->posts)
                    ->map(function ($trial) {
                        $categories = wp_get_post_terms($trial, $this->taxName);
                        return [
                            'nct_id' => get_field('api_data_nct_id', $trial),
                            'categories' => (!is_wp_error($categories) && count($categories) > 0) ?
                                collect($categories)
                                ->map(function ($category) {
                                    return [
                                        'name' => $category->name,
                                        'slug' => $category->slug,
                                    ];
                                }) : []
                        ];
                    })
            );
            return rest_ensure_response($return->toArray());
        }

        return rest_ensure_response(['count' => 0, 'trials' => []]);
    }
}
