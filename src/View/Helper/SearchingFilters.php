<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Mvc\Controller\Plugin\SearchResources;
use AdvancedSearch\Query;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters for the advanced search response.
 */
class SearchingFilters extends AbstractHelper
{
    use SearchFiltersTrait;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * The cleaned query.
     *
     * @var array
     */
    protected $query;

    /**
     * Render filters from advanced search query.
     *
     * Use the core helper searchFilters() in order to append the current config.
     * It allows to get specific arguments used by the form.
     *
     * @todo Should use the form adapter (but only main form is really used).
     * @see \AdvancedSearch\FormAdapter\AbstractFormAdapter
     *
     * @uses \Omeka\View\Helper\SearchFilters
     * @return self|string Return self when no config or no query is set, else
     * process search filters and return string via helper SearchFilters.
     */
    public function __invoke(?SearchConfigRepresentation $searchConfig = null, ?Query $query = null, array $options = [])
    {
        if (!$searchConfig || !$query) {
            return $this;
        }

        $view = $this->getView();
        $template = $options['template'] ?? null;

        // TODO Use the managed query to get a clean query.
        $params = $view->params();
        $request = $params->fromQuery();

        // Don't display the current item set argument on item set page.
        $currentItemSet = (int) $view->params()->fromRoute('item-set-id');
        if ($currentItemSet) {
            foreach ($request as $key => $value) {
                // TODO Use the form adapter to get the real arg for the item set.
                if ($value && $key === 'item_set_id' || $key === 'item_set') {
                    if (is_array($value)) {
                        // Check if this is not a sub array (item_set[id][]).
                        $first = reset($value);
                        if (is_array($first)) {
                            $value = $first;
                        }
                        $pos = array_search($currentItemSet, $value);
                        if ($pos !== false) {
                            if (count($request[$key]) <= 1) {
                                unset($request[$key]);
                            } else {
                                unset($request[$key][$pos]);
                            }
                        }
                    } elseif ((int) $value === $currentItemSet) {
                        unset($request[$key]);
                    }
                    break;
                }
            }
        }

        $request['__searchConfig'] = $searchConfig;
        $request['__searchQuery'] = $query;

        // The search filters trigger event "'view.search.filters", that calls
        // the method filterSearchingFilter(). This process allows to use the
        // standard filters.
        return $view->searchFilters($template, $request);
    }

