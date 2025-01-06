<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc\Controller\Plugin;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\FormAdapter\ApiFormAdapter;
use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Query;
use AdvancedSearch\Response as SearchResponse;
use Common\Stdlib\EasyMeta;
use Doctrine\ORM\EntityManager;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Log\LoggerInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Exception;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Request;
use Omeka\Api\ResourceInterface;
use Omeka\Api\Response;
use Omeka\Permissions\Acl;
use Omeka\Stdlib\Paginator;

class ApiSearch extends AbstractPlugin
{
    /**
     * @var \Omeka\Permissions\Acl
     */
    protected $acl;

    /**
     * @var \Omeka\Api\Adapter\Manager
     */
    protected $adapterManager;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \AdvancedSearch\FormAdapter\ApiFormAdapter
     */
    protected $apiFormAdapter;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Omeka\Stdlib\Paginator
     */
    protected $paginator;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * @var \Laminas\I18n\Translator\TranslatorInterface
     */
    protected $translator;

    public function __construct(
        ApiManager $api,
        Acl $acl = null,
        AdapterManager $adapterManager = null,
        ApiFormAdapter $apiFormAdapter = null,
        EasyMeta $easyMeta = null,
        EntityManager $entityManager = null,
        LoggerInterface $logger = null,
        Paginator $paginator = null,
        SearchConfigRepresentation $searchConfig = null,
        SearchEngineRepresentation $searchEngine = null,
        TranslatorInterface $translator = null
    ) {
        $this->api = $api;
        $this->acl = $acl;
        $this->adapterManager = $adapterManager;
        $this->apiFormAdapter = $apiFormAdapter;
        $this->easyMeta = $easyMeta;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->paginator = $paginator;
        $this->searchConfig = $searchConfig;
        $this->searchEngine = $searchEngine;
        $this->translator = $translator;
    }

    /**
     * Execute a search API request via the querier if available, else the api.
     *
     * Allows to get a standard Omeka Response from the external engine.
     * The internal and noop engines are skipped and use the default api.
     *
     * The arguments are the same than \Omeka\Mvc\Controller\Plugin\Api::search().
     * - Some features of the Omeka api are not available.
     * - Currently, many parameters are unavailable. Some methods miss in Query.
     * - The event "api.search.query" is not triggered.
     * - returnScalar is not managed.
     * - Ideally, the external search engine should answer like the api?
     *
     * @see \Omeka\Api\Manager::search()
     * @see \Omeka\Mvc\Controller\Plugin\Api
     *
     * @todo Convert in a standard api restful controller or in a standard page with the api form adapter.
     *
     * @param string $resource
     * @param array $data
     * @return Response
     */
    public function __invoke($resource, array $data = [], array $options = [])
    {
        if (!$this->searchEngine) {
            // Unset the "index" option to avoid a loop.
            unset($data['index']);
            unset($options['index']);
            return $this->api->search($resource, $data, $options);
        }

        // Check it the resource is managed by this index.
        if (!in_array($resource, $this->searchEngine->setting('resource_types', []))) {
            // Unset the "index" option to avoid a loop.
            unset($data['index']);
            unset($options['index']);
            return $this->api->search($resource, $data, $options);
        }

        $request = new Request(Request::SEARCH, $resource);
        $request->setContent($data)
            ->setOption($options);
        return $this->execute($request);
    }

    /**
     * Execute a request.
     *
     * @see \Omeka\Api\Manager::execute()
     *
     * @param Request $request
     * @return Response
     */
    protected function execute(Request $request)
    {
        // Copy of ApiManager, with adaptations and simplifications.
        $t = $this->translator;

        // Get the adapter.
        try {
            $adapter = $this->adapterManager->get($request->getResource());
        } catch (ServiceNotFoundException $e) {
            throw new Exception\BadRequestException(sprintf(
                $t->translate('The API does not support the "%s" resource.'), // @translate
                $request->getResource()
            ));
        }

        // Verify that the current user has general access to this resource.
        if (!$this->acl->userIsAllowed($adapter, $request->getOperation())) {
            throw new Exception\PermissionDeniedException(sprintf(
                $t->translate('Permission denied for the current user to %s the %s resource.'), // @translate
                $request->getOperation(),
                $adapter->getResourceId()
            ));
        }

        // TODO Remove all the api bypass feature and use the Omeka query format directly in search engines (this is useless for sql).
        // It is not possible to initialize a search query for properties,
        // because they are removed lately in "api.search.pre" and re-added
        // early in "api.search.query". So an option is added to skip it.
        if ($request->getOption('initialize', true)) {
            $request->setOption('is_index_search', true);
            $this->api->initialize($adapter, $request);
        }

        // This is the true request.
        $response = $this->doAdapterSearch($request);

        // Validate the response and response content.
        if (!$response instanceof Response) {
            throw new Exception\BadResponseException('The API response must implement Omeka\Api\Response');
        }

        $response->setRequest($request);

        // Return scalar content as-is; do not validate or finalize.
        // if (Request::SEARCH === $request->getOperation() && $request->getOption('returnScalar')) {
        //     return $response;
        // }

        $validateContent = function ($value): void {
            if (!$value instanceof ResourceInterface) {
                throw new Exception\BadResponseException('API response content must implement Omeka\Api\ResourceInterface.');
            }
        };
        $content = $response->getContent();
        is_array($content) ? array_walk($content, $validateContent) : $validateContent($content);

        if ($request->getOption('finalize', true)) {
            $this->api->finalize($adapter, $request, $response);
        }

        return $response;
    }

