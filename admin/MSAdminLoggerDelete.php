<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class MSAdminLoggerDelete
{
    /**
     * @var array|string[] Sets up dot files to omit for diff
     */
    protected array $dotFiles = ['.', '..'];

    /**
     * MSApiLogger constructor.
     *
     * @param  string  $apiLogDir    String filepath for the api log directory
     * @param  string  $apiErrDir    String filepath for the api error log directory
     * @param  string  $emailLogDir  String filepath for the email log directory
     * @param  string  $emailErrDir  String filepath for the email error directory
     */
    public function __construct(
        public string $apiLogDir = MERCK_SCRAPER_API_LOG_DIR . '/log',
        public string $apiErrDir = MERCK_SCRAPER_API_LOG_DIR . '/error',
        public string $emailLogDir = MERCK_SCRAPER_LOG_DIR . '/email/log',
        public string $emailErrDir = MERCK_SCRAPER_LOG_DIR . '/email/error',
    ) {
    }

    /**
     * Grabs the last 4 months worth of logs for each folder,
     * compresses them, and saves them into a zip folder directory
     * ready to download from the admin and help to free up drive space
     *
     * @return void
     */
    public function deleteTwoMonthsOfFiles()
    :void
    {
        $two_months_ago = Carbon::now('America/New_York')
                                ->subMonths(2)
                                ->endOfMonth();

        collect(
            [
                $this->apiLogDir,
                $this->apiErrDir,
                $this->emailErrDir,
                $this->emailLogDir,
                MERCK_SCRAPER_LOG_DIR . "/http/error",
            ],
        )
            // Strip out the '.', and '..' files
            ->diff($this->dotFiles)
            ->each(function ($folder) use ($two_months_ago) {
                $files = $this->filterFiles($folder, $two_months_ago);
                if ($files->isNotEmpty()) {
                    $files
                        ->each(fn ($file) => file_exists("$folder/$file") && unlink("$folder/$file"));
                }
            });
    }

    /**
     * Filters the files and returns ones ready for compressing
     *
     * @param  string  $folder           The folder path
     * @param  Carbon  $previous_months  The Carbon date of 2 months ago
     *
     * @return Collection
     */
    private function filterFiles(string $folder, Carbon $previous_months)
    :Collection
    {
        return collect(scandir($folder))
            ->diff($this->dotFiles)
            ->filter(fn ($file) => $file
                                   && $previous_months
                                       ->greaterThanOrEqualTo(
                                           Carbon::createFromTimestamp(
                                               filemtime("$folder/$file"),
                                           ),
                                       ))
            ->values();
    }
}
