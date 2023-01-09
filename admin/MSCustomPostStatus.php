<?php

declare(strict_types=1);

namespace Merck_Scraper\Admin;

use WP_Error;

/**
 * The Admin-specific functionality of the plugin to manage custom post status'.
 *
 * Defines the plugin name, version, registers the options page
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/Admin
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSCustomPostStatus
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct(private string $pluginName, private string $version)
    {
    }

    /**
     * Add the custom post type to backend post status dropdown
     * The trac ticket is still open and there are no new changes until now, so
     * this is just a workaround :(
     * https://core.trac.wordpress.org/ticket/12706
     *
     * @global array|object $post
     * @since    1.0.0
     */
    public function appendPostStatusList()
    :void
    {
        global $post;
        $status     = self::getStatus();
        if ($post->post_type === 'trials') {
            foreach ($status as $single_status) {
                $term_meta = get_option("taxonomy_term_$single_status->term_id");
                $complete  = '';
                $hidden    = 0;
                if (array_key_exists('hide_in_drop_down', $term_meta) && $term_meta['hide_in_drop_down'] == 1) {
                    $hidden = 1;
                }
                if ($post->post_status == $single_status->slug) {
                    $complete = ' selected="selected"'; ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery(".misc-pub-section span#post-status-display").append('<span id="post-status-display"><?php echo $single_status->name; ?></span>');
                        });
                    </script>
                    <?php
                }
                if ($hidden == 0 || $post->post_status == $single_status->slug) {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery('select#post_status').append('<option value="<?php echo $single_status->slug; ?>" <?php echo $complete; ?>><?php echo $single_status->name; ?></option>');
                        });
                    </script>
                    <?php
                }
            }
        }
        foreach ($status as $single_status) {
            $term_meta = get_option("taxonomy_term_$single_status->term_id");
            $hidden    = 0;
            if (array_key_exists('hide_in_drop_down', $term_meta) && $term_meta['hide_in_drop_down'] == 1) {
                $hidden = 1;
            }
            if ($hidden == 0) {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function () {
                        jQuery('select[name="_status"]').append('<option value="<?php echo $single_status->slug; ?>"><?php echo $single_status->name; ?></option>');
                    });
                </script>
                <?php
            }
        }
    }

    /**
     * Add the custom post type to backend post quick edit status dropdown
     *
     * @since    1.0.0
     */
    public function appendPostStatusListQuickedit()
    :void
    {
        $status = self::getStatus();
        if ($this->isTrialsAdmin()) {
            foreach ($status as $single_status) {
                $term_meta = get_option("taxonomy_term_$single_status->term_id");
                $hidden    = 0;
                if (array_key_exists('hide_in_drop_down', $term_meta) && $term_meta['hide_in_drop_down'] == 1) {
                    $hidden = 1;
                } ?>
                <script type="text/javascript">
                    jQuery(document).ready(function () {
                        jQuery('#bulk-edit select[name="_status"]').append('<option value="<?php echo $single_status->slug; ?>" class="hidden-<?php echo $hidden; ?>"><?php echo $single_status->name; ?></option>');
                        jQuery('.quick-edit-row select[name="_status"]').append('<option value="<?php echo $single_status->slug; ?>" class="hidden-<?php echo $hidden; ?>"><?php echo $single_status->name; ?></option>');
                    });
                </script>
                <?php
            } ?>
            <script type="text/javascript">
                jQuery('#the-list').bind('DOMSubtreeModified', postListUpdated);

                function postListUpdated() {
                    // Wait for the quick-edit dom to change
                    setTimeout(function () {
                        var post_quickedit_tr_id = jQuery('.inline-editor').attr('id');
                        if (post_quickedit_tr_id) {
                            var post_edit_tr = post_quickedit_tr_id.replace("edit", "post");
                            jQuery('.quick-edit-row select[name="_status"] option').each(function () {
                                jQuery(this).show();
                                if (jQuery(this).hasClass('hidden-1') && !jQuery('#' + post_edit_tr).hasClass('status-' + jQuery(this).val())) {
                                    jQuery(this).hide();
                                }
                            });
                        }
                        jQuery('#bulk-edit select[name="_status"] option').each(function () {
                            jQuery(this).show();
                            if (jQuery(this).hasClass('hidden-1')) {
                                jQuery(this).hide();
                            }
                        });
                    }, 100);
                }
            </script>
            <?php
        }
    }

    /**
     * Add status to post list
     *
     * @param   $statuses
     *
     * @return array|mixed
     * @global  $post
     * @since    1.0.0
     */
    public function appendPostStatusPostOverview($statuses)
    :mixed
    {
        global $post;
        $status = self::getStatus();
        if ($post) {
            foreach ($status as $single_status) {
                if ($single_status->slug === $post->post_status) {
                    return [$single_status->name];
                }
            }
        }

        return $statuses;
    }

    /**
     * Add custom post status
     *
     * @since    1.0.0
     */
    public function registerPostStatus()
    :void
    {
        $status = self::getStatus();
        foreach ($status as $single_status) {
            if (is_object($single_status)) {
                $term_meta = get_option("taxonomy_term_$single_status->term_id");
                register_post_status(
                    $single_status->slug,
                    collect(
                        [
                            'label'       => $single_status->name,
                            'label_count' => _n_noop(
                                "$single_status->name <span class='count'>(%s)</span>",
                                "$single_status->name <span class='count'>(%s)</span",
                            ),
                        ],
                    )
                        ->put(
                            'public',
                            (array_key_exists('public', $term_meta) && $term_meta['public'] == 1) || current_user_can('edit_posts'),
                        )
                        ->put(
                            'show_in_admin_all_list',
                            array_key_exists('show_in_admin_all_list', $term_meta) && $term_meta['show_in_admin_all_list'] == 1,
                        )
                        ->put(
                            'show_in_admin_status_list',
                            array_key_exists('show_in_admin_status_list', $term_meta) && $term_meta['show_in_admin_status_list'] == 1,
                        )
                        ->put(
                            'hide_in_drop_down',
                            array_key_exists('hide_in_drop_down', $term_meta) && $term_meta['hide_in_drop_down'] == 1,
                        )
                        ->toArray(),
                );
            }
        }
    }

    /**
     * Manipulate the taxonomy form fields
     *
     * @param   $tag
     *
     * @since    1.0.0
     */
    public function statusTaxonomyCustomFields($tag)
    :void
    {
        $returner  = '';
        $term_meta = false;
        if (is_object($tag)) {
            $t_id      = $tag->term_id;
            $term_meta = get_option("taxonomy_term_$t_id");
        }

        $fields = [
            'public'                    => [
                'label' => __('Public', 'merck-scraper'),
                'desc'  => __("Trials with this status are public.<br /> If you're logged in, you can see this trial on the frontend still.", 'merck-scraper'),
            ],
            'show_in_admin_all_list'    => [
                'label' => __('Show posts in admin "All" list', 'merck-scraper'),
                'desc'  => __('Trials with this status will be listed in all trials overview.', 'merck-scraper'),
            ],
            'show_in_admin_status_list' => [
                'label' => __('Show status in admin status list', 'merck-scraper'),
                'desc'  => __('Status appears in status list.', 'merck-scraper'),
            ],
            'hide_in_drop_down'         => [
                'label' => __('Hide status in admin drop downs', 'merck-scraper'),
                'desc'  => __('Status is not selectable in the admin dropdowns.', 'merck-scraper'),
            ],
        ];
        foreach ($fields as $key => $value) {
            $checked = '';
            if ($term_meta && $term_meta[$key] == 1) {
                $checked = 'checked="checked"';
            }
            $returner .= "
                <tr class='form-field'>
                    <th scope='row' valign='top'>
                        <label for='term_meta[$key]'>
                            <input type='checkbox' name='term_meta[$key]' id='term_meta[$key]' value='1' $checked/>{$value['label']}
                        </label>
                    </th>
                    <td>
                        <label for='term_meta[$key]'>
                            <p>{$value['desc']}</p>
                        </label>
                        <br />
                    </td>
                </tr>
            ";
        }
        echo $returner;
    }

    /**
     * Save the manipulated taxonomy form fields
     *
     * @param   $term_id
     *
     * @since    1.0.0
     */
    public function saveStatusTaxonomyCustomFields($term_id)
    :void
    {
        $fields = [
            'public',
            'show_in_admin_all_list',
            'show_in_admin_status_list',
            'hide_in_drop_down',
        ];

        $is_inline_edit = filter_input(INPUT_POST, '_inline_edit');

        /* Reset all custom checkbox fields */
        if (!$is_inline_edit) {
            foreach ($fields as $field) {
                $term_meta[$field] = 0;
            }
            update_option("taxonomy_term_$term_id", $term_meta);
        }

        /* Update new values */
        if (isset($_POST['term_meta'])) {
            $term_meta = get_option("taxonomy_term_$term_id");
            $cat_keys  = array_keys($_POST['term_meta']);
            foreach ($cat_keys as $key) {
                if (isset($_POST['term_meta'][$key])) {
                    $term_meta[$key] = $_POST['term_meta'][$key];
                }
            }
            update_option("taxonomy_term_$term_id", $term_meta);
        }
    }

    /**
     * Override core field after the update of a status taxonomy
     * Used to check if the slug is longer than 20 chars, because the database
     * field for statuses is limited to 20 chars
     *
     *
     * @param   $data
     * @param   $term_id
     * @param   $taxonomy
     * @param   $args
     *
     * @return
     * @since    1.0.2
     */
    public function overrideStatusTaxonomyOnSave($data, $term_id, $taxonomy, $args)
    {
        if ($taxonomy == 'custom_trial_publication_status') {
            $slug = $data['slug'];

            if (strlen($slug) > 20) {
                $data['slug'] = substr($slug, 0, 20);
            }
        }

        return $data;
    }

    /**
     * Returns all status
     *
     * @return
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
     * Edit the status taxonomy table
     *
     * @param   $columns
     *
     * @return
     * @since    1.0.0
     */
    public function editStatusTaxonomyColumns($columns)
    {
        if (isset($columns['description'])) {
            unset($columns['description']);
        }
        if (isset($columns['posts'])) {
            unset($columns['posts']);
        }
        $columns['settings']    = __('Settings', 'merck-scraper');
        $columns['count_trials'] = __('Trials', 'merck-scraper');

        return $columns;
    }

    /**
     * Add content to new created custom column in taxonomy table
     *
     * @param  $content
     * @param  $column_name
     * @param  $term_id
     *
     * @return string
     * @since    1.0.0
     */
    public function addStatusTaxonomyColumnsContent($content, $column_name, $term_id)
    :string
    {
        $content   = '';
        $term      = get_term($term_id);
        $term_meta = get_option("taxonomy_term_$term_id");
        if ('settings' === $column_name) {
            if ($term_meta['public'] ?? false) {
                $content .= "&bullet; " . __('Public', 'merck-scraper') . "<br />";
            }
            if ($term_meta['show_in_admin_all_list'] ?? false) {
                $content .= "&bullet; " . __('Show in admin "All" list', 'merck-scraper') . "<br />";
            }
            if ($term_meta['show_in_admin_status_list'] ?? false) {
                $content .= "&bullet; " . __('Show in admin status list', 'merck-scraper') . "<br />";
            }
            if ($term_meta['hide_in_drop_down'] ?? false) {
                $content .= "&bullet; " . __('Hide in admin drop downs', 'merck-scraper') . "<br />";
            }
            $content = rtrim($content, ', ');
        }

        if ('count_trials' === $column_name) {
            $count       = wp_count_posts('trials');
            $slug        = $term->slug;
            $count_trials = 0;
            if (property_exists($count, $slug)) {
                $count_trials = $count->$slug;
            }
            $content .= '<a href="edit.php?post_status=' . $slug . '&post_type=trials" target="_self">' . $count_trials . '</a>';
        }

        return $content;
    }

    /**
     * Add status meta box to gutenberg editor
     *
     * @since    1.0.0
     */
    public function addStatusMetabox()
    :void
    {
        $is_block_editor = get_current_screen()->is_block_editor();
        if ($is_block_editor) {
            add_meta_box(
                'custom_trial_publication_status',
                __('Custom Trial Publication Status', 'merck-scraper'),
                [$this, 'statusMetaboxContent'],
                null,
                'side',
                'high'
            );
        }
    }

    /**
     * Add meta box content
     *
     * @global object $post
     * @since    1.0.0
     */
    public function statusMetaboxContent()
    :void
    {
        global $post;
        $returner = '';
        $statuses = self::getAllStatusArray();
        $returner .= '<select name="post_status_">';
        $returner .= '<option value="none">' . __('- Select status -', 'merck-scraper') . '</option>';
        foreach ($statuses as $key => $value) {
            $term = get_term_by('slug', $key, 'custom_trial_publication_status');
            if ($term) {
                $term_meta = get_option("taxonomy_term_$term->term_id");
            }
            $hidden = 0;
            if ($term && ($term_meta['hide_in_drop_down'] ?? false)) {
                $hidden = 1;
            }
            if ($key === $post->post_status) {
                $returner .= '<option value="' . $key . '" selected="selected">' . $value . '</option>';
            } else {
                if (!$hidden) {
                    $returner .= '<option value="' . $key . '">' . $value . '</option>';
                }
            }
        }
        $returner .= '</select>';
        echo $returner;
    }

    /**
     * Get array of all statuses
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

    /**
     * Initialize the view for the overridden query
     *
     * @global $pagenow
     * @since    1.0.1
     */
    public function overrideAdminPostListInit()
    :void
    {
        global $pagenow;
        if ('edit.php' === $pagenow) {
            add_action('parse_query', [$this, 'overrideAdminPostList']);
        }
    }

    /**
     * Override the post query
     *
     * @param  array|object  $query
     *
     * @return void
     * @since    1.0.1
     */
    public function overrideAdminPostList(array|object $query)
    :void
    {
        $statuses = self::getStatus();
        /* Check if query has no further params */
        if ((array_key_exists('post_status', $query->query) && empty($query->query['post_status']))) {
            $statuses_show_in_admin_all_list = self::getAllPostStatuses();
            foreach ($statuses as $status) {
                $term_meta = get_option("taxonomy_term_$status->term_id");
                if (!in_array($status->slug, $statuses_show_in_admin_all_list)) {
                    if ($term_meta['show_in_admin_all_list'] === 1) {
                        $statuses_show_in_admin_all_list[] = $status->slug;
                    }
                } else {
                    if ($term_meta['show_in_admin_all_list'] !== 1) {
                        if (($key = array_search($status->slug, $statuses_show_in_admin_all_list)) !== false) {
                            unset($statuses_show_in_admin_all_list[$key]);
                        }
                    }
                }
            }

            set_query_var('post_status', array_values($statuses_show_in_admin_all_list));

            return;
        }

        return;
    }

    /**
     * Get all available post statuses
     *
     * @return array
     * @since    1.0.17
     */
    private static function getAllPostStatuses()
    :array
    {
        global $wpdb;
        $query = $wpdb->get_results("SELECT DISTINCT $wpdb->posts.post_status as post_status FROM $wpdb->posts WHERE post_status NOT IN ('auto-draft', 'trash', 'inherit')");

        return wp_list_pluck($query, 'post_status');
    }

    /**
     * Redirects in admin context
     * - Redirect the main admin menu status page to original taxonomy page
     *
     * @global $pagenow
     * @since    1.0.4
     */
    public function adminRedirects()
    :void
    {
        global $pagenow;
        if (($pagenow === 'admin.php' || $pagenow === 'options-general.php') && filter_input(INPUT_GET, 'page') === 'custom-trial-publication-status-taxonomy') {
            wp_redirect(admin_url('edit-tags.php?taxonomy=custom_trial_publication_status'), 301);
            exit;
        }
    }

    /**
     * Parent file settings
     * - Used to fake the status page in main admin menu
     *
     * @param  string  $parent_file
     *
     * @return string
     * @since    1.0.4
     */
    public function parentFile(string $parent_file)
    :string
    {
        if (get_current_screen()->taxonomy === 'custom_trial_publication_status') {
            if (get_option('custom-trial-status-add-extra-admin-menu-item', false)) {
                $parent_file = 'custom-trial-publication-status-taxonomy';
            } else {
                $parent_file = 'options-general.php';
            }
        }

        return $parent_file;
    }

    /**
     * Submenu file settings
     * - Used to fake the status page in admin submenu
     *
     * @param  string  $submenu_file
     *
     * @return string
     * @since    1.0.8
     */
    public function submenuFile(string $submenu_file)
    :string
    {
        if (get_current_screen()->taxonomy === 'custom_trial_publication_status') {
            $submenu_file = 'custom-trial-publication-status-taxonomy';
        }

        return $submenu_file;
    }

    /**
     * Override the core status field with the custom status field
     * - If the post is getting trashed, don't do this!
     * - If the post is a planned post for the future, don't do this!
     * - If no custom status is set (equals 'none'), set post status to draft
     *
     * @param  array  $data
     * @param  array  $postarr
     *
     * @return array
     * @since    1.0.13
     */
    public function wpInsertPostData(array $data, array $postarr)
    :array
    {
        if (array_key_exists('post_status_', $postarr) && $data['post_status'] !== 'trash' && $data['post_status'] !== 'future') {
            $data['post_status'] = $postarr['post_status_'];
        }
        if ($data['post_status'] === 'none') {
            $data['post_status'] = 'draft';
        }

        return $data;
    }

    /**
     * Override the text on the Gutenberg publish button
     * - This is done to prevent confusion while publishing or saving a post
     *
     * @since    1.0.18
     */
    public function changePublishButtonGutenberg()
    :void
    {
        if (wp_script_is('wp-i18n')) {
            ?>
            <script type="text/javascript">
                wp.i18n.setLocaleData({'Publish': ['<?php echo __('Save'); ?>']});
            </script>
            <?php
        }
    }

    /**
     * Remove the "two click" publishing sidebar
     * - See: https://github.com/WordPress/gutenberg/issues/9077#issuecomment-458309231
     *
     * @since    1.0.18
     */
    public function removePublishingSidebarGutenberg()
    :void
    {
        wp_enqueue_script(
            "$this->pluginName-disablePublishSidebar",
            plugin_dir_url(__FILE__) . 'dist/disablePublishSidebar.js',
            ['jquery'],
            $this->version,
        );
    }

    /**
     * Override gettext snippets
     *
     * @param $translated
     * @param $original
     * @param $domain
     *
     * @return
     * @since    1.0.18
     */
    public function gettextOverride($translated, $original, $domain)
    {
        if ($original === 'Post published.') {
            $translated = __('Post saved.');
        }

        return $translated;
    }

    /**
     * Checks whether we're on the Admin edit-trials archive page
     *
     * @return bool
     */
    protected function isTrialsAdmin(): bool
    {
        if (is_admin() && function_exists('get_current_screen')) {
            return (get_current_screen()->id ?? '') === 'edit-trials';
        }
        return false;
    }
}
