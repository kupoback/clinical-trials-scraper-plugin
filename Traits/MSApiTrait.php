<?php

declare(strict_types=1);

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
     * @param  string  $route       The route name
     * @param  string  $rest_type
     * @param  array   $callback    The method callback
     * @param  string  $query_args  Any query args for the route name
     * @param  array   $args        Any args for the route
     */
    protected function registerRoute(
        string $route = '',
        string $rest_type = WP_REST_Server::READABLE,
        array  $callback = [],
        string $query_args = '',
        array  $args = [],
    ):void {
        $rest_prefix = "$this->apiNamespace/$this->apiVersion";

        if ($query_args) {
            $route = "$route/$query_args";
        }

        register_rest_route(
            $rest_prefix,
            $route,
            [
                'methods'             => $rest_type,
                'callback'            => $callback,
                'args'                => $args,
                'permission_callback' => '__return_true',
            ],
        );
    }

    //region File Grabber
    /**
     * Method to grab the folders contents
     *
     * @param  string  $folder_name
     * @param  string  $file_extension
     *
     * @return false|Collection
     */
    protected function getFileNames(string $folder_name, string $file_extension = '.log')
    :bool|Collection
    {
        if (is_dir($folder_name)) {
            $dir_contents = scandir($folder_name);
            $dir_contents = collect($dir_contents)
                ->filter(fn ($file) => strpos($file, $file_extension))
                ->reverse()
                ->values();

            if ($dir_contents->isNotEmpty()) {
                return $dir_contents
                    ->map(fn ($file) => [
                        'id'       => pathinfo($file, PATHINFO_FILENAME),
                        'filePath' => "$folder_name/$file",
                        'fileDate' => Carbon::createFromTimestamp(
                            filemtime("$folder_name/$file"),
                            'America/New_York',
                        )
                                            ->format('F j, Y g:i A'),
                        'fileName' => $file,
                        'fileUrl'  => '',
                    ]);
            }
        }

        return false;
    }

    /**
     * Grabs teh contents of the file and returns them via an array
     *
     * @param  string  $file          The file name
     * @param  string  $folder        The folder to look for the file in
     * @param  string  $download_url  The URL to download the file
     *
     * @return array|WP_Error
     */
    protected function getFileContents(string $file = '', string $folder = '', string $download_url = '')
    :WP_Error|array
    {
        // @TODO Could possibly refactor to pass in the full path instead of building it
        if ($file && $folder) {
            $log_file = "$folder/$file";
            if (file_exists($log_file)) {
                return [
                    'message'      => null,
                    'fileContents' => file_get_contents($log_file, true) ?: "Empty file",
                ];
            }
        }

        return new WP_Error(
            200,
            [
                'message'      =>
                    __("Error getting file and or it's contents", 'merck-scraper'),
                'fileContents' => '',
            ]
        );
    }
    //endregion

    //region API Position Methods
    /**
     * Returns the current position of the import process useful for an AJAX call
     *
     * @return mixed|WP_REST_Response
     */
    public function importPosition()
    :mixed
    {
        $response     = get_option('merck_import_position');
        $current_time = $this->timeNowFormatted();

        $return = [
            'helper'     => null,
            'name'       => null,
            'position'   => null,
            'totalCount' => null,
            'status'     => 400,
            'time'       => null,
        ];

        if (!empty($response)) {
            // Get the time that the import was last updated
            $updated_time = $response['time'];
            if (is_object($updated_time)) {
                // Check the number of minutes past
                $time_diff = abs($current_time->getTimestamp() - $updated_time->getTimeStamp()) / 60;
                if ($time_diff <= 3) {
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
     * @param  string  $import_name  The name of the import item
     * @param  array   $args
     *
     * @type string    $helper       The text of the position
     * @type int       $position     The current position of the import
     * @type int       $total_count  The total number of items importing
     * @type DateTime  $time         The last time the import was updated
     *
     */
    private function updatePosition(string $import_name, array $args = [])
    :void
    {
        update_option(
            'merck_import_position',
            [
                'helper'     => $args['helper'] ?? '',
                'name'       => $import_name,
                'position'   => $args['position'] ?? 0,
                'totalCount' => $args['total_count'] ?? 0,
                'status'     => 200,
                'subData'    => $args['sub_data'] ?? (object) [],
                'time'       => $this->timeNowFormatted(),
                // 'memoryUsage' => floor((memory_get_usage() / 1024) / 1024) . 'MB / ' . ini_get('memory_limit'),
            ],
        );
    }

    /**
     * Resets the option of the current position
     */
    private function clearPosition()
    :bool
    {
        update_option('merck_import_position', '');

        return true;
    }

    /**
     * Creates a new instance of the DateTime of right now
     *
     * @return DateTime
     */
    private function timeNowFormatted()
    :DateTime
    {
        return new DateTime();
    }
    //endregion
}
