<?php

declare(strict_types = 1);

namespace Merck_Scraper\Helper;

use Error;
use Mailjet\Client;
use Mailjet\Resources;
use Merck_Scraper\Traits\MSAcf;

class MSMailer
{

    use MSAcf;

    /**
     * This is an email compiler
     *
     * @param array<array> $email_to {
     *   {
     *      @type string $email The email address we're sending the email to
     *      @type string $name  The name of the person or persons we're sending an email to
     *   }
     * }
     *
     * @param array<string, bool> $body {
     *      @type string $subject      The string subject line
     *      @type string $body_text    The string body text. This will get sanitized
     *      @type string $body_html    Can be a string or a file path relative to the active theme
     *      @type bool   $use_template If set to true, will locate the template based on the path relative to the active theme in $body_html
     * }
     *
     * @param array<string> $addt_args These are the body parameters for the email sending {
     *      @type array<array-key> $Attachments {
     *          @type string $ContentType  The content type of the attachment
     *          @type string $Filename     The filename of the attachment
     *      }
     * }
     *
     * @return bool|Error
     * @link https://dev.mailjet.com/email/guides/send-api-v31/
     */
    public function mailer(array $email_to = [], array $body = ['subject' => '', 'body_text' => '', 'body_html' => "", 'use_template' => false], array $addt_args = [])
    {
        if (empty($email_to) || (!$email_to['email'] || !$email_to['name'])) {
            return new Error("An email address and Name are required for email submission", 400);
        }

        $api_key         = self::acfOptionField('mailjet_api_key');
        $api_secret      = self::acfOptionField('mailjet_api_secret_key');
        $email_from      = self::acfOptionField('email_from');
        $email_from_name = self::acfOptionField('email_from_name');

        if (!$api_key || $api_secret) {
            return new Error("Please check that the API Key or Secret Key are populated and/or valid", 400);
        }

        // @TODO Remove keys comments before production
        $mailjet = new Client(
            $api_key,    // f353ad42fe63daac532f4caeacd96a9c
            $api_secret, // b5d676dce0e6d7df18c01fcaeb5a3d11
            true,
            [
                'version' => 'v3.1',
            ]
        );

        $email_to = collect($email_to);

        if ($email_to->isEmpty()) {
            return new Error("Email recepiants are required", 400);
        }

        $email_to = $email_to->map(function ($send_to) {
            $send_to = array_change_key_case($send_to, CASE_LOWER);
            return [
                'Email' => $send_to['email'],
                'Name'  => $send_to['name'],
            ];
        })
                             ->filter()
                             ->toArray();

        /**
         * Grabs either the php file to parse or the blade template
         */
        if ($body['use_template']) {
            ob_start();
            $template = strpos($body['body_html'], 'blade') && class_exists('App')
                ? \App\locate_template($body['body_html'])
                : locate_template($body['body_html'], true);
            ob_get_clean();
        }

        $mailjet_body = [
            'Messages' => [
                'From'     => [
                    'Email' => $email_from,
                    'Name'  => $email_from_name,
                ],
                'To'       => $email_to,
                'Subject'  => $body['subject'] ?? "Greetings from Mailjet.",
                'TextPart' => $body['body_text'] ?? "My first Mailjet email",
                $addt_args,
            ],
        ];

        if ($body['body_html'] || isset($template)) {
            $mailjet_body['Messages']['HtmlPart'] = $template ?? $body['body_html'];
        }

        $response = $mailjet->post(Resources::$Email, ['body' => $mailjet_body]);

        if ($response->success()) {
            return true;
        }
        return new Error("Error submissing email");
    }
}
