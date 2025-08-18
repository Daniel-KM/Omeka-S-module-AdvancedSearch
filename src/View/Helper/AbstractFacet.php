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
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $site = null;

    /**
     * @var int
     */
    protected $siteId = null;

    /**
     * @var array
     */
    protected $siteLocales = [];

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
            $this->site = $plugins
                ->get(\Laminas\View\Helper\ViewModel::class)
                ->getRoot()
                ->getVariable('site');
            $this->siteId = $this->site->id();
            $locale = $plugins->get('siteSetting')('locale');
            $this->siteLocales = array_unique([
                $locale,
                $locale ? substr($locale, 0, 2) : '',
                // It should be null, but it is deprecated in resource->value()
                // so use empty string for now.
                // null,
                '',
            ]);
        }

        unset($this->queryBase['page']);

        // For active facets, there is no facet field.
        if ($facetField === null) {
            /** @see \AdvancedSearch\View\Helper\FacetActives::prepareActiveFacetData() */
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
        $isFacetModeDirect = in_array($options['mode'] ?? null, ['link', 'js']);

        foreach ($facetValues as $facetIndex => &$facetValue) {
            $facetValueValue = (string) $facetValue['value'];

            // The facet value is compared against a string (the query args).
            $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValueValue);
            if (strlen($facetValueLabel)) {
                [$active, $url] = $this->prepareActiveAndUrl($facetField, $facetValueValue, $isFacetModeDirect);
            } else {
                // TODO Check item sets facets that are not filtered by site with module Search Solr.
                // The facet value is not a real value; or not in the current
                // site and there is a bad index.
                // $active = false;
                // $url = '';
                unset($facetValues[$facetIndex]);
                continue;
            }

            $facetValue['value'] = $facetValueValue;
            $facetValue['label'] = $facetValueLabel;
            $facetValue['active'] = $active;
            $facetValue['url'] = $url;
        }
        unset($facetValue);

        // The facets should be reordered when option is "total then alpha".
        $isTotalThenAlpha = strtok($options['order'] ?? '', ' ') === 'total_alpha' && !empty($options['more']);
        if ($isTotalThenAlpha
            && count($facetValues) > $options['more'] + 1
        ) {
            // This sort is normally useless since it's done earlier, but may
            // avoid issues, in particular when the search engine does not
            // manage it.
            usort($facetValues, fn($a, $b) => $b['count'] <=> $a['count']);
            $firsts = array_slice($facetValues, 0, $options['more']);
            $lasts = array_slice($facetValues, $options['more']);
            usort($lasts, fn ($a, $b) => strnatcasecmp($a['value'], $b['value']));
            $facetValues = array_merge($firsts, $lasts);
        }

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
                    ? (string) $resource->displayTitle(null, $this->siteLocales)
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'owner':
            case 'owner_id':
            // Manage Solr quickly.
            case 'owner_id_is':
            case 'owner_is':
                /** @var \Omeka\Api\Representation\UserRepresentation $resource */
                // Only allowed users can read and search users.
                if (is_numeric($value)) {
                    try {
                        return $this->api->read('users', ['id' => $value])->getContent()->name();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
                // No more check: email is not reference, so it always the name.
                return $value;

            case 'site':
            case 'site_id':
            // Manage Solr quickly.
            case 'site_id_is':
            case 'site_is':
                /** @var \Omeka\Api\Representation\SiteRepresentation $resource */
                // Manage the case where a resource was indexed but removed.
                try {
                    return $this->api->read('sites', [is_numeric($value) ? 'id' : 'slug' => $value])->getContent()->title();
                } catch (\Exception $e) {
                    return null;
                }

            case 'class':
            case 'resource_class_id':
            case 'resource_class':
            // Manage Solr quickly.
            case 'resource_class_id_is':
            case 'resource_class_is':
            case 'resource_class_s':
                /** @var \Omeka\Api\Representation\ResourceClassRepresentation $resource */
                // Manage the case where a resource was indexed but removed.
                if (is_numeric($value)) {
                    try {
                        $resource = $this->api->read('resource_classes', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                } elseif (!strpos($value, ':')) {
                    return null;
                } else {
                    try {
                        $vocabularyId = $this->api->read('vocabularies', ['prefix' => strtok($value, ':')])->getContent()->id();
                        $resource = $this->api->read('resource_classes', ['vocabulary' => $vocabularyId, 'localName' => strtok(':')])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
                return $this->translate->__invoke($resource->label());

            case 'template':
            case 'resource_template_id':
            case 'resource_template':
            // Manage Solr quickly.
            case 'resource_template_id_is':
            case 'resource_template_is':
            case 'resource_template_s':
                // Manage the case where a resource was indexed but removed.
                if (is_numeric($value)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $resource */
                        $resource = $this->api->read('resource_templates', ['id' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                } else {
                    try {
                        $resource = $this->api->read('resource_templates', ['label' => $value])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
                return $this->translate->__invoke($resource->label());

            case 'item_sets_tree':
            // Manage Solr quickly.
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
            // Manage Solr quickly.
            case 'item_set_id_is':
            case 'item_set_is':
                $data = ['id' => $value];
                // The site id is required in public.
                // TODO Avoid to use searchOne(), but required for now with item set and site.
                if ($this->siteId) {
                    $data['site_id'] = $this->siteId;
                    /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                    $resource = $this->api->searchOne('item_sets', $data)->getContent();
                } else {
                    /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                    try {
                        $resource = $this->api->read('item_sets', $data)->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                }
                return $resource
                    ? (string) $resource->displayTitle(null, $this->siteLocales)
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'property':
            default:
                return $value;
        }
    }
}
