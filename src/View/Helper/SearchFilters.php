<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Mvc\Controller\Plugin\SearchResources;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters.
 *
 * Override core helper in order to add the urls without the filters and
 * resources without template, class, etc.
 *
 * @see \Omeka\View\Helper\SearchFilters
 */
class SearchFilters extends AbstractHelper
{
    use SearchFiltersTrait;

    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/search-filters';

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * The cleaned query without specific keys.
     *
     * @var array
     */
    protected $query;

    /**
     * The cleaned query.
     *
     * @var array
     */
    protected $searchCleanQuery;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var \AdvancedSearch\Query
     */
    protected $searchQuery;

    /**
     * Render filters from search query, with urls if needed (if set in theme).
     *
     * @see \Omeka\View\Helper\SearchFilters::__invoke()
     */
    public function __invoke($partialName = null, ?array $query = null): string
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $api = $plugins->get('api');
        $params = $plugins->get('params');
        $translate = $plugins->get('translate');
        $cleanQuery = $plugins->get('cleanQuery');

        $filters = [];
        $query ??= $params->fromQuery();

        $this->baseUrl = $url(null, [], true);
        $this->query = $cleanQuery($query);
        $this->searchCleanQuery = $this->query;
        $this->searchConfig = $this->query['__searchConfig'] ?? null;
        $this->searchQuery = $this->query['__searchQuery'] ?? null;

        unset(
            $this->query['page'],
            $this->query['offset'],
            $this->query['submit'],
            $this->query['__searchConfig'],
            $this->query['__searchQuery'],
            $this->query['__searchCleanQuery']
        );

        // This function fixes some forms that add an array level.
        // This function manages only one level, so check value when needed.
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

