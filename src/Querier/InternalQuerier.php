<?php declare(strict_types=1);

namespace Search\Querier;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Search\Querier\Exception\QuerierException;
use Search\Response;

class InternalQuerier extends AbstractQuerier
{
    /**
     * MariaDB can only use 61 tables in a join and Omeka adds a join for each
     * property. To manage modules, the number is limited to 50.
     */
    const REQUEST_MAX_ARGS = 50;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $resourceTypes;

    /**
     * @var array
     */
    protected $args;

    /**
     * @var array
     */
    protected $argsWithoutActiveFacets;

    public function query(): Response
    {
        $this->response = new Response;

        $this->args = $this->getPreparedQuery();
        if (is_null($this->args)) {
            return $this->response
                ->setMessage('An issue occurred.'); // @translate
        }

        /**
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         */
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $api = $plugins->get('api');
        $hasReferences = $plugins->has('references');

        // The standard api way implies a double query, because scalar doesn't
        // set the full total and doesn't use paginator.
        // So get all ids, then slice it here.
        $dataQuery = $this->args;
        $limit = empty($dataQuery['limit']) ? null : (int) $dataQuery['limit'];
        $offset = empty($dataQuery['offset']) ? 0 : (int) $dataQuery['offset'];
        unset($dataQuery['limit'], $dataQuery['offset']);

        foreach ($this->resourceTypes as $resourceType) {
            try {
                // Return scalar doesn't allow to get the total of results.
                // So skip offset and limit, then apply them in order to avoid
                // the double query.
                // TODO Check if this internal api paginator is quicker in all cases (small/long results) than previous double query.
                $apiResponse = $api->search($resourceType, $dataQuery, ['returnScalar' => 'id']);
                $totalResults = $apiResponse->getTotalResults();
                $result = $apiResponse->getContent();
                if ($result && ($offset || $limit)) {
                    $result = array_slice($result, $offset, $limit ?: null);
                    // $apiResponse->setContent($result);
                }
            } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                throw new QuerierException($e->getMessage(), $e->getCode(), $e);
            }
            $this->response->setResourceTotalResults($resourceType, $totalResults);
            if ($totalResults) {
                $result = array_map(function ($v) {
                    return ['id' => $v];
                }, $result);
            } else {
                $result = [];
            }
            $this->response->addResults($resourceType, $result);
        }

        $this->response->setTotalResults(
            array_sum($this->response->getResourceTotalResults())
        );

        if ($hasReferences) {
            $this->fillFacetResponse();
        }

