<?php declare(strict_types=1);

namespace AdvancedSearch\Querier;

use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Response;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

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
        /** @var \Omeka\Api\Manager $api */
        $api = $this->services->get('Omeka\ApiManager');

        $this->response = new Response;
        $this->response->setApi($api);

        $this->args = $this->getPreparedQuery();
        if (is_null($this->args)) {
            return $this->response
                ->setMessage('An issue occurred.'); // @translate
        }

        $plugins = $this->services->get('ControllerPluginManager');
        $hasReferences = $plugins->has('references');

        // The standard api way implies a double query, because scalar doesn't
        // set the full total and doesn't use paginator.
        // So get all ids, then slice it here.
        $dataQuery = $this->args;
        $limit = empty($dataQuery['limit']) ? null : (int) $dataQuery['limit'];
        $offset = empty($dataQuery['offset']) ? 0 : (int) $dataQuery['offset'];
        unset($dataQuery['limit'], $dataQuery['offset']);

        // TODO Inverse logic: search all resources (store id and type), and return by type only when needed (rarely).

        foreach ($this->resourceTypes as $resourceType) {
            try {
                // Return scalar doesn't allow to get the total of results.
                // So skip offset and limit, then apply them in order to avoid
                // the double query.
                // TODO Check if this internal api paginator is quicker in all cases (small/long results) than previous double query.
                $apiResponse = $api->search($resourceType, $dataQuery, ['returnScalar' => 'id']);
                $totalResults = $apiResponse->getTotalResults();
                $result = $apiResponse->getContent();
                // TODO Currently experimental. To replace by a query + arg "querier=internal".
                $this->response->setAllResourceIdsForResourceType($resourceType, $result ?: []);
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

        $totalResults = array_sum($this->response->getResourceTotalResults());
        $this->response->setTotalResults($totalResults);

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
            $results = $connection
                ->executeQuery($sql, $bind, $types)
                ->fetchAll();
        } catch (\Doctrine\DBAL\DBALException $e) {
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
        $inClasses = '"' . implode('", "', $classes) . '"';

        // TODO Manage site id and item set id and any other filter query.
        // TODO Use the full text search table.

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');
        $q = $this->query->getQuery();
        $bind = [
            // 'resource_types' => $classes,
            'limit' => $this->query->getLimit(),
            'value_length' => mb_strlen($q),
            'length' => $this->query->getSuggestOptions()['length'] ?? 50,
        ];
        $types = [
            // 'resource_types' => $connection::PARAM_STR_ARRAY,
            'limit' => \PDO::PARAM_INT,
            'value_length' => \PDO::PARAM_INT,
            'length' => \PDO::PARAM_INT,
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
            $bind['property_ids'] = $ids;
            $types['property_ids'] = $connection::PARAM_INT_ARRAY;
        } else {
            $sqlFields = '';
        }

        $excludedFields = $this->query->getExcludedFields();
        if ($excludedFields) {
            $ids = $this->listPropertyIds($excludedFields);
            if ($ids) {
                $sqlFields .= ' AND `value`.`property_id` NOT IN (:excluded_property_ids)';
                $bind['excluded_property_ids'] = $ids;
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

        $mode = $this->query->getSuggestOptions()['mode_search'] ?: 'start';
        if ($mode === 'contain') {
            // TODO Improve direct sql for full suggestions.
            $sql = <<<SQL
SELECT DISTINCT
    SUBSTRING(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), 1, :length) AS "value",
    COUNT(SUBSTRING(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), 1, :length)) AS "data"
FROM `value` AS `value`
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
$sqlSite
WHERE `resource`.`resource_type` IN ($inClasses)
    $sqlIsPublic
    $sqlFields
    AND `value`.`value` LIKE :value_like
GROUP BY
    SUBSTRING(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), 1, :length)
ORDER BY data DESC
LIMIT :limit
;
SQL;
            $bind['value_like'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $types['value_like'] = \PDO::PARAM_STR;
        } elseif ($mode === 'start') {
            // Use keys "value" and "data" to get a well formatted output for
            // suggestions.
            // The query cuts the value to the first space. The end of line is
            // removed to avoid some strange results.
            // The group by uses the same than the select, because suggester
            // requires "value".
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
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
$sqlSite
WHERE `resource`.`resource_type` IN ("Omeka\\Entity\\Item", "Omeka\\Entity\\ItemSet")
    AND `value`.`value` LIKE :value_like
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
INNER JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
$sqlSite
WHERE `resource`.`resource_type` IN ($inClasses)
    $sqlIsPublic
    $sqlFields
    AND `value`.`value` LIKE :value_like
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
            $results = $connection
                ->executeQuery($sql, $bind, $types)
                ->fetchAll();
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->err($e->getMessage());
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

        $indexerResourceTypes = $this->engine->setting('resources', []);
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
            $params = $this->services->get('ControllerPluginManager')->get('params');
            $req = $params->fromQuery();
            unset($req['csrf']);
            $req = urldecode(http_build_query(array_filter($req), '', '&', PHP_QUERY_RFC3986));
            $messenger = new Messenger;
            if ($this->query->getExcludedFields()) {
                $message = new Message('The query "%1$s" uses %2$d properties, that is more than the %3$d supported currently. Excluded fields are removed.', // @translate
                    $req, count($this->args['property']), self::REQUEST_MAX_ARGS);
                $this->query->setExcludedFields([]);
                $messenger->addWarning($message);
                $this->logger->warn($message);
                return $this->getPreparedQuery();
            }

            $message = new Message('The query "%1$s" uses %2$d properties, that is more than the %3$d supported currently. Request is troncated.', // @translate
                $req, count($this->args['property']), self::REQUEST_MAX_ARGS);
            $messenger->addWarning($message);
            $this->logger->warn($message);
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

        if ($this->engine->settingAdapter('default_search_partial_word', false)) {
            $this->args['property'][] = [
                'joiner' => 'and',
                'property' => '',
                'type' => 'in',
                'text' => $q,
            ];
            return;
        }

        // Full text search is the default Omeka mode.
        // TODO It uses fulltext_search, but when more than 50% results, no results, not understandable by end user (or use boolean mode).
        $this->args['fulltext_search'] = $q;
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

        // TODO Try to support the exact search and the full text search (removed in previous version).
        if (mb_substr($q, 0, 1) === '"' && mb_substr($q, -1) === '"') {
            $q = trim($q, '" ');
        }

        if ($this->engine->settingAdapter('default_search_partial_word', false)) {
            $this->args['property'][] = [
                'joiner' => 'and',
                'property' => '',
                'except' => $excludedFields,
                'type' => 'in',
                'text' => $q,
            ];
            return;
        }

        // Full text search is the default Omeka mode. Exclusion is not possible.
        // TODO It uses fulltext_search, but when more than 50% results, no results, not understandable by end user (or use boolean mode).
        $this->args['fulltext_search'] = $q;
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

        $multifields = $this->engine->settingAdapter('multifields', []);

        $dateRangeFilters = $this->query->getDateRangeFilters();
        foreach ($dateRangeFilters as $field => $filterValues) {
            if ($field === 'created' || $field === 'modified') {
                $argName = 'datetime';
            } else {
                $field = $this->getPropertyTerm($field)
                    ?? $multifields[$field]['fields']
                    ?? $this->underscoredNameToTerm($field)
                    ?? null;
                if (!$field) {
                    continue;
                }
                $argName = 'property';
            }
            foreach ($filterValues as $filterValue) {
                if (strlen($filterValue['from'])) {
                    $this->args[$argName][] = [
                        'joiner' => 'and',
                        'property' => $field,
                        'type' => 'gte',
                        'text' => $filterValue['from'],
                    ];
                }
                if (strlen($filterValue['to'])) {
                    $this->args[$argName][] = [
                        'joiner' => 'and',
                        'property' => $field,
                        'type' => 'lte',
                        'text' => $filterValue['to'],
                    ];
                }
            }
        }

        $filters = $this->query->getFilterQueries();
        foreach ($filters as $field => $values) {
            $field = $this->getPropertyTerm($field)
                ?? $multifields[$field]['fields']
                ?? $this->underscoredNameToTerm($field)
                ?? null;
            if (!$field) {
                continue;
            }
            foreach ($values as $value) {
                $this->args['property'][] = [
                    'joiner' => $value['join'],
                    'property' => $field,
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
        $multifields = $this->engine->settingAdapter('multifields', []);

        $flatArray = function ($values): array {
            if (!is_array($values)) {
                return [$values];
            } elseif (is_array(reset($values))) {
                return array_merge(...$values);
            }
            return $values;
        };

        // Empty values are already filtered by the form adapter.
        foreach ($filters as $field => $values) {
            switch ($field) {
                // "resource_type" is used externally and "resource_name" internally.
                case 'resource_name':
                case 'resource_type':
                    $this->args['resource_name'] = $flatArray($values);
                    continue 2;

                // "is_public" is automatically managed by this internal adapter
                // TODO Improve is_public to search public/private only.
                case 'is_public':
                    continue 2;

                case 'id':
                case 'resource':
                    $this->args['id'] = array_filter(array_map('intval', $flatArray($values)));
                    continue 2;

                case 'site_id':
                    $values = $flatArray($values);
                    $this->args['site_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listSiteIds($values);
                    continue 2;

                case 'owner_id':
                    $values = $flatArray($values);
                    $this->args['owner_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listUserIds($values);
                continue 2;

                case 'resource_class_id':
                    $values = $flatArray($values);
                    $this->args['resource_class_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listResourceClassIds($values);
                    continue 2;

                case 'resource_template_id':
                    $values = $flatArray($values);
                    $this->args['resource_template_id'] = is_numeric(reset($values))
                        ? array_filter(array_map('intval', $values))
                        : $this->listResourceTemplateIds($values);
                    continue 2;

                case 'item_set_id':
                    $this->args['item_set_id'] = array_filter(array_map('intval', $flatArray($values)));
                    continue 2;

                default:
                    $field = $this->getPropertyTerm($field)
                        ?? $multifields[$field]['fields']
                        ?? $this->underscoredNameToTerm($field)
                        ?? null;
                    if (!$field) {
                        break;
                    }
                    // "In list" is used for facets.
                    if ($inList) {
                        $this->args['property'][] = [
                            'joiner' => 'and',
                            'property' => $field,
                            'type' => 'list',
                            'text' => $flatArray($values),
                        ];
                        break;
                    }
                    foreach ($values as $value) {
                        if (is_array($value)) {
                            $this->args['property'][] = [
                                'joiner' => 'and',
                                'property' => $field,
                                'type' => 'list',
                                'text' => $value,
                            ];
                        } else {
                            $this->args['property'][] = [
                                'joiner' => 'and',
                                'property' => $field,
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

    protected function fillFacetResponse(): void
    {
        $this->response->setActiveFacets($this->query->getActiveFacets());

        /** @var \Reference\Mvc\Controller\Plugin\References $references */
        $references = $this->services->get('ControllerPluginManager')->get('references');

        $facetFields = $this->query->getFacetFields();
        $facetLimit = $this->query->getFacetLimit();
        $facetOrder = $this->query->getFacetOrder();
        $facetLanguages = $this->query->getFacetLanguages();

        $metadataFieldsToNames = [
            'resource_name' => 'resource_type',
            'resource_type' => 'resource_type',
            'is_public' => 'is_public',
            'site_id' => 'o:site',
            'owner_id' => 'o:owner',
            'resource_class_id' => 'o:resource_class',
            'resource_template_id' => 'o:resource_template',
            'item_set_id' => 'o:item_set',
        ];

        // Convert multi-fields into a list of property terms.
        // Normalize search query keys as omeka keys for items and item sets.
        $multifields = $this->engine->settingAdapter('multifields', []);
        $fields = [];
        foreach ($facetFields as $facetField) {
            $fields[$facetField] = $metadataFieldsToNames[$facetField]
                ?? $this->getPropertyTerm($facetField)
                ?? $multifields[$facetField]['fields']
                ?? $facetField;
        }

        // Facet counts don't make a distinction by resource type, so they
        // should be merged here. This is needed as long as there is no single
        // query for resource (items and item sets together).
        $facetCountsByField = array_fill_keys($facetFields, []);

        $facetData = $this->argsWithoutActiveFacets;

        $facetOrders = [
            'alphabetic asc' => [
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
            ],
            'total asc' => [
                'sort_by' => 'total',
                'sort_order' => 'ASC',
            ],
            'total desc' => [
                'sort_by' => 'total',
                'sort_order' => 'DESC',
            ],
            'default' => [
                'sort_by' => 'alphabetic',
                'sort_order' => 'ASC',
            ],
        ];
        if (!isset($facetOrders[$facetOrder])) {
            $facetOrder = 'default';
        }

        foreach ($this->resourceTypes as $resourceType) {
            $options = [
                'resource_name' => $resourceType,
                // Options sql.
                'per_page' => $facetLimit,
                'page' => 1,
                'sort_by' => $facetOrders[$facetOrder]['sort_by'],
                'sort_order' => $facetOrders[$facetOrder]['sort_order'],
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
                ->setMetadata($fields)
                ->setQuery($facetData)
                ->setOptions($options)
                ->list();

            foreach (array_keys($fields) as $facetField) {
                // Manage the exceptions.
                $referenceKey = $metadataFieldsToNames[$facetField] ?? $facetField;
                foreach ($values[$referenceKey]['o:references'] ?? [] as $value => $count) {
                    if (empty($facetCountsByField[$facetField][$value])) {
                        $facetCountsByField[$facetField][$value] = [
                            'value' => $value,
                            'count' => $count,
                        ];
                    } else {
                        $facetCountsByField[$facetField][$value] = [
                            'value' => $value,
                            'count' => $count + $facetCountsByField[$facetField][$value]['count'],
                        ];
                    }
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

        if (is_null($resourceTemplates)) {
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
     * Get the list of vocabulary prefixes by vocabulary ids.
     */
    protected function getVocabularyPrefixes(): array
    {
        static $vocabularies;

        if (is_null($vocabularies)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'vocabulary.id AS id',
                    'vocabulary.prefix AS prefix'
                )
                ->from('vocabulary', 'vocabulary')
                ->orderBy('vocabulary.id)', 'ASC')
            ;
            $vocabularies = $connection->executeQuery($qb)->fetchAllKeyValue();
        }

        return $vocabularies;
    }

    /**
     * Check a property term or id.
     *
     * @see \Bulk\Mvc\Controller\Plugin\Bulk::getPropertyTerm()
     */
    protected function getPropertyTerm($termOrId): ?string
    {
        $ids = $this->getPropertyIds();
        return is_numeric($termOrId)
            ? (array_search($termOrId, $ids) ?: null)
            : (array_key_exists($termOrId, $ids) ? $termOrId : null);
    }

    /**
     * Convert a list of terms into a list of property ids.
     *
     * @see \Reference\Mvc\Controller\Plugin\References::listPropertyIds()
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other ones are removed.
     */
    protected function listPropertyIds(array $values): array
    {
        return array_intersect_key($this->getPropertyIds(), array_fill_keys($values, null));
    }

    /**
     * Get all property terms by id, ordered by descendant total values.
     *
     * @return array Associative array of terms by ids.
     */
    protected function getUsedPropertyByIds(): array
    {
        static $properties;

        if (is_null($properties)) {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'property.id AS id',
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term'
                    // 'COUNT(value.id) AS total'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->innerJoin('property', 'value', 'value', 'property.id = value.property_id')
                ->groupBy('id')
                ->orderBy('COUNT(value.id)', 'DESC')
            ;
            $properties = $connection->executeQuery($qb)->fetchAllKeyValue();
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
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'CONCAT(vocabulary.prefix, "_", property.local_name) AS "key"',
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS "term"'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->innerJoin('property', 'value', 'value', 'property.id = value.property_id')
            ;
            $properties = $connection->executeQuery($qb)->fetchAllKeyValue();
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
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    'property.id AS id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
            ;
            $properties = array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
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
     * other ones are removed.
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
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS term',
                    'resource_class.id AS id'
                )
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
                ->orderBy('term', 'asc')
            ;
            $resourceClasses = array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
        }

        return $resourceClasses;
    }

    /**
     * Convert a list of terms into a list of resource template ids.
     *
     * @param array $values
     * @return array Only values that are labels are converted into ids, the
     * other ones are removed.
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
            $connection = $this->services->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'resource_template.label AS label',
                    'resource_template.id AS id'
                )
                ->from('resource_template', 'resource_template')
                ->orderBy('id', 'asc')
            ;
            $resourceTemplates = array_map('intval', $connection->executeQuery($qb)->fetchAllKeyValue());
        }

        return $resourceTemplates;
    }
}
