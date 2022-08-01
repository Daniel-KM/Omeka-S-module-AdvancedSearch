<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Omeka\Api\Adapter\ResourceAdapter;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters.
 *
 * Override core helper in order to add the urls without the filters and
 * resources without template, class, etc.
 *
 * @see \Omeka\View\Helper\SearchFilters
 */
class SearchFilters extends \Omeka\View\Helper\SearchFilters
{
    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var array
     */
    protected $query;

    /**
     * @var ResourceAdapter
     */
    protected $resourceAdapter;

    public function __construct(ResourceAdapter $resourceAdapter)
    {
        $this->resourceAdapter = $resourceAdapter;
    }

    /**
     * Render filters from search query, with urls if needed (if set in theme).
     */
    public function __invoke($partialName = null, array $query = null): string
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $api = $plugins->get('api');
        $translate = $plugins->get('translate');

        $filters = [];
        $query = $query ?? $view->params()->fromQuery();

        $this->baseUrl = $this->view->url(null, [], true);
        $searchResourcesListener = new \AdvancedSearch\Listener\SearchResourcesListener();
        $this->query = $searchResourcesListener->normalizeQueryDateTime($query);
        unset(
            $this->query['page'],
            $this->query['offset'],
            $this->query['submit'],
            $this->query['__searchConfig'],
            $this->query['__searchQuery']
        );

        $queryTypes = [
            'eq' => $translate('is exactly'), // @translate
            'neq' => $translate('is not exactly'), // @translate
            'in' => $translate('contains'), // @translate
            'nin' => $translate('does not contain'), // @translate
            'sw' => $translate('starts with'), // @translate
            'nsw' => $translate('does not start with'), // @translate
            'ew' => $translate('ends with'), // @translate
            'new' => $translate('does not end with'), // @translate
            'res' => $translate('is resource with ID'), // @translate
            'nres' => $translate('is not resource with ID'), // @translate
            'ex' => $translate('has any value'), // @translate
            'nex' => $translate('has no values'), // @translate
            'exs' => $translate('has a single value'), // @translate
            'exm' => $translate('has multiple values'), // @translate
            'nexm' => $translate('has not multiple values'), // @translate
            'lex' => $translate('is a linked resource'), // @translate
            'nlex' => $translate('is not a linked resource'), // @translate
            'lres' => $translate('is linked with resource with ID'), // @translate
            'nlres' => $translate('is not linked with resource with ID'), // @translate
        ];

        $withoutValueQueryTypes = [
            'ex',
            'nex',
            'exs',
            'exm',
            'nexm',
            'lex',
            'nlex',
        ];

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
                    $filterLabel = $translate('Class');
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
                    $easyMeta = $plugins->get('easyMeta');
                    // TODO The array may be more than zero when firsts are standard (see core too for inverse).
                    $index = 0;
                    foreach ($value as $subKey => $queryRow) {
                        if (!(is_array($queryRow)
                            && array_key_exists('type', $queryRow)
                        )) {
                            continue;
                        }
                        $queryType = $queryRow['type'];
                        if (!isset($queryTypes[$queryType])) {
                            continue;
                        }
                        $value = $queryRow['text'] ?? null;
                        if (in_array($queryType, $withoutValueQueryTypes, true)) {
                            $value = null;
                        } elseif ((is_array($value) && !count($value)) || !strlen((string) $value)) {
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
                                $label = $easyMeta->propertyLabels($property);
                                $propertyLabel[] = $label ? $translate($label) : $translate('Unknown property'); // @translate
                            }
                            $propertyLabel = implode(' ' . $translate('OR') . ' ', $propertyLabel);
                        } else {
                            $propertyLabel = $translate('[Any property]'); // @translate
                        }
                        $filterLabel = $propertyLabel . ' ' . $queryTypes[$queryType];
                        if ($index > 0) {
                            if ($joiner === 'or') {
                                $filterLabel = $translate('OR') . ' ' . $filterLabel;
                            } elseif ($joiner === 'not') {
                                $filterLabel = $translate('EXCEPT') . ' ' . $filterLabel;
                            } else {
                                $filterLabel = $translate('AND') . ' ' . $filterLabel;
                            }
                        }

                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = implode(', ', $flatArray($value));
                        ++$index;
                    }
                    break;

                case 'search':
                    $filterLabel = $translate('Search');
                    $filters[$filterLabel][$this->urlQuery($key)] = $value;
                    break;

                // Search resource template
                case 'resource_template_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('Template');
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
                    $filterLabel = $translate('Item set');
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
                    $filterLabel = $translate('User');
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
                    $filterLabel = $translate('Site');
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
                        'gt' => $translate('after'),
                        'gte' => $translate('after or on'),
                        'eq' => $translate('on'),
                        'neq' => $translate('not on'),
                        'lte' => $translate('before or on'),
                        'lt' => $translate('before'),
                        'ex' => $translate('has any date / time'),
                        'nex' => $translate('has no date / time'),
                    ];

                    $value = $this->query['datetime'];
                    $engine = 0;
                    foreach ($value as $subKey => $queryRow) {
                        $joiner = $queryRow['joiner'];
                        $field = $queryRow['field'];
                        $type = $queryRow['type'];
                        $datetimeValue = $queryRow['value'];

                        $fieldLabel = $field === 'modified' ? $translate('Modified') : $translate('Created');
                        $filterLabel = $fieldLabel . ' ' . $queryTypesDatetime[$type];
                        if ($engine > 0) {
                            if ($joiner === 'or') {
                                $filterLabel = $translate('OR') . ' ' . $filterLabel;
                            } elseif ($joiner === 'not') {
                                $filterLabel = $translate('EXCEPT') . ' ' . $filterLabel; // @translate
                            } else {
                                $filterLabel = $translate('AND') . ' ' . $filterLabel;
                            }
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $datetimeValue;
                        ++$engine;
                    }
                    break;

                case 'is_public':
                    $filters[$translate('Visibility')][$this->urlQuery($key)] = $value
                        ? $translate('Private')
                        : $translate('Public');
                    break;

                case 'resource_class_term':
                    $filterLabel = $translate('Class'); // @translate
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'has_media':
                    $filterLabel = $translate('Has media'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('yes') // @translate
                        : $translate('no'); // @translate
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

                default:
                    break;
            }
        }

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
     * Get one or more property ids by JSON-LD terms or by numeric ids.
     *
     * @param array|int|string|null $termsOrIds One or multiple ids or terms.
     * @return int[] The property ids matching terms or ids, or all properties
     * by terms.
     */
    protected function getPropertyIds($termsOrIds = null): array
    {
        if (is_scalar($termsOrIds)) {
            $termsOrIds = [$termsOrIds];
        }
        return $this->view->easyMeta()->propertyIds($termsOrIds);
    }
}