        return $this->response
            ->setIsSuccess(true);
    }

    public function querySuggestions(): Response
    {
        $this->response = new Response;

        $this->args = $this->getPreparedQuery();
        if (is_null($this->args)) {
            return $this->response
                ->setMessage('An issue occurred.'); // @translate
        }

        /**
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         */
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $api = $plugins->get('api');

        // Only items and itemSets are managed currently.
        $resourceTypesToClasses = [
            // 'items' => \Omeka\Entity\Item::class,
            // 'item_sets' => \Omeka\Entity\ItemSet::class,
            'items' => 'Omeka\\\\Entity\\\\Item',
            'item_sets' => 'Omeka\\\\Entity\\\\ItemSet',
        ];
        $sqlIsPublic = $this->query->getIsPublic()
            ? 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1'
            : '';

        // The bind is not working in a array, so use a direct string.
        $classes = array_intersect_key($resourceTypesToClasses, array_flip($this->resourceTypes));
        $classes = '"' . implode('", "', $classes) . '"';

        // TODO Manage site id and item set id and any other filter query.
        // TODO Use the full text search table.

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $q = $this->query->getQuery();
        $bind = [
            // 'resource_types' => $classes,
            'limit' => $this->query->getLimit(),
            'value_length' => mb_strlen($q),
        ];
        $types = [
            // 'resource_types' => $connection::PARAM_STR_ARRAY,
            'limit' => \PDO::PARAM_INT,
            'value_length' => \PDO::PARAM_INT,
        ];

        $fields = $this->query->getSuggestFields();
        if ($fields) {
            $ids = $this->listPropertyIds($fields);
            if (!$ids) {
                // Searching inside non-existing properties outputs no result.
                return $this->response
                   ->setIsSuccess(true);
            }
            $sqlFields = 'AND `value`.`property_id` IN (:property_ids)';
            $bind['property_ids'] = $this->listPropertyIds($fields);
            $types['property_ids'] = $connection::PARAM_INT_ARRAY;
        } else {
            $sqlFields = '';
        }

        if ($this->query->getSuggestMode() === 'contain') {
            // $bind['value'] = $q;
            // $bind['value_like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            return $this->response
                ->setMessage('This mode is currently not supported with the internal search engine.'); // @translate
        } else {
            // Use keys "value" and "data" to get a well formatted output for
            // suggestions.
            $sql = <<<SQL
SELECT
    DISTINCT SUBSTRING(`value`.`value`, 1, LOCATE(" ", CONCAT(`value`.`value`, " "), :value_length)) AS "value",
    COUNT(SUBSTRING(`value`.`value`, 1, LOCATE(" ", CONCAT(`value`.`value`, " "), :value_length))) as "data"
FROM `value` AS `value`
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
WHERE `resource`.`resource_type` IN ($classes)
    $sqlIsPublic
    $sqlFields
    AND `value`.`value` LIKE :value_like
GROUP BY SUBSTRING(`value`.`value`, 1, LOCATE(" ", CONCAT(`value`.`value`, " "), :value_length))
ORDER BY data DESC
LIMIT :limit
;
SQL;
            $bind['value_like'] = str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        }

        try {
            $results = $connection
                ->executeQuery($sql, $bind, $types)
                ->fetchAll();
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->getServiceLocator()->get('Omeka\Logger')->err($e->getMessage());
            return $this->response
                ->setMessage('An internal issue in database occurred.'); // @translate
        }
        return $this->response
            ->setSuggestions($results)
            ->setIsSuccess(true);
    }

    /**
     * @return array|null Arguments for the Omeka api, or null if unprocessable
     * or empty results.
     *
     * List of resource types is prepared too.
     *
     * {@inheritDoc}
     * @see \Search\Querier\AbstractQuerier::getPreparedQuery()
     */
    public function getPreparedQuery()
    {
        if (empty($this->query)) {
            $this->args = null;
            $this->argsWithoutActiveFacets = null;
            return $this->args;
        }

        // The data are the ones used to build the query with the standard api.
        // Queries are multiple (one by resource type and by facet).
        // Note: the query is a scalar one, so final events are not triggered.
        // TODO Do a full api reference search or only scalar ids?
        $this->args = [];

        // TODO Normalize search url arguments. Here, the ones from default form, adapted from Solr, are taken.

        $indexerResourceTypes = $this->getSetting('resources', []);
        $this->resourceTypes = $this->query->getResources() ?: $indexerResourceTypes;
        $this->resourceTypes = array_intersect($this->resourceTypes, $indexerResourceTypes);
        if (empty($this->resourceTypes)) {
            $this->args = null;
            $this->argsWithoutActiveFacets = null;
            return $this->args;
        }

        $isDefaultQuery = $this->defaultQuery();
        if (!$isDefaultQuery) {
            $this->mainQuery();
        }

        // "is_public" is automatically managed by the api, but there may be an
        // option in the form.
        // TODO Manage an option "is_public".

        // The site is a specific filter that can be used as part of main query.
        $siteId = $this->query->getSiteId();
        if ($siteId) {
            $this->args['site_id'] = $siteId;
        }

        $this->filterQuery();

        if (!empty($this->args['property'])
            && count($this->args['property']) > self::REQUEST_MAX_ARGS
        ) {
            $services = $this->getServiceLocator();
            $params = $services->get('ControllerPluginManager')->get('params');
            $req = $params->fromQuery();
            unset($req['csrf']);
            $req = urldecode(http_build_query(array_filter($req), '', '&', PHP_QUERY_RFC3986));
            $messenger = new Messenger;
            $logger = $this->getServiceLocator()->get('Omeka\Logger');
            if ($this->query->getExcludedFields()) {
                $message = new Message('The query "%1$s" uses %2$d properties, that is more than the %3$d supported currently. Excluded fields are removed.', // @translate
                    $req, count($this->args['property']), self::REQUEST_MAX_ARGS);
                $this->query->setExcludedFields([]);
                $messenger->addWarning($message);
                $logger->warn($message);
                return $this->getPreparedQuery();
            }

            $message = new Message('The query "%1$s" uses %2$d properties, that is more than the %3$d supported currently. Request is troncated.', // @translate
                $req, count($this->args['property']), self::REQUEST_MAX_ARGS);
            $messenger->addWarning($message);
            $logger->warn($message);
            $this->args['property'] = array_slice($this->args['property'], 0, self::REQUEST_MAX_ARGS);
        }

        $sort = $this->query->getSort();
        if ($sort) {
            list($sortField, $sortOrder) = explode(' ', $sort);
            $this->args['sort_by'] = $sortField;
            $this->args['sort_order'] = $sortOrder === 'desc' ? 'desc' : 'asc';
        }

        $limit = $this->query->getLimit();
        if ($limit) {
            $this->args['limit'] = $limit;
        }

        $offset = $this->query->getOffset();
        if ($offset) {
            $this->args['offset'] = $offset;
        }

        return $this->args;
    }

    protected function defaultQuery(): bool
    {
        $q = $this->query->getQuery();
        if (strlen($q)) {
            return false;
        }

        $this->args = [];
        return true;
    }

    protected function mainQuery(): void
    {
        $q = $this->query->getQuery();
        if (!strlen($q)) {
            return;
        }

        if ($this->query->getExcludedFields()) {
            $this->mainQueryWithExcludedFields();
            return;
        }

        // TODO Try to support the exact search and the full text search (removed in previous version).
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
        }

        // TODO Use fulltext_search, but when more than 50% results, no results, not understandable by end user (or use boolean mode).
        $this->args['property'][] = [
            'joiner' => 'and',
            'property' => '',
            'type' => 'in',
            'text' => $q,
        ];
    }

    /**
     * Prepare the main query with excluded fields.
     *
     * Require module AdvancedSearchPlus.
     *
     * @todo Add support of exclude item set.
     * @todo Add support of grouped query (mutliple properties and/or multiple other properties).
     */
    protected function mainQueryWithExcludedFields(): void
    {
        $q = $this->query->getQuery();
        $excludedFields = $this->query->getExcludedFields();

        // TODO Try to support the exact search and the full text search (removed in previous version).
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
        }

        $this->args['property'][] = [
            'joiner' => 'and',
            'property' => '',
            'except' => $excludedFields,
            'type' => 'in',
            'text' => $q,
        ];
    }

    /**
     * Filter the query.
     *
     * @todo Fix the process for facets: all the facets should be displayed, and "or" by group of facets.
     * @todo Make core search properties groupable ("or" inside a group, "and" between group).
     *
     * Note: when a facet is selected, it is managed like a filter.
     */
    protected function filterQuery(): void
    {
        // Don't use excluded fields for filters.
        $this->filterQueryValues($this->query->getFilters());

        // TODO Manage the date range filters (one or two properties?).
        /*
        $dateRangeFilters = $this->query->getFiltersDateRange();
        foreach ($dateRangeFilters as $name => $filterValues) {
            $name = $this->underscoredNameToTerm($name);
            if (!$name) {
                continue;
            }
            foreach ($filterValues as $filterValue) {
                $start = $filterValue['start'] ? $filterValue['start'] : '*';
                $end = $filterValue['end'] ? $filterValue['end'] : '*';
                $this->args['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:date',
                    'type' => 'gte',
                    'text' => $start,
                ];
                $this->args['property'][] = [
                    'joiner' => 'and',
                    'property' => 'dcterms:date',
                    'type' => 'lte',
                    'text' => $end,
                ];
            }
        }
        */

        $filters = $this->query->getFilterQueries();
        foreach ($filters as $name => $values) {
            $name = $this->underscoredNameToTerm($name);
            if (!$name) {
                continue;
            }
            foreach ($values as $value) {
                $this->args['property'][] = [
                    'joiner' => $value['join'],
                    'property' => $name,
                    'type' => $value['type'],
                    'text' => $value['value'],
                ];
            }
        }

        $this->argsWithoutActiveFacets = $this->args;

        $this->filterQueryValues($this->query->getActiveFacets(), true);
    }

    protected function filterQueryValues(array $filters, bool $inList = false): void
    {
        foreach ($filters as $name => $values) {
            // "is_public" is automatically managed by this internal adapter.
            // "creation_date_year_field" should be a property.
            // "date_range_field" is managed below.
            switch ($name) {
                case 'is_public':
                case 'is_public_field':
                    continue 2;

                case 'id':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['id'] = array_filter(array_map('intval', $values));
                    continue 2;

                case 'item_set_id':
                case 'item_set_id_field':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['item_set_id'] = array_filter(array_map('intval', $values));
                    continue 2;

                case 'resource_class_id':
                case 'resource_class_id_field':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['resource_class_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listResourceClassIds($values);
                    continue 2;

                case 'resource_template_id':
                case 'resource_template_id_field':
                    if (!is_array($values)) {
                        $values = [$values];
                    } elseif (is_array(reset($values))) {
                        $values = array_merge(...$values);
                    }
                    $this->args['resource_template_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listResourceTemplateIds($values);
                    continue 2;

                default:
                    $name = $this->underscoredNameToTerm($name);
                    if (!$name) {
                        break;
                    }
                    if ($inList) {
                        $this->args['property'][] = [
                            'joiner' => 'and',
                            'property' => $name,
                            // FIXME Require a hack (or the module AdvancedSearchPlus).
                            'type' => 'list',
                            'text' => is_array($values) ? $values : [$values],
                        ];
                        break;
                    }
                    foreach ($values as $value) {
                        // Use "or" when multiple (checkbox), else "and" (radio).
                        if (is_array($value)) {
                            foreach ($value as $val) {
                                $this->args['property'][] = [
                                    'joiner' => 'or',
                                    'property' => $name,
                                    'type' => 'eq',
                                    'text' => $val,
                                ];
                            }
                        } else {
                            $this->args['property'][] = [
                                'joiner' => 'and',
                                'property' => $name,
                                'type' => 'eq',
                                'text' => $value,
                            ];
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Convert a name with an underscore into a standard term.
     *
     * Manage dcterms_subject_ss, ss_dcterms_subject, etc. from specific search
     * forms, so they can be used with the internal querier without change.
     *
     * Note: some properties use "_" in their name.
     */
    protected function underscoredNameToTerm($name): ?string
    {
        static $underscoredTerms;
        static $underscoredTermsRegex;

        // Quick check for adapted forms.
        $name = (string) $name;
        if (strpos($name, ':') || !strpos($name, '_')) {
            return $name;
        }

        // A common name.
        if ($name === 'title') {
            return 'dcterms:title';
        }

        if (is_null($underscoredTerms)) {
            $underscoredTerms = $this->getUnderscoredUsedProperties();
            $underscoredTermsRegex = '~(?:' . implode('|', array_keys($underscoredTerms)) . '){1}~';
        }

        $matches = [];
        return preg_match($underscoredTermsRegex, $name, $matches, PREG_UNMATCHED_AS_NULL) && count($matches) === 1
            ? reset($matches)
            : null;
    }

    protected function fillFacetResponse(): void
    {
        $this->response->setActiveFacets($this->query->getActiveFacets());

        /** @var \Reference\Mvc\Controller\Plugin\References $references */
        $references = $this->getServiceLocator()->get('ControllerPluginManager')->get('references');

        $facetFields = $this->query->getFacetFields();
        $facetLimit = $this->query->getFacetLimit();
        $facetLanguages = $this->query->getFacetLanguages();

        $metadataFieldsToNames = [
            'is_public' => 'is_public',
            'is_public_field' => 'is_public',
            'item_set_id' => 'o:item_set',
            'item_set_id_field' => 'o:item_set',
            'resource_class_id' => 'o:resource_class',
            'resource_class_id_field' => 'o:resource_class',
            'resource_template_id' => 'o:resource_template',
            'resource_template_id_field' => 'o:resource_template',
        ];

        // Normalize search query keys as omeka keys for items and item sets.
        $fields = array_combine($facetFields, array_map(function ($v) use ($metadataFieldsToNames) {
            return $metadataFieldsToNames[$v] ?? $v;
        }, $facetFields));

        // Facet counts don't make a distinction by resource type, so they
        // should be merged here. This is needed as long as there is no single
        // query for resource (items and item sets together).
        $facetCountsByField = array_fill_keys($facetFields, []);

        // TODO To be removed when the filters will be groupable (see version 3.5.12 where this is after filter).
        // Facets data don't use sort or limit.
        $facetData = $this->argsWithoutActiveFacets;
        unset($facetData['sort_by']);
        unset($facetData['sort_order']);
        unset($facetData['limit']);
        unset($facetData['offset']);

        foreach ($this->resourceTypes as $resourceType) {
            $options = [
                'resource_name' => $resourceType,
                // Options sql.
                'per_page' => $facetLimit,
                'page' => 1,
                'sort_by' => 'total',
                'sort_order' => 'DESC',
                'filters' => [
                    'languages' => $facetLanguages,
                    'datatypes' => [],
                ],
                'values' => [],
                // Output options.
                'first' => false,
                'initial' => false,
                'distinct' => false,
                'type' => false,
                'lang' => false,
                'include_without_meta' => false,
                'output' => 'associative',
            ];

            $values = $references
                ->setMetadata(array_values($fields))
                ->setQuery($facetData)
                ->setOptions($options)
                ->list();

            foreach ($facetFields as $facetField) {
                foreach ($values[$fields[$facetField]]['o:references'] ?? [] as $value => $count) {
                    empty($facetCountsByField[$facetField][$value])
                        ? $facetCountsByField[$facetField][$value] = [
                            'value' => $value,
                            'count' => $count,
                        ]
                        : $facetCountsByField[$facetField][$value] = [
                            'value' => $value,
                            'count' => $count + $facetCountsByField[$facetField][$value]['count'],
                        ];
                }
            }
        }
        $this->response->setFacetCounts(array_map('array_values', $facetCountsByField));
    }

    /**
     * Get the list of vocabulary prefixes by vocabulary ids.
     */
    protected function getVocabularyPrefixes(): array
    {
        static $vocabularies;

        if (is_null($vocabularies)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'vocabulary.id AS id',
                    'vocabulary.prefix AS prefix',
                ])
                ->from('vocabulary', 'vocabulary')
                ->orderBy('vocabulary.id)', 'ASC')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $vocabularies = $stmt->fetchAll(\Doctrine\DBAL\FetchMode::COLUMN);
            $vocabularies = array_column($vocabularies, 'prefix', 'id');
        }

        return $vocabularies;
    }

    /**
     * Convert a list of terms into a list of property ids.
     *
     * @see \Reference\Mvc\Controller\Plugin\References::listPropertyIds()
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listPropertyIds(array $values): array
    {
        return array_intersect_key($this->getPropertyIds(), array_fill_keys($values, null));
    }

    /**
     * Get all property terms by id, ordered by descendant total values.
     *
     * @return array Associative array of ids by term.
     */
    protected function getUsedPropertyByIds(): array
    {
        static $properties;

        if (is_null($properties)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
            ->select([
                'DISTINCT property.id AS id',
                "CONCAT(vocabulary.prefix, ':', property.local_name) AS term",
                // 'COUNT(value.id) AS total',
            ])
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->innerJoin('property', 'value', 'value', 'property.id = value.property_id')
            ->addGroupBy('id')
            ->orderBy('COUNT(value.id)', 'DESC')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $properties = array_column($properties, 'term', 'id');
        }

        return $properties;
    }

    /**
     * Get all property terms as terms with "_".
     *
     * This allows to convert some Solr keys like "dcterms_subject_ss" into a
     * standard term manageable by the standard api.
     *
     * @return array Associative array of used term by terms with "_".
     */
    protected function getUnderscoredUsedProperties(): array
    {
        static $properties;

        if (is_null($properties)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    "CONCAT(vocabulary.prefix, '_', property.local_name) AS key",
                    "CONCAT(vocabulary.prefix, ':', property.local_name) AS term",
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->innerJoin('property', 'value', 'value', 'property.id = value.property_id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $properties = array_column($properties, 'term', 'key');
        }

        return $properties;
    }

    /**
     * Get all property ids by term.
     *
     * @see \BulkImport\Mvc\Controller\Plugin\Bulk::getPropertyIds()
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds(): array
    {
        static $properties;

        if (is_null($properties)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    'property.id AS id',
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
                ->addGroupBy('property.id')
            ;
            $stmt = $connection->executeQuery($qb);
            $properties = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $properties = array_map('intval', $properties);
        }

        return $properties;
    }

    /**
     * Convert a list of terms into a list of resource class ids.
     *
     * @see \Reference\Mvc\Controller\Plugin\References::listResourceClassIds()
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listResourceClassIds(array $values): array
    {
        return array_values(array_intersect_key($this->getResourceClassIds(), array_fill_keys($values, null)));
    }

    /**
     * Get all resource class ids by term.
     *
     * @see \Reference\Mvc\Controller\Plugin\References::getResourceClassIds()
     *
     * @return array Associative array of ids by term.
     */
    protected function getResourceClassIds(): array
    {
        static $resourceClasses;

        if (is_null($resourceClasses)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    "CONCAT(vocabulary.prefix, ':', resource_class.local_name) AS term",
                    'resource_class.id AS id',
                ])
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $resourceClasses = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $resourceClasses = array_map('intval', array_column($resourceClasses, 'id', 'term'));
        }

        return $resourceClasses;
    }

    /**
     * Convert a list of terms into a list of resource template ids.
     *
     * @param array $values
     * @return array Only values that are labels are converted into ids, the
     * other are removed.
     */
    protected function listResourceTemplateIds(array $values): array
    {
        return array_values(array_intersect_key($this->getResourceTemplateIds(), array_fill_keys($values, null)));
    }

    /**
     * Get all resource template ids by label.
     *
     * @return array Associative array of ids by label.
     */
    protected function getResourceTemplateIds(): array
    {
        static $resourceTemplates;

        if (is_null($resourceTemplates)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'resource_template.label AS label',
                    'resource_template.id AS id',
                ])
                ->from('resource_template', 'resource_template')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $resourceTemplates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $resourceTemplates = array_map('intval', array_column($resourceTemplates, 'id', 'label'));
        }

        return $resourceTemplates;
    }
}
