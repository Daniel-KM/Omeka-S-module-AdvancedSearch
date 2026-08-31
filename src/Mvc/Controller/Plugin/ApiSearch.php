<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc\Controller\Plugin;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
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
        ?Acl $acl = null,
        ?AdapterManager $adapterManager = null,
        ?EasyMeta $easyMeta = null,
        ?EntityManager $entityManager = null,
        ?LoggerInterface $logger = null,
        ?Paginator $paginator = null,
        ?SearchConfigRepresentation $searchConfig = null,
        ?SearchEngineRepresentation $searchEngine = null,
        ?TranslatorInterface $translator = null
    ) {
        $this->api = $api;
        $this->acl = $acl;
        $this->adapterManager = $adapterManager;
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
        if ($request->getOption('returnScalar')) {
            return $response;
        }

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

        // Begin building the search query. The standard api query is the pivot:
        // normalized generically, without any config, and resolved natively by
        // the querier.
        $resourceType = $request->getResource();
        $searchQuery = Query::fromApiQuery($query);
        $searchQuery->setResourceTypes([$resourceType]);

        // Keep the aliases of the config, so a filter on an aggregated field
        // stays usable through the api.
        $searchQuery->setAliases($this->searchConfig->subSetting('index', 'aliases', []));

        $fieldBoosts = $this->searchConfig->subSetting('index', 'field_boosts', []);
        $searchQuery->setFieldBoosts($fieldBoosts);
        $searchQuery->setMinimumMatch(trim((string) $this->searchConfig->subSetting('index', 'minimum_match', '')));
        $searchQuery->setTieBreaker(trim((string) $this->searchConfig->subSetting('index', 'tie_breaker', '')));

        // Note: the event search.query is not triggered.

        // Nevertheless, the "is public" is automatically forced for visitors.
        // TODO Improve the visibility check (owner). Store all specific rights datas (by role or owner or something else (module access resource))?
        if (!$this->acl->getAuthenticationService()->hasIdentity()) {
            $searchQuery->setIsPublic(true);
        }

        // No site by default for the api (added by controller only).

        // Finish building the search query. The sort is set by fromApiQuery()
        // when specified; the default sort stays the one of the search engine.
        $this->limitQuery($searchQuery, $query, []);

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

        $totalResults = array_map(fn ($resource) => $searchResponse->getResourceTotalResults($resource), $this->searchEngine->setting('resource_types', []));

        // Get resource IDs from the search response.
        $ids = $this->extractIdsFromResponse($searchResponse, $resourceType);

        // Handle returnScalar option (from options) or return_scalar (from query).
        // @see \Omeka\Api\Adapter\AbstractEntityAdapter::search()
        $scalarField = $request->getOption('returnScalar');
        if (!$scalarField && !empty($query['return_scalar'])) {
            $scalarField = $query['return_scalar'];
            $request->setOption('returnScalar', $scalarField);
        }
        if ($scalarField) {
            $content = $this->getScalarResult($ids, $resourceType, $scalarField);
            $response = new Response($content);
            $response->setTotalResults($totalResults);
            return $response;
        }

        // Get entities from the database with eager loading to avoid N+1 queries.
        // Without eager loading, each entity requires additional queries for
        // values, resourceClass, resourceTemplate, owner, etc. during finalize().
        $entityClass = $this->easyMeta->entityClass($resourceType);

        if (empty($ids)) {
            $entities = [];
        } elseif (is_subclass_of($entityClass, \Omeka\Entity\Resource::class)) {
            // Eager load associations for Resource entities to avoid N+1 queries.
            // This significantly improves performance when finalizing results.
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('e', 'v', 'vp', 'rc', 'rt', 'o', 't')
                ->from($entityClass, 'e')
                ->leftJoin('e.values', 'v')
                ->leftJoin('v.property', 'vp')
                ->leftJoin('e.resourceClass', 'rc')
                ->leftJoin('e.resourceTemplate', 'rt')
                ->leftJoin('e.owner', 'o')
                ->leftJoin('e.thumbnail', 't')
                ->where($qb->expr()->in('e.id', ':ids'))
                ->setParameter('ids', $ids);
            $entities = $qb->getQuery()->getResult();
        } else {
            // Fallback for non-Resource entities (e.g., Site, User).
            $repository = $this->entityManager->getRepository($entityClass);
            $entities = $repository->findBy(['id' => $ids]);
        }

        // The original order of the ids must be kept (Solr relevance order).
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
        // Max results allowed by the search config (api form adapter setting),
        // used as a cap. Null when not configured.
        $maxResults = empty($options['max_results']) ? null : (int) $options['max_results'];

        // Paginated request: a page and/or a per_page is provided. The page
        // defaults to 1 so a per_page alone is honored, like a standard browse.
        if (is_numeric($query['page']) || is_numeric($query['per_page'])) {
            $searchPage = is_numeric($query['page']) && (int) $query['page'] > 0
                ? (int) $query['page']
                : 1;
            if (is_numeric($query['per_page']) && (int) $query['per_page'] > 0) {
                $perPage = (int) $query['per_page'];
                $this->paginator->setPerPage($perPage);
            } else {
                $perPage = $this->paginator->getPerPage();
            }
            if ($maxResults && $perPage > $maxResults) {
                $perPage = $maxResults;
            }
            $searchQuery->setLimitPage($searchPage, $perPage);
            return;
        }

        $limit = is_numeric($query['limit']) && (int) $query['limit'] > 0 ? (int) $query['limit'] : null;
        $offset = is_numeric($query['offset']) && (int) $query['offset'] > 0 ? (int) $query['offset'] : null;

        // No pagination: return all results, like the core api. The search
        // engine requires a positive row count, so the configured max results
        // or a high value is used as "all".
        if ($limit === null) {
            $limit = $maxResults ?: 1000000;
        } elseif ($maxResults && $limit > $maxResults) {
            $limit = $maxResults;
        }

        // TODO Offset is not really managed in apiSearch (but rarely used).
        if ($offset) {
            $searchPage = $offset > $limit ? 1 + (int) (($offset - 1) / $limit) : 1;
            $searchQuery->setLimitPage($searchPage, $limit);
        } else {
            $searchQuery->setLimitPage(1, $limit);
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

    /**
     * Get scalar results for the given IDs and field.
     *
     * Returns an array with format [id => scalarValue, ...].
     *
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::search()
     *
     * @param int[] $ids Resource IDs from search results
     * @param string $resourceType Resource type (items, media, item_sets)
     * @param string $scalarField Field name to return (id, title, etc.)
     * @return array
     */
    protected function getScalarResult(array $ids, string $resourceType, string $scalarField): array
    {
        if (empty($ids)) {
            return [];
        }

        // For 'id' field, just return the IDs directly.
        if ($scalarField === 'id') {
            return array_combine($ids, $ids);
        }

        // For other fields, query the database.
        $entityClass = $this->easyMeta->entityClass($resourceType);
        $classMetadata = $this->entityManager->getClassMetadata($entityClass);
        $fieldNames = $classMetadata->getFieldNames();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->from($entityClass, 'omeka_root');

        if (in_array($scalarField, $fieldNames)) {
            // Direct field (e.g., 'title', 'created', 'modified').
            $qb->select(['omeka_root.id', 'omeka_root.' . $scalarField]);
        } else {
            // Association field (e.g., 'owner', 'resourceClass').
            $associationNames = $classMetadata->getAssociationNames();
            if (in_array($scalarField, $associationNames)) {
                $qb->select(['omeka_root.id', "IDENTITY(omeka_root.$scalarField) AS $scalarField"]);
            } else {
                // Field not found, return IDs as fallback.
                $this->logger->warn(
                    'The "{field}" field is not available for returnScalar in resource type "{type}".', // @translate
                    ['field' => $scalarField, 'type' => $resourceType]
                );
                return array_combine($ids, $ids);
            }
        }

        $qb->where($qb->expr()->in('omeka_root.id', ':ids'))
            ->setParameter('ids', $ids);

        $results = $qb->getQuery()->getScalarResult();

        // Build the result array with original order preserved.
        $content = array_fill_keys($ids, null);
        foreach ($results as $row) {
            $content[$row['id']] = $row[$scalarField];
        }

        return $content;
    }
}