        // Normally, query is already cleaned.
        // TODO Remove checks of search keys, already done during event api.search.pre.
        foreach ($this->query as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            switch ($key) {
                // Fulltext
                case 'fulltext_search':
                    $filterLabel = $translate('Search full-text'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value;
                    break;

                // Search by class
                case 'resource_class_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('Class'); // @translate
                    foreach ($value as $subKey => $subValue) {
                        if (!is_numeric($subValue)) {
                            continue;
                        }
                        if ($subValue) {
                            try {
                                $filterValue = $translate($api->read('resource_classes', $subValue)->getContent()->label());
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown class'); // @translate
                            }
                        } else {
                            $filterValue = $translate('[none]'); // @translate
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                // Search values (by property or all)
                case 'property':
                    $queryTypesLabels = $this->getQueryTypesLabels();
                    /** @var \Common\Stdlib\EasyMeta $easyMeta */
                    $easyMeta = $plugins->get('easyMeta')();
                    // TODO The array may be more than zero when firsts are standard (see core too for inverse).
                    $index = 0;
                    foreach ($value as $subKey => $queryRow) {
                        if (!is_array($queryRow)
                            || empty($queryRow['type'])
                            || !isset(SearchResources::PROPERTY_QUERY['reciprocal'][$queryRow['type']])
                        ) {
                            continue;
                        }
                        $queryType = $queryRow['type'];
                        $value = $queryRow['text'] ?? null;
                        $noValue = in_array($queryType, SearchResources::PROPERTY_QUERY['value_none'], true);
                        if ($noValue) {
                            $value = null;
                        } elseif ((is_array($value) && !count($value))
                            || (!is_array($value) && !strlen((string) $value))
                        ) {
                            continue;
                        }
                        $joiner = $queryRow['joiner'] ?? null;
                        $queriedProperties = $queryRow['property'] ?? null;
                        // Properties may be an array with an empty value
                        // (any property) in advanced form, so remove empty
                        // strings from it, in which case the check should
                        // be skipped.
                        if (is_array($queriedProperties) && in_array('', $queriedProperties, true)) {
                            $queriedProperties = [];
                        }
                        if ($queriedProperties) {
                            $propertyLabel = [];
                            $properties = is_array($queriedProperties) ? $queriedProperties : [$queriedProperties];
                            foreach ($properties as $property) {
                                $label = $easyMeta->propertyLabel($property);
                                $propertyLabel[] = $label ? $translate($label) : $translate('Unknown property'); // @translate
                            }
                            $propertyLabel = implode(' ' . $translate('OR') . ' ', $propertyLabel);
                        } else {
                            $propertyLabel = $translate('[Any property]'); // @translate
                        }
                        $filterLabel = $noValue
                            ? $propertyLabel
                            : ($propertyLabel . ' ' . $queryTypesLabels[$queryType]);
                        if ($index > 0) {
                            if ($joiner === 'or') {
                                $filterLabel = $translate('OR') . ' ' . $filterLabel;
                            } elseif ($joiner === 'not') {
                                $filterLabel = $translate('EXCEPT') . ' ' . $filterLabel;
                            } else {
                                $filterLabel = $translate('AND') . ' ' . $filterLabel;
                            }
                        }
                        if (in_array($queryType, ['resq', 'nresq', 'lkq', 'nlkq']) && !$noValue) {
                            $value = array_map('urldecode', $value);
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $noValue
                            ? $queryTypesLabels[$queryType]
                            : implode(', ', $flatArray($value));
                        ++$index;
                    }
                    break;

                case 'search':
                    $filterLabel = $translate('Search'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value;
                    break;

                // Search resource template
                case 'resource_template_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('Template'); // @translate
                    foreach ($value as $subKey => $subValue) {
                        if (!is_numeric($subValue)) {
                            continue;
                        }
                        if ($subValue) {
                            try {
                                $filterValue = $api->read('resource_templates', $subValue)->getContent()->label();
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown template'); // @translate
                            }
                        } else {
                            $filterValue = $translate('[none]'); // @translate
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                // Search item set
                case 'item_set_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('In item set'); // @translate
                    foreach ($value as $subKey => $subValue) {
                        if (!is_numeric($subValue)) {
                            continue;
                        }
                        if ($subValue) {
                            try {
                                $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown item set'); // @translate
                            }
                        } else {
                            $filterValue = $translate('[none]'); // @translate
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                // Search not item set
                case 'not_item_set_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('Not in item set'); // @translate
                    foreach ($value as $subKey => $subValue) {
                        if (!is_numeric($subValue)) {
                            continue;
                        }
                        if ($subValue) {
                            try {
                                $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown item set'); // @translate
                            }
                        } else {
                            $filterValue = $translate('[none]'); // @translate
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                // Search user
                case 'owner_id':
                    $filterLabel = $translate('User'); // @translate
                    if ($value) {
                        try {
                            $filterValue = $api->read('users', $value)->getContent()->name();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown user'); // @translate
                        }
                    } else {
                        $filterValue = $translate('[none]'); // @translate
                    }
                    $filters[$filterLabel][$this->urlQuery($key)] = $filterValue;
                    break;

                // Search site
                case 'site_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('Site'); // @translate
                    foreach ($value as $subKey => $subValue) {
                        if (!is_numeric($subValue)) {
                            continue;
                        }
                        // Normally, "0" is moved to "in_sites".
                        if ($subValue) {
                            try {
                                $filterValue = $api->read('sites', ['id' => $subValue])->getContent()->title();
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown site'); // @translate
                            }
                        } else {
                            $filterValue = $translate('[none]'); // @translate
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                case 'in_sites':
                    $filterLabel = $translate('In a site'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('yes') // @translate
                        : $translate('no'); // @translate
                    break;

                case 'datetime':
                    $queryTypesDatetime = [
                        'lt' => $translate('before'), // @translate
                        'lte' => $translate('before or on'), // @translate
                        'eq' => $translate('on'), // @translate
                        'neq' => $translate('not on'), // @translate
                        'gte' => $translate('after or on'), // @translate
                        'gt' => $translate('after'), // @translate
                        'ex' => $translate('has any date / time'), // @translate
                        'nex' => $translate('has no date / time'), // @translate
                    ];

                    $value = $this->query['datetime'];
                    $engine = 0;
                    foreach ($value as $subKey => $queryRow) {
                        $joiner = $queryRow['joiner'];
                        $field = $queryRow['field'];
                        $type = $queryRow['type'];
                        $datetimeValue = $queryRow['value'];

                        $fieldLabel = $field === 'modified'
                            ? $translate('Modified') // @translate
                            : $translate('Created'); // @translate
                        $filterLabel = $fieldLabel . ' ' . $queryTypesDatetime[$type];
                        if ($engine > 0) {
                            $joiners = [
                                'or' => $translate('OR'), // @translate
                                'not' => $translate('EXCEPT'), // @translate
                                'and' => $translate('AND'), // @translate
                            ];
                            $filterLabel = ($joiners[$joiner] ?? $joiners['and']) . ' ' . $filterLabel;
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $datetimeValue;
                        ++$engine;
                    }
                    break;

                case 'is_public':
                    $filterLabel = $translate('Visibility'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('Public') // @translate
                        : $translate('Not public'); // @translate
                    break;

                case 'resource_class_term':
                    $filterLabel = $translate('Class'); // @translate
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'has_media':
                    $filterLabel = $translate('Media presence'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('Has media')  // @translate
                        : $translate('Has no media'); // @translate
                    break;

                case 'has_original':
                    $filterLabel = $translate('Has original'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('yes') // @translate
                        : $translate('no'); // @translate
                    break;

                case 'has_thumbnails':
                    $filterLabel = $translate('Has thumbnails'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('yes') // @translate
                        : $translate('no'); // @translate
                    break;

                case 'media_types':
                    $filterLabel = $translate('Media types'); // @translate
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'id':
                    $filterLabel = $translate('ID'); // @translate
                    // Avoid a strict type issue, so convert ids as string.
                    $ids = $value;
                    if (is_int($ids)) {
                        $ids = [(string) $ids];
                    } elseif (is_string($ids)) {
                        $ids = strpos($ids, ',') === false ? [$ids] : explode(',', $ids);
                    } elseif (!is_array($ids)) {
                        $ids = [];
                    }
                    $ids = array_map('trim', $ids);
                    $ids = array_filter($ids, 'strlen');
                    $value = $ids;
                    // TODO Keep style like omeka?
                    // $filters[$filterLabel][$this->urlQuery($key)] = implode(', ', $ids);
                    foreach ($value as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                default:
                    break;
            }
        }

        if ($this->searchConfig) {
            $query['__searchConfig'] = $this->searchConfig;
            $query['__searchQuery'] = $this->searchQuery;
            $query['__searchCleanQuery'] = $this->searchCleanQuery;
        }

        // Run event for modules, included AdvancedSearch.
        $result = $view->trigger(
            'view.search.filters',
            ['filters' => $filters, 'query' => $query, 'baseUrl' => $this->baseUrl],
            true
        );
        $filters = $result['filters'];

        return $view->partial($partialName, [
            'filters' => $filters,
        ]);
    }

    /**
     * Get url of the query without the specified key and subkey.
     *
     * @param string|int $key
     * @param string|int|null $subKey
     * @return string
     *
     * Copy:
     * @see \AdvancedSearch\View\Helper\SearchFilters::urlQuery()
     * @see \AdvancedSearch\View\Helper\SearchingFilters::urlQuery()
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
}
