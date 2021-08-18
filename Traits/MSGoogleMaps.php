<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Merck_Scraper\Helper\Helper;

/**
 * Traits for the Google Maps get location
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSGoogleMaps
{

    use MSApiTrait;
    use MSAcf;

    /**
     * The google maps API key
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
    private string $googleApiUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=';

    /**
     * The client call
     *
     * @var Client
     */
    private Client $client;

    /**
     * The error message for REST API
     *
     * @var array
     */
    private array $error;

    public function __construct()
    {
        $this->apiKey = self::acfOptionField('google_maps_api_key');
        $this->client = new Client(['verify' => false]);
    }

    /**
     * Grabs the lat/lng for a place based on the address
     *
     * @param $param
     *
     * @throws ClientException
     */
    public function getCoordinates($param)
    {
        $coords = '';
        try {
            $request_uri = $this->googleApiUrl;
            $address = urlencode(implode('+', $param));
            $uri_key = "&key={$this->apiKey}";

            $response = $this->client
                ->request(
                    'GET',
                    $request_uri . $address . $uri_key
                );

            if ($response->getStatusCode() == '200') {
                $body_res = (string) $response->getBody();
                $body_res = json_encode($body_res);

                if (!property_exists($body_res, 'error_message') && isset($body_res->results) && count($body_res->results) > 0) {
                    $latitude = $body_res->results[0]->geometry->location->lat ?: null;
                    $longitude = $body_res->results[0]->geometry->location->lng ?: null;

                    $coords = (object) [
                        'lat' => !is_null($latitude) ? (float) $latitude : null,
                        'lng' => !is_null($longitude) ? (float) $longitude : null,
                    ];
                } else {
                    $coords = 'Google Map API - ' . $body_res->error_message;
                }
            }
        } catch (ClientException $exception) {
            $coords = $exception->getResponse()->getBody()->getContents();
        }

        return $coords;
    }

}
