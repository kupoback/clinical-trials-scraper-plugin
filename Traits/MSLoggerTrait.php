<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use DateTimeZone;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Logger Traits for the Merck Scraper Plugin
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSLoggerTrait
{
    /**
     * Sets up the Logger
     *
     * @param string       $name            The logger name
     * @param  int|string  $file_name       The file name for the logger
     * @param string       $file_path       The path to place the file
     * @param int          $logger_type     The type of log file this is
     * @param string       $text_extension  The file extension if needed to override log files
     *
     * @return false|Logger
     * @link LineFormatter https://github.com/Seldaek/monolog/blob/main/doc/message-structure.md
     *
     * @link Monolog https://github.com/Seldaek/monolog
     */
    protected function initLogger(
        string      $name,
        int|string  $file_name,
        string      $file_path = MERCK_SCRAPER_LOG_DIR,
        int         $logger_type = Logger::ERROR,
        string      $text_extension = 'log',
    ) :Logger|bool {
        if (!$name || !$file_name) {
            return false;
        }

        $date_format = 'Y-m-d H:i:s';
        $output      = "[%datetime%] %level_name% \n %message% \n %context% \n %extra%\n";
        $formatter   = new LineFormatter($output, $date_format, true, true);
        // Allows us to pretty the array return
        $formatter->setJsonPrettyPrint(true);
        $formatter->setMaxNormalizeDepth(10);

        // Setup our StreamHandler and set our formatter
        $stream    = new StreamHandler("$file_path/$file_name.$text_extension", $logger_type);
        $stream->setFormatter($formatter);

        $logger = new Logger($name);
        $logger->setTimezone(new DateTimeZone("America/New_York"));
        $logger->pushHandler($stream);

        return $logger;
    }
}
