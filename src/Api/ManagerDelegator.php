<?php declare(strict_types=1);

namespace AdvancedSearch\Api;

// use AdvancedSearch\Mvc\Controller\Plugin\ApiSearch;
use AdvancedSearch\Stdlib\SearchResources;
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
     * @var \AdvancedSearch\Stdlib\SearchResources
     */
    protected $searchResources;

    /**
     * @var bool
     */
    protected $hasAdvancedSearch;

    /**
     * Override core api search:
     * - Allows to override a search when initialize is false.
     * - Execute a search API request with an option to do a quick search.
     *
     * The quick search is enabled when the argument "index" is true in the
     * options or in the data. It would be better to use the argument "options",
     * but it is not available in the admin user interface, for example in block
     * layouts, neither in the view helper api().
     * @todo Remove "index" from the display if any.
     * @todo Use a real delegator (with the delegate) to simplify override for property.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Manager::search()
     */
    public function search($resource, array $data = [], array $options = [])
    {
        // ApiSearch is set static to avoid a loop during init of Api Manager.
        /** @var \AdvancedSearch\Mvc\Controller\Plugin\ApiSearch $apiSearch */
        static $apiSearch;

        /** @see \AdvancedSearch\Module::onApiSearchPre() */
        if (empty($options['index'])
            && empty($data['index'])
            && empty($options['is_index_search'])
        ) {
            // Use the standard process when possible.
            // When option "initialized" is set false, init search here.
            if (array_key_exists('initialize', $options)
                && !$options['initialize']
                && in_array($resource, [
                    /** @see \AdvancedSearch\Module::attachListeners() */
                    'items',
                    'media',
                    'item_sets',
                    // Annotations are managed directly in Omeka, so no need to override.
                    // 'annotations',
                    // Generation does not use complex search for now.
                    // 'generations',
                ])
            ) {
                $override = null;
                $data = $this->searchResources->startOverrideQuery($data, $override);
                $options['override'] = $override;
            }
            return parent::search($resource, $data, $options);
        }

        if (is_null($apiSearch)) {
            $apiSearch = $this->controllerPlugins->get('apiSearch');
        }

        return $apiSearch($resource, $data, $options);
    }

    public function setControllerPlugins(ControllerPluginManager $controllerPlugins): self
    {
        $this->controllerPlugins = $controllerPlugins;
        return $this;
    }

    public function setSearchResources(SearchResources $searchResources): self
    {
        $this->searchResources = $searchResources;
        return $this;
    }
}
