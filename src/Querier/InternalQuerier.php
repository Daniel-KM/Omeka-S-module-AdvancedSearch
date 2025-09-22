<?php declare(strict_types=1);

namespace AdvancedSearch\Querier;

use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Response;
use AdvancedSearch\Stdlib\SearchResources;
use Common\Stdlib\PsrMessage;
use Doctrine\ORM\Query\Expr\Join;

class InternalQuerier extends AbstractQuerier
{
    /**
     * MariaDB can only use 61 tables in a join but Omeka adds a join for each
     * property. So, to manage modules, the number is limited to 50 here.
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
     * @var bool
     */
    protected $byResourceType = false;

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
        /** @var \Omeka\Api\Manager $api */
        $api = $this->services->get('Omeka\ApiManager');

        // The response is failed by default until filled.
        $this->response = new Response();
        $this->response
            ->setApi($api)
            ->setQuery($this->query);

        $this->byResourceType = $this->query
            ? $this->query->getByResourceType()
            : false;

        $this->args = $this->getPreparedQuery();

        $this->response
            // Here, the resource types are the ones supported by the querier.
            ->setResourceTypes($this->resourceTypes)
        ;

        // When no query or resource types are set.
        if ($this->args === null) {
            return $this->response
                ->setMessage('An issue occurred.'); // @translate
        }

        $plugins = $this->services->get('ControllerPluginManager');
        $hasReferences = $plugins->has('references');

        // Versions < 3.4.49 required to get all ids early, but this need was
        // only used to create the link for bulk export (but it can use a query)
        // and to prepare the mapping features when a map is displayed on an
        // item set in some theme. Furthermore, to get all ids created an issue
        // when there was a fulltext_search, so a fix was done (/** @link https://github.com/omeka/omeka-s/pull/2224 */).
        // Important: the full list of ids is used for the facets too.

        // The new way is to get the full resources, like omeka browse, and to
        // get the ids on request only, so checked and limited, via a yield.

        // Some query arguments and facets are not manageable via resource type
        // "resources" in Omeka v4.1 (no search on mixed "resources").
        $isSpecificQuery = $this->isSpecificQuery(true);

