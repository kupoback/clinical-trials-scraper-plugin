<?php

declare(strict_types = 1);

namespace Merck_Scraper\Helper;

use Error;
use Illuminate\Support\Collection;
use Mailjet\Resources;
use Merck_Scraper\Traits\MSAcfTrait;
use Merck_Scraper\Traits\MSEmailTrait;
use Merck_Scraper\Traits\MSLoggerTrait;
use Monolog\Logger;
use WP_Error;

class MSMailer
{

    use MSEmailTrait;
    use MSAcfTrait;
    use MSLoggerTrait;

    /**
     * This is an email compiler
     *
     * @param null|Collection $send_to
     * @param array<array>    $extra_args
     *
     * @return array|WP_Error
     * @link https://dev.mailjet.com/email/guides/send-api-v31/
     */
    public function mailer(Collection $send_to = null, array $extra_args = [])
    {
        // $send_to is required and must be a Collection
        if (is_null($send_to) || $send_to->isEmpty()) {
            return new WP_Error("An email address and Name are required for email submission", 400);
        }

        // Set up the Email logger
        $email_logger = self::initLogger(
            'email-log',
            'email',
            MERCK_SCRAPER_LOG_DIR . '/email/log',
            Logger::API
        );

        $error_logger = self::initLogger(
            'email-error',
            'email-error',
            MERCK_SCRAPER_LOG_DIR . '/email/error',
            Logger::API,
        );

        $email_from      = self::acfOptionField('email_from');
        $email_from_name = self::acfOptionField('email_from_name');

        $mailjet = self::mailerClient();

        // Quit if the mailerClient fails to set up
        if (is_wp_error($mailjet)) {
            $error_logger->error("Failed to setup Email Client", ['message' => $mailjet->get_error_message()]);
            return new WP_Error("Failed to setup Email Client. {$mailjet->get_error_message()}");
        }

        $send_to = $send_to
            ->map(function ($send_to) {
                $send_to = array_change_key_case($send_to, CASE_LOWER);
                return [
                    'Email' => $send_to['email'],
                    'Name'  => $send_to['name'],
                ];
            })
            ->filter()
            ->toArray();

        $mailjet_body = [
            'Messages' => [
                [
                    // The email address from
                    'From' => [
                        'Email' => $email_from,
                        'Name'  => $email_from_name,
                    ],
                    // Who the emails being sent to
                    'To'   => $send_to,
                ],
            ],
        ];

        if (!empty($extra_args)) {
            // Anything else like Subject, HTMLPart, TextPart, Variables, Template ID
            foreach ($extra_args as $key_name => $value) {
                $mailjet_body['Messages'][0][$key_name] = $value;
            }
        }

        $response  = $mailjet->post(Resources::$Email, ['body' => $mailjet_body]);
        $resp_body = $response->getBody();

        if ($response->success()) {
            $email_logger->info("Email Sent", $resp_body['Messages'][0]['To'] ?? '');
            return $resp_body;
        } else {
            $error_logger->error("Email Failed", $response->getBody());
            return new WP_Error($response->getStatus() ?? 400, $resp_body);
        }
    }
}
