<?php

declare(strict_types = 1);

namespace Merck_Scraper\Helper;

use Error;
use Illuminate\Support\Collection;
use Mailjet\Client;
use Mailjet\Resources;
use Merck_Scraper\Traits\MSAcfTrait;
use Merck_Scraper\Traits\MSLoggerTrait;
use Monolog\Logger;

class MSMailer
{

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
        if (is_null($send_to) || $send_to->isEmpty()) {
            return new Error("An email address and Name are required for email submission", 400);
        }

        $api_key         = self::acfOptionField('mailjet_api_key');
        $api_secret      = self::acfOptionField('mailjet_api_secret_key');
        $email_from      = self::acfOptionField('email_from');
        $email_from_name = self::acfOptionField('email_from_name');

        if (!$api_key || !$api_secret) {
            return new Error("Please check that the API Key or Secret Key are populated and/or valid", 400);
        }

        // Setup the Email logger
        $email_logger = self::initLogger('email-log', 'email', MERCK_SCRAPER_LOG_DIR . '/email', Logger::API);

        $mailjet = new Client(
            $api_key,    // f353ad42fe63daac532f4caeacd96a9c
            $api_secret, // b5d676dce0e6d7df18c01fcaeb5a3d11
            true,
            [
                'version' => 'v3.1',
            ]
        );

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
                ]
            ],
        ];

        if (!empty($addt_args)) {
            // Anything else like Subject, HTMLPart, TextPart, Variables, Template ID
            foreach ($addt_args as $key_name => $value) {
                $mailjet_body['Messages'][0][$key_name] = $value;
            }
        }

        $response = $mailjet->post(Resources::$Email, ['body' => $mailjet_body]);
        $resp_body = $response->getBody();

        if ($response->success()) {
            $email_logger->info("Email Sent", $resp_body['Messages'][0]['To']);
            return $response->getBody();
        }

        $email_logger->error("Email Failed", $response->getBody());
        return new Error($response->getBody());
    }
}
