<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc\Controller\Plugin;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Querier\Exception\QuerierException;
use Common\Stdlib\PsrMessage;
use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\Paginator;

/**
 * FIXME Remove or simplify this class or use it to convert the query directly to a omeka (or sql) or a solarium query.
 * TODO Remove the useless view helpers.
 */
class SearchRequestToResponse extends AbstractPlugin
{
    /**
     * @var SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * Get response from a search request.
     *
     * @param array $request Validated request.
     * @return array Result with a status, data, and message if error.
     */
    public function __invoke(
        array $request,
        SearchConfigRepresentation $searchConfig,
        SiteRepresentation $site = null
    ): array {
        $this->searchConfig = $searchConfig;

        // The controller may not be available.
        $services = $searchConfig->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $logger = $services->get('Omeka\Logger');
        $translator = $services->get('MvcTranslator');

        $formAdapterName = $searchConfig->formAdapterName();
        if (!$formAdapterName) {
            $message = new PsrMessage('This search config has no form adapter.'); // @translate
            $logger->err($message->getMessage());
            return [
                'status' => 'error',
                'message' => $message->setTranslator($translator),
            ];
        }

        /** @var \AdvancedSearch\FormAdapter\FormAdapterInterface $formAdapter */
        $formAdapter = $searchConfig->formAdapter();
        if (!$formAdapter) {
            $message = new PsrMessage(
                'Form adapter "{name}" not found.', // @translate
                ['name' => $formAdapterName]
            );
            $logger->err($message->getMessage(), $message->getContext());
            return [
                'status' => 'error',
                'message' => $message->setTranslator($translator),
            ];
        }

        $searchConfigSettings = $searchConfig->settings();

        [$request, $isEmptyRequest] = $this->cleanRequest($request);
        if ($isEmptyRequest) {
            // Keep the other arguments of the request (mainly pagination, sort,
            // and facets).
            $request = ['*' => ''] + $request;
        }

        // TODO Prepare the full query here for simplification (or in FormAdapter).

        $searchFormSettings = $searchConfigSettings['form'] ?? [];

        $this->searchEngine = $searchConfig->engine();
        $searchAdapter = $this->searchEngine ? $this->searchEngine->adapter() : null;
        if ($searchAdapter) {
            $availableFields = $searchAdapter->setSearchEngine($this->searchEngine)->getAvailableFields();
            // Include the specific fields to simplify querying with main form.
            $searchFormSettings['available_fields'] = $availableFields;
            $specialFieldsToInputFields = [
                'resource_type' => 'resource_type',
                'is_public' => 'is_public',
                'owner/o:id' => 'owner',
                'site/o:id' => 'site',
                'resource_class/o:id' => 'class',
                'resource_template/o:id' => 'template',
                'item_set/o:id' => 'item_set',
            ];
            foreach ($availableFields as $field) {
                if (!empty($field['from'])
                    && isset($specialFieldsToInputFields[$field['from']])
                    && empty($availableFields[$specialFieldsToInputFields[$field['from']]])
                ) {
                    $searchFormSettings['available_fields'][$specialFieldsToInputFields[$field['from']]] = [
                        'name' => $specialFieldsToInputFields[$field['from']],
                        'to' => $field['name'],
                    ];
                }
            }
        } else {
            $searchFormSettings['available_fields'] = [];
        }

        // Solr doesn't allow unavailable args anymore (invalid or unknown).
        $searchFormSettings['only_available_fields'] = $searchAdapter
            && $searchAdapter instanceof \SearchSolr\Adapter\SolariumAdapter;

        // TODO Copy the option for per page in the search config form (keeping the default).
        // TODO Add a max per_page.
        if ($site) {
            $siteSettings = $plugins->get('siteSettings')();
            $settings = $plugins->get('settings')();
            $perPage = (int) $siteSettings->get('pagination_per_page')
                ?: (int) $settings->get('pagination_per_page', Paginator::PER_PAGE);
        } else {
            $settings = $plugins->get('settings')();
            $perPage = (int) $settings->get('pagination_per_page', Paginator::PER_PAGE);
        }
        $searchFormSettings['request']['per_page'] = $perPage ?: Paginator::PER_PAGE;

        // Facets are needed to check active facets with range, where the
        // default value should be skipped.
        $searchFormSettings['facet'] = $searchConfigSettings['facet'] ?? [];

        /** @var \AdvancedSearch\Query $query */
        $query = $formAdapter->toQuery($request, $searchFormSettings);

        // Append hidden query if any (filter, date range filter, filter query).
        $hiddenFilters = $searchConfigSettings['request']['hidden_query_filters'] ?? [];
        if ($hiddenFilters) {
            // TODO Convert a generic hidden query filters into a specific one?
            // $hiddenFilters = $formAdapter->toQuery($hiddenFilters, $searchFormSettings);
            $query->setFiltersQueryHidden($hiddenFilters);
        }

        // Add global parameters.

        $engineSettings = $this->searchEngine->settings();

        // Manage rights of resources to search: visibility public/private.

        // TODO Researcher and author may not access all private resources.
        // TODO Manage roles from modules and access level from module Access.

        // For module Access, this is a standard filter.

        $user = $plugins->get('identity')();
        $omekaRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];
        $userRole = $user ? $user->getRole() : null;

