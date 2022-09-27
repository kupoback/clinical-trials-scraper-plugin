<?php

declare(strict_types=1);

namespace Merck_Scraper\Frontend;

/**
 * Plugin Classes
 */

use Error;
use Geocoder\Collection;
use Geocoder\Exception\Exception;
use Geocoder\Provider\GeoPlugin\GeoPlugin;
use Geocoder\Provider\Provider;
use Geocoder\ProviderAggregator;
use Geocoder\Query\GeocodeQuery;
use Http\Adapter\Guzzle7\Client;
use WP_Error;

/**
 * Merck Class/Traits
 */

use Merck_Scraper\Traits\MSLoggerTrait;

/**
 * Grabs the users location info based on an IP provided
 *
 * Defines the plugin name, version.
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/frontend
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 * @uses       Geocoder https://github.com/geocoder-php/Geocoder
 */
class MSUserLocation
{

    use MSLoggerTrait;

    /**
     * @var string The users IP
     */
    private string $userLocation;

    /**
     * @var array The provider used for getting the location
     */
    private array $provider;

    /**
     * @var string The providers using name
     */
    private string $usingName;

    /**
     * @var WP_Error The error message when no user IP is provided
     */
    private WP_Error $noUserIp;

    /**
     * @var bool[] The basic return if locations cannot be grabbed
     */
    private array $errReturn;

    /**
     * MSUserLocation __construct
     *
     * @param  string           $userLocation  The users location
     * @param  array{Provider}  $provider      An array of service providers to override the FreeGeoIp used
     * @param  string           $using_name    The service providers using name.
     */
    public function __construct(string $userLocation = '', array $provider = [], string $using_name = 'free_geo_ip')
    {
        $this->userLocation = $userLocation;
        $this->noUserIp     = new WP_Error(400, "No user IP defined");
        $this->usingName    = $using_name;
        $this->errReturn    = ['err' => true,];

        if (!empty($provider)) {
            $this->provider = $provider;
        } else {
            $this->provider = [new GeoPlugin(new Client())];
        }
    }

    /**
     * Grabs the full users location and returns the results
     *
     */
    public function getLocations()
    :array|WP_Error
    {
        if (!$this->userLocation) {
            return $this->noUserIp;
        }

        $err_return              = $this->errReturn;
        $err_return['locations'] = [];

        $results = $this->geoQueryCallback();

        if (!$results->isEmpty() && !is_wp_error($results)) {
            return [
                'err'       => false,
                'locations' => $results,
            ];
        }

        return $err_return;
    }

    /**
     * Method to grab the {
     *  adminLvlCode Example: FL
     *  adminLvlFullName Example: Florida
     *  coordinates: latitude and longitude
     *  countryName: Example: United States
     *  countryCode: Example: US
     *  locality: Example: Jacksonville - pretty much "city"
     *  postalCode: Example: 60601 - Generally a "Zip code"
     *  street: Street Name
     *  streetNumber: The Street Number
     *  timezone: Timezone based on the region
     * }
     *
     * @return WP_Error|array
     * @throws Exception
     */
    public function getFirstLocation()
    :WP_Error|array
    {
        if (!$this->userLocation) {
            return $this->noUserIp;
        }

        $results = $this->geoQueryCallback();

        if (!$results->isEmpty() && !is_wp_error($results)) {
            $first_result = $results->first();

            return [
                'err'      => false,
                'location' => [
                    'adminLvlAbbv'     => $first_result
                        ->getAdminLevels()
                        ->first()
                        ->getCode(),
                    'adminLvlFullname' => $first_result
                        ->getAdminLevels()
                        ->first()
                        ->getName(),
                    'coordinates'      => [
                        'lat' => $first_result
                            ->getCoordinates()
                            ->getLatitude(),
                        'lng' => $first_result
                            ->getCoordinates()
                            ->getLongitude(),
                    ],
                    'countryName'      => $first_result
                        ->getCountry()
                        ->getName(),
                    'countryCode'      => $first_result
                        ->getCountry()
                        ->getCode(),
                    'locality'         => $first_result->getLocality(),
                    'postalCode'       => $first_result->getPostalCode(),
                    'street'           => $first_result->getStreetName(),
                    'streetNumber'     => $first_result->getStreetNumber(),
                    'timezone'         => $first_result->getTimezone(),
                ],
            ];
        }

        return new WP_Error($results->get_error_code(), $results->get_error_message());
    }

    /**
     * Returns the Users Country based on their IP address
     *
     * @return WP_Error|bool|array|Error
     */
    public function returnUserCountry()
    :WP_Error|bool|array|Error
    {
        if (!$this->userLocation) {
            return $this->noUserIp;
        }

        $location = self::geoQueryCallback();

        if (!is_wp_error($location)) {
            return [
                'err'      => false,
                'location' => [
                    'countryName' => $location
                        ->first()
                        ->getCountry()
                        ->getName(),
                    'countryCode'      => $location
                        ->first()
                        ->getCountry()
                        ->getCode(),
                ],
            ];
        }

        return false;
    }

    /**
     * Grabs the users zipcode based on their IP
     *
     * @return array|WP_Error|Error
     */
    public function getZipAndCoords()
    :array|WP_Error|Error
    {
        if (!$this->userLocation) {
            return $this->noUserIp;
        }

        $err_return = $this->errReturn;

        $err_return['zipcode']     = false;
        $err_return['coordinates'] = false;

        $results = $this->geoQueryCallback();

        if (!$results->isEmpty() && !is_wp_error($results)) {
            $first_result = $results->first();

            return [
                'err'         => false,
                'zipcode'     => $first_result
                    ->getPostalCode(),
                'coordinates' => [
                    'lat' => $first_result
                        ->getCoordinates()
                        ->getLatitude(),
                    'lng' => $first_result
                        ->getCoordinates()
                        ->getLongitude(),
                ],
            ];
        }

        return $err_return;
    }

    /**
     * Sets up the provider with Free Geo IP
     *
     * @return Error|Collection|WP_Error
     */
    protected function geoQueryCallback()
    :WP_Error|Error|Collection
    {
        $geocoder = new ProviderAggregator();
        $geocoder->registerProviders($this->provider);

        try {
            return $geocoder
                ->using($this->usingName)
                ->geocodeQuery(GeocodeQuery::create($this->userLocation));
        } catch (Exception $exception) {
            $logger = $this->initLogger('geolocate', 'iplookup', MERCK_SCRAPER_LOG_DIR . '/iplookup');
            $logger->error(
                "Provider or Providers Using Name is incorrectly set or missing. {$exception->getMessage()}",
            );

            return new WP_Error(
                401,
                __(
                    "Provider or Providers Using Name is incorrectly set or missing. {$exception->getMessage()}",
                    'merck-scraper',
                ),
            );
        }
    }
}
