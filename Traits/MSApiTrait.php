<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Traits for the Merck Scraper API
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSApiTrait
{

    /**
     * The api url namespace
     *
     * @var string
     */
    private string $apiNamespace = 'merck-scraper';

    /**
     * The api version
     *
     * @var string
     */
    private string $apiVersion = 'v1';

    /**
     * The method to help speed up registering the routes
     *
     * @param string $route      The route name
     * @param string $rest_type
     * @param string $callback   The method callback
     * @param string $query_args Any query args for the route name
     * @param array  $args       Any args for the route
     */
    protected function registerRoute(string $route = '', $rest_type = WP_REST_Server::READABLE, array $callback = [], string $query_args = '', array $args = [])
    {
        $rest_prefix = "{$this->apiNamespace}/{$this->apiVersion}";

        if ($query_args) {
            $route = "{$route}/{$query_args}";
        }

        register_rest_route(
            $rest_prefix,
            $route,
            [
                'methods'             => $rest_type,
                'callback'            => $callback,
                'args'                => $args,
                'permission_callback' => '__return_true',
            ]
        );
    }

    //region File Grabber

    /**
     * Method to grab the folders contents
     *
     * @param string $folder_name
     *
     * @return false|Collection
     */
    protected function getFileNames(string $folder_name)
    {
        if (is_dir($folder_name)) {
            $dir_contents = scandir($folder_name);
            $dir_contents = collect($dir_contents)
                ->filter(function ($file) {
                    return strpos($file, '.log');
                })
                ->reverse()
                ->values();

            if ($dir_contents->isNotEmpty()) {
                return $dir_contents
                    ->map(function ($file) use ($folder_name) {
                        $file_with_path = "{$folder_name}/{$file}";
                        return [
                            // 'fileContent' => file_get_contents($file_with_path, true),
                            'id'       => pathinfo($file, PATHINFO_FILENAME),
                            'filePath' => $file_with_path,
                            'fileDate' => Carbon::createFromTimestamp(
                                filemtime($file_with_path),
                                'America/New_York'
                            )->format('F j, Y g:i A'),
                            'fileName' => $file,
                        ];
                    });
            }
        }
        return false;
    }

    /**
     * Grabs teh contents of the file and returns them via an array
     *
     * @param string $file   The file name
     * @param string $folder The folder to look for the file in
     *
     * @return array|WP_Error
     */
    protected function getFileContents(string $file = '', string $folder = '')
    {
        // @TODO Could possibly refactor to pass in the full path instead of building it
        if ($file && $folder) {
            $log_file = "{$folder}/{$file}.log";
            if (file_exists($log_file)) {
                return [
                    'message'      => null,
                    'fileContents' => file_get_contents($log_file, true) ?: "Empty file",
                ];
            }
        }

        return new WP_Error(200, ['message' => __("Error getting file and or it's contents", 'sage'), 'fileContents' => '']);
    }
    //endregion

    //region API Position Methods
    /**
     * Returns the current position of the import process useful for an AJAX call
     *
     * @return mixed|WP_REST_Response
     */
    public function importPosition()
    {
        $response = get_option('merck_import_position');
        $current_time = self::timeNowFormated();

        $return = [
            'helper'     => null,
            'name'       => null,
            'position'   => null,
            'totalCount' => null,
            'status'     => 400,
            'time'       => null,
        ];

        if ($response && !empty($response)) {
            // Get the time that the import was last updated
            $updated_time = $response['time'];
            if (is_object($updated_time)) {
                // Check the number of minutes past
                $time_diff = abs($current_time->getTimestamp() - $updated_time->getTimeStamp()) / 60;
                if ($time_diff <= 1) {
                    $return = $response;
                }
            }
        }

        return $return;
    }

    /**
     * A function used to update the option import_position to display with
     * the WP-Admin via an API call
     *
     * @param string $import_name The name of the import item
     * @param array  $args        {
     *      @type string    $helper      The text of the position
     *      @type int       $position    The current position of the import
     *      @type int       $total_count The total number of items importing
     *      @type DateTime  $time        The last time the import was updated
     * }
     */
    private function updatePosition(string $import_name, $args = [])
    {
        update_option(
            'merck_import_position',
            [
                'helper'     => $args['helper'] ?? '',
                'name'       => $import_name,
                'position'   => $args['position'] ?? 0,
                'totalCount' => $args['total_count'] ?? 0,
                'status'     => 200,
                'time'       => self::timeNowFormated(),
                // 'memoryUsage' => floor((memory_get_usage() / 1024) / 1024) . 'MB / ' . ini_get('memory_limit'),
            ]
        );
    }

    /**
     * Resets the option of the current position
     */
    private function clearPosition()
    {
        update_option('merck_import_position', '');
        return true;
    }

    /**
     * Creates a new instance of the DateTime of right now
     *
     * @return DateTime
     */
    private function timeNowFormated()
    {
        return new DateTime();
    }
    //endregion
}
