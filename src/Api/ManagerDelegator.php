<?php declare(strict_types=1);

namespace AdvancedSearch\Api;

// use AdvancedSearch\Mvc\Controller\Plugin\ApiSearch;
use Laminas\Mvc\Controller\PluginManager as ControllerPluginManager;

/**
 * API manager service (delegator).
 */
class ManagerDelegator extends \Omeka\Api\Manager
{
    /**
     * @var ControllerPluginManager
     */
    protected $controllerPlugins;

    /**
     * @var bool
     */
    protected $hasAdvancedSearch;

    /**
     * Override core api search:
     * - Allows to override a search by property when initialize is false.
     * - Execute a search API request with an option to do a quick search.
     *
     * The quick search is enabled when the argument "index" is true in the
     * options or in the data. It would be better to use the argument "options",
     * but it is not available in the admin user interface, for example in block
     * layouts, neither in the view helper api().
     * @todo Remove "index" from the display if any.
     * @todo Use a true delegator (with the delegate) to simplify override for property.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Manager::search()
     */
    public function search($resource, array $data = [], array $options = [])
    {
        // ApiSearch is set static to avoid a loop during init of Api Manager.
        /** @var \AdvancedSearch\Mvc\Controller\Plugin\ApiSearch $apiSearch */
        static $apiSearch;

        if (empty($options['index']) && empty($data['index'])) {
            // Use the standard process when possible.
            if (array_key_exists('initialize', $options)
                && !$options['initialize']
                && in_array($resource, [
                    'items',
                    'media',
                    'item_sets',
                    'annotations',
                    'generations',
                ])
                && !empty($data['property'])
            ) {
                $options['override'] = ['property' => $data['property']];
                unset($data['property']);
            }
            return parent::search($resource, $data, $options);
        }

        if (is_null($apiSearch)) {
            $apiSearch = $this->controllerPlugins->get('apiSearch');
        }

        return $apiSearch($resource, $data, $options);
    }

    public function setControllerPlugins(ControllerPluginManager $controllerPlugins)
    {
        $this->controllerPlugins = $controllerPlugins;
        return $this;
    }
}
