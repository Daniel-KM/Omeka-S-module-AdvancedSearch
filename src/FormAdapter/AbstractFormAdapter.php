<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2019-2024
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AdvancedSearch\FormAdapter;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use AdvancedSearch\Stdlib\SearchResources;

abstract class AbstractFormAdapter implements FormAdapterInterface
{
    /**
     * @var string|null
     */
    protected $configFormClass = null;

    /**
     * @var string|null
     */
    protected $formClass = null;

    /**
     * @var string|null
     */
    protected $formPartial = null;

    /**
     * @var string|null
     */
    protected $formPartialHeaders = null;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    public function setSearchConfig(?SearchConfigRepresentation $searchConfig): self
    {
        $this->searchConfig = $searchConfig;
        return $this;
    }

    public function getConfigFormClass(): ?string
    {
        return $this->configFormClass;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFormClass(): ?string
    {
        return $this->formClass;
    }

    public function getFormPartialHeaders(): ?string
    {
        return $this->formPartialHeaders;
    }

    public function getFormPartial(): ?string
    {
        return $this->formPartial;
    }

    public function getForm(array $options = []): ?\Laminas\Form\Form
    {
        if (!$this->formClass || !$this->searchConfig) {
            return null;
        }
        $formElementManager = $this->searchConfig->getServiceLocator()
            ->get('FormElementManager');
        if (!$formElementManager->has($this->formClass)) {
            return null;
        }
        $options['search_config'] = $this->searchConfig;
        return $formElementManager
            ->get($this->formClass, $options)
            ->setAttribute('method', 'GET');
    }

    public function renderForm(array $options = []): string
    {
        /**
         * @var \Laminas\Mvc\Controller\PluginManager $plugins
         * @var \Laminas\View\HelperPluginManager $helpers
         * @var \Omeka\Mvc\Status $status
         * @var \AdvancedSearch\Mvc\Controller\Plugin\SearchRequestToResponse $searchRequestToResponse
         *
         * @see \AdvancedSearch\Controller\SearchController::searchAction()
         */
        $services = $this->searchConfig->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $helpers = $services->get('ViewHelperManager');

        $vars = [
            'searchConfig' => $this->searchConfig,
            'site' => $options['site'] ?? $helpers->get('currentSite')(),
            // The form is set via searchConfig.
            'form' => null,
            // Set a default empty query and response to simplify view.
            'query' => new Query,
            'response' => new Response,
            'skipFormAction' => false,
        ];

        $options += [
            'template' => null,
            'skip_form_action' => false,
            'skip_partial_headers' => false,
            'variant' => null,
        ];

        $form = $this->getForm($options);
        if (!$form) {
            return '';
        }

        if (!$options['template']) {
            $options['template'] = $this->getFormPartial();
            if (!$options['template']) {
                return '';
            }
        }

        /** @var \Laminas\View\Helper\Partial $partial */
        $partial = $helpers->get('partial');

        // In rare cases, view may be missing.
        $view = $partial->getView();
        if ($view && !$view->resolver($options['template'])) {
            return '';
        }

        if (!$options['skip_partial_headers']) {
            $partialHeaders = $this->getFormPartialHeaders();
            if ($partialHeaders) {
                // No output for this partial.
                $partial($partialHeaders, ['searchConfig' => $this->searchConfig] + $options);
            }
        }

        $vars['skipFormAction'] = !empty($options['skip_form_action']);
        if (!$vars['skipFormAction']) {
            $status = $services->get('Omeka\Status');
            $isAdmin = $status->isAdminRequest();
            $formActionUrl = $isAdmin
                ? $this->searchConfig->adminSearchUrl()
                : $this->searchConfig->siteUrl();
            $form->setAttribute('action', $formActionUrl);
        }

        if (!empty($options['request'])) {
            $form->setData($options['request']);
            $searchRequestToResponse = $plugins->get('searchRequestToResponse');
            $result = $searchRequestToResponse($options['request'], $this->searchConfig, $vars['site']);
            if ($result['status'] === 'fail') {
                return '';
            } elseif ($result['status'] === 'error') {
                $this->messenger()->addError($result['message']);
                return '';
            }
            // Data contain query and response.
            $vars = $result['data'] + $vars;
        }

        $vars['form'] = $form;

        return $partial($options['template'], $vars + $options);
    }

    public function toQuery(array $request, array $formSettings): Query
    {
        // TODO Prepare the full query here for simplification.
        $query = new Query;

        $query
            ->setAliases($formSettings['aliases'] ?? [])
            ->setOption('remove_diacritics', !empty($formSettings['remove_diacritics']))
            ->setOption('default_search_partial_word', !empty($formSettings['default_search_partial_word']));

        // Solr doesn't allow unavailable args anymore (invalid or unknown).
        // Furthermore, fields are case sensitive.
        $onlyAvailableFields = !empty($formSettings['only_available_fields']);
        if ($onlyAvailableFields) {
            $availableFields = $formSettings['available_fields'] ?? [];
            if ($availableFields) {
                $checkAvailableField = fn ($field) => isset($availableFields[$field]);
            } else {
                $checkAvailableField = fn ($field) => false;
            }
        } else {
            $checkAvailableField = fn ($field) => true;
        }

        // TODO Manage the "browse_attached_items" / "site_attachments_only".

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

        $isSimpleList = function ($value): bool {
            if (!is_array($value)) {
                return false;
            }
            foreach ($value as $key => $val) {
                if (!is_numeric($key) || is_array($val)) {
                    return false;
                }
            }
            return true;
        };

        $page = null;
        $perPage = null;
        $limit = null;
        $offset = null;
        $sort = null;
        $sortBy = null;
        $sortOrder = null;

        foreach ($request as $name => $value) {
            if ($value === '' || $value === [] || $value === null) {
                continue;
            }
            $name = (string) $name;
            switch ($name) {
                case 'q':
                    $query->setQuery($value);
                    continue 2;

                case 'rft':
                    // Two values: record only or all (record and full text).
                    // There is only one full text search index in internal
                    // querier, but inversely a specific index for Solr, so it
                    // is managed via a specific filter via the adapter.
                    // TODO Add a specific index for full text in the internal database, so it will be a normal filter.
                    $query->setRecordOrFullText($value);
                    continue 2;

                // Special fields of the main form and internal adapter are
                // managed here currently.

                // Resource name in fact.
                case 'resource_type':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $query->setResourceTypes($value);
                    break;

                case 'id':
                    $valueArray = $flatArray($value);
                    $query->addFilter('id', $valueArray);
                    continue 2;

                // Specific fields.

                case 'is_public':
                case 'is_open':
                    if (is_string($value)
                        && strlen($value)
                        && isset($formSettings['available_fields'][$name]['to'])
                    ) {
                        $query->addFilter($formSettings['available_fields'][$name]['to'], (bool) $value);
                    }
                    continue 2;

                case 'site':
                case 'owner':
                case 'class':
                case 'template':
                case 'item_set':
                    if (isset($formSettings['available_fields'][$name]['to'])) {
                        $valueArray = $flatArray($value);
                        $query->addFilter($formSettings['available_fields'][$name]['to'], $valueArray);
                    }
                    continue 2;

                case 'access':
                    if (is_string($value)
                        && strlen($value)
                        && isset($formSettings['available_fields'][$name]['to'])
                    ) {
                        $query->addFilter($formSettings['available_fields'][$name]['to'], $value);
                    }
                    continue 2;

                case 'filter':
                    // The request filters are the advanced ones in the form settings.
                    // The default query type is "in" (contains).
                    // TODO Clarify the default field joiner an operator between standard fields.
                    $joiner = !empty($formSettings['advanced']['field_joiner']);
                    $operator = !empty($formSettings['advanced']['field_operator']);

                    // TODO The filter field can be multiple (as array).

                    // Previously, the key "val" was named "value".
                    // The compatibility is checked with old queries and forms.
                    $hasFilterKeyValue = false;

                    if (empty($joiner)) {
                        if (empty($operator)) {
                            foreach ($value as $filter) {
                                if (!isset($filter['val']) && isset($filter['value'])) {
                                    $hasFilterKeyValue = true;
                                    $filter['val'] = $filter['value'];
                                    unset($filter['value']);
                                }
                                if (isset($filter['field'])
                                    && isset($filter['val'])
                                    && !is_array($filter['val'])
                                    && trim($filter['val']) !== ''
                                    && $checkAvailableField($filter['field'])
                                ) {
                                    $query->addFilter($filter['field'], $filter['val']);
                                }
                            }
                        } else {
                            foreach ($value as $filter) {
                                if (!isset($filter['val']) && isset($filter['value'])) {
                                    $hasFilterKeyValue = true;
                                    $filter['val'] = $filter['value'];
                                    unset($filter['value']);
                                }
                                if (isset($filter['field']) && $checkAvailableField($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    if (in_array($type, SearchResources::FIELD_QUERY['value_none'])) {
                                        $query->addFilterQuery($filter['field'], null, $type);
                                    } elseif (isset($filter['val']) && trim($filter['val']) !== '') {
                                        $query->addFilterQuery($filter['field'], $filter['val'], $type);
                                    }
                                }
                            }
                        }
                    } else {
                        if (empty($operator)) {
                            foreach ($value as $filter) {
                                if (!isset($filter['val']) && isset($filter['value'])) {
                                    $hasFilterKeyValue = true;
                                    $filter['val'] = $filter['value'];
                                    unset($filter['value']);
                                }
                                if (isset($filter['field']) && isset($filter['val']) && trim($filter['val']) !== '' && $checkAvailableField($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                    $query->addFilterQuery($filter['field'], $filter['val'], $type, $join);
                                }
                            }
                        } else {
                            foreach ($value as $filter) {
                                if (!isset($filter['val']) && isset($filter['value'])) {
                                    $hasFilterKeyValue = true;
                                    $filter['val'] = $filter['value'];
                                    unset($filter['value']);
                                }
                                if (isset($filter['field']) && $checkAvailableField($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    if (in_array($type, SearchResources::FIELD_QUERY['value_none'])) {
                                        $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                        $query->addFilterQuery($filter['field'], null, $type, $join);
                                    } elseif (isset($filter['val']) && trim($filter['val']) !== '') {
                                        $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                        $query->addFilterQuery($filter['field'], $filter['val'], $type, $join);
                                    }
                                }
                            }
                        }
                    }
                    continue 2;

                case 'excluded':
                    $excluded = $flatArray($value);
                    $query->setExcludedFields($excluded);
                    continue 2;

                // TODO Manage the main form to use multiple times the same field. Or force creation of alias (like multifield)?
                // Standard field, generally a property or an alias/multifield.
                // The fields cannot be repeated for now: use an alias if needed.
                // The availability is not checked here, but by the search engine.

                case 'page':
                    $page = (int) $value ?: null;
                    break;
                case 'per_page':
                    $perPage = (int) $value ?: null;
                    break;
                case 'limit':
                    $limit = (int) $value ?: null;
                    break;
                case 'offset':
                    $offset = (int) $value ?: null;
                    break;

                case 'sort':
                    $sort = $value;
                    break;
                case 'sort_by':
                    $sortBy = $value;
                    break;
                case 'sort_order':
                    $sortOrder = $value;
                    break;

                case 'facet':
                    if (!is_array($value)) {
                        continue 2;
                    }
                    foreach ($value as $facetName => $facetValues) {
                        $firstFacetKey = key($facetValues);
                        if ($firstFacetKey === 'from' || $firstFacetKey === 'to') {
                            // Don't reorder: if user wants something illogic,
                            // return empty.
                            $facetRangeFrom = isset($facetValues['from']) && $facetValues['from'] !== ''
                                ? (string) $facetValues['from']
                                : null;
                            $facetRangeTo = isset($facetValues['to']) && $facetValues['to'] !== ''
                                ? (string) $facetValues['to']
                                : null;
                            // Do not append a range from/to if it is the same
                            // than the default value: it avoids useless active
                            // facets. Of course, the facet range min/max should
                            // be defined.
                            $facetData = $formSettings['facet']['facets'][$facetName] ?? null;
                            if ($facetData) {
                                $min = ($facetData['min'] ?? '') === '' ? null : (string) $facetData['min'];
                                if ($min !== null && $min === $facetRangeFrom) {
                                    $facetRangeFrom = null;
                                }
                                $max = ($facetData['max'] ?? '') === '' ? null : (string) $facetData['max'];
                                if ($max !== null && $max === $facetRangeTo) {
                                    $facetRangeTo = null;
                                }
                            }
                            if ($facetRangeFrom !== null || $facetRangeTo !== null) {
                                $query->addActiveFacetRange($facetName, $facetRangeFrom, $facetRangeTo);
                            }
                        } else {
                            foreach ($facetValues as $facetValue) {
                                $query->addActiveFacet($facetName, $facetValue);
                            }
                        }
                    }
                    break;

                case 'thesaurus':
                    if (!$checkAvailableField($name)) {
                        continue 2;
                    }

                    if (is_string($value)
                        || $isSimpleList($value)
                    ) {
                        continue 2;
                    }

                    foreach ($value as $field => $vals) {
                        if (!$checkAvailableField($field)) {
                            continue;
                        }
                        if (!is_string($vals) && !$isSimpleList($vals)) {
                            continue;
                        }
                        foreach ($flatArray($vals) as $val) {
                            $query->addFilterQuery($field, $val, 'res');
                        }
                    }
                    break;

                default:
                    if (!$checkAvailableField($name)) {
                        continue 2;
                    }

                    if (is_string($value)
                        || $isSimpleList($value)
                    ) {
                        // Manage simple field "Text", that should not be
                        // "equals" ("eq"), but "contains" ("in"), and that is
                        // managed in the form as a simple filter, not an
                        // advanced filter query.
                        // Other fields are predefined.
                        // TODO Don't check form, but settings['filters'] with field = name and type.
                        // TODO Simplify these checks (or support multi-values anywhere).
                        $valueArray = $flatArray($value);
                        $form = $this->getForm(['skip_values' => true]);
                        if ($form
                            && $form->has($name)
                            && ($element = $form->get($name)) instanceof \Laminas\Form\Element\Text
                        ) {
                            if ($element instanceof \AdvancedSearch\Form\Element\TextExact
                                || $element instanceof \AdvancedSearch\Form\Element\MultiText
                            ) {
                                foreach ($valueArray as $val) {
                                    $query->addFilter($name, $val);
                                }
                            } else {
                                // Included \AdvancedSearch\Form\Element\MultiText.
                                foreach ($valueArray as $val) {
                                    $query->addFilterQuery($name, $val);
                                }
                            }
                        } else {
                            foreach ($valueArray as $val) {
                                $query->addFilter($name, $val);
                            }
                        }
                        continue 2;
                    }

                    // TODO Sub-sub-input key is not managed currently.
                    $firstValue = reset($value);
                    if (is_array($firstValue)) {
                        continue 2;
                    }

                    $firstKey = key($value);
                    switch ($firstKey) {
                        default:
                            $query->addFilter($name, $value);
                            continue 3;

                        case 'from':
                        case 'to':
                            $dateFrom = (string) ($value['from'] ?? '');
                            $dateTo = (string) ($value['to'] ?? '');
                            if (strlen($dateFrom) || strlen($dateTo)) {
                                $query->addFilterRange($name, $dateFrom, $dateTo);
                            }
                            continue 3;
                    }
                    continue 2;
            }
        }

        // $page, $perPage, $offset, $limit are null or int, but not settings.
        $formSettings['request']['per_page'] = empty($formSettings['request']['per_page']) ? null : (int) $formSettings['request']['per_page'];
        if ($page || empty($offset)) {
            $page ??= 1;
            $perPage ??= $limit ?? $formSettings['request']['per_page'] ?? \Omeka\Stdlib\Paginator::PER_PAGE;
            $query->setLimitPage($page, $perPage);
        } else {
            $limit ??= $perPage ?? $formSettings['request']['per_page'] ?? \Omeka\Stdlib\Paginator::PER_PAGE;
            $query->setLimitOffset($offset, $perPage);
        }

        if ($sort) {
            $query->setSort($sort);
        } elseif ($sortBy) {
            $query->setSort($sortBy . ($sortOrder ? ' ' . $sortOrder : ''));
        }

        if (!empty($hasFilterKeyValue)) {
            $this->searchConfig->getServiceLocator()->get('Omeka\Logger')
                ->err('The query contains a filter with the key "value", that must be replaced with "val".'); // @translate
        }

        return $query;
    }
}
