<?php

declare(strict_types = 1);

namespace Merck_Scraper\frontend;

use Merck_Scraper\Traits\MSApiTrait;
use Merck_Scraper\Traits\MSGoogleMaps;
use WP_Error;
use WP_HTTP_Response;
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

    public function __construct()
    {
    }

    public function registerEndpoint()
    {
        /**
         * Registers the endpoint to grab the Lat/Lng from Google Maps
         */
        self::registerRoute(
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
                        return rest_ensure_response(self::getLatLng($get_location));
                    })
            );
        }
        return rest_ensure_response(new WP_Error(300, __('Error with the API call')));
    }
}