        // Resources types are filtered from the query or from the indexes.
        if ($this->byResourceType || $isSpecificQuery) {
            foreach ($this->resourceTypes as $resourceType) {
                try {
                    $apiResponse = $api->search($resourceType, $this->args);
                    $totalResults = $apiResponse->getTotalResults();
                    $result = $apiResponse->getContent();
                } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                    throw new QuerierException($e->getMessage(), $e->getCode(), $e);
                }
                $this->response->setResourceTotalResults($resourceType, $totalResults);
                $this->response->setResourcesForType($resourceType, $result);
            }
            $totalResults = array_sum($this->response->getResourceTotalResults());
            $this->response->setTotalResults($totalResults);
        } else {
            // It is not possible to return the resource type for now with
            // doctrine, but it is useless.
            $mainResourceType = count($this->resourceTypes) === 1
                ? reset($this->resourceTypes)
                : 'resources';
            try {
                $apiResponse = $api->search($mainResourceType, $this->args);
                $totalResults = $apiResponse->getTotalResults();
                $result = $apiResponse->getContent();
            } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                throw new QuerierException($e->getMessage(), $e->getCode(), $e);
            }
            $this->response->setResourceTotalResults($mainResourceType, $totalResults);
            $this->response->setResources($result);
            $this->response->setTotalResults($totalResults);
        }

        $this->response->setCurrentPage($this->query->getPage());
        $this->response->setPerPage($this->query->getPerPage());

        // Remove specific results when settings are not by resource type, so
        // merge all results and keep only "resources".
        // TODO The order may be different when "resources" is not used.
        // Facets are always grouped.
        // TODO Clarify: if there is "resources", why reprocess?
        if ($isSpecificQuery && !$this->byResourceType && count($this->resourceTypes) > 1) {
            // Here, the resources were stored by type above.
            $resourcesByType = $this->response->getResources(null, false);
            $this->response->setResourcesForType(null, null);
            $total = $this->response->getTotalResults();
            if ($total) {
                if (isset($resourcesByType['resources'])) {
                    $this->response->setResourcesForType('resources', $resourcesByType['resources']);
                } else {
                    $this->response->setResourcesForType('resources', array_merge(...array_values($resourcesByType)));
                }
            } else {
                $this->response->setResourcesForType('resources', []);
            }
            // Total may be different for resources?
            $totalResultsByType = $this->response->getResourceTotalResults();
            $total = isset($totalResultsByType['resources'])
                ? $totalResultsByType['resources']
                : array_sum($totalResultsByType);
            $this->response->setAllResourceTotalResults(['resources' => $total]);
            $this->response->setTotalResults($total);
        }

        if ($hasReferences) {
            $this->fillFacetResponse();
        }

        return $this->response
            ->setIsSuccess(true);
    }

    public function querySuggestions(): Response
    {
        $this->response = new Response;
        $this->response->setApi($this->services->get('Omeka\ApiManager'));

        $this->args = $this->getPreparedQuery();
        if ($this->args === null) {
            return $this->response
                ->setMessage('An issue occurred.'); // @translate
        }

        $suggestOptions = $this->query->getSuggestOptions();
        if (!empty($suggestOptions['direct'])) {
            return $this->querySuggestionsDirect();
        }

        // TODO Manage site id and item set id and any other filter query.
        // TODO Use the index full text?
        // TODO Manage site here?

        // The mode index, resource types, fields, and length are managed during
        // indexation.

        $suggester = (int) ($suggestOptions['suggester'] ?? 0);
        if (empty($suggester)) {
            return $this->response
                ->setMessage('An issue occurred for the suggester.'); // @translate
        }

        $isPublic = $this->query->getIsPublic();
        $column = $isPublic ? 'public' : 'all';

        $modeSearch = $suggestOptions['mode_search'] ?? 'start';

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');
        $q = $this->query->getQuery();
        $bind = [
            'suggester' => $suggester,
            'limit' => $this->query->getLimit(),
            'value_like' => ($modeSearch === 'contain' ? '%' : '')
                . strtr($q, ['%' => '\%', '_' => '\_']) . '%',
        ];
        $types = [
            'suggester' => \PDO::PARAM_INT,
            'limit' => \PDO::PARAM_INT,
            'value_like' => \PDO::PARAM_STR,
        ];

        $sql = <<<SQL
            SELECT DISTINCT
                `text` AS "value",
                `total_$column` AS "data"
            FROM `search_suggestion` AS `search_suggestion`
            WHERE `search_suggestion`.`suggester_id` = :suggester
                AND `search_suggestion`.`text` LIKE :value_like
            ORDER BY data DESC
            LIMIT :limit
            ;
            SQL;

        try {
            $results = $connection->executeQuery($sql, $bind, $types)->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->logger->err($e->getMessage());
            return $this->response
                ->setMessage('An internal issue in database occurred.'); // @translate
        }
        return $this->response
            ->setSuggestions($results)
            ->setIsSuccess(true);
    }

    protected function querySuggestionsDirect(): Response
    {
        // TODO Manage site id and item set id and any other filter query.
        // TODO Use the full text search table.
        // TODO Manage the field query args for suggestion.

        $mapResourcesToClasses = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'value_annotations' => \Omeka\Entity\ValueAnnotation::class,
            'annotations' => \Annotate\Entity\Annotation::class,
        ];

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');
        $q = $this->query->getQuery();
        $bind = [
            'limit' => $this->query->getLimit(),
            'value_length' => mb_strlen($q),
            'length' => (int) ($this->query->getSuggestOptions()['length'] ?: 50),
        ];
        $types = [
            'limit' => \PDO::PARAM_INT,
            'value_length' => \PDO::PARAM_INT,
            'length' => \PDO::PARAM_INT,
        ];

        // When searching in resources too, don't add the condition.
        $resourceClasses = in_array('resources', $this->resourceTypes)
            ? []
            : array_intersect_key($mapResourcesToClasses, array_flip($this->resourceTypes));
        if ($resourceClasses) {
            $sqlResourceTypes = 'AND `resource`.`resource_type` IN (:resource_types)';
            $bind['resource_types'] = array_values($resourceClasses);
            $types['resource_types'] = $connection::PARAM_STR_ARRAY;
        } else {
            $sqlResourceTypes = '';
        }

        $sqlIsPublic = $this->query->getIsPublic()
            ? 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1'
            : '';

        $fields = $this->query->getSuggestFields();
        if ($fields) {
            $ids = $this->easyMeta->propertyIds($fields);
            if (!$ids) {
                // Searching inside non-existing properties outputs no result.
                return $this->response
                   ->setIsSuccess(true);
            }
            $sqlFields = 'AND `value`.`property_id` IN (:property_ids)';
            $bind['property_ids'] = array_values($ids);
            $types['property_ids'] = $connection::PARAM_INT_ARRAY;
        } else {
            $sqlFields = '';
        }

        $excludedFields = $this->query->getExcludedFields();
        if ($excludedFields) {
            $ids = $this->easyMeta->propertyIds($excludedFields);
            if ($ids) {
                $sqlFields .= 'AND `value`.`property_id` NOT IN (:excluded_property_ids)';
                $bind['excluded_property_ids'] = array_values($ids);
                $types['excluded_property_ids'] = $connection::PARAM_INT_ARRAY;
            }
        }

        // FIXME The sql for site doesn't manage site item sets.
        $site = $this->query->getSiteId();
        if ($site) {
            $sqlSite = 'JOIN `item_site` ON `item_site`.`item_id` = `resource`.`id` AND `item_site`.`site_id` = ' . $site;
        } else {
            $sqlSite = '';
        }

        // TODO Check the index mode for the direct search of suggestions.

        // Use keys "value" and "data" to get a well formatted output for
        // suggestions.
        // The query cuts the value to the first space. The end of line is
        // removed to avoid some strange results.
        // The group by uses the same than the select, because suggester
        // requires "value".

        $mode = $this->query->getSuggestOptions()['mode_search'] ?: 'start';
        if ($mode === 'contain') {
            // TODO Improve direct sql for full suggestions.
            $sql = <<<SQL
                SELECT DISTINCT
                    SUBSTRING(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), 1, :length) AS "value",
                    COUNT(SUBSTRING(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), 1, :length)) AS "data"
                FROM `value` AS `value`
                INNER JOIN
                    `resource` ON `resource`.`id` = `value`.`resource_id`
                    $sqlResourceTypes
                $sqlSite
                WHERE
                    `value`.`value` LIKE :value_like
                    $sqlIsPublic
                    $sqlFields
                GROUP BY
                    SUBSTRING(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), 1, :length)
                ORDER BY data DESC
                LIMIT :limit
                ;
                SQL;
            $bind['value_like'] = '%' . strtr($q, ['%' => '\%', '_' => '\_']) . '%';
            $types['value_like'] = \PDO::PARAM_STR;
        } elseif ($mode === 'start') {
            /*
            $sql = <<<SQL
                SELECT DISTINCT
                    SUBSTRING(SUBSTRING_INDEX(
                        CONCAT(
                            TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")),
                        " "), " ", 1
                    ), 1, :length) AS "value",
                    COUNT(SUBSTRING(SUBSTRING_INDEX(
                        CONCAT(
                            TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")),
                        " "), " ", 1
                    ), 1, :length)) as "data"
                FROM `value` AS `value`
                INNER JOIN
                    `resource` ON `resource`.`id` = `value`.`resource_id`
                    $sqlResourceTypes
                $sqlSite
                WHERE
                    `value`.`value` LIKE :value_like
                GROUP BY SUBSTRING(SUBSTRING_INDEX(
                        CONCAT(
                            TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")),
                        " "), " ", 1
                    ), 1, :length)
                ORDER BY data DESC
                LIMIT :limit
                ;
                SQL;
                */
            $sql = <<<SQL
                SELECT DISTINCT
                    SUBSTRING(
                        SUBSTRING(
                            TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")),
                            1,
                            LOCATE(" ", CONCAT(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), " "), :value_length)
                        ), 1, :length
                    ) AS "value",
                    COUNT(
                        SUBSTRING(
                            SUBSTRING(
                                TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")),
                                1,
                                LOCATE(" ", CONCAT(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), " "), :value_length)
                            ), 1, :length
                        )
                    ) AS "data"
                FROM `value` AS `value`
                INNER JOIN
                    `resource` ON `resource`.`id` = `value`.`resource_id`
                    $sqlResourceTypes
                $sqlSite
                WHERE
                    `value`.`value` LIKE :value_like
                    $sqlIsPublic
                    $sqlFields
                GROUP BY
                    SUBSTRING(
                        SUBSTRING(
                            TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")),
                            1,
                            LOCATE(" ", CONCAT(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), " "), :value_length)
                        ), 1, :length
                    )
                ORDER BY data DESC
                LIMIT :limit
                ;
                SQL;
            $bind['value_like'] = strtr($q, ['%' => '\%', '_' => '\_']) . '%';
            $types['value_like'] = \PDO::PARAM_STR;
        } else {
            return $this->response
                ->setMessage('This mode is currently not supported with the internal search engine.'); // @translate
        }

        try {
            $results = $connection->executeQuery($sql, $bind, $types)->fetchAllAssociative();
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->err($e->getMessage());
            return $this->response
                ->setMessage('An internal issue in database occurred.'); // @translate
        } catch (\Exception $e) {
            $this->logger->err($e->getMessage());
            return $this->response
                ->setMessage('An internal issue occurred.'); // @translate
        }

        return $this->response
            ->setSuggestions($results)
            ->setIsSuccess(true);
    }

    /**
     * Get an associative list of all unique values of a field.
     *
     * @todo Support any resources, not only item.
     *
     * Note: In version previous 3.4.15, the module Reference was used, that
     * managed languages, but a lot slower for big databases.
     *
     * @todo Factorize with \AdvancedSearch\Querier\InternalQuerier::fillFacetResponse()
     *
     * Adapted:
     * @see \AdvancedSearch\Api\Representation\SearchConfigRepresentation::suggest()
     * @see \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation::suggest()
     * @see \AdvancedSearch\Querier\InternalQuerier::queryValues()
     * @see \Reference\Mvc\Controller\Plugin\References
     */
    public function queryValues(string $field): array
    {
        // Check if the field is a special or a multifield.

        // Convert multi-fields into a list of property terms.
        // Normalize search query keys as omeka keys for items and item sets.
        $aliases = $this->query ? $this->query->getAliases() : [];
        $fields = [];
        $fields[$field] = $this->easyMeta->propertyTerm($field)
            ?? $aliases[$field]['fields']
            ?? $field;

        // Simplified from References::listDataForProperty().
        /** @see \Reference\Mvc\Controller\Plugin\References::listDataForProperties() */
        $fields = reset($fields);
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        $propertyIds = $this->easyMeta->propertyIds($fields);
        if (!$propertyIds) {
            return [];
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->services->get('Omeka\EntityManager');
        $qb = $entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // For example to list all authors that are a resource.
        // TODO Manage custom vocabs with item set.
        // TODO This usage of arg type is not standard and uncommon. Documentate it.
        $fieldQueryArgs = $this->query ? $this->query->getFieldQueryArgs($field) : null;
        $isResourceQuery = $fieldQueryArgs
            && isset($fieldQueryArgs['type'])
            && isset($fieldQueryArgs[$fieldQueryArgs['type']])
            && in_array($fieldQueryArgs[$fieldQueryArgs['type']], SearchResources::FIELD_QUERY['main_type']['resource']);

        if ($isResourceQuery) {
            $qb
                ->select('valueResource.title AS v')
                ->from(\Omeka\Entity\Value::class, 'value')
                // This join allow to check visibility automatically too.
                ->innerJoin(\Omeka\Entity\Item::class, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
                ->innerJoin(\Omeka\Entity\Item::class, 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
                // Always return a non-empty string, not null.
                ->where('valueResource.title IS NOT NULL')
                ->andWhere('valueResource.title != ""');
        } else {
            $qb
                // Always return a string, not null.
                // Doctrine rejects empty string withy double quote.
                ->select("COALESCE(value.value, valueResource.title, value.uri, '') AS v")
                ->from(\Omeka\Entity\Value::class, 'value')
                // This join allow to check visibility automatically too.
                ->innerJoin(\Omeka\Entity\Item::class, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
                // The values should be distinct for each type.
                ->leftJoin(\Omeka\Entity\Item::class, 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
                ->where("COALESCE(value.value, valueResource.title, value.uri, '') != ''");
        }

        if (!empty($fieldQueryArgs['lang'])) {
            $fieldLangs = is_array($fieldQueryArgs['lang']) ? $fieldQueryArgs['lang'] : [$fieldQueryArgs['lang']];
            if (in_array('', $fieldLangs)) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->isNull('value.lang'),
                        $expr->in('value.lang', ':lang'))
                    );
            } else {
                $qb
                    ->andWhere($expr->in('value.lang', ':lang'));
            }
            $qb
                ->setParameter('lang', array_values($fieldLangs), \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
        }

        if (!empty($fieldQueryArgs['datatype'])) {
            $fieldDataTypes = is_array($fieldQueryArgs['datatype']) ? $fieldQueryArgs['datatype'] : [$fieldQueryArgs['datatype']];
            $qb
                ->andWhere($expr->in('value.type', ':datatype'))
                ->setParameter('datatype', array_values($fieldDataTypes), \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
        }

        $siteId = $this->query->getSiteId();
        if ($siteId) {
            $siteAlias = 'site';
            $qb
                ->innerJoin('resource.sites', $siteAlias, 'WITH', $expr->eq("$siteAlias.id", ':site_id'))
                ->setParameter('site_id', $siteId);
            // TODO Manage settings site_attachements_only. See ItemAdapter.
        }

        $qb
            ->andWhere($expr->in('value.property', ':properties'))
            ->setParameter('properties', array_values($propertyIds), \Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            ->groupBy('v')
            ->orderBy('v', 'asc');

        // Empty values (null and "") and duplicates are removed earlier.
        $list = $qb->getQuery()->getSingleColumnResult();
        return array_combine($list, $list);
    }

    /**
     * With internal querier, this method should be avoided when the total
     * resources is too big for the server, else a memory overflow can occur.
     *
     * {@inheritDoc}
     * @see \AdvancedSearch\Querier\AbstractQuerier::allResourceIdsByResourceType()
     */
    public function queryAllResourceIds(?string $resourceType = null, bool $byResourceType = false): array
    {
        $result = [];

        // One-level recursivity.
        if (!$resourceType) {
            foreach ($this->resourceTypes as $resourceType) {
                $result[$resourceType] = $this->queryAllResourceIds($resourceType);
            }
            return $result;
        }

        $dataQuery = $this->args;
        unset($dataQuery['page'], $dataQuery['per_page'], $dataQuery['limit'], $dataQuery['offset']);

        try {
            /** @var \Omeka\Api\Manager $api */
            $api = $this->services->get('Omeka\ApiManager');
            return $api->search($resourceType, $dataQuery, ['returnScalar' => 'id'])->getContent();
        } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
            throw new QuerierException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array|null Arguments for the Omeka api, or null if unprocessable
     * or empty results.
     *
     * List of resource types is prepared too.
     *
     * {@inheritDoc}
     * @see \AdvancedSearch\Querier\AbstractQuerier::getPreparedQuery()
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

        $indexerResourceTypes = $this->searchEngine->setting('resource_types', []);
        $this->resourceTypes = $this->query->getResourceTypes() ?: $indexerResourceTypes;
        $this->resourceTypes = array_intersect($this->resourceTypes, $indexerResourceTypes);
        if (empty($this->resourceTypes)) {
            $this->args = null;
            $this->argsWithoutActiveFacets = null;
            return $this->args;
        }

        // Add all the resource types in all cases. If it is useless or if it
        // includes "resources", it will be skipped by api later.
        // The arg "resource_type" can be filtered later when the query set it.
        $this->args['resource_type'] = $this->resourceTypes;

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

        $this->appendHiddenFilters();
        $this->filterQuery();

        // Estimate the number of joins early.
        $totalProperties = count($this->args['property'] ?? []);
        $totalFilters = count($this->args['filter'] ?? []);
        $totalPropertiesAndFilters = $totalProperties + $totalFilters + count($this->args) - 10;
        if ($totalPropertiesAndFilters > self::REQUEST_MAX_ARGS) {
            $plugins = $this->services->get('ControllerPluginManager');
            $messenger = $plugins->get('messenger');
            $params = $plugins->get('params');
            $req = $params->fromQuery();
            unset($req['csrf']);
            $req = urldecode(http_build_query(array_filter($req), '', '&', PHP_QUERY_RFC3986));
            $message = new PsrMessage(
                'The query "{query}" uses {count} properties or filters, that is more than the {total} supported currently. The query should be simplified.', // @translate
                ['query' => $req, 'count' => $totalPropertiesAndFilters, 'total' => self::REQUEST_MAX_ARGS]
            );
            $messenger->addWarning($message);
            $this->logger->warn(
                'A user tried a query with {count} arguments. The config or theme should be checked to forbid them earlier. Query: {query}', // @translate
                ['count' => $totalPropertiesAndFilters, 'query' => $req]
            );
            return null;
        }

        $sort = $this->query->getSort();
        if ($sort) {
            [$sortField, $sortOrder] = explode(' ', $sort);
            $this->args['sort_by'] = $sortField;
            $this->args['sort_order'] = $sortOrder === 'desc' ? 'desc' : 'asc';
        }

        // Limit is per page and offset is page x limit.
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

        if ($this->query->getOption('remove_diacritics', false)) {
            if (extension_loaded('intl')) {
                $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
                $q = $transliterator->transliterate($q);
            } elseif (extension_loaded('iconv')) {
                $q = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $q);
            }
            // TODO See transliterator from solr.
        }

        // TODO Try to support the exact search and the full text search (removed in version 3.5.17.3).
        $isWrappedWithQuote = mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"';
        if ($isWrappedWithQuote) {
            $q = trim($q, '" ');
        }

        if ($isWrappedWithQuote || $this->query->getOption('default_search_partial_word', false)) {
            $this->args['filter'][] = [
                'join' => 'and',
                'field' => '',
                'type' => 'in',
                'val' => $q,
            ];
            return;
        }

        // Full text search is the default Omeka mode.
        // TODO It uses fulltext_search, but when more than 50% results, no results, not understandable by end user (or use boolean mode).
        if ($this->query->getRecordOrFullText() === 'record') {
            $rft = $this->query->getAlias('full_text');
            if ($rft && !empty($rft['fields'])) {
                $this->args['filter'][] = [
                    'join' => 'and',
                    'field' => '',
                    'except' => $rft['fields'],
                    'type' => 'in',
                    'val' => $q,
                ];
            } else {
                $this->args['search'] = $q;
            }
        } elseif ($q !== '*') {
            // A full text search cannot be "*" alone. Anyway it has no meaning.
            $this->args['fulltext_search'] = $q;
        }
    }

    /**
     * Prepare the main query with excluded fields.
     *
     * @todo Add support of exclude item set.
     * @todo Add support of grouped query (mutliple properties and/or multiple other properties).
     */
    protected function mainQueryWithExcludedFields(): void
    {
        $q = $this->query->getQuery();
        $excludedFields = $this->query->getExcludedFields();

        if ($this->query->getOption('remove_diacritics', false)) {
            if (extension_loaded('intl')) {
                $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
                $q = $transliterator->transliterate($q);
            } elseif (extension_loaded('iconv')) {
                $q = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $q);
            }
            // TODO See transliterator from solr.
        }

        // TODO Try to support the exact search and the full text search (removed in previous version).
        $isWrappedWithQuote = mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"';
        if ($isWrappedWithQuote) {
            $q = trim($q, '" ');
        }

        if ($isWrappedWithQuote || $this->query->getOption('default_search_partial_word', false)) {
            $this->args['filter'][] = [
                'join' => 'and',
                'field' => '',
                'except' => $excludedFields,
                'type' => 'in',
                'val' => $q,
            ];
            return;
        }

        // A full text search cannot be "*" alone. Anyway it has no meaning.
        if ($q === '*') {
            return;
        }

        // Full text search is the default Omeka mode. Exclusion is not possible.
        // TODO It uses fulltext_search, but when more than 50% results, no results, not understandable by end user (or use boolean mode).
        $this->args['fulltext_search'] = $q;
    }

    protected function appendHiddenFilters(): void
    {
        $hiddenFilters = $this->query->getFiltersQueryHidden();
        if (!$hiddenFilters) {
            return;
        }
        $this->filterQueryAny($hiddenFilters, false, true);
        $this->filterQueryRanges($hiddenFilters);
        $this->filterQueryAny($hiddenFilters);
    }

    /**
     * Filter the query.
     *
     * @todo Add an option for type of facets and/or by group/item. All the facets should be displayed, and "or" by group of facets.
     * @todo Make core search properties groupable ("or" inside a group, "and" between group).
     *
     * Note: when a facet is selected, it is managed like a filter.
     * For facet ranges, filters are managed as lower than / greater than.
     */
    protected function filterQuery(): void
    {
        // Don't use excluded fields for filters.
        $this->filterQueryAny($this->query->getFilters(), false, true);
        $this->filterQueryRanges($this->query->getFiltersRange());
        $this->filterQueryAny($this->query->getFiltersQuery());
        $this->argsWithoutActiveFacets = $this->args;
        $this->filterQueryAny($this->query->getActiveFacets(), true, true);
        $this->filterQueryRefine($this->query->getQueryRefine());
    }

    protected function filterQueryAny(array $filters, bool $inListForFacets = false, bool $isSimpleValue = false): void
    {
        $flatArray = function ($values): array {
            if (!is_array($values)) {
                // Scalar value.
                return [$values];
            } elseif (!is_array(reset($values))) {
                // Simple level array.
                return $values;
            }
            // Manage sub arrays.
            if (array_key_exists('val', $values)) {
                // The array may be a simple filter.
                return is_array($values['val']) ? $values['val'] : [$values['val']];
            }
            // The array may be an array of values or an array of filters.
            $firstValue = reset($values);
            if (array_key_exists('val', $firstValue)) {
                $values = array_column($values, 'val');
                $values = array_map(fn ($v) => is_array($v) ? $v : [$v], $values);
                return array_merge(...array_values($values));
            }
            // Else it is an unknown array of arrays to extract.
            return array_merge(...array_values($values));
        };

        // Empty values are already filtered by the form adapter.
        foreach ($filters as $fieldName => $values) switch ($fieldName) {
            // "resource_type" is used externally and "resource_name" internally
            // and "resource-type" by omeka main search engine in admin, with
            // the controller name, but it is a fake argument that redirect to
            // the controller.
            // Anyway, "resource_name" is no more used.
            case 'resource_type':
                $values = $flatArray($values);
                if (!$values) {
                    continue 2;
                }
                // Only managed resource types are searchable.
                // The arg is already set above.
                $this->args['resource_type'] = array_unique(array_intersect($this->resourceTypes, array_merge($this->args['resource_type'] ?? [], $values)));
                continue 2;

            // "is_public" is automatically managed by this internal adapter
            // TODO Improve is_public to search public/private only.
            case 'is_public':
                continue 2;

            case 'id':
                $values = array_filter(array_map('intval', $flatArray($values)));
                $this->args['id'] = empty($this->args['id'])
                    ? $values
                    : array_merge(is_array($this->args['id']) ? $this->args['id'] : [$this->args['id']], $values);
                continue 2;

            case 'owner_id':
                $values = $flatArray($values);
                $values = is_numeric(reset($values))
                    ? array_filter(array_map('intval', $values))
                    : $this->listUserIds($values);
                $this->args['owner_id'] = empty($this->args['owner_id'])
                    ? $values
                    : array_merge(is_array($this->args['owner_id']) ? $this->args['owner_id'] : [$this->args['owner_id']], $values);
                continue 2;

            case 'site_id':
                $values = $flatArray($values);
                $values = is_numeric(reset($values))
                    ? array_filter(array_map('intval', $values))
                    : $this->listSiteIds($values);
                $this->args['site_id'] = empty($this->args['site_id'])
                    ? $values
                    : array_merge(is_array($this->args['site_id']) ? $this->args['site_id'] : [$this->args['site_id']], $values);
                continue 2;

            case 'resource_class_id':
                $values = $flatArray($values);
                $values = is_numeric(reset($values))
                    ? array_filter(array_map('intval', $values))
                    : array_values($this->easyMeta->resourceClassIds($values));
                $this->args['resource_class_id'] = empty($this->args['resource_class_id'])
                    ? $values
                    : array_merge(is_array($this->args['resource_class_id']) ? $this->args['resource_class_id'] : [$this->args['resource_class_id']], $values);
                continue 2;

            case 'resource_template_id':
                $values = $flatArray($values);
                $values = is_numeric(reset($values))
                    ? array_filter(array_map('intval', $values))
                    : array_values($this->easyMeta->resourceTemplateIds($values));
                $this->args['resource_template_id'] = empty($this->args['resource_template_id'])
                    ? $values
                    : array_merge(is_array($this->args['resource_template_id']) ? $this->args['resource_template_id'] : [$this->args['resource_template_id']], $values);
                continue 2;

            case 'item_set_id':
                $values = array_filter(array_map('intval', $flatArray($values)));
                $this->args['item_set_id'] = empty($this->args['item_set_id'])
                    ? $values
                    : array_merge(is_array($this->args['item_set_id']) ? $this->args['item_set_id'] : [$this->args['item_set_id']], $values);
                continue 2;

            // Module Access: access level (free, reserved, protected, forbidden).
            case 'access':
                $values = array_filter($flatArray($values));
                $this->args['access'] = empty($this->args['access'])
                    ? $values
                    : array_merge(is_array($this->args['access']) ? $this->args['access'] : [$this->args['access']], $values);
                continue 2;

            // Module Item Sets Tree.
            case 'item_sets_tree':
                $values = array_filter(array_map('intval', $flatArray($values)));
                $this->args['item_sets_tree'] = empty($this->args['item_sets_tree'])
                    ? $values
                    : array_merge(is_array($this->args['item_sets_tree']) ? $this->args['item_sets_tree'] : [$this->args['item_sets_tree']], $values);
                continue 2;

            case $inListForFacets:
                // TODO Facets does not manage field query args.
                $fieldData = $this->query->getFacet($fieldName);
                if (!$fieldData) {
                    break;
                }
                $fieldNameFacet = $fieldData['field'] ?? $fieldName;
                $field = $this->fieldToIndex($fieldNameFacet);
                if (!$field) {
                    break;
                }
                $firstKey = key($values);
                // Check for a facet range.
                if (count($values) <= 2 && ($firstKey === 'from' || $firstKey === 'to')) {
                    if (isset($values['from']) && $values['from'] !== '') {
                        $this->args['filter'][] = [
                            'join' => 'and',
                            'field' => $field,
                            'type' => '≥',
                            'val' => $values['from'],
                        ];
                    }
                    if (isset($values['to']) && $values['to'] !== '') {
                        $this->args['filter'][] = [
                            'join' => 'and',
                            'field' => $field,
                            'type' => '≤',
                            'val' => $values['to'],
                        ];
                    }
                } else {
                    $fieldQueryArgs = $this->query->getFieldQueryArgs($fieldName);
                    if ($fieldQueryArgs) {
                        $this->args['filter'][] = [
                            'join' => $fieldQueryArgs['join'] ?? 'and',
                            'field' => $field,
                            'except' => $fieldQueryArgs['except'] ?? null,
                            'type' => $fieldQueryArgs['type'] ?? 'eq',
                            'val' => $flatArray($values),
                            'lang' => $fieldQueryArgs['lang'] ?? null,
                            'datatype' => $fieldQueryArgs['datatype'] ?? null,
                        ];
                    } else {
                        $this->args['filter'][] = [
                            'join' => 'and',
                            'field' => $field,
                            'type' => 'eq',
                            'val' => $flatArray($values),
                        ];
                    }
                }
                break;

            case !$isSimpleValue:
                // The filter is a query row in SearchResource, but the filters
                // are grouped by field.
                if (!is_array($values)) {
                    continue 2;
                }

                $field = $this->fieldToIndex($fieldName);
                if (!$field) {
                    continue 2;
                }

                foreach ($values as $queryFilter) {
                    // Skip simple filters (for hidden queries).
                    if (!$queryFilter
                        || !is_array($queryFilter)
                        || empty($queryFilter['type'])
                        || !isset(SearchResources::FIELD_QUERY['reciprocal'][$queryFilter['type']])
                    ) {
                        continue;
                    }
                    $queryFilter += ['join' => null, 'type' => null, 'val' => null];
                    $this->args['filter'][] = [
                        'join' => $queryFilter['join'],
                        'field' => $field,
                        'type' => $queryFilter['type'],
                        'val' => $queryFilter['val'],
                    ];
                }
                break;

            default:
                // Normally, the fields are already converted in standard
                // advanced search form.
                $field = $this->fieldToIndex($fieldName);
                if (!$field) {
                    break;
                }
                $fieldQueryArgs = $this->query->getFieldQueryArgs($fieldName);
                foreach ($values as $value) {
                    if (is_array($value)) {
                        // Skip date range queries (for hidden queries).
                        if (isset($value['from']) || isset($value['to'])) {
                            continue;
                        }
                        // Skip queries filters (for hidden queries).
                        if (isset($value['joiner'])
                            || isset($value['type'])
                            || isset($value['text'])
                            || isset($value['join'])
                            || isset($value['val'])
                            // Deprecated.
                            || isset($value['value'])
                        ) {
                            continue;
                        }
                        if ($fieldQueryArgs) {
                            $this->args['filter'][] = [
                                'join' => $fieldQueryArgs['join'] ?? 'and',
                                'field' => $field,
                                'except' => $fieldQueryArgs['except'] ?? null,
                                'type' => $fieldQueryArgs['type'] ?? 'eq',
                                'val' => $value,
                                'lang' => $fieldQueryArgs['lang'] ?? null,
                                'datatype' => $fieldQueryArgs['datatype'] ?? null,
                            ];
                        } else {
                            $this->args['filter'][] = [
                                'join' => 'and',
                                'field' => $field,
                                'type' => 'eq',
                                'val' => $value,
                            ];
                        }
                    } else {
                        if ($fieldQueryArgs) {
                            $this->args['filter'][] = [
                                'join' => $fieldQueryArgs['join'] ?? 'and',
                                'field' => $field,
                                'except' => $fieldQueryArgs['except'] ?? null,
                                'type' => $fieldQueryArgs['type'] ?? 'eq',
                                'val' => $value,
                                'lang' => $fieldQueryArgs['lang'] ?? null,
                                'datatype' => $fieldQueryArgs['datatype'] ?? null,
                            ];
                        } else {
                            $this->args['filter'][] = [
                                'join' => 'and',
                                'field' => $field,
                                'type' => 'eq',
                                'val' => $value,
                            ];
                        }
                    }
                }
                break;
        }
    }

    protected function filterQueryRanges(array $dateRangeFilters): void
    {
        foreach ($dateRangeFilters as $field => $filterValues) {
            if ($field === 'created' || $field === 'modified') {
                $argName = 'datetime';
            } else {
                $field = $this->fieldToIndex($field);
                if (!$field) {
                    continue;
                }
                $argName = 'filter';
            }
            foreach ($filterValues as $filterValue) {
                // Skip simple and query filters (for hidden queries).
                if (!is_array($filterValue)) {
                    continue;
                }
                if (isset($filterValue['from']) && strlen($filterValue['from'])) {
                    $this->args[$argName][] = [
                        'join' => 'and',
                        'field' => $field,
                        'type' => '≥',
                        'val' => $filterValue['from'],
                    ];
                }
                if (isset($filterValue['to']) && strlen($filterValue['to'])) {
                    $this->args[$argName][] = [
                        'join' => 'and',
                        'field' => $field,
                        'type' => '≤',
                        'val' => $filterValue['to'],
                    ];
                }
            }
        }
    }

    /**
     * Refine the main search.
     *
     * There may be only one full search by query, so use a property query.
     *
     * @todo Use excluded fields?
     */
    protected function filterQueryRefine($queryRefine): void
    {
        if (strlen((string) $queryRefine)) {
            $this->args['filter'][] = [
                'join' => 'and',
                'field' => '',
                // 'except' => $excludedFields,
                'type' => 'in',
                'val' => $queryRefine,
            ];
        }
    }

    /**
     * @param bool $useArgsWithFacets Use this args with or without facets.
     *
     * @todo The check of specific query should use the real keys, not the query field names.
     */
    protected function isSpecificQuery(bool $useArgsWithFacets = false): bool
    {
        // TODO Manage search "resources".
        // No resource types mean "resources". "resources" is not fully managed:
        // only a query without keys specific to a resource are managed.
        $singleResourceType = count($this->resourceTypes) === 1;
        if ($singleResourceType && reset($this->resourceTypes) !== 'resources') {
            return false;
        }

        $specificKeys = [
            'item_set_id' => null,
            'not_item_set_id' => null,
            // Site id is managed by all resources, but differently.
            'site_id' => null,
            'in_sites' => null,
            'has_media' => null,
            'item_id' => null,
            'media_type' => null,
            'ingester' => null,
            'renderer' => null,
            'is_open' => null,
            // More.
            'item_set/o:id' => null,
            'site/o:id' => null,
            // Modules.
            'item_sets_tree' => null,
            // Old keys.
            'itemset' => null,
            'itemSet' => null,
            'item_set' => null,
            'site' => null,
            // Multi.
            'item_set_id[]' => null,
            'not_item_set_id[]' => null,
            // Site id is managed by all resources, but differently.
            'site_id[]' => null,
            'in_sites[]' => null,
            'item_id[]' => null,
            'media_type[]' => null,
            'ingester[]' => null,
            'renderer[]' => null,
            // Modules.
            'item_sets_tree[]' => null,
            'item_sets_tree[id]' => null,
            // Old keys.
            'itemset[]' => null,
            'itemSet[]' => null,
            'item_set[]' => null,
            'itemset[id]' => null,
            'itemSet[id]' => null,
            'item_set[id]' => null,
            'site[]' => null,
        ];

        if (!$useArgsWithFacets) {
            return (bool) array_intersect_key($specificKeys, $this->argsWithoutActiveFacets);
        }
        if (array_intersect_key($specificKeys, $this->args)) {
            return true;
        }
        return isset($this->args['facet'])
            && is_array($this->args['facet'])
            && array_intersect_key($specificKeys, $this->args['facet']);
    }

    /**
     * Convert a field argument into one or more indexes.
     *
     * The indexes are the properties in internal sql.
     *
     * @return array|string|null
     */
    protected function fieldToIndex(string $field)
    {
        return $this->query->getAliases()[$field]['fields']
            ?? $this->easyMeta->propertyTerm($field)
            ?? $this->underscoredNameToTerm($field)
            ?? null;
    }

    /**
     * Convert a name with an underscore into a standard term.
     *
     * The input name should not be a term (should be checked before).
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
        if ($underscoredTerms === null) {
            $underscoredTerms = $this->getUnderscoredUsedProperties();
            $underscoredTermsRegex = '~(?:' . implode('|', array_keys($underscoredTerms)) . '){1}~';
        }

        if (isset($underscoredTerms[$name])) {
            return $underscoredTerms[$name];
        }

        $matches = [];
        return preg_match($underscoredTermsRegex, $name, $matches, PREG_UNMATCHED_AS_NULL) && count($matches) === 1
            ? reset($matches)
            : null;
    }

    /**
     * @todo Factorize with \AdvancedSearch\Form\MainSearchForm::listValues()
     *
     * Adapted:
     * @see \AdvancedSearch\Api\Representation\SearchConfigRepresentation::suggest()
     * @see \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation::suggest()
     * @see \AdvancedSearch\Form\MainSearchForm::listValuesForField()
     * @see \Reference\Mvc\Controller\Plugin\References
     */
    protected function fillFacetResponse(): void
    {
        $this->response->setActiveFacets($this->query->getActiveFacets());

        /** @var \Reference\Mvc\Controller\Plugin\References $references */
        $references = $this->services->get('ControllerPluginManager')->get('references');

        $facets = $this->query->getFacets();

        $metadataFieldsToReferenceFields = [
            'resource_name' => 'resource_type',
            'resource_type' => 'resource_type',
            'is_public' => 'is_public',
            'owner_id' => 'o:owner',
            'site_id' => 'o:site',
            'resource_class_id' => 'o:resource_class',
            'resource_template_id' => 'o:resource_template',
            'item_set_id' => 'o:item_set',
            'access' => 'access',
            'item_sets_tree' => 'o:item_set',
        ];

        $facetOrders = [
            // Default alphabetic order is asc.
            'alphabetic' => [
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
            ],
            'alphabetic asc' => [
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
            ],
            'alphabetic desc' => [
                'sort_by' => 'alphabetic',
                'sort_order' => 'DESC',
            ],
            // Default total order is desc.
            'total' => [
                'sort_by' => 'total',
                'sort_order' => 'DESC',
            ],
            'total asc' => [
                'sort_by' => 'total',
                'sort_order' => 'ASC',
            ],
            'total desc' => [
                'sort_by' => 'total',
                'sort_order' => 'DESC',
            ],
            // Default total alpha order is desc.
            'total_alpha' => [
                'sort_by' => 'total',
                'sort_order' => 'DESC',
            ],
            'total_alpha desc' => [
                'sort_by' => 'total',
                'sort_order' => 'DESC',
            ],
            // Default values order is asc.
            'values' => [
                'sort_by' => 'values',
                'sort_order' => 'ASC',
            ],
            'values asc' => [
                'sort_by' => 'values',
                'sort_order' => 'ASC',
            ],
            'values desc' => [
                'sort_by' => 'values',
                'sort_order' => 'DESC',
            ],
            // Default values order is alphabetic asc.
            'default' => [
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
            ],
        ];

        // Normalize search query keys as omeka keys for items and item sets.
        // TODO Manages individual options for range, main data types, data types.

        // The module Reference use the key "resource_name" for now.
        $referenceMetadata = [];
        $referenceOptions = [
            'resource_name' => null,
            'output' => 'associative',
            'meta_options' => [],
        ];
        foreach ($facets as $facetName => $facetData) {
            if (empty($facetData['field'])) {
                $this->logger->err(
                    'The field for the facet "{name}" is not set.', // @translate
                    ['name' => $facetName]
                );
                continue;
            }
            // Check for specific fields of the search engine.
            $facetField = $facetData['field'];
            $facetField = $metadataFieldsToReferenceFields[$facetField]
                ?? $this->fieldToIndex($facetField)
                ?? $facetField;
            $referenceMetadata[$facetName] = $facetField;
            // Check for specific options for the current facet.
            $order = $facetOrders[$facetData['order'] ?? 'default'] ?? $facetOrders['default'];
            $facetOptions = [
                'sort_by' => $order['sort_by'],
                'sort_order' => $order['sort_order'],
                'per_page' => $facetData['limit'] ?? 0,
                'filters' => [
                    'languages' => $facetData['languages'] ?? [],
                    'main_types' => $facetData['main_types'] ?? [],
                    'data_types' => $facetData['data_types'] ?? [],
                    'values' => $facetData['values'] ?? [],
                ],
            ];
            // Manage an exception for facet ranges: skip limit and order.
            // TODO Add an individual sort_by for numeric facet ranges. Currently "alphabetic" is used, that works fine only for years.
            // TODO Make InternalQuerier and References manage facet ranges (for now via a manual process here and above and in FacetSelectRange).
            $isFacetRange = ($facetData['type'] ?? null) === 'SelectRange';
            if ($isFacetRange) {
                $facetOptions['sort_by'] = 'alphabetic';
                $facetOptions['sort_order'] = 'asc';
                $facetOptions['per_page'] = 0;
                $facetOptions['is_facet_range'] = true;
            }
            $referenceOptions['meta_options'][$facetName] = $facetOptions;
        }

        if (!$referenceMetadata) {
            return;
        }

        // Facet counts don't make a distinction by resource type, so they
        // should be merged here. This is needed as long as there is no single
        // query for resource (items and item sets together).
        $facetCountsByField = array_fill_keys(array_keys($facets), []);

        $isAllFacets = $this->query->getOption('facet_list') === 'all';

        // The query already contains the arg "resource_type", so it is now
        // possible to do a unique request to references, since there is only
        // one list of facets for all resources.
        // Nevertheless, this is not possible when the facets contain specific
        // fields, in particular item sets or item sets tree, that are available
        // only for items.

        // TODO Remove processing specific queries.
        $isSpecificQuery = $this->isSpecificQuery(!$isAllFacets);

        if (!$isSpecificQuery) {
            $mainResourceType = count($this->resourceTypes) === 1 ? reset($this->resourceTypes) : 'resources';
            $totalRess = $this->response->getTotalResults();
            // Like Solr, get only available useful values or all existing values.
            /** @see https://solr.apache.org/guide/solr/latest/query-guide/faceting.html */
            if ($isAllFacets) {
                // Do the query one time for all facets, for each resource type.
                // It is not possible when there are facets for item set or site
                // because they are removed from the query.
                // FIXME Where the item sets and facets are removed from the query?
                // TODO Check if item sets and sites are still an exception for references.
                /** @see \Reference\Mvc\Controller\Plugin\References::searchQuery() */
                $referenceQuery = $this->argsWithoutActiveFacets;
            } elseif (!$totalRess) {
                return;
            } else {
                // For performance, use the full list of resource ids when possible,
                // instead of the original query, that implies to run query twice.
                // This is no more possible, because the full list of ids is no
                // more filled early.
                $referenceQuery = $this->args;
            }

            $referenceOptions['resource_name'] = $mainResourceType;
            $values = $references
                ->setMetadata($referenceMetadata)
                ->setQuery($referenceQuery)
                ->setOptions($referenceOptions)
                ->list();
            foreach (array_keys($referenceMetadata) as $facetName) foreach ($values[$facetName]['o:references'] ?? [] as $value => $count) {
                $facetCountsByField[$facetName][$value] = [
                    'value' => $value,
                    'count' => $count,
                ];
            }
            $this->response->setFacetCounts(array_map('array_values', $facetCountsByField));
            return;
        }

        // Manage exceptions in some query arguments or facets, because the
        // adapter for resources does not manage all arguments.

        // The query already contains the arg "resource_type".
        foreach ($this->byResourceType ? $this->resourceTypes : ['resources'] as $resourceType) {
            $totalRess = $this->response->getTotalResults();
            if ($isAllFacets) {
                $referenceQuery = $this->argsWithoutActiveFacets;
            } elseif (!$totalRess) {
                continue;
            } else {
                $referenceQuery = $this->args;
            }

            $referenceOptions['resource_name'] = $resourceType;
            $values = $references
                ->setMetadata($referenceMetadata)
                ->setQuery($referenceQuery)
                ->setOptions($referenceOptions)
                ->list();
            foreach (array_keys($referenceMetadata) as $facetName) foreach ($values[$facetName]['o:references'] ?? [] as $value => $count) {
                if (empty($facetCountsByField[$facetName][$value])) {
                    $facetCountsByField[$facetName][$value] = [
                        'value' => $value,
                        'count' => $count,
                    ];
                } else {
                    $facetCountsByField[$facetName][$value] = [
                        'value' => $value,
                        'count' => $count + $facetCountsByField[$facetName][$value]['count'],
                    ];
                }
            }
        }

        $this->response->setFacetCounts(array_map('array_values', $facetCountsByField));
    }

    /**
     * Convert a list of site slugs into a list of site ids.
     *
     * @param array $values
     * @return array Only values that are slugs are converted into ids, the
     * other ones are removed.
     *
     * @todo Include in easyMeta? But the rights should be checked (but this is not the case here anyway).
     */
    protected function listSiteIds(array $values): array
    {
        return array_values(array_intersect_key($this->getSiteIds(), array_fill_keys($values, null)));
    }

    /**
     * Get all site ids by slug.
     *
     * @return array Associative array of ids by slug.
     */
    protected function getSiteIds(): array
    {
        static $sites;

        if ($sites === null) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'site.slug AS slug',
                    'site.id AS id'
                )
                ->from('site', 'site')
                ->orderBy('id', 'asc')
            ;
            $sites = array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
        }

        return $sites;
    }

    /**
     * Convert a list of user names into a list of ids.
     *
     * @param array $values
     * @return array Only values that are user name are converted into ids, the
     * other ones are removed.
     *
     * @todo Include in easyMeta? But the rights should be checked (but this is not the case here anyway).
     */
    protected function listUserIds(array $values): array
    {
        return array_values(array_intersect_key($this->getUserIds(), array_fill_keys($values, null)));
    }

    /**
     * Get all user ids by name.
     *
     * @return array Associative array of ids by user name.
     */
    protected function getUserIds(): array
    {
        static $users;

        if ($users === null) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'user.name AS name',
                    'user.id AS id'
                )
                ->from('user', 'user')
                ->orderBy('id', 'asc')
            ;
            $users = array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
        }

        return $users;
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

        if ($properties === null) {
            $usedPropertyByTerms = $this->easyMeta->propertyIdsUsed();
            $properties = [];
            foreach (array_keys($usedPropertyByTerms) as $term) {
                $properties[strtr($term, [':' => '_'])] = $term;
            }
        }

        return $properties;
    }
}
