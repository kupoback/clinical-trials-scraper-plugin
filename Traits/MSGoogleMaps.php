<?php

declare(strict_types = 1);

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

    use MSAcf;
    use MSApi;
    use MSHttpCallback;

    /**
     * The google maps API key
     *
     * @var string
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
     * The endpoint to get the geocode
     *
     * @var string $geoCodeEP
     */
    private string $geoCodeEP = '/maps/api/geocode/json';

    /**
     * The error message for REST API
     *
     * @var array
     */
    private array $error;

    protected function getFullLocation(array $location = [])
    {
        $gm_api_callback = self::googleMapsApiCB(
            collect($location)
                ->implode('+')
        );

        if (!is_wp_error($gm_api_callback)) {
            $address = self::parseAddress(collect($gm_api_callback->address_components));
            if ($gm_api_callback->geometry->location ?? false) {
                $address->put('latitude', $gm_api_callback->geometry->location->lat);
                $address->put('longitude', $gm_api_callback->geometry->location->lng);
            }

            return $address->filter();
        }
        return new WP_Error($gm_api_callback);
    }

    /**
     * Grabs the lat/lng for a place based on the address
     *
     * @param array $location
     */
    protected function getLatLng(array $location = [])
    {
        $gm_api_callback = self::googleMapsApiCB(
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
    protected function parseAddress(Collection $address)
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
                    $types    = collect($array->types)
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

            $subprem       = $address->pull('subpremise');
            $street_number = $address->pull('street_number');
            $street_name   = $address->pull('route');

            if ($subprem && $street_number && $street_name) {
                $street_address = "{$subprem} {$street_number} {$street_name}";
            } elseif (!$subprem && $street_number && $street_name) {
                $street_address = "{$street_number} {$street_name}";
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
     * @param $param
     */
    protected function googleMapsApiCB($address)
    {
        $coords = '';
        $api_key  = self::acfOptionField('google_maps_api_key');
        $response = self::httpCallback(
            $this->googleApiUrl,
            $this->geoCodeEP,
            "GET",
            [
                'address' => $address,
                'key'     => $api_key,
            ],
            [
                'http_args' => [
                    'delay' => 180,
                ],
                'guzzle'    => [
                    'verify' => false,
                ],
            ]
        );

        if ($response->getStatusCode() == '200') {
            $body_res = (string) $response->getBody();
            $body_res = json_decode($body_res);

            if ($body_res->status === 'OK' && !empty($body_res->results)) {
                return $body_res->results[0];
            } else {
                return new WP_Error($response->getStatusCode(), $body_res->error_message ?? '');
            }
        }

        return $coords;
    }
}
