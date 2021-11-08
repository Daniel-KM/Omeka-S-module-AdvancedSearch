<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau, 2019-2021
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

        // TODO Manage the "browse_attached_items" / "site_attachments_only".

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

                case 'resource_type':
                    if (!is_array($value)) {
                        $value = [$value];
                    }
                    $query->setResources($value);
                    break;

                case 'is_public':
                    if (is_string($value) && strlen($value)) {
                        $nameFilter = empty($formSettings['resource_fields']['is_public_field']) ? 'is_public': $formSettings['resource_fields']['is_public_field'];
                        $query->addFilter($nameFilter, (bool) $value);
                    }
                    continue 2;

                case 'resource':
                    $valueArray = $flatArray($value);
                    $query->addFilter('id', $valueArray);
                    continue 2;

                case 'item_set':
                    $nameFilter = empty($formSettings['resource_fields']['item_set_id_field']) ? 'item_set_id': $formSettings['resource_fields']['item_set_id_field'];
                    $valueArray = $flatArray($value);
                    $query->addFilter($nameFilter, $valueArray);
                    continue 2;

                case 'class':
                    $nameFilter = empty($formSettings['resource_fields']['resource_class_id_field']) ? 'resource_class_id': $formSettings['resource_fields']['resource_class_id_field'];
                    $valueArray = $flatArray($value);
                    $query->addFilter($nameFilter, $valueArray);
                    continue 2;

                case 'template':
                    $nameFilter = empty($formSettings['resource_fields']['resource_template_id_field']) ? 'resource_class_id': $formSettings['resource_fields']['resource_template_id_field'];
                    $valueArray = $flatArray($value);
                    $query->addFilter($nameFilter, $valueArray);
                    continue 2;

                // TODO Manage query on owner (only one in core).
                case 'owner':
                    $nameFilter = empty($formSettings['resource_fields']['owner_id_field']) ? 'owner_id': $formSettings['resource_fields']['owner_id_field'];
                    $valueArray = $flatArray($value);
                    $query->addFilter($nameFilter, $valueArray);
                    continue 2;

                case 'filter':
                    // The request filters are the advanced ones in the form settings.
                    $joiner = null;
                    $operator = null;
                    foreach ($formSettings['filters'] as $filter) {
                        if ($filter['type'] === 'Advanced') {
                            $joiner = (bool) $filter['field_joiner'];
                            $operator = (bool) $filter['field_operator'];
                            break;
                        }
                    }

                    // TODO The filter field can be multiple.

                    if (empty($joiner)) {
                        if (empty($operator)) {
                            foreach ($value as $filter) {
                                if (isset($filter['field']) && isset($filter['value']) && trim($filter['value']) !== '') {
                                    $query->addFilter($filter['field'], $filter['value']);
                                }
                            }
                        } else {
                            foreach ($value as $filter) {
                                if (isset($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    if ($type === 'ex' || $type === 'nex') {
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
                                if (isset($filter['field']) && isset($filter['value']) && trim($filter['value']) !== '') {
                                    $join = isset($filter['join']) && in_array($filter['join'], ['or', 'not']) ? $filter['join'] : 'and';
                                    $query->addFilterQuery($filter['field'], $filter['value'], $type, $join);
                                }
                            }
                        } else {
                            foreach ($value as $filter) {
                                if (isset($filter['field'])) {
                                    $type = empty($filter['type']) ? 'in' : $filter['type'];
                                    if ($type === 'ex' || $type === 'nex') {
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
                        foreach ($facetValues as $facetValue) {
                            $query->addActiveFacet($facetName, $facetValue);
                        }
                    }
                    break;

                default:
                    if (is_string($value)
                        || $isSimpleList($value)
                    ) {
                        // Manage simple field "Text", that should not be
                        // "equals" ("eq"), but "contains" ("in"), and that is
                        // managed in the form as a simple filter, not an
                        // advanced filter query.
                        // Other fields are predefined.
                        // TODO Don't check form, but settings['filters'] with field = name and type.
                        if ($this->form
                            && $this->form->has($name)
                            && ($element = $this->form->get($name)) instanceof \Laminas\Form\Element\Text
                            && !($element instanceof \AdvancedSearch\Form\Element\TextExact)
                        ) {
                            $query->addFilterQuery($name, $value);
                        } else {
                            $query->addFilter($name, $value);
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
            $page = $page ?? 1;
            $perPage = $perPage ?? $limit ?? $formSettings['search']['per_page'] ?? \Omeka\Stdlib\Paginator::PER_PAGE;
            $query->setLimitPage($page, $perPage);
        } else {
            $limit = $limit ?? $perPage ?? $formSettings['search']['per_page'] ?? \Omeka\Stdlib\Paginator::PER_PAGE;
            $query->setLimitOffset($offset, $perPage);
        }

        if ($sort) {
            $query->setSort($sort);
        } elseif ($sortBy) {
            $query->setSort($sortBy . ($sortOrder ? ' ' . $sortOrder: ''));
        }

        return $query;
    }
}
