<?php

declare(strict_types = 1);

namespace Merck_Scraper\Includes;

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Merck_Scraper
 * @subpackage Merck_Scraper/includes
 * @author     Clique Studios <buildsomething@cliquestudios.com>
 */
class MSLoader
{

    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array $actions The actions registered with WordPress to fire when the plugin loads.
     */
    protected array $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array $filters The filters registered with WordPress to fire when the plugin loads.
     */
    protected array $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->actions = [];
        $this->filters = [];
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param  string  $hook           The name of the WordPress action that is being registered.
     * @param  object  $component      A reference to the instance of the object on which the action is defined.
     * @param  string  $callback       The name of the function definition on the $component.
     * @param  int     $priority       Optional. The priority at which the function should be fired. Default is 10.
     * @param  int     $accepted_args  Optional. The number of arguments that should be passed to the $callback. Default is 1.
     *
     * @since    1.0.0
     */
    public function addAction(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1)
    :void
    {
        $this->actions = $this->addUtility($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param  string  $hook           The name of the WordPress filter that is being registered.
     * @param  object  $component      A reference to the instance of the object on which the filter is defined.
     * @param  string  $callback       The name of the function definition on the $component.
     * @param  int     $priority       Optional. The priority at which the function should be fired. Default is 10.
     * @param  int     $accepted_args  Optional. The number of arguments that should be passed to the $callback. Default is 1
     *
     * @since    1.0.0
     */
    public function addFilter(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1)
    :void
    {
        $this->filters = $this->addUtility($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new rest api init action to the collection to be registered with WordPress.
     *
     * @param  object  $component      A reference to the instance of the object on which the action is defined.
     * @param  string  $callback       The name of the function definition on the $component.
     * @param  int     $priority       Optional. The priority at which the function should be fired. Default is 10.
     * @param  int     $accepted_args  Optional. The number of arguments that should be passed to the $callback. Default is 1.
     *
     * @since    1.0.0
     */
    public function addRestRoute(object $component, string $callback, int $priority = 10, int $accepted_args = 1)
    :void
    {
        $this->actions = $this->addUtility($this->actions, 'rest_api_init', $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @param array  $hooks         The collection of hooks that is being registered (that is, actions or filters).
     * @param string $hook          The name of the WordPress filter that is being registered.
     * @param object $component     A reference to the instance of the object on which the filter is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      The priority at which the function should be fired.
     * @param int    $accepted_args The number of arguments that should be passed to the $callback.
     *
     * @return   array                                  The collection of actions and filters registered with WordPress.
     * @since    1.0.0
     * @access   private
     */
    private function addUtility(array $hooks, string $hook, object $component, string $callback, int $priority, int $accepted_args)
    :array
    {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    1.0.0
     */
    public function executeUtility()
    :void
    {
        foreach ($this->filters as $util_hook) {
            add_filter($util_hook['hook'], [$util_hook['component'], $util_hook['callback']], $util_hook['priority'], $util_hook['accepted_args']);
        }

        foreach ($this->actions as $util_hook) {
            add_action($util_hook['hook'], [$util_hook['component'], $util_hook['callback']], $util_hook['priority'], $util_hook['accepted_args']);
        }
    }
}
