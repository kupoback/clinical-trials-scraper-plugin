<?php

declare(strict_types=1);

namespace Merck_Scraper\Traits;

use Mailjet\Client;
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

    protected function mailerClient()
    {
        $api_key         = $this->acfOptionField('mailjet_api_key');
        $api_secret      = $this->acfOptionField('mailjet_api_secret_key');

        if (!$api_key || !$api_secret) {
            return new WP_Error("Please check that the API Key or Secret Key are populated and/or valid", 400);
        }

        return new Client(
            $api_key,
            $api_secret,
            true,
            ['version' => 'v3.1',]
        );
    }
}
