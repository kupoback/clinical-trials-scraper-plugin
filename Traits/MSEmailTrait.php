<?php

declare(strict_types = 1);

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
        $api_key         = self::acfOptionField('mailjet_api_key');
        $api_secret      = self::acfOptionField('mailjet_api_secret_key');

        if (!$api_key || !$api_secret) {
            return new WP_Error("Please check that the API Key or Secret Key are populated and/or valid", 400);
        }

        return new Client(
            $api_key,    // f353ad42fe63daac532f4caeacd96a9c
            $api_secret, // b5d676dce0e6d7df18c01fcaeb5a3d11
            true,
            [
                'version' => 'v3.1',
            ]
        );
    }
}
