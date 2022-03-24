<?php

declare(strict_types = 1);

namespace Merck_Scraper\Traits;

use Exception;
use TenQuality\WP\Database\QueryBuilder;
use WP_Error;

/**
 * Traits for the Merck Scraper DB Callbacks
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Traits
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
trait MSDBCallbacks
{

    /**
     * Creates a DB fetch to grab the post ID from the postmeta table based on
     * the $meta_key that's passed.
     *
     * If using ACF and the value is serialized, this will not work.
     *
     * @param string $db_col the meta key to equal this to
     * @param string $value  an object to compare within the DB call
     *
     * @return array|int|object|WP_Error
     * @throws Exception
     */
    protected function dbFetchPostId(string $db_col = '', string $value = '')
    {
        if (!$db_col || !$value) {
            return new WP_Error(400, __("Did not include a meta_key or value with a valid value", "merck-scraper"));
        }

        return QueryBuilder::create('getPostId')
                           ->select('pm.post_id')
                           ->from('postmeta as `pm`')
                           ->where(["pm.$db_col" => $value,])
                           ->first()
                ->post_id ?? 0;
    }

    /**
     * @throws Exception
     */
    protected function dbFetchNctId(int $post_id)
    {
        if (!$post_id) {
            return new WP_Error(400, __("Did not pass a post_id", "merck-scraper"));
        }

        return QueryBuilder::create('getNctId')
                           ->select('pm.meta_value')
                           ->from('postmeta as `pm`')
                           ->where(
                               [
                                   "pm.post_id"  => $post_id,
                                   "pm.meta_key" => 'api_data_nct_id',
                               ]
                           )
                           ->value() ?? 0;
    }

    /**
     * @throws Exception
     */
    protected function dbArchivedPosts()
    :array
    {
        return QueryBuilder::create("getArchivedPosts")
                           ->select('p.ID')
                           ->from('posts as `p`')
                           ->where(
                               [
                                   'p.post_status' => 'trash',
                                   'p.post_type'   => 'trials',
                               ],
                           )
                           ->get();
    }
}
