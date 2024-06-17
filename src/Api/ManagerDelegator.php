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
        if (empty($options['index']) && empty($data['index'])) {
            // Use the standard process when possible.
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
                $query = &$data;

                // Clean simple useless fields to avoid useless checks in many places.
                // TODO Clean property, numeric, dates, etc.
                foreach ($query as $key => $value) {
                    if ($value === '' || $value === null || $value === []) {
                        unset($query[$key]);
                    } elseif ($key === 'id') {
                        $values = is_array($value) ? $value : [$value];
                        $values = array_filter($values, fn ($id) => $id !== '' && $id !== null);
                        if (count($values)) {
                            $query[$key] = $values;
                        } else {
                            unset($query[$key]);
                        }
                    } elseif (in_array($key, [
                        'owner_id',
                        'site_id',
                    ])) {
                        if (is_numeric($value)) {
                            $query[$key] = (int) $value;
                        } else {
                            unset($query[$key]);
                        }
                    } elseif (in_array($key, [
                        'resource_class_id',
                        'resource_template_id',
                        'item_set_id',
                    ])) {
                        $values = is_array($value) ? $value : [$value];
                        $values = array_map('intval', array_filter($values, 'is_numeric'));
                        if (count($values)) {
                            $query[$key] = $values;
                        } else {
                            unset($query[$key]);
                        }
                    }
                }

                // Override some keys (separated from loop for clean process).
                $override = [];
                if (isset($query['owner_id'])) {
                    $override['owner_id'] = $query['owner_id'];
                    unset($query['owner_id']);
                }
                if (isset($query['resource_class_id'])) {
                    $override['resource_class_id'] = $query['resource_class_id'];
                    unset($query['resource_class_id']);
                }
                if (isset($query['resource_template_id'])) {
                    $override['resource_template_id'] = $query['resource_template_id'];
                    unset($query['resource_template_id']);
                }
                if (isset($query['item_set_id'])) {
                    $override['item_set_id'] = $query['item_set_id'];
                    unset($query['item_set_id']);
                }
                if (!empty($query['property'])) {
                    $override['property'] = $query['property'];
                    unset($query['property']);
                }
                // "site" is more complex and has already a special key "in_sites", that
                // can be true or false. This key is not overridden.
                // When the key "site_id" is set, the key "in_sites" is skipped in core.
                if (isset($query['site_id']) && (int) $query['site_id'] === 0) {
                    $query['in_sites'] = false;
                    unset($query['site_id']);
                }
                if ($override) {
                    $options['override'] = $override;
                }
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
