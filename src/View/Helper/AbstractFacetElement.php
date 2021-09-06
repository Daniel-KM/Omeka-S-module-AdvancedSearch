<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AbstractFacetElement extends AbstractHelper
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
     * @param array $facet A facet has two keys: value and count.
     * @return string|array
     */
    public function __invoke(string $name, array $facet, array $options = [], bool $asData = false)
    {
        // Variables are static to speed up process for all facets.
        // TODO Share the list between active and facet helpers.
        static $urlHelper;
        static $partialHelper;
        static $escapeHtml;
        static $escapeHtmlAttr;
        static $facetLabel;

        static $route;
        static $params;
        static $queryBase;

        static $facetsData = [];

        if (is_null($route)) {
            $view = $this->getView();
            $plugins = $view->getHelperPluginManager();
            $urlHelper = $plugins->get('url');
            $partialHelper = $plugins->get('partial');
            $escapeHtml = $plugins->get('escapeHtml');
            $escapeHtmlAttr = $plugins->get('escapeHtmlAttr');
            $facetLabel = $plugins->get('facetLabel');

            $this->api = $plugins->get('api');
            $this->translate = $plugins->get('translate');

            $route = $plugins->get('matchedRouteName')();
            $params = $view->params()->fromRoute();
            $queryBase = $view->params()->fromQuery();

            $isSiteRequest = $plugins->get('status')->isSiteRequest();
            if ($isSiteRequest) {
                $this->siteId = $plugins
                    ->get('Laminas\View\Helper\ViewModel')
                    ->getRoot()
                    ->getVariable('site')
                    ->id();
            }

            unset($queryBase['page']);
        }

        $facetValue = (string) $facet['value'];
        if (!isset($facetsData[$name][$facetValue])) {
            $query = $queryBase;

            // The facet value is compared against a string (the query args).
            $facetValueLabel = (string) $this->facetValueLabel($name, $facetValue);
            if (strlen($facetValueLabel)) {
                if (isset($query['facet'][$name]) && array_search($facetValue, $query['facet'][$name]) !== false) {
                    $values = $query['facet'][$name];
                    $values = array_filter($values, function ($v) use ($facetValue) {
                        return $v !== $facetValue;
                    });
                    $query['facet'][$name] = $values;
                    $active = true;
                } else {
                    $query['facet'][$name][] = $facetValue;
                    $active = false;
                }
                $url = $urlHelper($route, $params, ['query' => $query]);
            } else {
                $active = false;
                $url = '';
            }

            $facetsData[$name][$facetValue] = [
                'name' => $name,
                'value' => $facetValue,
                'label' => $facetValueLabel,
                'count' => $facet['count'],
                'active' => $active,
                'url' => $url,
                'options' => $options,
                // To speed up process.
                'escapeHtml' => $escapeHtml,
                'escapeHtmlAttr' => $escapeHtmlAttr,
                'translate' => $this->translate,
                'facetLabel' => $facetLabel,
            ];
        } elseif (isset($facet['count'])) {
            // When facet selected is used, the count is null, so it should be
            // updated when possible.
            $facetsData[$name][$facetValue]['count'] = $facet['count'];
        }

        if ($asData) {
            return $facetsData[$name][$facetValue];
        }

        return strlen($facetsData[$name][$facetValue]['label'])
            ? $partialHelper($this->partial, $facetsData[$name][$facetValue])
            : '';
    }

    protected function facetValueLabel(string $name, string $facetValue): ?string
    {
        if (!strlen($facetValue)) {
            return null;
        }

        // TODO Simplify the list of field names (for historical reasons).
        switch ($name) {
            case 'resource':
                $data = ['id' => $facetValue];
                // The site id is required in public.
                if ($this->siteId) {
                    $data['site_id'] = $this->siteId;
                }
                /** @var \Omeka\Api\Representation\ItemSetRepresentation $resource */
                $resource = $this->api->searchOne('resources', $data)->getContent();
                return $resource
                    ? (string) $resource->displayTitle()
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'item_set':
            // Deprecated keys (use simple lower singular with "_").
            case 'item_sets':
            case 'itemSet':
            case 'item_set_id':
            case 'item_set_id_field':
                $data = ['id' => $facetValue];
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

            case 'class':
            // Deprecated keys (use simple lower singular with "_").
            case 'resource_class':
            case 'resource_classes':
            case 'resourceClass':
            case 'resource_class_id':
            case 'resource_class_id_field':
                if (is_numeric($facetValue)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceClassRepresentation $resource */
                        $resource = $this->api->read('resource_classes', ['id' => $facetValue])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $this->translate->__invoke($resource->label());
                }
                $resource = $this->api->searchOne('resource_classes', ['term' => $facetValue])->getContent();
                return $resource
                    ? $this->translate->__invoke($resource->label())
                    // Manage the case where a resource was indexed but removed.
                    : null;
                break;

            case 'template':
            // Deprecated keys (use simple lower singular with "_").
            case 'resource_template':
            case 'resource_templates':
            case 'resourceTemplate':
            case 'resource_template_id':
            case 'resource_template_id_field':
                if (is_numeric($facetValue)) {
                    try {
                        /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $resource */
                        $resource = $this->api->read('resource_templates', ['id' => $facetValue])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $resource->label();
                }
                $resource = $this->api->searchOne('resource_templates', ['label' => $facetValue])->getContent();
                return $resource
                    ? $resource->label()
                    // Manage the case where a resource was indexed but removed.
                    : null;
                break;

            case 'property':
            // Deprecated keys (use simple lower singular with "_").
            case 'properties':
            default:
                return $facetValue;
        }
    }
}
