<?php

declare(strict_types=1);

namespace Merck_Scraper\Traits;

use Illuminate\Support\Collection;
use WP_Error;

/**
 * Traits for the Google Maps get location
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSGoogleMaps
{

    use MSAcfTrait;
    use MSApiTrait;
    use MSHttpCallback;

    /**
     * @var string The Google Maps API key
     */
    private string $apiKey = '';

    /**
     * The Google Request URL
     *
     * @since    1.0.0
     * @access   private
     * @var      string $google_api_url The URL request from Google
     */
    private string $googleApiUrl = 'https://maps.googleapis.com';

    /**
     * @var string $geoCodeEP The endpoint to get the geocode
     */
    private string $geoCodeEP = '/maps/api/geocode/json';

    /**
     * @var array The error message for REST API
     */
    private array $error;

    /**
     * Grabs the full locations' data from Google's Map API
     *
     * @param array $location
     *
     * @return Collection|mixed|WP_Error
     */
    protected function getFullLocation(array $location = [])
    {
        $gm_api_callback = $this->googleMapsApiCB(
            collect($location)
                ->map(function ($location) {
                    return urlencode($location);
                })
                ->implode('+')
        );

        if (!is_wp_error($gm_api_callback)) {
            $address = $this->parseAddress(collect($gm_api_callback->address_components));
            if ($gm_api_callback->geometry->location ?? false) {
                $address->put('latitude', $gm_api_callback->geometry->location->lat);
                $address->put('longitude', $gm_api_callback->geometry->location->lng);
            }

            return $address->filter();
        }

        return $gm_api_callback;
    }

    /**
     * Grabs the lat/lng for a place based on the address
     *
     * @param array $location
     *
     * @return false|mixed|WP_Error
     */
    protected function getLatLng(array $location = [])
    {
        $gm_api_callback = $this->googleMapsApiCB(
            collect($location)
                ->implode('+')
        );
        if (!is_wp_error($gm_api_callback)) {
            if ($gm_api_callback->geometry ?? false) {
                return $gm_api_callback->geometry->location ?? false;
            }
        }

        return $gm_api_callback;
    }

    /**
     * Parses the Google Maps API address_components
     *
     * @param Collection $address
     *
     * @return Collection
     */
    protected function parseAddress(Collection $address): Collection
    {
        if ($address->isNotEmpty()) {
            $accepted_types = [
                'subpremise', // Floor/Apt/Unit Number
                'street_number', // Street number
                'route', // Street Name
                'locality', // City
                'administrative_area_level_1', // State
                'postal_code', // Zip Code
                'country', // Country Name
            ];
            $address        = $address
                ->filter(function ($array) use ($accepted_types) {
                    $types = collect($array->types)
                        ->filter(function ($type) use ($accepted_types) {
                            return in_array($type, $accepted_types);
                        })
                        ->filter()
                        ->values();
                    return $types->isNotEmpty();
                })
                ->mapWithKeys(function ($array) {
                    $types = collect($array->types)
                        ->filter(function ($type) {
                            return $type !== 'political';
                        });

                    $the_type = $types->first();
                    switch ($the_type) {
                        case "locality":
                            $the_type = 'city';
                            break;
                        case "administrative_area_level_1":
                            $the_type = 'state';
                            break;
                        case "postal_code":
                            $the_type = 'zipcode';
                            break;
                        default:
                            break;
                    }
                    return [$the_type => $array->long_name];
                })
                ->filter();

            $subpremise    = $address->pull('subpremise');
            $street_number = $address->pull('street_number');
            $street_name   = $address->pull('route');

            if ($subpremise && $street_number && $street_name) {
                $street_address = "$subpremise $street_number $street_name";
            } elseif (!$subpremise && $street_number && $street_name) {
                $street_address = "$street_number $street_name";
            } else {
                $street_address = $street_name ?: '';
            }
            $address->put('street', $street_address);

            return $address->filter();
        }
        return collect();
    }

    /**
     * Makes an API call to Google Maps, and returns with the response or WP_Error
     *
     * @param string $address
     *
     * @return mixed|WP_Error
     */
    protected function googleMapsApiCB(string $address)
    {
        set_time_limit(120);
        ini_set('max_execution_time', '120');
        if ($this->gmApiKey ?? false) {
            $response = $this->httpCallback(
                $this->googleApiUrl,
                $this->geoCodeEP,
                "GET",
                [
                    'address' => $address,
                    'key'     => $this->gmApiKey,
                ],
                [
                    'http_args' => [
                        'delay' => 150,
                    ],
                    'guzzle'    => [
                        'verify' => true,
                    ],
                ]
            );

            if ($response->getStatusCode() == '200') {
                $body_res = (string) $response->getBody();
                $body_res = json_decode($body_res);

                if ($body_res->status === 'OK' && !empty($body_res->results)) {
                    return $body_res->results[0];
                } else {
                    $this
                        ->errorLog
                        ->error("Error getting location", $body_res);
                    return new WP_Error(
                        $response->getStatusCode(),
                        "Unable to get location." . PHP_EOL . ($body_res ?? '')
                    );
                }
            }

            return new WP_Error(400, __("There was an error with the Google Maps Call"));
        }

        return new WP_Error(412, __("No Google Maps API key provided", 'merck-scraper'));
    }
}
