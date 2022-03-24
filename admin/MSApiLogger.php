<?php

declare(strict_types=1);

namespace Merck_Scraper\admin;

use Merck_Scraper\Traits\MSApiTrait;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers the rest api for the scraper
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSApiLogger
{

    use MSApiTrait;

    /**
     * Folder for the api logs
     *
     * @var string
     */
    public string $apiLogDir;

    /**
     * Folder for the api error logs
     *
     * @var string|mixed
     */
    public string $apiErrDir;

    /**
     * MSApiLogger constructor.
     *
     * @param string $apiLogDir String filepath for the api log directory
     * @param string $apiErrDir String filepath for the api error log directory
     */
    public function __construct(string $apiLogDir = MERCK_SCRAPER_API_LOG_DIR . '/log', string $apiErrDir = MERCK_SCRAPER_API_LOG_DIR . '/error')
    {
        $this->apiLogDir = $apiLogDir;
        $this->apiErrDir = $apiErrDir;
    }

    /**
     * Method to register our API Endpoints
     */
    public function registerEndpoint()
    {
        // Returns the API Position
        $this->registerRoute('api-position', WP_REST_Server::READABLE, [$this, 'apiPosition']);
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
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
            ]
        );
        // Returns a log file
        $this->registerRoute(
            'api-get-log-file',
            WP_REST_Server::CREATABLE,
            [$this, 'apiGetLogFile'],
            '(?P<file>[a-zA-Z0-9-]+)',
            [
                'file'     => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
                'fileType' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
            ]
        );

        // Deletes a specific file
        $this->registerRoute(
            'api-delete-file',
            WP_REST_Server::CREATABLE,
            [$this, 'apiDeleteFile'],
            '(?P<file>[a-zA-Z0-9-]+)',
            [
                'file' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
                'filePath' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
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
     * Grabs a list of the directories from the MERCK_SCRAPER_LOG_DIR to change which logs show in the admin
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function getLogDirs()
    {
        return rest_ensure_response(
            collect(scandir(MERCK_SCRAPER_LOG_DIR))
                ->filter(function ($directory) {
                    return ($directory !== '.' && $directory !== '..');
                })
                ->map(function ($directory) {
                    return [
                        'dirLabel' => ucwords($directory),
                        'dirValue' => $directory,
                    ];
                })
                ->values()
        );
    }

    /**
     * This method grabs and returns the contents of the log files
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function apiLogger(WP_REST_Request $request)
    {
        $message = [];

        $dir_type = $request->get_param('dirType');

        if ($dir_type === 'api') {
            $log_files = $this->getFileNames($this->apiLogDir);
            $err_files = $this->getFileNames($this->apiErrDir);
        } else {
            $log_files = $this->getFileNames(MERCK_SCRAPER_LOG_DIR . "/$dir_type/log");
            $err_files = $this->getFileNames(MERCK_SCRAPER_LOG_DIR . "/$dir_type/error");
        }

        if ($log_files) {
            $message['logsFiles'] = $log_files;
        }

        if ($err_files) {
            $message['errFiles'] = $err_files;
        }

        return rest_ensure_response(!empty($message) ? $message : ['logsFiles' => [], 'errFiles' => []]);
    }

    /**
     * Method that returns the content from a log file from the wp-admin
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function apiGetLogFile(WP_REST_Request $request)
    {
        $file_name = $request['file'];
        $file_type = $request['fileType'];
        $dir_type = $request['fileDir'];

        if ($file_name && $file_type) {
            if ($dir_type === 'api') {
                $file_dir = $file_type === 'success'
                    ? $this->apiLogDir : ($file_type === 'err' ? $this->apiErrDir : null);
            } else {
                if ($file_type === 'err') {
                    $file_type = 'error';
                } elseif ($file_type === 'success') {
                    $file_type = 'log';
                }

                $file_dir = MERCK_SCRAPER_LOG_DIR . "/$dir_type/$file_type";
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
