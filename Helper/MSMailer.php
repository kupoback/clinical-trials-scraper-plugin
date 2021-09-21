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

class MSMailer
{

    use MSEmailTrait;
    use MSAcfTrait;
    use MSLoggerTrait;

    /**
     * This is an email compiler
     *
     * @param Collection|null $email_defaults
     * @param array<array>    $addt_args
     *
     * @return array|Error
     * @link https://dev.mailjet.com/email/guides/send-api-v31/
     */
    public function mailer(Collection $send_to = null, array $addt_args = [])
    {
        // $send_to is required and must be a Collection
        if (is_null($send_to) || $send_to->isEmpty()) {
            return new Error("An email address and Name are required for email submission", 400);
        }

        // Setup the Email logger
        $email_logger = self::initLogger(
            'email-log',
            'email',
            MERCK_SCRAPER_LOG_DIR . '/email',
            Logger::API
        );

        $email_from      = self::acfOptionField('email_from');
        $email_from_name = self::acfOptionField('email_from_name');

        $mailjet = self::mailerClient();

        // Quit if the mailerClient fails to setup
        if (is_wp_error($mailjet)) {
            $email_logger->error("Failed to setup Email Client", ['message' => $mailjet->get_error_message()]);
            return new Error("Failed to setup Email Client. {$mailjet->get_error_message()}");
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

        if (!empty($addt_args)) {
            // Anything else like Subject, HTMLPart, TextPart, Variables, Template ID
            foreach ($addt_args as $key_name => $value) {
                $mailjet_body['Messages'][0][$key_name] = $value;
            }
        }

        $response  = $mailjet->post(Resources::$Email, ['body' => $mailjet_body]);
        $resp_body = $response->getBody();

        if ($response->success()) {
            $email_logger->info("Email Sent", $resp_body['Messages'][0]['To']);
            return $response->getBody();
        }

        $email_logger->error("Email Failed", $response->getBody());
        return new Error($response->getBody());
    }
}
