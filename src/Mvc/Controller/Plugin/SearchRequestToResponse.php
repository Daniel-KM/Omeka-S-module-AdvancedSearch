<?php
namespace Search\Mvc\Controller\Plugin;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\Message;
use Omeka\Stdlib\Paginator;
use Search\Api\Representation\SearchIndexRepresentation;
use Search\Api\Representation\SearchPageRepresentation;
use Search\Querier\Exception\QuerierException;
use Zend\EventManager\Event;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class SearchRequestToResponse extends AbstractPlugin
{
    /**
     * @var SearchPageRepresentation
     */
    protected $page;

    /**
     * @var SearchIndexRepresentation
     */
    protected $index;

    /**
     * Get response from a search request.
     *
     * @param array $request Validated request.
     * @param SearchPageRepresentation $searchPage
     * @param SiteRepresentation $site
     * @return array Result with a status, data, and message if error.
     */
    public function __invoke(
        array $request,
        SearchPageRepresentation $searchPage,
        SiteRepresentation $site = null
    ) {
        $controller = $this->getController();
        $this->page = $searchPage;

        /** @var \Search\FormAdapter\FormAdapterInterface $formAdapter */
        $formAdapter = $searchPage->formAdapter();
        if (!$formAdapter) {
            $formAdapterName = $searchPage->formAdapterName();
            $message = new Message('Form adapter "%s" not found.', $formAdapterName); // @translate
            $controller->logger()->err($message);
            return [
                'status' => 'error',
                'message' => $message,
            ];
        }

        $searchPageSettings = $searchPage->settings();

        list($request, $isEmptyRequest) = $this->cleanRequest($request);
        if ($isEmptyRequest) {
            // Keep the other arguments of the request (mainly pagination, sort,
            // and facets).
            $request = ['*' => ''] + $request;
        }

        $searchFormSettings = isset($searchPageSettings['form']) ? $searchPageSettings['form'] : [];

        /** @var \Search\Query $query */
        $query = $formAdapter->toQuery($request, $searchFormSettings);

        // Add global parameters.

        $searchIndex = $this->index = $searchPage->index();
        $indexSettings = $searchIndex->settings();

        $user = $controller->identity();
        // TODO Manage roles from modules.
        $omekaRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];
        if ($user && in_array($user->getRole(), $omekaRoles)) {
            $query->setIsPublic(false);
        }

        if ($site) {
            $query->setSiteId($site->id());
        }

        if (array_key_exists('resource-type', $request)) {
            $resourceType = $request['resource-type'];
            if (!is_array($resourceType)) {
                $resourceType = [$resourceType];
            }
            $query->setResources($resourceType);
        } else {
            $query->setResources($indexSettings['resources']);
        }

        // Don't sort if it's already managed by the form, like the api form.
        $sortOptions = $this->getSortOptions();
        $sort = $query->getSort();
        if (!is_null($sort)) {
            if (isset($request['sort']) && isset($sortOptions[$request['sort']])) {
                $sort = $request['sort'];
            } else {
                reset($sortOptions);
                $sort = key($sortOptions);
            }
            $query->setSort($sort);
        }

        // Note: the global limit is managed via the pagination.
        $pageNumber = isset($request['page']) && $request['page'] > 0 ? (int) $request['page'] : 1;
        if (isset($request['per_page']) && $request['per_page'] > 0) {
            $perPage = (int) $request['per_page'];
        } elseif ($site) {
            $siteSettings = $controller->siteSettings();
            $perPage = $siteSettings->get('pagination_per_page') ?: $controller->settings()->get('pagination_per_page', Paginator::PER_PAGE);
        } else {
            $perPage = $controller->settings()->get('pagination_per_page', Paginator::PER_PAGE);
        }
        $query->setLimitPage($pageNumber, $perPage);

        $hasFacets = !empty($searchPageSettings['facets']);
        if ($hasFacets) {
            foreach ($searchPageSettings['facets'] as $name => $facet) {
                if ($facet['enabled']) {
                    $query->addFacetField($name);
                }
            }
            if (isset($searchPageSettings['facet_limit'])) {
                $query->setFacetLimit($searchPageSettings['facet_limit']);
            }
            if (isset($searchPageSettings['facet_languages'])) {
                $query->setFacetLanguages($searchPageSettings['facet_languages']);
            }
            if (!empty($request['limit']) && is_array($request['limit'])) {
                foreach ($request['limit'] as $name => $values) {
                    foreach ($values as $value) {
                        $query->addFilter($name, $value);
                    }
                }
            }
        }

        $eventManager = $controller->getEventManager();
        $eventArgs = $eventManager->prepareArgs([
            'request' => $request,
            'query' => $query,
        ]);
        $eventManager->triggerEvent(new Event('search.query.pre', $searchPage, $eventArgs));
        $query = $eventArgs['query'];

        // Send the query to the search engine.
        $querier = $searchIndex
            ->querier()
            ->setQuery($query);
        try {
            $response = $querier->query();
        } catch (QuerierException $e) {
            $message = new Message('Query error: %s', $e->getMessage()); // @translate
            $controller->logger()->err($message);
            return [
                'status' => 'error',
                'message' => $message,
            ];
        }

        if ($hasFacets) {
            $facetCounts = $response->getFacetCounts();
            $facetCounts = $this->sortFieldsByWeight($facetCounts, 'facets');
            $response->setFacetCounts($facetCounts);
        }

        $totalResults = array_map(function ($resource) use ($response) {
            return $response->getResourceTotalResults($resource);
        }, $indexSettings['resources']);
        $controller->paginator(max($totalResults), $pageNumber);

        return [
            'status' => 'success',
            'data' => [
                'site' => $site,
                'query' => $query,
                'response' => $response,
                'sortOptions' => $sortOptions,
            ],
        ];
    }

    /**
     * Remove all empty values (zero length strings) and check empty request.
     *
     * @param array $request
     * @return array First key is the cleaned request, the second a bool to
     * indicate if it is empty.
     */
    protected function cleanRequest(array $request)
    {
        // They should be already removed.
        unset($request['csrf'], $request['submit']);

        $this->arrayFilterRecursive($request);

        $checkRequest = array_diff_key(
            $request,
            [
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
                // Note: facets use "limit" currently.
                'page' => null, 'per_page' => null, 'limit' => null, 'offset' => null,
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::search().
                'sort_by' => null, 'sort_order' => null,
                // Used by Search.
                'resource-type' => null, 'sort' => null,
            ]
        );

        return [
            $request,
            !count($checkRequest),
        ];
    }

    /**
     * Remove zero-length values or an array, recursively.
     *
     * @param array $array
     * @return array
     */
    protected function arrayFilterRecursive(array &$array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->arrayFilterRecursive($value);
                if (!count($array[$key])) {
                    unset($array[$key]);
                }
            } elseif (!strlen(trim($array[$key]))) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * Normalize the sort options of the index.
     *
     * @todo Normalize the sort options when the index or page is hydrated. To be save in a hidden setting of the page.
     *
     * @return array
     */
    protected function getSortOptions()
    {
        $sortOptions = [];

        $settings = $this->page->settings();
        if (empty($settings['sort_fields'])) {
            return [];
        }

        $indexAdapter = $this->index->adapter();
        if (empty($indexAdapter)) {
            return [];
        }
        $sortFields = $this->index->adapter()->getAvailableSortFields($this->index);
        foreach ($settings['sort_fields'] as $name => $sortField) {
            if (!$sortField['enabled']) {
                // A break is possible, because now, the sort fields are ordered
                // when they are saved.
                break;
            }
            if (!empty($sortField['display']['label'])) {
                $label = $sortField['display']['label'];
            } elseif (!empty($sortFields[$name]['label'])) {
                $label = $sortFields[$name]['label'];
            } else {
                $label = $name;
            }
            $sortOptions[$name] = $label;
        }
        // The sort options are sorted one time only, when saved.

        return $sortOptions;
    }

    /**
     * Order the field by weigth.
     *
     * @param array $fields
     * @param string $settingName
     * @return array
     */
    protected function sortFieldsByWeight(array $fields, $settingName)
    {
        $settings = $this->page->settings()[$settingName];
        uksort($fields, function ($a, $b) use ($settings) {
            $aWeight = $settings[$a]['weight'];
            $bWeight = $settings[$b]['weight'];
            return $aWeight - $bWeight;
        });
        return $fields;
    }
}
