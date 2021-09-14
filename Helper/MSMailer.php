<?php

declare(strict_types = 1);

namespace Merck_Scraper\Helper;

use Error;
use Illuminate\Support\Collection;
use Mailjet\Client;
use Mailjet\Resources;
use Merck_Scraper\Traits\MSAcfTrait;

class MSMailer
{

    use MSAcfTrait;

    /**
     * This is an email compiler
     *
     * @param Collection|null $email_defaults
     * @param array<array>    $addt_args
     *
     * @return bool|Error
     * @link https://dev.mailjet.com/email/guides/send-api-v31/
     */
    public function mailer(Collection $send_to = null, array $addt_args = [])
    {

        if (!is_null($send_to) || $send_to->isEmpty()) {
            return new Error("An email address and Name are required for email submission", 400);
        }

        $api_key         = self::acfOptionField('mailjet_api_key');
        $api_secret      = self::acfOptionField('mailjet_api_secret_key');
        $email_from      = self::acfOptionField('email_from');
        $email_from_name = self::acfOptionField('email_from_name');

        if (!$api_key || $api_secret) {
            return new Error("Please check that the API Key or Secret Key are populated and/or valid", 400);
        }

        $mailjet = new Client(
            $api_key,    // f353ad42fe63daac532f4caeacd96a9c
            $api_secret, // b5d676dce0e6d7df18c01fcaeb5a3d11
            true,
            [
                'version' => 'v3.1',
            ]
        );

        $mailjet_body = [
            'Messages' => [
                'From' => [
                    'Email' => $email_from,
                    'Name'  => $email_from_name,
                ],
                'To'   => $send_to
                    ->map(function ($send_to) {
                        $send_to = array_change_key_case($send_to, CASE_LOWER);
                        return [
                            'Email' => $send_to['email'],
                            'Name'  => $send_to['name'],
                        ];
                    })->filter()->toArray(),
                $addt_args,
            ],
        ];

        // if ($body['body_html'] || isset($template)) {
        //     $mailjet_body['Messages']['HtmlPart'] = $template ?? $body['body_html'];
        // }

        $response = $mailjet->post(Resources::$Email, ['body' => $mailjet_body]);

        if ($response->success()) {
            return true;
        }
        return new Error("Error submissing email");
    }
}
