<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Merck_Scraper\Traits\MSApiTrait;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZipArchive;

/**
 * Registers the rest api for the scraper
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSApiLogger
{

    use MSApiTrait;

    /**
     * @var array|string[] Sets up dot files to omit for diff
     */
    protected array $dotFiles = ['.', '..'];

    /**
     * @var string Constant for the file dir path to the API Changelog directory
     */
    protected string $changeLogDir = MERCK_SCRAPER_API_CHANGES_DIR;

    /**
     * MSApiLogger constructor.
     *
     * @param string $apiLogDir String filepath for the api log directory
     * @param string $apiErrDir String filepath for the api error log directory
     * @param string $emailLogDir String filepath for the email log directory
     * @param string $emailErrDir String filepath for the email error directory
     */
    public function __construct(
        public string $apiLogDir = MERCK_SCRAPER_API_LOG_DIR . '/log',
        public string $apiErrDir = MERCK_SCRAPER_API_LOG_DIR . '/error',
        public string $emailLogDir = MERCK_SCRAPER_LOG_DIR . '/email/log',
        public string $emailErrDir = MERCK_SCRAPER_LOG_DIR . '/email/error'
    ) {
    }

    /**
     * Method to register our API Endpoints
     */
    public function registerEndpoint()
    :void
    {
        // Returns the API Position
        $this->registerRoute('api-position', WP_REST_Server::READABLE, [$this, 'apiPosition']);
        // Clears the API options in the WP options table since the API failed
        $this->registerRoute('api-clear-position', WP_REST_Server::READABLE, [$this, 'apiClearPosition']);
        // Returns all the directories in the MERCK_SCRAPER_LOG_DIR
        $this->registerRoute('api-directories', WP_REST_Server::READABLE, [$this, 'getLogDirs']);

        // Returns all the log files
        $this->registerRoute(
            'api-log',
            WP_REST_Server::READABLE,
            [$this, 'apiLogger'],
            '(?P<dirType>[a-zA-Z0-9-]+)',
            [
                'dirType'     => [
                    'required'          => false,
                    'validate_callback' => fn ($param) => is_string($param),
                ],
            ]
        );
        // Returns a log file
        $this->registerRoute(
            'api-get-log-file',
            WP_REST_Server::CREATABLE,
            [$this, 'apiGetLogFile'],
            '(?P<file>[a-zA-Z0-9\-\:]+)',
            [
                'file'     => [
                    'required'          => true,
                    'validate_callback' => fn ($param) => is_string($param),
                ],
                'fileType' => [
                    'required'          => true,
                    'validate_callback' => fn ($param) => is_string($param),
                ],
            ]
        );

        // Deletes a specific file
        $this->registerRoute(
            'api-delete-file',
            WP_REST_Server::CREATABLE,
            [$this, 'apiDeleteFile'],
            '(?P<file>[a-zA-Z0-9\-\:]+)',
            [
                'file' => [
                    'required'          => true,
                    'validate_callback' => fn ($param) => is_string($param),
                ],
                'filePath' => [
                    'required'          => true,
                    'validate_callback' => fn ($param) => is_string($param),
                ],
            ]
        );
    }

    /**
     * This method returns the current position of the import
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function apiPosition()
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        return rest_ensure_response($this->importPosition());
    }

    /**
     * Clears an import progression in progress
     *
     * @return WP_REST_Request
     */
    public function apiClearPosition(): WP_REST_Request
    {
        return rest_ensure_request($this->clearPosition());
    }

    /**
     * Grabs a list of the directories from the MERCK_SCRAPER_LOG_DIR to change which logs show in the Admin
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function getLogDirs()
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        return rest_ensure_response(
            collect(scandir(MERCK_SCRAPER_LOG_DIR))
                ->diff($this->dotFiles)
                ->map(fn ($directory) => [
                    'dirLabel' => ucwords($directory),
                    'dirValue' => $directory,
                ])
                ->push(
                    [
                        'dirLabel' => 'Change Log',
                        'dirValue' => 'ms-api-changes',
                    ],
                )
                ->sortBy('dirLabel')
                ->values()
        );
    }

    /**
     * This method grabs and returns the contents of the log files
     *
     * @param  WP_REST_Request  $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function apiLogger(WP_REST_Request $request)
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        $message = [];

        $dir_type = $request->get_param('dirType');

        if ($dir_type === 'api') {
            $log_files = $this->getFileNames($this->apiLogDir);
            $err_files = $this->getFileNames($this->apiErrDir);
        } elseif ($dir_type === 'ms-api-changes') {
            $log_files = $this->getFileNames($this->changeLogDir, '.txt');
        } else {
            $log_files = $this->getFileNames(MERCK_SCRAPER_LOG_DIR . "/$dir_type/log");
            $err_files = $this->getFileNames(MERCK_SCRAPER_LOG_DIR . "/$dir_type/error");
        }

        $message = collect(
            [
                'logsFiles' => $log_files ?? [],
                'errFiles' => $err_files ?? [],
            ]
        )
            ->filter();

        return rest_ensure_response(
            $message->isNotEmpty()
                ? $message->toArray()
                : ['logsFiles' => [], 'errFiles' => []]
        );
    }

    /**
     * Method that returns the content from a log file from the wp-Admin
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function apiGetLogFile(WP_REST_Request $request)
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        $file_name = $request['file'];
        $file_type = $request['fileType'];
        $dir_type = $request['fileDir'];
        $file_path = $request['filePath'];

        if ($file_name && $file_type) {
            $download_url = '';
            if ($dir_type !== 'ms-api-changes') {
                $file_name = "$file_name.log";
                if ($dir_type === 'api') {
                    $file_dir = $file_type === 'success'
                        ? $this->apiLogDir
                        : ($file_type === 'err' ? $this->apiErrDir : null);
                } else {
                    $file_type = match ($file_type) {
                        'err'     => 'error',
                        'success' => 'log',
                    };

                    $file_dir = MERCK_SCRAPER_LOG_DIR . "/$dir_type/$file_type";
                }
            } else {
                $file_dir = $this->changeLogDir;
                $file_name = "$file_name.txt";
            }

            if (!is_null($file_dir)) {
                return rest_ensure_response($this->getFileContents($file_name, $file_dir));
            } else {
                return rest_ensure_response(
                    new WP_Error(
                        403,
                        [
                            'message' => __('Err: Incorrect File Type', 'merck-scraper')
                        ]
                    )
                );
            }
        }

        return rest_ensure_response(new WP_Error(403, ['message' => __('Err: Incorrect File', 'merck-scraper')]));
    }

    /**
     * This method looks for and tries to delete a specific log file that's passed through the request
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function apiDeleteFile(WP_REST_Request $request)
    :WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        $file_path = $request['filePath'] ?? '';
        $return = ['message' => __('File not deleted', 'merck-scraper'), 'status' => 404];
        if ($request['file']) {
            $file_name = "{$request['file']}.log";
            $find_file = collect(glob($file_path));

            $return['message'] = __('No files matching.', 'merck-scraper');
            if ($find_file->isNotEmpty()) {
                $return['message'] = 'File not found';

                $the_file = $find_file->first();
                if (file_exists($the_file)) {
                    unlink($the_file);
                    $return['message'] = __("Found and deleted $file_name", 'merck-scraper');
                    $return['status']  = 200;
                }
            }
        }

        return rest_ensure_response($return);
    }
}