    /**
     * Manage specific arguments of the module searching form.
     *
     * @todo Should use the form adapter (but only main form is really used).
     * @see \AdvancedSearch\FormAdapter\AbstractFormAdapter
     *
     * @todo Use Query instead of query? The Query is available in the request.
     *
     * @var array $query The query is the cleaned query.
     * @return array The updated filters.
     */
    public function filterSearchingFilters(SearchConfigRepresentation $searchConfig, array $query, array $filters): array
    {
        // Warning: unlike plugin helper, view helper api() cannot use options.
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $api = $plugins->get('api');
        $translate = $plugins->get('translate');

        $this->baseUrl = $url(null, [], true);
        $this->query = $query;

        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        $availableFields = empty($searchAdapter)
            ? []
            : $searchAdapter->setSearchEngine($searchEngine)->getAvailableFields();
        $searchFormSettings = $searchConfig->setting('form') ?: [];

        // Manage all fields, included those not in the form in order to support
        // queries for long term. But use labels set in the form if any.
        $formFieldLabels = array_column($searchFormSettings['filters'] ?? [], 'label', 'field');
        $availableFieldLabels = array_combine(array_keys($availableFields), array_column($availableFields ?? [], 'label'));
        $fieldLabels = array_replace($availableFieldLabels, array_filter($formFieldLabels));

        // @see \AdvancedSearch\FormAdapter\AbstractFormAdapter::toQuery()
        // This function manages only one level, so check value when needed.
        // TODO Simplify queries (or make clear distinction between standard and old way).
        $flatArray = function ($value): array {
            if (!is_array($value)) {
                return [$value];
            }
            $firstKey = key($value);
            if (is_numeric($firstKey)) {
                return $value;
            }
            return is_array(reset($value)) ? $value[$firstKey] : [$value[$firstKey]];
        };

        $flatArrayValueResourceIds = function ($value, array $titles): array {
            if (is_array($value)) {
                $firstKey = key($value);
                if (is_numeric($firstKey)) {
                    $values = $value;
                } else {
                    $values = is_array(reset($value)) ? $value[$firstKey] : [$value[$firstKey]];
                }
            } else {
                $values = [$value] ;
            }
            $values = array_unique($values);
            $values = array_combine($values, $values);
            return array_replace($values, $titles);
        };

        foreach ($this->query as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            switch ($key) {
                case 'q':
                    $filterLabel = $translate('Query'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value;
                    break;

                    // Resource type is "items", "item_sets", etc.
                case 'resource_type':
                    $resourceTypes = [
                        'items' => $translate('Items'),
                        'item_sets' => $translate('Item sets'),
                    ];
                    $filterLabel = $translate('Resource type'); // @translate
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $resourceTypes[$subValue] ?? $subValue;
                    }
                    break;

                    // Resource id.
                case 'id':
                    $filterLabel = $translate('Resource id'); // @translate
                    foreach (array_filter(array_map('intval', $flatArray($value))) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'site':
                    $filterLabel = $translate('Site');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($flatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('sites', $subValue)->getContent()->title();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown site');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'owner':
                    $filterLabel = $translate('User');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($flatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('users', $subValue)->getContent()->name();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown user');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'class':
                    $filterLabel = $translate('Class'); // @translate
                    $isId = is_array($value) && key($value) === 'id';
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        if (is_numeric($subValue)) {
                            try {
                                $filterValue = $translate($api->read('resource_classes', $subValue)->getContent()->label());
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown class'); // @translate
                            }
                        } else {
                            $filterValue = $translate($api->searchOne('resource_classes', ['term' => $subValue])->getContent());
                            $filterValue = $filterValue ? $filterValue->label() : $translate('Unknown class');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'template':
                    $filterLabel = $translate('Template'); // @translate
                    $isId = is_array($value) && key($value) === 'id';
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        if (is_numeric($subValue)) {
                            try {
                                $filterValue = $translate($api->read('resource_templates', $subValue)->getContent()->label());
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown template'); // @translate
                            }
                        } else {
                            $filterValue = $translate($api->searchOne('resource_templates', ['label' => $subValue])->getContent());
                            $filterValue = $filterValue ? $filterValue->label() : $translate('Unknown template');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'item_set':
                    $filterLabel = $translate('Item set');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($flatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown item set');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'filter':
                    $value = array_filter($value, 'is_array');
                    if (!count($value)) {
                        break;
                    }

                    $queryTypesLabels = $this->getQueryTypesLabels();

                    // Get all resources titles with one query.
                    $vrTitles = [];
                    $vrIds = [];
                    foreach ($value as $queryRow) {
                        if (is_array($queryRow)
                            && isset($queryRow['type'])
                            && !empty($queryRow['value'])
                            && in_array($queryRow['type'], SearchResources::PROPERTY_QUERY['value_subject'])
                        ) {
                            is_array($queryRow['value'])
                                ? $vrIds = array_merge($vrIds, array_values($queryRow['value']))
                                : $vrIds[] = $queryRow['value'];
                        }
                    }
                    $vrIds = array_unique(array_filter(array_map('intval', $vrIds)));
                    if ($vrIds) {
                        // Currently, "resources" cannot be searched, so use adapter
                        // directly. Rights are managed.
                        /** @var \Doctrine\ORM\EntityManager $entityManager */
                        $services = $this->getServiceLocator();
                        $entityManager = $services->get('Omeka\EntityManager');
                        $qb = $entityManager->createQueryBuilder();
                        $qb
                            ->select('omeka_root.id', 'omeka_root.title')
                            ->from(\Omeka\Entity\Resource::class, 'omeka_root')
                            ->where($qb->expr()->in('omeka_root.id', ':ids'))
                            ->setParameter('ids', $vrIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
                        $vrTitles = array_column($qb->getQuery()->getScalarResult(), 'title', 'id');
                    }

                    // To get the name of the advanced fields, a loop should be done for now.
                    $searchFormAdvancedLabels = [];
                    foreach ($searchFormSettings['filters'] as $searchFormFilter) {
                        if ($searchFormFilter['type'] === 'Advanced') {
                            $searchFormAdvancedLabels = array_column($searchFormFilter['fields'], 'label', 'value');
                            break;
                        }
                    }
                    $fieldFiltersLabels = array_replace($fieldLabels, array_filter($searchFormAdvancedLabels));

                    $index = 0;
                    foreach ($value as $subKey => $queryRow) {
                        // Default query type is "in", unlike standard search.
                        $queryType = $queryRow['type'] ?? 'in';
                        if (!isset(SearchResources::PROPERTY_QUERY['reciprocal'][$queryType])) {
                            continue;
                        }

                        $joiner = $queryRow['join'] ?? 'and';
                        $value = $queryRow['value'] ?? '';

                        $isWithoutValue = in_array($queryType, SearchResources::PROPERTY_QUERY['value_none'], true);

                        // A value can be an array with types "list" and "nlist".
                        if (!is_array($value)
                            && !strlen((string) $value)
                            && !$isWithoutValue
                        ) {
                            continue;
                        }

                        if ($isWithoutValue) {
                            $value = '';
                        }

                        // The field is currently always single: use multi-fields else.
                        // TODO Support multi-fields.
                        $queryField = $queryRow['field'] ?? '';
                        /*
                        $fieldLabel = $queryField
                            ? $fieldFiltersLabels[$queryField] ?? $translate('Unknown field') // @ translate
                            : $translate('[Any field]'); // @ translate
                        */
                        // Support default solr index names for compatibility
                        // of custom themes.
                        if ($queryField) {
                            if (isset($fieldFiltersLabels[$queryField])) {
                                $fieldLabel = $fieldFiltersLabels[$queryField];
                            } elseif (strpos($queryField, '_')) {
                                $fieldLabel = $fieldFiltersLabels[strtok($queryField, '_') . ':' . strtok('_')] ?? $translate('Unknown field'); // @translate
                            } else {
                                $fieldLabel = $translate('Unknown field'); // @translate
                            }
                        } else {
                            $fieldLabel = $translate('[Any field]'); // @translate
                        }

                        $filterLabel = $fieldLabel . ' ' . $queryTypesLabels[$queryType];
                        if ($index > 0) {
                            if ($joiner === 'or') {
                                $filterLabel = $translate('OR') . ' ' . $filterLabel;
                            } elseif ($joiner === 'not') {
                                $filterLabel = $translate('EXCEPT') . ' ' . $filterLabel; // @translate
                            } else {
                                $filterLabel = $translate('AND') . ' ' . $filterLabel;
                            }
                        }

                        $vals = in_array($queryType, SearchResources::PROPERTY_QUERY['value_subject'])
                            ? $flatArrayValueResourceIds($value, $vrTitles)
                            : $flatArray($value);
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = implode(', ', $vals);

                        ++$index;
                    }
                    break;

                default:
                    // Append only fields that are not yet processed somewhere
                    // else, included searchFilters helper.
                    if (isset($fieldLabels[$key]) && !isset($filters[$fieldLabels[$key]])) {
                        if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
                            $filterLabel = $fieldLabels[$key];
                            if (array_key_exists('from', $value) && array_key_exists('to', $value)) {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('from %s to %s'), $value['from'], $value['to']); // @translate
                            } elseif (array_key_exists('from', $value)) {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('since %s'), $value['from']); // @translate
                            } elseif (array_key_exists('to', $value)) {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('until %s'), $value['to']); // @translate
                            }
                            break;
                        }

                        $filterLabel = $fieldLabels[$key];
                        foreach (array_filter(array_map('trim', array_map('strval', $flatArray($value))), 'strlen') as $subKey => $subValue) {
                            $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                        }
                    }
                    break;
            }
        }

        return $filters;
    }

    /**
     * Get the url of the query without the specified key and subkey.
     *
     * @param string|int $key
     * @param string|int|null $subKey
     * @return string
     */
    protected function urlQuery($key, $subKey = null): string
    {
        $newQuery = $this->query;
        if (is_null($subKey) || !is_array($newQuery[$key]) || count($newQuery[$key]) <= 1) {
            unset($newQuery[$key]);
        } else {
            unset($newQuery[$key][$subKey]);
        }
        return $newQuery
            ? $this->baseUrl . '?' . http_build_query($newQuery, '', '&', PHP_QUERY_RFC3986)
            : $this->baseUrl;
    }

    /**
     * Get url of the query without specified key and subkey for special fields.
     *
     * @todo Remove this special case.
     *
     * @param string|int $key
     * @param string|int|null $subKey
     * @return string
     */
    protected function urlQueryId($key, $subKey): string
    {
        $newQuery = $this->query;
        if (!is_array($newQuery[$key]) || !is_array($newQuery[$key]['id']) || count($newQuery[$key]['id']) <= 1) {
            unset($newQuery[$key]);
        } else {
            unset($newQuery[$key]['id'][$subKey]);
        }
        return $newQuery
            ? $this->baseUrl . '?' . http_build_query($newQuery, '', '&', PHP_QUERY_RFC3986)
            : $this->baseUrl;
    }
}