    /**
     * Do the search via the index querier.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter
     * @see \AdvancedSearch\Controller\SearchController::searchAction()
     *
     * @param Request $request
     * @return Response
     */
    protected function doAdapterSearch(Request $request)
    {
        // TODO Clarify the process.

        // TODO Manage all standard params.
        // See \Omeka\Api\Adapter\AbstractEntityAdapter::search() to normalize params.
        // See \AdvancedSearch\Controller\SearchController::searchAction() for process.
        // Currently, only manage simple search and common params.
        // This corresponds to the search page form, but for the api.
        $query = $request->getContent();

        // Set default query parameters
        if (! isset($query['page'])) {
            $query['page'] = null;
        }
        if (! isset($query['per_page'])) {
            $query['per_page'] = null;
        }
        if (! isset($query['limit'])) {
            $query['limit'] = null;
        }
        if (! isset($query['offset'])) {
            $query['offset'] = null;
        }
        if (! isset($query['sort_by'])) {
            $query['sort_by'] = null;
        }
        if (isset($query['sort_order'])
            && in_array(strtolower((string) $query['sort_order']), ['asc', 'desc'])
        ) {
            $query['sort_order'] = strtolower((string) $query['sort_order']);
        } else {
            // Sort order is not forced because it may be the inverse for score.
            $query['sort_order'] = null;
        }

        // There is no form validation/filter.

        /** @see \AdvancedSearch\Form\Admin\ApiFormConfigFieldset */

        // Begin building the search query.
        $resourceType = $request->getResource();
        $searchConfigSettings = $this->searchConfig->settings();
        $searchConfigSettingsDefault = [
            'options' => [],
            'metadata' => [],
            'properties' => [],
            'sort_fields' => [],
        ];
        $searchFormSettings = empty($searchConfigSettings['form'])
            ? $searchConfigSettingsDefault
            : $searchConfigSettings['form'] + $searchConfigSettingsDefault;
        $searchFormSettings['resource'] = $resourceType;
        // Fix to be removed.
        $engineAdapter = $this->searchConfig->engineAdapter();
        if ($engineAdapter) {
            $availableFields = $engineAdapter->getAvailableFields();
            $searchFormSettings['available_fields'] = array_combine(array_keys($availableFields), array_keys($availableFields));
        } else {
            $searchFormSettings['available_fields'] = [];
        }

        // Solr doesn't allow unavailable args anymore (invalid or unknown).
        $searchFormSettings['only_available_fields'] = $engineAdapter
            && $engineAdapter instanceof \SearchSolr\EngineAdapter\Solarium;

        $searchFormSettings['aliases'] = $this->searchConfig->subSetting('index', 'aliases', []);
        $searchFormSettings['fields_query_args'] = $this->searchConfig->subSetting('index', 'query_args', []);
        $searchFormSettings['remove_diacritics'] = (bool) $this->searchConfig->subSetting('q', 'remove_diacritics', false);
        $searchFormSettings['default_search_partial_word'] = (bool) $this->searchConfig->subSetting('q', 'default_search_partial_word', false);

        $searchQuery = $this->apiFormAdapter->toQuery($query, $searchFormSettings);
        $searchQuery->setResourceTypes([$resourceType]);

        // Note: the event search.query is not triggered.

        // Nevertheless, the "is public" is automatically forced for visitors.
        // TODO Improve the visibility check (owner). Store all specific rights datas (by role or owner or something else (module access resource))?
        if (!$this->acl->getAuthenticationService()->hasIdentity()) {
            $searchQuery->setIsPublic(true);
        }

        // No site by default for the api (added by controller only).

        // Finish building the search query.
        // The default sort is the one of the search engine, so it is not added,
        // except if it is specifically set.
        $this->sortQuery($searchQuery, $query, $searchFormSettings['metadata'] ?? [], $searchFormSettings['sort_fields'] ?? []);
        $this->limitQuery($searchQuery, $query, $searchFormSettings['options'] ?? []);
        // $searchQuery->addOrderBy("$entityClass.id", $query['sort_order']);

        // No filter for specific limits.

        // No facets for the api.

        // Send the query to the search engine.
        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = $this->searchEngine
            ->querier()
            ->setQuery($searchQuery);
        try {
            $searchResponse = $querier->query();
        } catch (QuerierException $e) {
            throw new Exception\BadResponseException($e->getMessage(), $e->getCode(), $e);
        }

        // TODO Manage returnScalar.

        $totalResults = array_map(fn ($resource) => $searchResponse->getResourceTotalResults($resource), $this->searchEngine->setting('resource_types', []));

        // Get entities from the search response.
        $ids = $this->extractIdsFromResponse($searchResponse, $resourceType);
        $entityClass = $this->easyMeta->entityClass($resourceType);
        $repository = $this->entityManager->getRepository($entityClass);
        $entities = $repository->findBy([
            'id' => $ids,
        ]);

        // The original order of the ids must be kept.
        $orderedEntities = array_fill_keys($ids, null);
        foreach ($entities as $entity) {
            $orderedEntities[$entity->getId()] = $entity;
        }
        $entities = array_values(array_filter($orderedEntities));

        $response = new Response($entities);
        $response->setTotalResults($totalResults);
        return $response;
    }

