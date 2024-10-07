<?php declare(strict_types=1);

namespace AdvancedSearch\Querier;

use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Response;
use Common\Stdlib\PsrMessage;

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
        $this->response = new Response;
        $this->response->setApi($api);

        $this->byResourceType = $this->query ? $this->query->getByResourceType() : false;
        $this->response->setByResourceType($this->byResourceType);

        $this->args = $this->getPreparedQuery();

        // When no query or resource types are set.
        if ($this->args === null) {
            return $this->response
                ->setMessage('An issue occurred.'); // @translate
        }

        $plugins = $this->services->get('ControllerPluginManager');
        $hasReferences = $plugins->has('references');

        // Omeka S v4.1 does not allow to search fulltext and return scalar ids.
        // Check if the fix Omeka S is not ready.
        // @see https://github.com/omeka/omeka-s/pull/2224
        if (isset($this->args['fulltext_search'])
            && substr((string) $this->query->getSort(), 0, 9) === 'relevance'
        ) {
            $requireFix2224 = file_get_contents(OMEKA_PATH . '/application/src/Api/Adapter/AbstractEntityAdapter.php');
            $requireFix2224 = !strpos($requireFix2224, '$hasFullTextSearchOrder');
            if ($requireFix2224) {
                $this->logger->warn('The fix https://github.com/omeka/omeka-s/pull/2224 is not integrated. A workaround is used.'); // @translate
            }
        } else {
            $requireFix2224 = null;
        }

        // The standard api way implies a double query, because scalar doesn't
        // set the full total and doesn't use paginator.
        // So get all ids, then slice it here.
        $dataQuery = $this->args;
        $limit = empty($dataQuery['limit']) ? null : (int) $dataQuery['limit'];
        $offset = empty($dataQuery['offset']) ? 0 : (int) $dataQuery['offset'];
        unset($dataQuery['limit'], $dataQuery['offset']);

        // Some query arguments and facets are not manageable via resource type
        // "resources".
        $isSpecificQuery = $this->isSpecificQuery(true);

        // Return scalar doesn't allow to get the total of results.
        // So skip offset and limit, then apply them in order to avoid the
        // double query.
        // Important: the full list of ids is used for the facets too.
        // TODO Check if this internal api paginator is quicker in all cases (small/long results) than previous double query.

        // Resources types are filtered from the query or from the indexes.
        if ($this->byResourceType || $isSpecificQuery) {
            foreach ($this->resourceTypes as $resourceType) {
                try {
                    if ($requireFix2224) {
                        $apiResponse = $api->search($resourceType, $dataQuery, ['returnScalar' => 'id', 'require_fix_2224' => true]);
                        $totalResults = $apiResponse->getRequest()->getOption('total_results', 0);
                        $result = $apiResponse->getRequest()->getOption('results', []);
                    } else {
                        $apiResponse = $api->search($resourceType, $dataQuery, ['returnScalar' => 'id']);
                        $totalResults = $apiResponse->getTotalResults();
                        $result = $apiResponse->getContent();
                    }
                } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                    throw new QuerierException($e->getMessage(), $e->getCode(), $e);
                }
                // TODO Currently experimental. To replace by a query + arg "querier=internal".
                $this->response->setAllResourceIdsForResourceType($resourceType, array_map('intval', $result) ?: []);
                if ($result && ($offset || $limit)) {
                    $result = array_slice($result, $offset, $limit ?: null);
                    // $apiResponse->setContent($result);
                }
                $this->response->setResourceTotalResults($resourceType, $totalResults);
                if ($totalResults) {
                    $result = array_map(fn ($v) => ['id' => $v], $result);
                } else {
                    $result = [];
                }
                $this->response->addResults($resourceType, $result);
            }
            $totalResults = array_sum($this->response->getResourceTotalResults());
            $this->response->setTotalResults($totalResults);
        } else {
            try {
                // It is not possible to return the resource type for now with
                // doctrine, but it is useless.
                $mainResourceType = count($this->resourceTypes) === 1 ? reset($this->resourceTypes) : 'resources';
                if ($requireFix2224) {
                    $apiResponse = $api->search($mainResourceType, $dataQuery, ['returnScalar' => 'id', 'require_fix_2224' => true]);
                    $totalResults = $apiResponse->getRequest()->getOption('total_results', 0);
                    $result = $apiResponse->getRequest()->getOption('results', []);
                } else {
                    $apiResponse = $api->search($mainResourceType, $dataQuery, ['returnScalar' => 'id']);
                    $totalResults = $apiResponse->getTotalResults();
                    $result = $apiResponse->getContent();
                }
            } catch (\Omeka\Api\Exception\ExceptionInterface $e) {
                throw new QuerierException($e->getMessage(), $e->getCode(), $e);
            }
            // TODO Currently experimental. To replace by a query + arg "querier=internal".
            $this->response->setAllResourceIdsForResourceType('resources', array_map('intval', $result) ?: []);
            if ($result && ($offset || $limit)) {
                $result = array_slice($result, $offset, $limit ?: null);
                // $apiResponse->setContent($result);
            }
            $this->response->setResourceTotalResults('resources', $totalResults);
            if ($totalResults) {
                $result = array_map(fn ($v) => ['id' => $v], $result);
            } else {
                $result = [];
            }
            $this->response->addResults('resources', $result);
            $this->response->setTotalResults($totalResults);
        }

        $this->response->setCurrentPage($limit ? 1 + (int) floor($offset / $limit) : 1);
        $this->response->setPerPage($limit);

        // Remove specific results when settings are not by resource type.
        // TODO The order may be different when "resources" is not used.
        // This is the same in SolariumQuerier.
        if ($isSpecificQuery && !$this->byResourceType && count($this->resourceTypes) > 1) {
            $allResourceIdsByType = $this->response->getAllResourceIds(null, true);
            if (isset($allResourceIdsByType['resources'])) {
                $this->response->setAllResourceIdsByResourceType(['resources' => $allResourceIdsByType['resources']]);
            } else {
                $this->response->setAllResourceIdsByResourceType(['resources' => array_merge(...array_values($allResourceIdsByType))]);
            }
            $resultsByType = $this->response->getResults();
            if (isset($resultsByType['resources'])) {
                $this->response->setResults(['resources' => $resultsByType['resources']]);
            } else {
                $this->response->setResults(['resources' => array_replace(...array_values($resultsByType))]);
            }
            $totalResultsByType = $this->response->getResourceTotalResults();
            $total = isset($totalResultsByType['resources']) ? $totalResultsByType['resources'] : array_sum($totalResultsByType);
            $this->response->setResourceTotalResults('resources', $total);
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
        if (is_null($this->args)) {
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
                . str_replace(['%', '_'], ['\%', '\_'], $q) . '%',
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
            $bind['value_like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
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
            $bind['value_like'] = str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
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

        $indexerResourceTypes = $this->engine->setting('resource_types', []);
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

        $totalProperties = count($this->args['property'] ?? []);
        $totalFilters = count($this->args['filter'] ?? []);
        $totalPropertiesAndFilters = $totalProperties + $totalFilters;

        if ($totalPropertiesAndFilters > self::REQUEST_MAX_ARGS) {
            $plugins = $this->services->get('ControllerPluginManager');
            $params = $plugins->get('params');
            $req = $params->fromQuery();
            unset($req['csrf']);
            $req = urldecode(http_build_query(array_filter($req), '', '&', PHP_QUERY_RFC3986));
            $messenger = $plugins->get('messenger');
            if ($this->query->getExcludedFields()) {
                $message = new PsrMessage(
                    'The query "{query}" uses {count} properties or filters, that is more than the {total} supported currently. Excluded fields are removed.', // @translate
                    ['query' => $req, 'count' => $totalPropertiesAndFilters, 'total' => self::REQUEST_MAX_ARGS]
                );
                $this->query->setExcludedFields([]);
                $messenger->addWarning($message);
                $this->logger->warn($message->getMessage(), $message->getContext());
                return $this->getPreparedQuery();
            }

            $message = new PsrMessage(
                'The query "{query}" uses {count} properties or filters, that is more than the {total} supported currently. Request is troncated.', // @translate
                ['query' => $req, 'count' => $totalPropertiesAndFilters, 'total' => self::REQUEST_MAX_ARGS]
            );
            $messenger->addWarning($message);
            $this->logger->warn($message->getMessage(), $message->getContext());
            if ($totalProperties) {
                $this->args['property'] = array_slice($this->args['property'] ?? [], 0, self::REQUEST_MAX_ARGS);
            } else {
                unset($this->args['property']);
            }
            if (!$totalFilters || ((self::REQUEST_MAX_ARGS - $totalProperties) <= 0)) {
                unset($this->args['filter']);
            } else {
                $this->args['filter'] = array_slice($this->args['filter'] ?? [], 0, self::REQUEST_MAX_ARGS - $totalProperties);
            }
        }

        $sort = $this->query->getSort();
        if ($sort) {
            [$sortField, $sortOrder] = explode(' ', $sort);
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

        if ($this->query->getOption('remove_diacritics', false)) {
            if (extension_loaded('intl')) {
                $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
                $q = $transliterator->transliterate($q);
            } elseif (extension_loaded('iconv')) {
                $q = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $q);
            }
        }

        // TODO Try to support the exact search and the full text search (removed in version 3.5.17.3).
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
        }

        if ($this->query->getOption('default_search_partial_word', false)) {
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
            $this->args['search'] = $q;
        } else {
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
        }

        // TODO Try to support the exact search and the full text search (removed in previous version).
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
        }

        if ($this->query->getOption('default_search_partial_word', false)) {
            $this->args['filter'][] = [
                'join' => 'and',
                'field' => '',
                'except' => $excludedFields,
                'type' => 'in',
                'val' => $q,
            ];
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
        $this->filterQueryValues($hiddenFilters);
        $this->filterQueryRanges($hiddenFilters);
        $this->filterQueryFilters($hiddenFilters);
    }

    /**
     * Filter the query.
     *
     * @todo Fix the process for facets: all the facets should be displayed, and "or" by group of facets.
     * @todo Make core search properties groupable ("or" inside a group, "and" between group).
     *
     * Note: when a facet is selected, it is managed like a filter.
     * For facet ranges, filters are managed as lower than / greater than.
     */
    protected function filterQuery(): void
    {
        // Don't use excluded fields for filters.
        $this->filterQueryValues($this->query->getFilters());
        $this->filterQueryRanges($this->query->getFiltersRange());
        $this->filterQueryFilters($this->query->getFiltersQuery());
        $this->argsWithoutActiveFacets = $this->args;
        $this->filterQueryValues($this->query->getActiveFacets(), true);
    }

    protected function filterQueryValues(array $filters, bool $inListForFacets = false): void
    {
        $flatArray = function ($values): array {
            if (!is_array($values)) {
                return [$values];
            } elseif (is_array(reset($values))) {
                return array_merge(...array_values($values));
            }
            return $values;
        };

        // Empty values are already filtered by the form adapter.
        foreach ($filters as $field => $values) switch ($field) {
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
                $this->args['resource_type'] = array_unique(array_intersect($this->resourceType, array_merge($this->args['resource_type'], $values)));
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
                $fieldName = $field;
                $fieldData = $this->query->getFacet($fieldName);
                if (!$fieldData) {
                    break;
                }
                $field = $fieldData['field'] ?? $fieldName;
                $field = $this->fieldToIndex($field);
                if (!$field) {
                    break;
                }
                // "In list" is used for facets.
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
                    $this->args['filter'][] = [
                        'join' => 'and',
                        'field' => $field,
                        'type' => 'list',
                        'val' => $flatArray($values),
                    ];
                }
                break;

            default:
                $field = $this->fieldToIndex($field);
                if (!$field) {
                    break;
                }
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
                        $this->args['filter'][] = [
                            'join' => 'and',
                            'filter' => $field,
                            'type' => 'list',
                            'val' => $value,
                        ];
                    } else {
                        $this->args['filter'][] = [
                            'join' => 'and',
                            'filter' => $field,
                            'type' => 'eq',
                            'val' => $value,
                        ];
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
     * @todo In internal querier, advanced filters manage only properties for now.
     */
    protected function filterQueryFilters(array $filters): void
    {
        foreach ($filters as $field => $values) {
            $field = $this->fieldToIndex($field);
            if (!$field) {
                continue;
            }
            foreach ($values as $value) {
                // Skip simple filters (for hidden queries).
                if (!$value || !is_array($value)) {
                    continue;
                }
                $value += ['join' => null, 'type' => null, 'val' => null];
                $this->args['filter'][] = [
                    'join' => $value['join'],
                    'field' => $field,
                    'type' => $value['type'],
                    'val' => $value['val'],
                ];
            }
        }
    }

    /**
     * @param bool $useArgsWithFacets Use this args with or without facets.
     *
     * @todo The check of specific query should use the real keys, not the query field names.
     */
    protected function isSpecificQuery(bool $useArgsWithFacets = false): bool
    {
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
        $aliases = $this->query->getAliases();
        return $this->easyMeta->propertyTerm($field)
            ?? $aliases[$field]['fields']
            ?? $this->underscoredNameToTerm($field)
            ?? null;
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

        // A common name for Omeka resources.
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
            // Like Solr, get only available useful values or all existing values.
            /** @see https://solr.apache.org/guide/solr/latest/query-guide/faceting.html */
            if ($isAllFacets) {
                // Do the query one time for all facets, for each resource types.
                // Itis not possible when there are facets for item set or site
                // because they are removed from the query.
                // TODO Check if item sets and sites are still an exception for references.
                /** @see \Reference\Mvc\Controller\Plugin\References::searchQuery() */
                if ((in_array('o:item_set', $referenceMetadata) && (isset($this->argsWithoutActiveFacets['item_set_id']) || isset($this->argsWithoutActiveFacets['item_set']) || isset($this->argsWithoutActiveFacets['itemset'])))
                    || (in_array('o:site', $referenceMetadata) && (isset($this->argsWithoutActiveFacets['site_id']) || isset($this->argsWithoutActiveFacets['site'])))
                ) {
                    $referenceQuery = $this->argsWithoutActiveFacets;
                } else {
                    /** @var \Omeka\Api\Manager $api */
                    $api = $this->services->get('Omeka\ApiManager');
                    $ids = $api->search($mainResourceType, $this->argsWithoutActiveFacets, ['returnScalar' => 'id'])->getContent();
                    if (!$ids) {
                        return;
                    }
                    $referenceQuery = ['id' => array_values($ids)];
                }
            } else {
                // For performance, use the full list of resource ids when possible,
                // instead of the original query.
                // $referenceQuery = $this->args;
                $ids = $this->response->getAllResourceIds();
                if (!$ids) {
                    return;
                }
                $referenceQuery = ['id' => array_values($ids)];
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
            // Like Solr, get only available useful values or all existing values.
            /** @see https://solr.apache.org/guide/solr/latest/query-guide/faceting.html */
            if ($isAllFacets) {
                // Do the query one time for all facets, for each resource types.
                // Itis not possible when there are facets for item set or site
                // because they are removed from the query.
                // TODO Check if item sets and sites are still an exception for references.
                /** @see \Reference\Mvc\Controller\Plugin\References::searchQuery() */
                if ((in_array('o:item_set', $referenceMetadata) && (isset($this->argsWithoutActiveFacets['item_set_id']) || isset($this->argsWithoutActiveFacets['item_set']) || isset($this->argsWithoutActiveFacets['itemset'])))
                    || (in_array('o:site', $referenceMetadata) && (isset($this->argsWithoutActiveFacets['site_id']) || isset($this->argsWithoutActiveFacets['site'])))
                ) {
                    $referenceQuery = $this->argsWithoutActiveFacets;
                } else {
                    /** @var \Omeka\Api\Manager $api */
                    $api = $this->services->get('Omeka\ApiManager');
                    $ids = $api->search($resourceType, $this->argsWithoutActiveFacets, ['returnScalar' => 'id'])->getContent();
                    if (!$ids) {
                        continue;
                    }
                    $referenceQuery = ['id' => array_values($ids)];
                }
            } else {
                // For performance, use the full list of resource ids when possible,
                // instead of the original query.
                // $referenceQuery = $this->args;
                $ids = $this->response->getAllResourceIds($resourceType, false);
                if (!$ids) {
                    continue;
                }
                $referenceQuery = ['id' => array_values($ids)];
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
     * Convert a list of site slug into a list of site ids.
     *
     * @param array $values
     * @return array Only values that are slugs are converted into ids, the
     * other ones are removed.
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

        if (is_null($sites)) {
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

        if (is_null($users)) {
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

        if (is_null($properties)) {
            $usedPropertyByTerms = $this->easyMeta->propertyIdsUsed();
            $properties = [];
            foreach (array_keys($usedPropertyByTerms) as $term) {
                $properties[str_replace(':', '_', $term)] = $term;
            }
        }

        return $properties;
    }
}
