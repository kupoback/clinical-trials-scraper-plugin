<?php

namespace Merck_Scraper\Admin\Traits;

use WP_Error;

trait MSAdminStatusTrait
{
    /**
     * Returns all status taxonomy types
     *
     * @return array|WP_Error|string
     * @since    1.0.0
     */
    public function getStatus()
    :array|WP_Error|string
    {
        return get_terms(
            [
                'taxonomy'   => 'custom_trial_publication_status',
                'hide_empty' => false,
            ]
        );
    }


    /**
     * Get array of all post status options,
     * including the taxonomy defined ones
     *
     * @return array
     * @since    1.0.0
     */
    public function getAllStatusArray()
    :array
    {
        $core_statuses   = get_post_statuses();
        $statuses        = $core_statuses;
        $custom_statuses = self::getStatus();
        foreach ($custom_statuses as $status) {
            $statuses[$status->slug] = $status->name;
        }

        return $statuses;
    }
}