    /**
     * Set sort_by and sort_order conditions to the query builder.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::sortQuery()
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::sortQuery()
     *
     * @param Query $searchQuery
     * @param array $query
     * @param array $metadata
     * @param array $sortFields
     */
    protected function sortQuery(Query $searchQuery, array $query, array $metadata, array $sortFields): void
    {
        if (empty($metadata) || empty($sortFields)) {
            return;
        }
        if (!is_string($query['sort_by'])) {
            return;
        }
        if (empty($metadata[$query['sort_by']])) {
            return;
        }
        $sortBy = $metadata[$query['sort_by']];

        if (isset($query['sort_order'])) {
            $sortOrder = strtolower((string) $query['sort_order']);
            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';
        } else {
            $sortOrder = null;
        }

        $property = $this->easyMeta->propertyTerm($sortBy);
        if ($property) {
            $sort = $sortOrder ? $property . ' ' . $sortOrder : $property;
        } elseif (in_array($sortBy, ['resource_class_label', 'owner_name'])) {
            $sort = $sortOrder ? $sortBy . ' ' . $sortOrder : $sortBy;
        } elseif (in_array($sortBy, ['id', 'is_public', 'created', 'modified'])) {
            $sort = $sortOrder ? $sortBy . ' ' . $sortOrder : $sortBy;
        } else {
            // Indicate that the sort is checked and that it will be default.
            $searchQuery->setSort(null);
            return;
        }

        // Check if the sort order is managed.
        if (in_array($sort, $sortFields)) {
            $searchQuery->setSort($sort);
        }

        // TODO Sort randomly is not managed (can be done partially in the view).
        // TODO Sort by item count is not managed.
        // Else sort by relevance (score) or by id?
    }

    /**
     * Set page, limit (max results) and offset (first result) conditions to the
     * query builder.
     *
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery()
     *
     * @param Query $searchQuery
     * @param array $query
     * @param array $options
     */
    protected function limitQuery(Query $searchQuery, array $query, array $options): void
    {
        if (is_numeric($query['page'])) {
            $searchPage = $query['page'] > 0 ? (int) $query['page'] : 1;
            if (is_numeric($query['per_page']) && $query['per_page'] > 0) {
                $perPage = (int) $query['per_page'];
                $this->paginator->setPerPage($perPage);
            } else {
                $perPage = $this->paginator->getPerPage();
            }
            $searchQuery->setLimitPage($searchPage, $perPage);
            return;
        }

        // Set the max limit.
        $maxResults = empty($options['max_results']) ? 1 : (int) $options['max_results'];

        // TODO Offset is not really managed in apiSearch (but rarely used).
        $limit = $query['limit'] > 0 ? min((int) $query['limit'], $maxResults) : $maxResults;
        $offset = $query['offset'] > 0 ? (int) $query['offset'] : null;
        if ($limit && $offset) {
            // TODO Check the formule to convert offset and limit to page and per page (rarely used).
            $searchPage = $offset > $limit ? 1 + (int) (($offset - 1) / $limit) : 1;
            $searchQuery->setLimitPage($searchPage, $limit);
        } elseif ($limit) {
            $searchQuery->setLimitPage(1, $limit);
        } elseif ($offset) {
            $searchQuery->setLimitPage($offset, 1);
        }
    }

    /**
     * Extract ids from a search response.
     *
     * @param SearchResponse $searchResponse
     * @param string $resourceType
     * @return int[]
     */
    protected function extractIdsFromResponse(SearchResponse $searchResponse, $resourceType)
    {
        return array_map(fn ($v) => $v['id'], $searchResponse->getResults($resourceType));
    }
}
