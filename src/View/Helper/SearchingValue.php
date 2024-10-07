<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ValueRepresentation;

class SearchingValue extends AbstractHelper
{
    /**
     * Get the link or url to the search page of the current site for a value.
     *
     * Adapted:
     * @see \AdvancedResourceTemplate\Module::handleRepresentationValueHtml()
     * @see \AdvancedSearch\View\Helper\SearchingValue::__invoke()
     *
     * @var array $options Available options:
     * - as_url (bool)
     * - as_array (array): array with key "url" and "label". The label may have
     *   html tags.
     */
    public function __invoke(ValueRepresentation $value, array $options = []): string
    {
        $view = $this->getView();

        /**
         * @var \Omeka\View\Helper\Hyperlink $hyperlink
         */
        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $status = $plugins->get('status');
        $hyperlink = $plugins->get('hyperlink');
        $getSearchConfig = $plugins->get('getSearchConfig');

        // Check if the current site/admin has a search form.
        $isAdmin = $status->isAdminRequest();
        $isSite = $status->isSiteRequest();
        $siteSlug = $isSite ? $status->getRouteParam('site-slug') : null;

        $advancedSearchConfig = $getSearchConfig();
        $engine = $advancedSearchConfig ? $advancedSearchConfig->engine() : null;
        $querier = $engine ? $engine->querier() : null;
        $isInternalSearch = $querier instanceof \AdvancedSearch\Querier\InternalQuerier;
        // Fallback to standard search for module Advanced search.
        if (!$querier || $querier instanceof \AdvancedSearch\Querier\NoopQuerier) {
            $advancedSearchConfig = null;
        }

        $asUrl = !empty($options['as_url']);
        $asArray = !empty($options['as_array']);

        $resource = $value->resource();
        $html = $value->asHtml();
        $property = $value->property()->term();
        $controllerName = $resource->getControllerName();

        $vr = $value->valueResource();
        $uri = $value->uri();
        $val = (string) $value->value();
        $uriOrVal = $uri ?: $val;

        // Advanced search.
        if ($advancedSearchConfig) {
            // For solr, at the choice of the administrator, the index may use
            // the real title for the value resource and no id.

            // There is currently no way to convert a query to a request, so do
            // it manually, because terms are managed in all queriers anyway.
            /*
            $query = new \AdvancedSearch\Query();
            if ($vr) {
                $query->addFilterQuery($property, $vr->id(), 'res');
            } else {
                $val = (string) $value->value();
                $query->addFilter($property, $uriOrVal);
            }
            $urlQuery = $advancedSearchConfig->toRequest($query);
            */

            if ($isInternalSearch) {
                $urlQuery = [
                    'filter' => [[
                        'field' => $property,
                        'type' => $vr ? 'res' : 'eq',
                        'value' => $vr ? $vr->id() : $uriOrVal,
                    ]],
                ];
            } else {
                // For resource, the id may or may not be indexed in Solr, so
                // use title. And the property may not be indexed too, anyway.
                if ($vr) {
                    $urlQuery = ['filter' => [
                        [
                            'field' => $property,
                            'type' => 'res',
                            'value' => $vr->id(),
                        ],
                        [
                            'join' => 'or',
                            'field' => $property,
                            'type' => 'eq',
                            'value' => $vr->displayTitle(),
                        ],
                    ]];
                } else {
                    $urlQuery = [
                        'filter' => [[
                            'field' => $property,
                            'type' => 'eq',
                            'value' => $uriOrVal,
                        ]],
                    ];
                }
            }
            $searchUrl = $isAdmin
                ? $advancedSearchConfig->adminSearchUrl(false, $urlQuery)
                : $advancedSearchConfig->siteUrl($siteSlug, false, $urlQuery);
        } else {
            // Standard search.
            if ($vr) {
                $searchUrl = $url(
                    $isAdmin ? 'admin/default' : 'site/resource',
                    ['site-slug' => $siteSlug, 'controller' => $controllerName, 'action' => 'browse'],
                    ['query' => [
                        'property[0][property]' => $property,
                        'property[0][type]' => 'res',
                        'property[0][text]' => $vr->id(),
                    ]]
                );
            } else {
                $searchUrl = $url(
                    $isAdmin ? 'admin/default' : 'site/resource',
                    ['site-slug' => $siteSlug, 'controller' => $controllerName, 'action' => 'browse'],
                    ['query' => [
                        'property[0][property]' => $property,
                        'property[0][type]' => 'eq',
                        'property[0][text]' => $uri ?: $val,
                    ]]
                );
            }
        }

        if ($asUrl) {
            return $searchUrl;
        }

        $searchLabel = $vr ? $html : (strlen($val) ? $val : $uri);

        if ($asArray) {
            return [
                'url' => $searchUrl,
                'label' => $searchLabel,
            ];
        }

        return $hyperlink($searchLabel, $searchUrl, ['class' => 'metadata-search-link']);
    }
}
