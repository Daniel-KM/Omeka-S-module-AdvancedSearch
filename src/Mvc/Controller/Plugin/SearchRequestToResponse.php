<?php declare(strict_types=1);

namespace Search\Mvc\Controller\Plugin;

use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\Message;
use Omeka\Stdlib\Paginator;
use Search\Api\Representation\SearchIndexRepresentation;
use Search\Api\Representation\SearchPageRepresentation;
use Search\Querier\Exception\QuerierException;

/**
 * FIXME Remove or simplify this class or use it to convert the query directly to a omeka (or sql) or a solarium query.
 * TODO Remove the useless view helpers.
 */
class SearchRequestToResponse extends AbstractPlugin
{
    /**
     * @var SearchPageRepresentation
     */
    protected $searchPage;

    /**
     * @var SearchIndexRepresentation
     */
    protected $searchIndex;

    /**
     * Get response from a search request.
     *
     * @param array $request Validated request.
     * @return array Result with a status, data, and message if error.
     */
    public function __invoke(
        array $request,
        SearchPageRepresentation $searchPage,
        SiteRepresentation $site = null
    ): array {
        $controller = $this->getController();
        $this->searchPage = $searchPage;

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

        $searchFormSettings = $searchPageSettings['form'] ?? [];

        /** @var \Search\Query $query */
        $query = $formAdapter->toQuery($request, $searchFormSettings);

        // Add global parameters.

        $this->searchIndex = $searchPage->index();
        $indexSettings = $this->searchIndex->settings();

        $user = $controller->identity();
        // TODO Manage roles from modules and visibility from modules (access resources).
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
            $perPage = (int) $siteSettings->get('pagination_per_page') ?: (int) $controller->settings()->get('pagination_per_page', Paginator::PER_PAGE);
        } else {
            $perPage = (int) $controller->settings()->get('pagination_per_page', Paginator::PER_PAGE);
        }
        $query->setLimitPage($pageNumber, $perPage);

        $hasFacets = !empty($searchPageSettings['facet']['facets']);
        if ($hasFacets) {
            // Set the settings.
            // TODO Set all the settings of the form one time (move process into Query, and other keys).
            $query->addFacetFields(array_keys($searchPageSettings['facet']['facets']));
            if (!empty($searchPageSettings['facet']['limit'])) {
                $query->setFacetLimit((int) $searchPageSettings['facet']['limit']);
            }
            if (!empty($searchPageSettings['facet']['languages'])) {
                $query->setFacetLanguages($searchPageSettings['facet']['languages']);
            }
            // Set the request for active facets.
            if (!empty($request['facet']) && is_array($request['facet'])) {
                foreach ($request['facet'] as $name => $values) {
                    foreach ($values as $value) {
                        $query->addActiveFacet($name, $value);
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

        /** @var \Search\Querier\QuerierInterface $querier */
        $querier = $this->searchIndex
            ->querier()
            ->setQuery($query);
        try {
            $response = $querier->query();
        } catch (QuerierException $e) {
            $message = new Message("Query error: %s\nQuery:%s", $e->getMessage(), json_encode($query->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); // @translate
            $controller->logger()->err($message);
            return [
                'status' => 'error',
                'message' => $message,
            ];
        }

        if ($hasFacets) {
            $facetCounts = $response->getFacetCounts();
            // Order facet according to settings of the search page.
            $facetCounts = array_intersect_key($facetCounts, $searchPageSettings['facet']['facets']);
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
     * @return array First key is the cleaned request, the second a bool to
     * indicate if it is empty.
     */
    protected function cleanRequest(array $request): array
    {
        // They should be already removed.
        unset(
            $request['csrf'],
            $request['submit']
        );

        $this->arrayFilterRecursive($request);

        $checkRequest = array_diff_key(
            $request,
            [
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
                'page' => null,
                'per_page' => null,
                'limit' => null,
                'offset' => null,
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::search().
                'sort_by' => null,
                'sort_order' => null,
                // Used by Search.
                'resource-type' => null,
                'sort' => null,
            ]
        );

        return [
            $request,
            !count($checkRequest),
        ];
    }

    /**
     * Remove zero-length values or an array, recursively.
     */
    protected function arrayFilterRecursive(array &$array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->arrayFilterRecursive($value);
                if (!count($array[$key])) {
                    unset($array[$key]);
                }
            } elseif (!strlen(trim((string) $array[$key]))) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * Normalize the sort options of the index.
     */
    protected function getSortOptions(): array
    {
        $sortFieldsSettings = $this->searchPage->subSetting('sort', 'fields', []);
        if (empty($sortFieldsSettings)) {
            return [];
        }
        $indexAdapter = $this->searchIndex->adapter();
        if (empty($indexAdapter)) {
            return [];
        }
        $availableSortFields = $indexAdapter->getAvailableSortFields($this->searchIndex);
        return array_intersect_key($sortFieldsSettings, $availableSortFields);
    }
}
