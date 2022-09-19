<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin\Traits;

use Illuminate\Support\Collection;
use Mailjet\Client;
use Merck_Scraper\Helper\MSMailer;
use Merck_Scraper\Traits\MSAcfTrait;
use WP_Error;

/**
 * Traits for MailJet integration
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSEmailTrait
{
    use MSAcfTrait;

    /**
     * Method that setups and sends out the email after the import has been run
     *
     * @param Collection $studies_imported A Collection of studies that were imported
     * @param int        $num_not_imported The number of studies not imported as they're filtered out
     *
     * @return WP_Error|bool|array
     */
    protected function emailSetup(Collection $studies_imported, int $num_not_imported = 0)
    :WP_Error|bool|array
    {
        /**
         * Merck Email Client
         */
        if ($this->sendTo->filter()->isEmpty()) {
            $this->sendTo = collect($this->acfOptionField('api_logger_email_to'));
        }

        if ($this->sendTo->filter()->isNotEmpty()) {

            /**
             * Check if AIO is installed and setup
             */
            $login_url = wp_login_url();
            global $aio_wp_security;
            if ($aio_wp_security && $aio_wp_security->configs->get_value('aiowps_enable_rename_login_page') === '1') {
                $home_url  = trailingslashit(home_url()) . (!get_option('permalink_structure') ? '?' : '');
                $login_url = "$home_url{$aio_wp_security->configs->get_value('aiowps_login_page_slug')}";
            }

            /**
             * A list of array fields for the MailJet Email Client
             */
            $email_args = [
                'TemplateLanguage' => true,
                'TemplateID'       => (int) ($this->acfOptionField('api_email_template_id') ?? 0),
                'Variables'        => [
                    'timestamp' => $this->nowTime
                        ->format("l F j, Y h:i A"),
                    'trials'    => '',
                    'wplogin'   => $login_url,
                ],
            ];

            /**
             * Adds the Trails' data to Variables
             */
            if ($studies_imported->isNotEmpty()) {
                $new_posts     = collect();
                $trashed_posts = collect();
                $updated_posts = collect();

                $studies_imported
                    ->map(function ($study) use ($new_posts, $trashed_posts, $updated_posts) {
                        $status = $study
                                ->get('POST_STATUS', false);
                        if (is_string($status)) {
                            match(strtolower($status)) {
                                'draft', 'pending' => $new_posts->push($study),
                                'trash' => $trashed_posts->push($study),
                                'publish' => $updated_posts->push($study),
                            };
                        }
                        return $study;
                    });

                // Contents for the output body of the email
                $total_found      = sprintf('<li>Total Found: %s</li>', $studies_imported->count());
                $new_posts        = sprintf('<li>New Trials: %s</li>', $new_posts->count());
                $trashed_posts    = sprintf('<li>Removed Trials: %s</li>', $trashed_posts->count());
                $num_not_imported = sprintf('<li>Trials Not Scraped: %s</li>', $num_not_imported ?? 0);
                $updated_posts    = sprintf('<li>Updated Trials: %s</li>', $updated_posts->count());

                $email_args['Variables']['trials'] = sprintf(
                    '<ul>%s</ul>',
                    $total_found . $new_posts . $trashed_posts . $num_not_imported . $updated_posts
                );
            }

            // Email notification on completion
            return (new MSMailer())
                ->mailer($this->sendTo, $email_args);
        }

        return false;
    }

    /**
     * The setup for the Mailer client
     *
     * @return WP_Error|Client
     */
    protected function mailerClient()
    :WP_Error|Client
    {
        $api_key         = $this->acfOptionField('mailjet_api_key');
        $api_secret      = $this->acfOptionField('mailjet_api_secret_key');

        if (!$api_key || !$api_secret) {
            return new WP_Error(
                400,
                __("Please check that the API Key or Secret Key are populated and/or valid", 'merck-scraper')
            );
        }

        return new Client(
            $api_key,
            $api_secret,
            true,
            ['version' => 'v3.1',]
        );
    }
}
