<?php declare(strict_types=1);

namespace Search\Api;

// use Search\Mvc\Controller\Plugin\ApiSearch;
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
    protected $hasAdvancedSearchPlus;

    /**
     * Execute a search API request with an option to do a quick search.
     *
     * The quick search is enabled when the argument "index" is true in the
     * options or in the data. It would be better to use the argument "options",
     * but it is not available in the admin user interface, for example in block
     * layouts, neither in the view helper api().
     * @todo Remove "index" from the display if any.
     * @todo Use a true delegator (with the delegate) to avoid the fix for AdvancedSearchPlus.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Manager::search()
     */
    public function search($resource, array $data = [], array $options = [])
    {
        // ApiSearch is set static to avoid a loop during init of Api Manager.
        /** @var \Search\Mvc\Controller\Plugin\ApiSearch $apiSearch */
        static $apiSearch;

        // Waiting for fix https://github.com/omeka/omeka-s/pull/1671.
        // The same in module AdvancedSearchPlus.
        if (isset($data['is_public']) && $data['is_public'] === '') {
            $data['is_public'] = null;
        }

        if (empty($options['index']) && empty($data['index'])) {
            // @see \AdvancedSearchPlus\Api\ManagerDelegator::search()
            if ($this->hasAdvancedSearchPlus
                // Use the standard process when possible.
                && array_key_exists('initialize', $options)
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

    public function setHasAdvancedSearchPlus(bool $hasAdvancedSearchPlus)
    {
        $this->hasAdvancedSearchPlus = $hasAdvancedSearchPlus;
        return $this;
    }
}
