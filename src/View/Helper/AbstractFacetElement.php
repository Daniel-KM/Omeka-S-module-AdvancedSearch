<?php

namespace Search\View\Helper;

use Zend\Mvc\Application;
use Zend\View\Helper\AbstractHelper;

class AbstractFacetElement extends AbstractHelper
{
    /**
     * @var string
     */
    protected $partial;

    /**
     * @var Application $application
     */
    protected $application;

    /**
     * @var \Omeka\View\Helper\Api
     */
    protected $api;

    /**
     * @var \Zend\I18n\View\Helper\Translate}
     */
    protected $translate;

    /**
     * @var int
     */
    protected $siteId;

    /**
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Create one facet link.
     *
     * @param string $name
     * @param array $facet
     * @return string
     */
    public function __invoke($name, $facet)
    {
        // Variables are static to speed up process.
        static $urlHelper;
        static $partialHelper;
        static $escapeHtml;

        static $mvcEvent;
        static $routeMatch;
        static $request;

        static $route;
        static $params;
        static $queryBase;

        if (is_null($mvcEvent)) {
            $plugins = $this->getView()->getHelperPluginManager();
            $urlHelper = $plugins->get('url');
            $partialHelper = $plugins->get('partial');
            $escapeHtml = $plugins->get('escapeHtml');
            $this->api = $plugins->get('api');
            $this->translate = $plugins->get('translate');

            $mvcEvent = $this->application->getMvcEvent();
            $routeMatch = $mvcEvent->getRouteMatch();
            $request = $mvcEvent->getRequest();

            $route = $routeMatch->getMatchedRouteName();
            $params = $routeMatch->getParams();
            $queryBase = $request->getQuery()->toArray();

            if ($plugins->get('status')->isSiteRequest()) {
                $this->siteId = $plugins
                    ->get('Zend\View\Helper\ViewModel')
                    ->getRoot()
                    ->getVariable('site')
                    ->id();
            }

            unset($queryBase['page']);
        }

        $query = $queryBase;

        // The facet value is compared against a string (the query args).
        $facetValue = (string) $facet['value'];
        $facetValueLabel = $this->facetValueLabel($name, $facetValue);
        if (!strlen($facetValueLabel)) {
            return '';
        }

        if (isset($query['limit'][$name]) && array_search($facetValue, $query['limit'][$name]) !== false) {
            $values = $query['limit'][$name];
            $values = array_filter($values, function ($v) use ($facetValue) {
                return $v !== $facetValue;
            });
            $query['limit'][$name] = $values;
            $active = true;
        } else {
            $query['limit'][$name][] = $facetValue;
            $active = false;
        }

        return $partialHelper($this->partial, [
            'name' => $name,
            'value' => $facetValue,
            'label' => $facetValueLabel,
            'count' => $facet['count'],
            'active' => $active,
            'url' => $urlHelper($route, $params, ['query' => $query]),
            // To speed up process.
            'escapeHtml' => $escapeHtml,
        ]);
    }

    protected function facetValueLabel($name, $facetValue)
    {
        if (!strlen($facetValue)) {
            return null;
        }

        // TODO Simplify the list of field names (for historical reasons).
        switch ($name) {
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
                    ? $resource->displayTitle()
                    // Manage the case where a resource was indexed but removed.
                    // In public side, the item set should belong to a site too.
                    : null;

            case 'resource_classes':
            case 'resourceClass':
            case 'resource_class_id':
            case 'resource_class_id_field':
                $translate = $this->translate;
                /* @var \Omeka\Api\Representation\ResourceClassRepresentation $resource */
                if (is_numeric($facetValue)) {
                    try {
                        $resource = $this->api->read('resource_classes', ['id' => $facetValue])->getContent();
                    } catch (\Exception $e) {
                        return null;
                    }
                    return $translate($resource->label());
                }
                $resource = $this->api->searchOne('resource_classes', ['term' => $facetValue])->getContent();
                return $resource
                    ? $translate($resource->label())
                    // Manage the case where a resource was indexed but removed.
                    : null;
                break;

            case 'resource_templates':
            case 'resourceTemplate':
            case 'resource_template_id':
            case 'resource_template_id_field':
                /* @var \Omeka\Api\Representation\ResourceTemplateRepresentation $resource */
                if (is_numeric($facetValue)) {
                    try {
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

            case 'properties':
            default:
                return $facetValue;
        }
    }
}
