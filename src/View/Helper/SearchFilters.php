<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters.
 *
 * Override core helper in order to add the urls without the filters.
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
     * Render filters from search query, with urls if needed (if set in theme).
     */
    public function __invoke($partialName = null, array $query = null): string
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $view = $this->getView();
        $translate = $view->plugin('translate');

        $filters = [];
        $api = $view->api();
        $query = $query ?? $view->params()->fromQuery();

        $this->baseUrl = $this->view->url(null, [], true);
        $this->query = $query;
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
        ];

        foreach ($this->query as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            switch ($key) {
                // Fulltext
                case 'fulltext_search':
                    $filterLabel = $translate('Search full-text');
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
                        try {
                            $filterValue = $translate($api->read('resource_classes', $subValue)->getContent()->label());
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown class');
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                // Search values (by property or all)
                case 'property':
                    $index = 0;
                    foreach ($value as $subKey => $queryRow) {
                        if (!(is_array($queryRow)
                            && array_key_exists('property', $queryRow)
                            && array_key_exists('type', $queryRow)
                        )) {
                            continue;
                        }
                        $propertyId = $queryRow['property'];
                        $queryType = $queryRow['type'];
                        $joiner = $queryRow['joiner'] ?? null;
                        $value = $queryRow['text'] ?? null;

                        if (!isset($queryTypes[$queryType])) {
                            continue;
                        }
                        if (!$value && $queryType !== 'nex' && $queryType !== 'ex') {
                            continue;
                        }
                        if ($propertyId) {
                            if (is_numeric($propertyId)) {
                                try {
                                    $property = $api->read('properties', $propertyId)->getContent();
                                } catch (NotFoundException $e) {
                                    $property = null;
                                }
                            } else {
                                $property = $api->searchOne('properties', ['term' => $propertyId])->getContent();
                            }

                            if ($property) {
                                $propertyLabel = $translate($property->label());
                            } else {
                                $propertyLabel = $translate('Unknown property');
                            }
                        } else {
                            $propertyLabel = $translate('[Any property]');
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

                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $value;
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
                        try {
                            $filterValue = $api->read('resource_templates', $subValue)->getContent()->label();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown template');
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
                        try {
                            $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown item set');
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                // Search user
                case 'owner_id':
                    $filterLabel = $translate('User');
                    try {
                        $filterValue = $api->read('users', $value)->getContent()->name();
                    } catch (NotFoundException $e) {
                        $filterValue = $translate('Unknown user');
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
                        try {
                            $filterValue = $api->read('sites', ['id' => $subValue])->getContent()->title();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown site');
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
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
}
