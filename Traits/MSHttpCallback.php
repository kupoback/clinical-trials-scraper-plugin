<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use Closure as ClosureAlias;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use WP_Error;

/**
 * Traits for the HTTP Calls
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSHttpCallback
{

    use MSLogger;
    use MSAcf;

    /**
     * Basic HTTP callback setup
     *
     * @param string $endpoint_path
     * @param string $request_type
     * @param array  $query_args
     * @param array  $http_args
     * @param array  $guzzle_args
     *
     * @return ResponseInterface|WP_Error
     */
    protected function scraperHttpCB(string $endpoint_path, string $request_type = "GET", array $query_args = [], array $http_args = [], array $guzzle_args = [])
    {
        $base_uri = self::acfOptionField('clinical_trials_api_base');

        if ($base_uri) {
            $handler_stack = HandlerStack::create(new CurlHandler());
            $handler_stack->push(Middleware::retry(self::retryCall(), self::retryDelay()));

            $default_args = [
                'base_uri' => $base_uri,
                'handler'  => $handler_stack,
            ];
            $client_args  = wp_parse_args($guzzle_args, $default_args);

            $guzzle_client = new Client($client_args);

            try {
                $query_args   = wp_parse_args($query_args, ['fmt' => 'JSON', 'min_rnk' => 1, 'max_rnk' => 10,]);
                $request_args = wp_parse_args($http_args, ['query' => $query_args]);

                $response = $guzzle_client->request(
                    $request_type,
                    $endpoint_path,
                    $request_args,
                );

                if ($response->getStatusCode() === 200) {
                    return $response;
                }

                return new WP_Error($response->getStatusCode(), $response->getBody()->getContents());
            } catch (GuzzleException $exception) {
                self::httpErrLogger()->error("Unable to connect to API for {$endpoint_path}. Error: {$exception->getMessage()}");
                return new WP_Error($exception->getCode(), $exception->getMessage());
            }
        }

        return new WP_Error(400, __("Error, unable to complete HTTP request. No base_uri set in Merck Scraper Options.", "merck-scraper"));
    }

    /**
     * Basic HTTP callback for anything else needed on the site
     *
     * @param string $api_base      The base URL we're calling to
     * @param string $endpoint_path The endpoing relative to the $api_base
     * @param string $request_type  The request type
     * @param array  $query_args    Any query args passed to the endpoint
     * @param array  $guzzle_args   Any additional args needed for the HTTP, either http_args or guzzle_args
     *
     * @return ResponseInterface|void|WP_Error
     */
    protected function httpCallback(string $api_base, string $endpoint_path, string $request_type = "GET", array $query_args = [], array $guzzle_args = [])
    {
        if ($api_base) {
            $handler_stack = HandlerStack::create(new CurlHandler());
            $handler_stack->push(Middleware::retry(self::retryCall(), self::retryDelay()));

            $default_args = [
                'base_uri' => $api_base,
                'handler'  => $handler_stack,
            ];
            $client_args  = wp_parse_args(($guzzle_args['guzzle'] ?? []), $default_args);

            $guzzle_client = new Client($client_args);

            try {
                $http_args    = $guzzle_args['http_args'] ?? ['delay' => 150];
                $query_args   = wp_parse_args($query_args, []);
                $request_args = wp_parse_args($http_args, ['query' => $query_args]);

                $response = $guzzle_client->request(
                    $request_type,
                    $endpoint_path,
                    $request_args,
                );

                if ($response->getStatusCode() === 200) {
                    return $response;
                }

                return new WP_Error($response->getStatusCode(), $response->getBody()->getContents());
            } catch (GuzzleException $exception) {
                self::httpErrLogger()->error("Unable to connect to API for {$endpoint_path}. Error: {$exception->getMessage()}");
                return new WP_Error($exception->getCode(), $exception->getMessage());
            }
        }
    }

    /**
     * Function to retry the HTTP call
     *
     * @return ClosureAlias
     */
    private function retryCall()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
            // Limit the number of retries to 5
            if ($retries >= 5) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                self::httpErrLogger()->error("Error Connection: {$request->getUri()}");
                return true;
            }

            if ($response) {
                // Retry on server errors
                if ($response->getStatusCode() >= 500) {
                    self::httpErrLogger()->error("Error status code: {$response->getStatusCode()} on url {$request->getUri()}");
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * The time between each retry
     *
     * @return ClosureAlias
     */
    private function retryDelay()
    {
        return function ($numberOfRetries) {
            return 1000 * $numberOfRetries;
        };
    }

    /**
     * Sets up the Logger for the HTTP methods to use
     *
     * @return false|Logger
     */
    protected function httpErrLogger()
    {
        return self::initLogger("http-error", "http-error", MERCK_SCRAPER_LOG_DIR . "/http");
    }
}
