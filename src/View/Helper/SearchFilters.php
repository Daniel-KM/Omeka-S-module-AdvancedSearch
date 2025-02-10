<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Stdlib\SearchResources;
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
     * The cleaned query without specific keys to build new queries.
     *
     * @var array
     */
    protected $queryForUrl;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * The cleaned query.
     *
     * @var array
     */
    protected $searchCleanQuery;

    /**
     * @var array
     */
    protected $searchProcesseds;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var array
     */
    protected $searchIndex = [
        'aliases' => [],
        'query_args' => [],
    ];

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

        /**
         * Don't use construct() for compatibility with Omeka helper.
         *
         * @var \Omeka\View\Helper\Url $url
         * @var \Omeka\View\Helper\Api $api
         * @var \Omeka\View\Helper\Params $params
         * @var \Laminas\I18n\View\Helper\Translate $translate
         * @var \Common\Stdlib\EasyMeta $easyMeta
         * @var \Omeka\View\Helper\DataType $dataTypeHelper
         * @var \AdvancedSearch\Stdlib\SearchResources $searchResources
         */
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $api = $plugins->get('api');
        $params = $plugins->get('params');
        $translate = $plugins->get('translate');
        $this->easyMeta = $plugins->get('easyMeta')();
        $dataTypeHelper = $plugins->get('dataType');
        $searchResources = $plugins->get('searchResources')();

        $filters = [];
        $query ??= $params->fromQuery();
        unset($query['submit']);

        // The cleaned query means expanded query too.
        $this->query = $searchResources->cleanQuery($query);

        $this->baseUrl = $url(null, [], true);
        $this->searchCleanQuery = $this->query;
        $this->searchProcesseds = [];
        $this->searchConfig = $this->query['__searchConfig'] ?? null;
        $this->searchQuery = $this->query['__searchQuery'] ?? null;

        unset(
            $this->query['page'],
            $this->query['offset'],
            $this->query['submit'],
            $this->query['__searchConfig'],
            $this->query['__searchQuery'],
            $this->query['__original_query'],
        );

        // Do not check updated filters.
        $replacedKeyValues = array_column(
            array_filter($this->query['filter'] ?? [], fn ($v) => !isset($v['replace_filter_key'])),
            'replaced_value',
            'replaced_field'
        );

        // To build tje new search filters urls, the keys should be the original
        // ones, so clean the key filter and rebuild original query from the
        // cleaned query.
        $this->prepareQueryForUrl();

        // Normally, query is already cleaned.
        // TODO Remove checks of search keys, already done during event api.search.pre.
        foreach ($this->query as $key => $value) {
            $this->searchProcesseds[$key] = $value;
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
                    // TODO The array may be more than zero when firsts are standard (see core too for inverse).
                    $index = 0;
                    foreach ($value as $subKey => $queryRow) {
                        if (!is_array($queryRow)
                            || empty($queryRow['type'])
                            || !isset(SearchResources::FIELD_QUERY['reciprocal'][$queryRow['type']])
                        ) {
                            continue;
                        }
                        $queryType = $queryRow['type'];
                        $text = $queryRow['text'] ?? null;
                        $noValue = in_array($queryType, SearchResources::FIELD_QUERY['value_none'], true);
                        if ($noValue) {
                            $text = null;
                        } elseif ((is_array($text) && !count($text))
                            || (!is_array($text) && !strlen((string) $text))
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
                                $label = $this->easyMeta->propertyLabel($property);
                                $propertyLabel[] = $label ? $translate($label) : $translate('Unknown property'); // @translate
                            }
                            $propertyLabel = implode(' ' . $translate('OR') . ' ', array_unique($propertyLabel));
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
                        // If this is a data type query, convert the value to
                        // the data type's label.
                        // This is a single value, so don't use improved 'dtp'/'ndtp'.
                        if (in_array($queryType, ['dt', 'ndt'])) {
                            $text = $dataTypeHelper->getLabel($text);
                        } elseif (in_array($queryType, SearchResources::FIELD_QUERY['value_single_array_or_string'])) {
                            $text = array_map('urldecode', $text);
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $noValue
                            ? $queryTypesLabels[$queryType]
                            : implode(', ', $this->checkAndFlatArray($text));
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

                case 'filter':
                    $value = array_filter($value, 'is_array');
                    if (count($value)) {
                        $filters = $this->searchFilterFilter($value, $filters);
                    }
                    break;

                case 'datetime':
                    $dateTimeQueryTypes = [
                        '<' => '<',
                        '≤' => '≤',
                        '=' => '=',
                        '≠' => '≠',
                        '≥' => '≥',
                        '>' => '>',
                        'lt' => '<',
                        'lte' => '≤',
                        'eq' => '=',
                        'neq' => '≠',
                        'gte' => '≥',
                        'gt' => '>',
                        'ex' => 'ex',
                        'nex' => 'nex',
                    ];
                    $queryTypesDatetime = [
                        '<' => $translate('before'), // @translate
                        '≤' => $translate('before or on'), // @translate
                        '=' => $translate('on'), // @translate
                        '≠' => $translate('not on'), // @translate
                        '≥' => $translate('after or on'), // @translate
                        '>' => $translate('after'), // @translate
                        'ex' => $translate('has any date / time'), // @translate
                        'nex' => $translate('has no date / time'), // @translate
                    ];

                    $value = $this->query['datetime'];
                    $engineIndex = 0;
                    foreach ($value as $subKey => $queryRow) {
                        $joiner = $queryRow['join'];
                        $field = $queryRow['field'];
                        $type = $queryRow['type'];
                        $datetimeValue = $queryRow['val'];
                        $type = $dateTimeQueryTypes[$type];

                        $fieldLabel = $field === 'modified'
                            ? $translate('Modified') // @translate
                            : $translate('Created'); // @translate
                        $filterLabel = $fieldLabel . ' ' . $queryTypesDatetime[$type];
                        if ($engineIndex > 0) {
                            $joiners = [
                                'or' => $translate('OR'), // @translate
                                'not' => $translate('EXCEPT'), // @translate
                                'and' => $translate('AND'), // @translate
                            ];
                            $filterLabel = ($joiners[$joiner] ?? $joiners['and']) . ' ' . $filterLabel;
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $datetimeValue;
                        ++$engineIndex;
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
                    foreach ($this->checkAndFlatArray($value) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'is_dynamic':
                    $filterLabel = $translate('Is dynamic'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('yes') // @translate
                        : $translate('no'); // @translate
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

                case 'has_asset':
                    $filterLabel = $translate('Has asset as thumbnail'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $value
                        ? $translate('yes') // @translate
                        : $translate('no'); // @translate
                    break;

                case 'asset_id':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $filterLabel = $translate('Thumbnail'); // @translate
                    foreach ($value as $subKey => $subValue) {
                        if (!is_numeric($subValue)) {
                            continue;
                        }
                        if ($subValue) {
                            $filterValue = sprintf($translate('#%d'), $subValue); // @translate
                        } else {
                            $filterValue = $translate('[none]'); // @translate
                        }
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $filterValue;
                    }
                    break;

                case 'media_types':
                    $filterLabel = $translate('Media types'); // @translate
                    foreach ($this->checkAndFlatArray($value) as $subKey => $subValue) {
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

                case 'rft':
                    // TODO Label for rft search filter depends on the search config.
                    $filterLabel = $value === 'record'
                        ? $translate('Record only') // @translate
                        : $translate('Full text'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = '';
                    break;

                default:
                    // Dynamic arguments are similar to filters, so use it.
                    // Normally, they are already managed via the filters added
                    // by cleanQuery().
                    if (!isset($replacedKeyValues[$key])) {
                        unset($this->searchProcesseds[$key]);
                    }
                    break;
            }
        }

        $query['__processed'] = $this->searchProcesseds;

        if ($this->searchConfig) {
            $query['__searchConfig'] = $this->searchConfig;
            $query['__searchQuery'] = $this->searchQuery;
        }

        // Run event for modules, included AdvancedSearch itself: see
        // SearchingFilters.
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

    protected function searchFilterFilter(array $filterFilters, array $filters): array
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $translate = $plugins->get('translate');
        $dataTypeHelper = $plugins->get('dataType');

        $engineAdapter = $this->searchConfig ? $this->searchConfig->engineAdapter() : null;
        $availableFields = $engineAdapter
            ? $engineAdapter->getAvailableFields()
            : [];
        $searchFormSettings = $this->searchConfig ? ($this->searchConfig->setting('form') ?: []) : [];

        // Manage all fields, included those not in the form in order to support
        // queries for long term. But use labels set in the form if any.
        $formFieldLabels = array_column($searchFormSettings['filters'] ?? [], 'label', 'field');
        $availableFieldLabels = array_combine(array_keys($availableFields), array_column($availableFields ?? [], 'label'));
        $fieldLabels = array_replace($availableFieldLabels, array_filter($formFieldLabels));

        // Get resources titles of all filters with one query.
        $vrTitles = [];
        $vrIds = [];
        foreach ($filterFilters as $queryRow) {
            if (is_array($queryRow)
                && isset($queryRow['type'])
                && !empty($queryRow['val'])
                && in_array($queryRow['type'], SearchResources::FIELD_QUERY['value_subject'])
            ) {
                is_array($queryRow['val'])
                    ? $vrIds = array_merge($vrIds, array_values($queryRow['val']))
                    : $vrIds[] = $queryRow['val'];
            }
        }

        $vrIds = array_unique(array_filter(array_map('intval', $vrIds)));
        if ($vrIds) {
            // Currently, "resources" cannot be searched, so use adapter
            // directly. Rights are managed.
            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $services = $this->searchConfig
                ? $this->searchConfig->getServiceLocator()
                : $view->api()->read('vocabularies', 1)->getContent()->getServiceLocator();
            $entityManager = $services->get('Omeka\EntityManager');
            $qb = $entityManager->createQueryBuilder();
            $qb
                ->select('omeka_root.id', 'omeka_root.title')
                ->from(\Omeka\Entity\Resource::class, 'omeka_root')
                ->where($qb->expr()->in('omeka_root.id', ':ids'))
                ->setParameter('ids', $vrIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
            $vrTitles = array_column($qb->getQuery()->getScalarResult(), 'title', 'id');
        }

        $queryTypesLabels = $this->getQueryTypesLabels();
        $searchFormAdvancedLabels = array_column($searchFormSettings['advanced']['fields'] ?? [], 'label', 'value');
        $fieldFiltersLabels = array_replace($fieldLabels, array_filter($searchFormAdvancedLabels));

        $index = 0;
        foreach ($filterFilters as $subKey => $queryRow) {
            // Default query type is "in", unlike standard search.
            // TODO Use an option to normalize default type.
            $queryType = $queryRow['type'] ?? 'in';
            if (!isset(SearchResources::FIELD_QUERY['reciprocal'][$queryType])) {
                continue;
            }

            $joiner = $queryRow['join'] ?? 'and';
            $val = $queryRow['val'] ?? '';

            $isWithoutValue = in_array($queryType, SearchResources::FIELD_QUERY['value_none'], true);

            // A value can be an array with types "list" and "nlist".
            if (!is_array($val)
                && !strlen((string) $val)
                && !$isWithoutValue
            ) {
                continue;
            }

            if ($isWithoutValue) {
                $val = '';
            }

            $queryFields = $queryRow['field'] ?? null;
            // Fields may be an array with an empty value (any
            // field) in advanced form, so remove empty strings from
            // it, in which case the check should be skipped.
            if (is_array($queryFields) && in_array('', $queryFields, true)) {
                $queryFields = [];
            }

            // Prepare label.
            if (isset($queryRow['label'])) {
                $fieldLabel = $queryRow['label'];
            } elseif ($queryFields) {
                $fieldLabel = [];
                foreach (is_array($queryFields) ? $queryFields : [$queryFields] as $queryField) {
                    if (isset($fieldFiltersLabels[$queryField])) {
                        $fieldLabel[] = $fieldFiltersLabels[$queryField];
                    } else {
                        $propertyLabel = $this->easyMeta->propertyLabel($queryField);
                        if ($propertyLabel) {
                            $fieldLabel[] = $translate($propertyLabel);
                        } else {
                            $fieldLabel[] = $translate('Unknown field'); // @translate
                        }
                    }
                }
                $fieldLabel = implode(' ' . $translate('OR') . ' ', array_unique($fieldLabel));
            } else {
                $fieldLabel = $translate('[Any field]'); // @translate
            }

            // Don't add the query type for shortcut filters in main form.
            // TODO Clarify use in standard form and module main form.
            if (!empty($queryRow['is_form_filter'])) {
                $filterLabel = $fieldLabel;
            } else {
                $filterLabel = $fieldLabel . ' ' . $queryTypesLabels[$queryType];
            }
            if ($index > 0) {
                $joiners = [
                    'or' => $translate('OR'), // @translate
                    'not' => $translate('EXCEPT'), // @translate
                    'and' => $translate('AND'), // @translate
                ];
                $filterLabel = ($joiners[$joiner] ?? $joiners['and']) . ' ' . $filterLabel;
            }

            if (in_array($queryType, SearchResources::FIELD_QUERY['value_subject'])) {
                $vals = $this->checkAndFlatArrayValueResourceIds($val, $vrTitles);
            } else {
                $vals = $this->checkAndFlatArray($val);
                // If this is a data type query, convert the value
                // to the data type's label.
                if (in_array($queryType, ['dt', 'ndt', 'dtp', 'ndtp'])) {
                    $vals = array_map(fn ($v) => $dataTypeHelper->getLabel($v), $vals);
                } elseif (in_array($queryType, SearchResources::FIELD_QUERY['value_single_array_or_string'])) {
                    $vals = array_map(fn ($v) => urldecode($v), $vals);
                }
            }

            $filters[$filterLabel][$this->urlQuery('filter', $subKey, !empty($queryRow['is_form_filter']))] = implode(', ', $vals);

            if (!isset($queryRow['replaced_filter_key'])
                && isset($queryRow['replaced_field'])
            ) {
                $this->searchProcesseds[$queryRow['replaced_field']] = $queryRow['replaced_value'];
            }

            ++$index;
        }

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
     * Flat a max two levels array of resource id.
     *
     * The array should be an associative array.
     *
     * This function fixes some forms that add an array level.
     * This function manages only one level, so check value when needed.
     */
    protected function checkAndFlatArrayValueResourceIds($value, array $titles): array
    {
        if (is_array($value)) {
            $firstKey = key($value);
            if (is_numeric($firstKey)) {
                $values = $value;
            } else {
                $values = is_array(reset($value))
                    ? $value[$firstKey]
                    : [$value[$firstKey]];
            }
        } else {
            $values = [$value] ;
        }
        $values = array_unique($values);
        $values = array_combine($values, $values);
        return array_replace($values, $titles);
    }

    /**
     * Prepare original query to use to build new urls skipping a part.
     *
     * @see \AdvancedSearch\Stdlib\SearchResources::expandFieldQueryArgs()
     * @see \AdvancedSearch\View\Helper\SearchFilters::prepareQueryForUrl()
     */
    protected function prepareQueryForUrl(): self
    {
        $this->queryForUrl = $this->query;
        if (empty($this->queryForUrl['filter'])) {
            return $this;
        }
        foreach ($this->queryForUrl['filter'] ?? [] as $key => $filter) {
            if (!empty($filter['is_form_filter'])) {
                $this->queryForUrl[$filter['replaced_field']] = $filter['replaced_value'];
                unset($this->queryForUrl['filter'][$key]);
            }
        }
        if (empty($this->queryForUrl['filter'])) {
            unset($this->queryForUrl['filter']);
        }
        return $this;
    }

    /**
     * Get url of the query without the specified key and subkey.
     *
     * @param string|int $key
     * @param string|int|null $subKey
     * @param bool $isFilterShortcut
     * @return string
     *
     * Copy:
     * @see \AdvancedSearch\View\Helper\SearchFilters::urlQuery()
     * @see \AdvancedSearch\View\Helper\SearchingFilters::urlQuery()
     */
    protected function urlQuery($key, $subKey = null, bool $isFilterShortcut = false): string
    {
        $newQuery = $this->queryForUrl;
        if ($isFilterShortcut) {
            unset($newQuery[$this->query['filter'][$subKey]['replaced_field']]);
        } elseif (is_null($subKey) || !is_array($newQuery[$key]) || count($newQuery[$key]) <= 1) {
            unset($newQuery[$key]);
        } else {
            unset($newQuery[$key][$subKey]);
        }
        return $newQuery
            ? $this->baseUrl . '?' . http_build_query($newQuery, '', '&', PHP_QUERY_RFC3986)
            : $this->baseUrl;
    }
}
