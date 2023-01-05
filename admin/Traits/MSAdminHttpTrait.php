<?php

namespace Merck_Scraper\Admin\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\TransferStats;
use Merck_Scraper\Helper\MSHelper;
use Merck_Scraper\Traits\MSHttpCallback;
use Psr\Http\Message\ResponseInterface;
use WP_Error;

trait MSAdminHttpTrait
{

    use MSHttpCallback;

    /**
     * Builds the expression for the API Call
     *
     * @param  string|bool  $nct_id_field The NCT ID to fetch for if defined
     *
     * @return string
     */
    protected function setupExpression(string|bool $nct_id_field)
    :string
    {
        /**
         * If pulling in specific trial ID's, ignore the above
         */
        if ($nct_id_field) {
            return collect(MSHelper::textareaToArr($nct_id_field))
                ->filter()
                ->map(fn ($nct_id) => "(AREA[NCTId]$nct_id)")
                ->implode(' OR ');
        } else {
            /**
             * The default search query.
             *
             * @uses Status OverallStatus of the Trial, Recruiting and Not yet recruiting
             * @uses Country The default country is the United States
             * @uses Sponsor Searches for Merck Sharp & Dohme as the sponsor
             */
            $expression = collect();

            // Keywords that are not allowed
            if ($this->disallowedConditions->isNotEmpty()) {
                $expression->push(
                    "(AREA[ConditionSearch] NOT ({$this->disallowedConditions->implode(' OR ')}))"
                );
            }

            // Keywords that are allowed
            if ($this->allowedConditions->isNotEmpty()) {
                $expression->push(
                    "(AREA[ConditionSearch] ({$this->allowedConditions->implode(' OR ')}))"
                );
            }

            // Trial Status Search type
            if ($this->trialStatus->isNotEmpty()) {
                $recruiting_status = $this->mapImplode($this->trialStatus);
                $expression->push(
                    "(AREA[OverallStatus] EXPAND[Term] COVER[FullMatch] ($recruiting_status))"
                );
            }

            // Trial Location search

            // Allowed Trial Locations
            // if ($this->allowedTrialLocations->isNotEmpty()) {
            //     $location = $this->mapImplode($this->allowedTrialLocations);
            //     $expression->push(
            //         "(AREA[LocationCountry] $location)"
            //     );
            // }

            // Disallowed Trial Locations
            // if ($this->disallowedTrialLocations->isNotEmpty()) {
            //     $location = $this->mapImplode($this->disallowedTrialLocations);
            //     $expression->push(
            //         "(AREA[LocationCountry] NOT $location)"
            //     );
            // }

            // Trial sponsor search name
            $sponsor_name = $this->acfOptionField('clinical_trials_api_sponsor_search') ?: "Merck Sharp &amp; Dohme";
            $expression->push(
                "(AREA[LeadSponsorName] \"$sponsor_name\")"
            );

            // Expression builder
            return $expression
                ->filter()
                ->implode(' AND ');
        }
    }

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
    :WP_Error|ResponseInterface
    {
        $base_uri = $this->acfOptionField('clinical_trials_api_base');

        if ($base_uri) {
            $handler_stack = HandlerStack::create(new CurlHandler());
            $handler_stack->push(Middleware::retry($this->retryCall(), $this->retryDelay()));

            $default_args = [
                'base_uri' => $base_uri,
                'handler'  => $handler_stack,
            ];
            $client_args  = wp_parse_args($guzzle_args, $default_args);

            $guzzle_client = new Client($client_args);

            try {
                $query_args   = wp_parse_args($query_args, ['fmt' => 'json', 'min_rnk' => 1, 'max_rnk' => 10,]);
                $request_args = wp_parse_args($http_args, [
                    'query' => $query_args,
                    // Used for debugging
                    // 'on_stats' => function (TransferStats $stats) use (&$url) {
                    //     $url = $stats->getEffectiveUri();
                    // }
                ]);

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
                $this->httpErrLogger()
                     ->error(
                         "Unable to connect to API for $endpoint_path. Error: {$exception->getMessage()}"
                     );
                return new WP_Error($exception->getCode(), $exception->getMessage());
            }
        }

        return new WP_Error(
            400,
            __("Error, unable to complete HTTP request. No base_uri set in Merck Scraper Options.", "merck-scraper")
        );
    }

}
