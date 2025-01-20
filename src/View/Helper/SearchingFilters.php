<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;

/**
 * View helper for rendering search filters for the advanced search response.
 *
 * @deprecated Use $searchConfig->renderSearchFilters() instead.
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
     *
     * @deprecated Use $searchConfig->renderSearchFilters() instead.
     */
    public function __invoke(?SearchConfigRepresentation $searchConfig = null, ?Query $query = null, array $options = [])
    {
        if (!$searchConfig || !$query) {
            return $this;
        }

        $view = $this->getView();
        $view->logger()->warn('The use of the view helper searchingFilters() is deprecated. Use $searchConfig->renderSearchFilters() instead.'); // @translate

        return $searchConfig->renderSearchFilters($query, $options);
    }

    /**
     * Manage specific arguments of the module searching form.
     *
     * For internal use only.
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
        /**
         * @var \Omeka\View\Helper\Api $api
         * @var \Common\Stdlib\EasyMeta $easyMeta
         *
         * Warning: unlike plugin helper, view helper api() cannot use options.
         */
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $api = $plugins->get('api');
        $translate = $plugins->get('translate');
        $easyMeta = $plugins->get('easyMeta')();

        $processed = $query['__processed'] ?? [];

        $this->baseUrl = $url(null, [], true);
        $this->query = $query;

        $engineAdapter = $searchConfig->engineAdapter();
        $availableFields = $engineAdapter
            ? $engineAdapter->getAvailableFields()
            : [];
        $searchFormSettings = $searchConfig->setting('form') ?: [];

        // Manage all fields, included those not in the form in order to support
        // queries for long term. But use labels set in the form if any.
        $formFieldLabels = array_column($searchFormSettings['filters'] ?? [], 'label', 'field');
        $availableFieldLabels = array_combine(array_keys($availableFields), array_column($availableFields ?? [], 'label'));
        $fieldLabels = array_replace($availableFieldLabels, array_filter($formFieldLabels));

        $skip = [
            'page' => null,
            'offset' => null,
            'submit' => null,
            '__processed' => null,
            '__original_query' => null,
            '__searchConfig' => null,
            '__searchQuery' => null,
        ];

        // id is overridden.
        unset($processed['id']);

        $remainingQueryKeys = array_diff_key($this->query, $skip, $processed);

        foreach ($remainingQueryKeys as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            switch ($key) {
                case 'q':
                    $filterLabel = $translate('Query'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value;
                    break;

                // Resource type is the api name ("items", "item_sets", etc.).
                case 'resource_type':
                    $filterLabel = $translate('Resource type'); // @translate
                    foreach ($this->checkAndFlatArray($value) as $subKey => $subValue) {
                        $subValueLabel = $easyMeta->resourceLabelPlural($subValue);
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValueLabel ? ucfirst($translate($subValueLabel)) : $subValue;
                    }
                    break;

                // Resource id.
                // Override standard search filters.
                case 'id':
                    $filterLabel = $translate('ID'); // @translate
                    foreach (array_filter(array_map('intval', $this->checkAndFlatArray($value))) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'site':
                    $filterLabel = $translate('Site');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($this->checkAndFlatArray($value), 'is_numeric') as $subKey => $subValue) {
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
                    foreach (array_filter($this->checkAndFlatArray($value), 'is_numeric') as $subKey => $subValue) {
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
                    foreach ($this->checkAndFlatArray($value) as $subKey => $subValue) {
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
                    foreach ($this->checkAndFlatArray($value) as $subKey => $subValue) {
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
                    foreach (array_filter($this->checkAndFlatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown item set');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'item_sets_tree':
                    $filterLabel = $translate('Item sets tree'); // @translate
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($this->checkAndFlatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown item set');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'thesaurus':
                    $is = $translate('is'); // @translate
                    foreach ($query['thesaurus'] as $term => $itemIds) {
                        if (!$itemIds) {
                            continue;
                        }
                        $propertyLabel = $easyMeta->propertyLabel($term);
                        if (!$propertyLabel) {
                            continue;
                        }
                        $filterLabel = $propertyLabel . ' ' . $is;
                        $itemTitles = $api->search('items', ['id' => $itemIds, 'return_scalar' => 'title'])->getContent();
                        if ($itemTitles) {
                            foreach ($itemTitles as $itemId => $itemTitle) {
                                $filters[$filterLabel][$this->urlQuery($key, $itemId)] = $itemTitle;
                            }
                        } else {
                            $filters[$filterLabel][$this->urlQuery($key)] = '–';
                        }
                    }
                    break;

                // Bypass filters processed by searchFilters.
                case array_key_exists($key, $processed):
                    break;

                default:
                    // Append only fields that are not yet processed somewhere
                    // else, included searchFilters helper.
                    if (isset($fieldLabels[$key])
                        && !isset($filters[$fieldLabels[$key]])
                    ) {
                        // Manage ranges.
                        if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
                            $valueFrom = $value['from'] ?? '';
                            $valueTo = $value['to'] ?? '';
                            $filterLabel = $fieldLabels[$key];
                            if ($valueFrom !== '' && $valueTo !== '') {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('from %s to %s'), $valueFrom, $valueTo);// @translate
                            } elseif ($valueFrom !== '') {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('since %s'), $valueFrom); // @translate
                            } elseif ($valueTo !== '') {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('until %s'), $valueTo); // @translate
                            }
                        }
                        // Else manage raw label/value.
                        else {
                            $filterLabel = $fieldLabels[$key];
                            foreach (array_filter(array_map('trim', array_map('strval', $this->checkAndFlatArray($value))), 'strlen') as $subKey => $subValue) {
                                $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                            }
                        }
                    }
                    break;
            }
        }

        // TODO Reorder filters according to query for better ui.

        return $filters;
    }

    /**
     * Flat a max two levels array, like the one in properties and filters.
     *
     * The array should be an associative array.
     *
     * This function fixes some forms that add an array level.
     * This function manages only one level, so check value when needed.
     *
     * @see \AdvancedSearch\FormAdapter\TraitFormAdapterClassic::toQuery()
     */
    protected function checkAndFlatArray($value): array
    {
        if (!is_array($value)) {
            return [$value];
        }
        $firstKey = key($value);
        if (is_numeric($firstKey)) {
            return $value;
        }
        return is_array(reset($value))
            ? $value[$firstKey]
            : [$value[$firstKey]];
    }

    /**
     * Get the url of the query without the specified key and subkey.
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
