<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2019-2023
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

use AdvancedSearch\Mvc\Controller\Plugin\SearchResources;
use AdvancedSearch\Query;

abstract class AbstractFormAdapter implements FormAdapterInterface
{
    protected $form;

    abstract public function getLabel(): string;

    public function setForm(?\Laminas\Form\Form $form): \AdvancedSearch\FormAdapter\FormAdapterInterface
    {
        $this->form = $form;
        return $this;
    }

    public function getForm(): ?\Laminas\Form\Form
    {
        return $this->form;
    }

    public function getFormPartialHeaders(): ?string
    {
        return null;
    }

    public function getFormPartial(): ?string
    {
        return null;
    }

    public function getConfigFormClass(): ?string
    {
        return null;
    }

    public function toQuery(array $request, array $formSettings): \AdvancedSearch\Query
    {
        $query = new Query;

        // Solr doesn't allow unavailable args anymore (invalid or unknown).
        // Furthermore, fields are case sensitive.
        $onlyAvailableFields = !empty($formSettings['only_available_fields']);
        if ($onlyAvailableFields) {
            $availableFields = $formSettings['available_fields'] ?? [];
            if ($availableFields) {
                $checkAvailableField = function ($field) use ($availableFields) {
                    return isset($availableFields[$field]);
                };
            } else {
                $checkAvailableField = function ($field) {
                    return false;
                };
            }
        } else {
            $checkAvailableField = function ($field) {
                return true;
            };
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
                    $query->setQuery($request['q']);
                    continue 2;

                // Special fields of the main form and internal adapter are
                // managed here currently.

                // Resource name in fact.
                case 'resource_type':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $query->setResources($value);
                    break;

                case 'id':
                    $valueArray = $flatArray($value);
                    $query->addFilter('id', $valueArray);
                    continue 2;

                // Specific fields.

                case 'is_public':
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

                case 'filter':
                    // The request filters are the advanced ones in the form settings.
                    // The default query type is "in" (contains).
                    $joiner = null;
                    $operator = null;
                    foreach ($formSettings['filters'] as $filter) {
                        if ($filter['type'] === 'Advanced') {
                            $joiner = (bool) $filter['field_joiner'];
                            $operator = (bool) $filter['field_operator'];
                            break;
                        }
                    }

                    // TODO The filter field can be multiple (as array).

                    if (empty($joiner)) {
                        if (empty($operator)) {
                            foreach ($value as $filter) {
                                if (isset($filter['field'])
                                    && isset($filter['value'])
                                    && !is_array($filter['value'])
                                    && trim($filter['value']) !== ''
                                    && $checkAvailableField($filter['field'])
                                ) {
                                    $query->addFilter($filter['field'], $filter['value']);
                                }
                            }
                        } else {
                            foreach ($value as $filter) {
                                if (isset($filter['field']) && $checkAvailableField($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    if (in_array($type, SearchResources::PROPERTY_QUERY['value_none'])) {
                                        $query->addFilterQuery($filter['field'], null, $type);
                                    } elseif (isset($filter['value']) && trim($filter['value']) !== '') {
                                        $query->addFilterQuery($filter['field'], $filter['value'], $type);
                                    }
                                }
                            }
                        }
                    } else {
                        if (empty($operator)) {
                            foreach ($value as $filter) {
                                if (isset($filter['field']) && isset($filter['value']) && trim($filter['value']) !== '' && $checkAvailableField($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                    $query->addFilterQuery($filter['field'], $filter['value'], $type, $join);
                                }
                            }
                        } else {
                            foreach ($value as $filter) {
                                if (isset($filter['field']) && $checkAvailableField($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    if (in_array($type, SearchResources::PROPERTY_QUERY['value_none'])) {
                                        $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                        $query->addFilterQuery($filter['field'], null, $type, $join);
                                    } elseif (isset($filter['value']) && trim($filter['value']) !== '') {
                                        $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                        $query->addFilterQuery($filter['field'], $filter['value'], $type, $join);
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
                            // Reorder early when needed.
                            // TODO Move to Query?
                            $facetRangeFrom = isset($facetValues['from']) && $facetValues['from'] !== ''
                                ? $facetValues['from']
                                : null;
                            $facetRangeTo = isset($facetValues['to']) && $facetValues['to'] !== ''
                                ? $facetValues['to']
                                : null;
                            if (!is_null($facetRangeFrom) && !is_null($facetRangeTo) && ($facetRangeFrom <=> $facetRangeTo) > 0) {
                                $facetRangeFromFrom = $facetRangeFrom;
                                $facetRangeFrom = $facetRangeTo;
                                $facetRangeTo = $facetRangeFromFrom;
                            }
                            $query->addActiveFacetRange($facetName, $facetRangeFrom, $facetRangeTo);
                        } else {
                            foreach ($facetValues as $facetValue) {
                                $query->addActiveFacet($facetName, $facetValue);
                            }
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
                        if ($this->form
                            && $this->form->has($name)
                            && ($element = $this->form->get($name)) instanceof \Laminas\Form\Element\Text
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
                                $query->addDateRangeFilter($name, $dateFrom, $dateTo);
                            }
                            continue 3;
                    }
                    continue 2;
            }
        }

        // $page, $perPage, $offset, $limit are null or int, but not settings.
        $formSettings['search']['per_page'] = empty($formSettings['search']['per_page']) ? null : (int) $formSettings['search']['per_page'];
        if ($page || empty($offset)) {
            $page ??= 1;
            $perPage ??= $limit ?? $formSettings['search']['per_page'] ?? \Omeka\Stdlib\Paginator::PER_PAGE;
            $query->setLimitPage($page, $perPage);
        } else {
            $limit ??= $perPage ?? $formSettings['search']['per_page'] ?? \Omeka\Stdlib\Paginator::PER_PAGE;
            $query->setLimitOffset($offset, $perPage);
        }

        if ($sort) {
            $query->setSort($sort);
        } elseif ($sortBy) {
            $query->setSort($sortBy . ($sortOrder ? ' ' . $sortOrder : ''));
        }

        return $query;
    }
}
