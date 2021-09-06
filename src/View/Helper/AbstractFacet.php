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
    public function __invoke(string $name, array $facetValues, array $options = [], bool $asData = false)
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
        if (!isset($facetsData[$name])) {
            $isFacetModeDirect = ($options['mode'] ?? '') === 'link';
            foreach ($facetValues as /* $facetIndex => */ &$facetValue) {
                $facetValueValue = (string) $facetValue['value'];
                $query = $queryBase;

                // The facet value is compared against a string (the query args).
                $facetValueLabel = (string) $this->facetValueLabel($name, $facetValueValue);
                if (strlen($facetValueLabel)) {
                    if (isset($query['facet'][$name]) && array_search($facetValueValue, $query['facet'][$name]) !== false) {
                        $values = $query['facet'][$name];
                        $values = array_filter($values, function ($v) use ($facetValueValue) {
                            return $v !== $facetValueValue;
                        });
                        $query['facet'][$name] = $values;
                        $active = true;
                    } else {
                        $query['facet'][$name][] = $facetValueValue;
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
            $facetsData[$name] = [
                'name' => $name,
                'facetValues' => $facetValues,
                'options' => $options,
            ];
        }

        if ($asData) {
            return $facetsData[$name];
        }

        return $partialHelper($this->partial, $facetsData[$name]);
    }

    protected function facetValueLabel(string $name, string $value): ?string
    {
        if (!strlen($value)) {
            return null;
        }

        // TODO Simplify the list of field names (for historical reasons).
        switch ($name) {
            case 'resource':
                $data = ['id' => $value];
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

            case 'class':
            // Deprecated keys (use simple lower singular with "_").
            case 'resource_class':
            case 'resource_classes':
            case 'resourceClass':
            case 'resource_class_id':
            case 'resource_class_id_field':
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
                break;

            case 'template':
            // Deprecated keys (use simple lower singular with "_").
            case 'resource_template':
            case 'resource_templates':
            case 'resourceTemplate':
            case 'resource_template_id':
            case 'resource_template_id_field':
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
                break;

            case 'property':
            // Deprecated keys (use simple lower singular with "_").
            case 'properties':
            default:
                return $value;
        }
    }
}
