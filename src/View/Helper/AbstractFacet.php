<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AbstractFacet extends AbstractHelper
{
    /**
     * @var \Omeka\View\Helper\Api
     */
    protected $api;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Omeka\View\Helper\Logger
     */
    protected $logger;

    /**
     * @var \Laminas\View\Helper\Partial
     */
    protected $partialHelper;

    /**
     * @var \Laminas\I18n\View\Helper\Translate
     */
    protected $translate;

    /**
     * @var \Omeka\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * @var string
     */
    protected $partial;

    /**
     * @var string
     */
    protected $route = '';

    /**
     * @var int
     */
    protected $siteId;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array
     */
    protected $queryBase = [];

    /**
     * Create one facet (list of facet values) as link, checkbox, select, etc.
     *
     * @param string|array $facetField Field name or null for active facets.
     * @param array $facetValues Each facet value has two keys: value and count.
     * May have more data for specific facets, like facet range.
     * For active facets, keys are names and values are list of values.
     * @param array $options Search config settings for the current facet, that
     * should contain the main mode and, for some types of facets, the type and
     * the label or the label of all facets (active facets).
     * @param bool $asData Return an array instead of the partial.
     * @return string|array
     */
    public function __invoke(?string $facetField, array $facetValues, array $options = [], bool $asData = false)
    {
        static $facetsData = [];

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $this->api = $plugins->get('api');
        $this->logger = $plugins->get('logger');
        $this->translate = $plugins->get('translate');
        $this->urlHelper = $plugins->get('url');
        $this->easyMeta = $plugins->get('easyMeta')();
        $this->partialHelper = $plugins->get('partial');

        $this->route = $plugins->get('matchedRouteName')();
        $this->params = $view->params()->fromRoute();
        $this->queryBase = $view->params()->fromQuery();

        // Keep browsing inside an item set.
        if (!empty($this->params['item-set-id'])) {
            $this->route = 'site/item-set';
        }

        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        if ($isSiteRequest) {
            $this->siteId = $plugins
                ->get('Laminas\View\Helper\ViewModel')
                ->getRoot()
                ->getVariable('site')
                ->id();
        }

        unset($this->queryBase['page']);

        // For active facets, there is no facet field.
        if ($facetField === null) {
            $facetsData[$facetField] = $this->prepareActiveFacetData($facetValues, $options);
        } elseif (!isset($facetsData[$facetField])) {
            $facetsData[$facetField] = $this->prepareFacetData($facetField, $facetValues, $options);
        }

        if ($asData) {
            return $facetsData[$facetField];
        }

        return $this->partialHelper->__invoke($this->partial, $facetField === null
            ? ['activeFacets' => $facetsData[$facetField], 'options' => $options]
            : $facetsData[$facetField]);
    }

    /**
     * Get facet values with "url" when display is direct, "active" or "label".
     *
     * May contain other keys for specific facets, like "from" and "to" for
     * facet ranges or "level" for facet tree.
     */
    protected function prepareFacetData(string $facetField, array $facetValues, array $options): array
    {
        $isFacetModeDirect = ($options['mode'] ?? '') === 'link';

        foreach ($facetValues as /* $facetIndex => */ &$facetValue) {
            $facetValueValue = (string) $facetValue['value'];

            // The facet value is compared against a string (the query args).
            $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValueValue);
            if (strlen($facetValueLabel)) {
                [$active, $url] = $this->prepareActiveAndUrl($facetField, $facetValueValue, $isFacetModeDirect);
            } else {
                $active = false;
                $url = '';
            }

            $facetValue['value'] = $facetValueValue;
            $facetValue['label'] = $facetValueLabel;
            $facetValue['active'] = $active;
            $facetValue['url'] = $url;
        }
        unset($facetValue);

        return [
            'name' => $facetField,
            'facetValues' => $facetValues,
            'options' => $options,
            'tree' => null,
        ];
    }

    protected function prepareActiveAndUrl(string $facetField, string $facetValueValue, bool $isFacetModeDirect): array
    {
        $query = $this->queryBase;

        if (isset($query['facet'][$facetField])
            && array_search($facetValueValue, $query['facet'][$facetField]) !== false
        ) {
            $values = $query['facet'][$facetField];
            // TODO Remove this filter to keep all active facet values?
            $values = array_filter($values, fn ($v) => $v !== $facetValueValue);
            $query['facet'][$facetField] = $values;
            $active = true;
        } else {
            $query['facet'][$facetField][] = $facetValueValue;
            $active = false;
        }

        $url = $isFacetModeDirect
            ? $this->urlHelper->__invoke($this->route, $this->params, ['query' => $query])
            : '';

        return [$active, $url];
    }

    /**
     * The facets may be indexed by the search engine.
     *
     * @param string|int|float|null $value
     *
     * @todo Remove search of facet labels: use values from the response (possible only for solr for now).
     */
    protected function facetValueLabel(string $facetField, $value): ?string
    {
        if (is_null($value) || !strlen((string) $value)) {
            return null;
        }

        switch ($facetField) {
            case 'access':
            case 'resource_name':
            case 'resource_type':
                return $value;

            case 'is_public':
                return $value
                    ? 'Private'
                    : 'Public';

            case 'id':
                $data = ['id' => $value];
                // The site id is required in public.
                if ($this->siteId) {
                    $data['site_id'] = $this->siteId;
                }
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                try {
                    // Resources cannot be searched, only read.
                    $resource = $this->api->read('resources', $data)->getContent();
                } catch (\Exception $e) {
                }
                return $resource
                    ? (string) $resource->displayTitle()
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'owner':
            case 'owner_id':
                /** @var \Omeka\Api\Representation\UserRepresentation $resource */
                // Only allowed users can read and search users.
                if (is_numeric($value)) {
                    try {
                        $resource = $this->api->read('users', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->name();
                }
                // No more check: email is not reference, so it always the name.
                return $value;

            case 'site':
            case 'site_id':
                /** @var \Omeka\Api\Representation\SiteRepresentation $resource */
                if (is_numeric($value)) {
                    try {
                        $resource = $this->api->read('sites', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->title();
                }
                $resource = $this->api->searchOne('sites', ['slug' => $value])->getContent();
                return $resource
                    ? $resource->title()
                    // Manage the case where a resource was indexed but removed.
                    : null;

            case 'class':
            case 'resource_class_id':
            case 'resource_class':
                if (is_numeric($value)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceClassRepresentation $resource */
                        $resource = $this->api->read('resource_classes', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $this->translate->__invoke($resource->label());
                }
                $resource = $this->api->searchOne('resource_classes', ['term' => $value])->getContent();
                return $resource
                    ? $this->translate->__invoke($resource->label())
                    // Manage the case where a resource was indexed but removed.
                    : null;

            case 'template':
            case 'resource_template_id':
            case 'resource_template':
                if (is_numeric($value)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $resource */
                        $resource = $this->api->read('resource_templates', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->label();
                }
                $resource = $this->api->searchOne('resource_templates', ['label' => $value])->getContent();
                return $resource
                    ? $resource->label()
                    // Manage the case where a resource was indexed but removed.
                    : null;

            case 'item_sets_tree':
            case 'item_sets_tree_is':
                if (!is_numeric($value)) {
                    return $value;
                }
                if (!empty($this->tree)) {
                    if (is_numeric($value)) {
                        return $this->tree[$value]['title'] ?? $value;
                    }
                    // Confirm that the title exists.
                    // This is useless for now, since item sets tree are indexed by id.
                    $labels = array_column($this->tree ?? [], 'title', 'id');
                    $key = array_search($value, $labels);
                    if ($key !== false) {
                        return $value;
                    }
                }
                // The tree may not be available, so get title via api.
                // no break.

            case 'item_set':
            case 'item_set_id':
                $data = ['id' => $value];
                // The site id is required in public.
                if ($this->siteId) {
                    $data['site_id'] = $this->siteId;
                }
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                $resource = $this->api->searchOne('item_sets', $data)->getContent();
                return $resource
                    ? (string) $resource->displayTitle()
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'property':
            default:
                return $value;
        }
    }
}
