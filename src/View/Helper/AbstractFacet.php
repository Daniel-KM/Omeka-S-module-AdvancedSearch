<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AbstractFacet extends AbstractHelper
{
    /**
     * @var string
     */
    protected $partial;

    /**
     * @var \Omeka\View\Helper\Api
     */
    protected $api;

    /**
     * @var \Laminas\I18n\View\Helper\Translate
     */
    protected $translate;

    /**
     * @var int
     */
    protected $siteId;

    /**
     * Create one facet as link, checkbox or button.
     *
     * @param array $facetValues Each facet value has two keys: value and count.
     * @return string|array
     */
    public function __invoke(string $facetField, array $facetValues, array $options = [], bool $asData = false)
    {
        static $facetsData = [];

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $urlHelper = $plugins->get('url');
        $partialHelper = $plugins->get('partial');

        $this->api = $plugins->get('api');
        $this->translate = $plugins->get('translate');

        $route = $plugins->get('matchedRouteName')();
        $params = $view->params()->fromRoute();
        $queryBase = $view->params()->fromQuery();

        // Keep browsing inside an item set.
        if (!empty($params['item-set-id'])) {
            $route = 'site/item-set';
        }

        $isSiteRequest = $plugins->get('status')->isSiteRequest();
        if ($isSiteRequest) {
            $this->siteId = $plugins
                ->get('Laminas\View\Helper\ViewModel')
                ->getRoot()
                ->getVariable('site')
                ->id();
        }

        unset($queryBase['page']);
        // Add url when display is direct, active and label.
        if (!isset($facetsData[$facetField])) {
            $isFacetModeDirect = ($options['mode'] ?? '') === 'link';
            foreach ($facetValues as /* $facetIndex => */ &$facetValue) {
                $facetValueValue = (string) $facetValue['value'];
                $query = $queryBase;

                // The facet value is compared against a string (the query args).
                $facetValueLabel = (string) $this->facetValueLabel($facetField, $facetValueValue);
                if (strlen($facetValueLabel)) {
                    if (isset($query['facet'][$facetField]) && array_search($facetValueValue, $query['facet'][$facetField]) !== false) {
                        $values = $query['facet'][$facetField];
                        $values = array_filter($values, function ($v) use ($facetValueValue) {
                            return $v !== $facetValueValue;
                        });
                        $query['facet'][$facetField] = $values;
                        $active = true;
                    } else {
                        $query['facet'][$facetField][] = $facetValueValue;
                        $active = false;
                    }
                    $url = $isFacetModeDirect ? $urlHelper($route, $params, ['query' => $query]) : '';
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
            $facetsData[$facetField] = [
                'name' => $facetField,
                'facetValues' => $facetValues,
                'options' => $options,
            ];
        }

        if ($asData) {
            return $facetsData[$facetField];
        }

        return $partialHelper($this->partial, $facetsData[$facetField]);
    }

    /**
     * The facets may be indexed by the search engine.
     *
     * @todo Remove search of facet labels: use values from the response.
     */
    protected function facetValueLabel(string $facetField, string $value): ?string
    {
        if (!strlen($value)) {
            return null;
        }

        switch ($facetField) {
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

            case 'item_set':
            case 'item_set_id':
            case 'item_set':
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