        $accessToAdmin = $user && in_array($userRole, $omekaRoles);
        if ($accessToAdmin) {
            $query->setIsPublic(false);
            // } elseif ($user && !in_array($userRole, $omekaRoles)) {
            // This is the default.
            // $query->setIsPublic(true);
        }

        if ($site) {
            $query->setSiteId($site->id());
        }

        $query->setByResourceType(!empty($searchConfigSettings['display']['by_resource_type']));

        // Check resources.
        $resourceTypes = $query->getResourceTypes();
        // TODO Check why resources may not be filled.
        $engineSettings['resource_types'] ??= ['resources'];
        if ($resourceTypes) {
            $resourceTypes = array_intersect($resourceTypes, $engineSettings['resource_types']) ?: $engineSettings['resource_types'];
            $query->setResourceTypes($resourceTypes);
        } else {
            $query->setResourceTypes($engineSettings['resource_types']);
        }

        // Check sort.
        // Don't sort if it's already managed by the form, like the api form.
        // TODO Previously: don't sort if it's already managed by the form, like the api form.
        $sort = $query->getSort();
        $sortOptions = $this->getSortOptions();
        if ($sort) {
            if (empty($request['sort']) || !isset($sortOptions[$request['sort']])) {
                reset($sortOptions);
                $sort = key($sortOptions);
                $query->setSort($sort);
            }
        } else {
            reset($sortOptions);
            $sort = key($sortOptions);
            $query->setSort($sort);
        }

        // Set the settings for facets.
        $hasFacets = !empty($searchConfigSettings['facet']['facets']);
        if ($hasFacets) {
            // Set all keys to simplify later process.
            $facetConfigDefault = [
                'field' => null,
                'label' => null,
                'type' => null,
                'order' => null,
                'limit' => 0,
                'languages' => [],
                'data_types' => [],
                'main_types' => [],
                'values' => [],
                // TODO "list" is currently a global setting, because the main query with the internal querier depends on it for all facets.
                // 'list' => null,
                'display_count' => false,
            ];
            foreach ($searchConfigSettings['facet']['facets'] as &$facetConfig) {
                $facetConfig += $facetConfigDefault;
            }
            unset($facetConfig);
            $query->setFacets($searchConfigSettings['facet']['facets']);
        }

        $query->setOption('facet_list', $searchConfigSettings['facet']['list'] ?? 'available');

        $eventManager = $services->get('Application')->getEventManager();
        $eventArgs = $eventManager->prepareArgs([
            'request' => $request,
            'query' => $query,
        ]);
        $eventManager->triggerEvent(new Event('search.query.pre', $searchConfig, $eventArgs));
        /** @var \AdvancedSearch\Query $query */
        $query = $eventArgs['query'];

        // Send the query to the search engine.

        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = $this->searchEngine
            ->querier()
            ->setQuery($query);
        try {
            $response = $querier->query();
        } catch (QuerierException $e) {
            $message = new PsrMessage(
                "Query error: {message}\nQuery: {json_query}", // @translate
                ['message' => $e->getMessage(), 'json_query' => json_encode($query->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]
            );
            $logger->err($message->getMessage(), $message->getContext());
            return [
                'status' => 'error',
                'message' => $message->setTranslator($translator),
            ];
        }

        // Order facet according to settings of the search page.
        if ($hasFacets) {
            $facetCounts = $response->getFacetCounts();
            $facetCounts = array_intersect_key($facetCounts, $searchConfigSettings['facet']['facets']);
            $response->setFacetCounts($facetCounts);
        }

        $totalResults = array_map(fn ($resource) => $response->getResourceTotalResults($resource), $engineSettings['resource_types']);
        $plugins->get('paginator')(max($totalResults), $query->getPage() ?: 1, $query->getPerPage());

        return [
            'status' => 'success',
            'data' => [
                'query' => $query,
                'response' => $response,
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
                // Used by Advanced Search.
                'resource_type' => null,
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
        $sortFieldsSettings = $this->searchConfig->subSetting('sorting', 'fields', []);
        if (empty($sortFieldsSettings)) {
            return [];
        }
        $engineAdapter = $this->searchEngine->adapter();
        if (empty($engineAdapter)) {
            return [];
        }
        $availableSortFields = $engineAdapter->setSearchEngine($this->searchEngine)->getAvailableSortFields();
        return array_intersect_key($sortFieldsSettings, $availableSortFields);
    }
}
