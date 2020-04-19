<?php

/*
 * Copyright Daniel Berthereau 2020
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

namespace Search\FormAdapter;

use Search\Query;

trait TraitUnrestrictedQuery
{
    public function toUnrestrictedQuery(array $request, array $formSettings)
    {
        $query = new Query;

        if (isset($request['q'])) {
            $query->setQuery($request['q']);
        }

        if (isset($formSettings['is_public_field'])
            && isset($request['isPublic'])
            && strlen($request['isPublic'])
        ) {
            $query->addFilter($formSettings['is_public_field'], (bool) $request['isPublic']);
        }

        if (!empty($formSettings['item_set_filter_type'])
            && !empty($formSettings['item_set_id_field'])
            && isset($request['itemSet']['ids'])
        ) {
            $query->addFilter($formSettings['item_set_id_field'], $request['itemSet']['ids']);
        }

        if (!empty($formSettings['resource_class_filter_type'])
            && !empty($formSettings['resource_class_id_field'])
            && isset($request['resourceClass']['ids'])
        ) {
            $query->addFilter($formSettings['resource_class_id_field'], $request['resourceClass']['ids']);
        }

        if (!empty($formSettings['resource_template_filter_type'])
            && !empty($formSettings['resource_template_id_field'])
            && isset($request['resourceTemplate']['ids'])
        ) {
            $query->addFilter($formSettings['resource_template_id_field'], $request['resourceTemplate']['ids']);
        }

        // TODO Manage query on owner (only one in core).

        if (isset($request['text']['filters'])) {
            if (empty($formSettings['filter_value_joiner'])) {
                if (empty($formSettings['filter_value_type'])) {
                    foreach ($request['text']['filters'] as $filter) {
                        if (!empty($filter['value'])) {
                            $query->addFilter($filter['field'], $filter['value']);
                        }
                    }
                } else {
                    foreach ($request['text']['filters'] as $filter) {
                        $type = isset($filter['type']) && $filter['type'] ? $filter['type'] : 'in';
                        if ($type === 'ex' || $type === 'nex') {
                            $query->addFilterQuery($filter['field'], null, $type);
                        } elseif (!empty($filter['value'])) {
                            $query->addFilterQuery($filter['field'], $filter['value'], $type);
                        }
                    }
                }
            } else {
                if (empty($formSettings['filter_value_type'])) {
                    foreach ($request['text']['filters'] as $filter) {
                        if (!empty($filter['value'])) {
                            $joiner = isset($filter['join']) && $filter['join'] === 'or' ? 'or' : 'and';
                            $query->addFilterQuery($filter['field'], $filter['value'], 'in', $joiner);
                        }
                    }
                } else {
                    foreach ($request['text']['filters'] as $filter) {
                        $type = isset($filter['type']) && $filter['type'] ? $filter['type'] : 'in';
                        if ($type === 'ex' || $type === 'nex') {
                            $joiner = isset($filter['join']) && $filter['join'] === 'or' ? 'or' : 'and';
                            $query->addFilterQuery($filter['field'], null, $type, $joiner);
                        } elseif (!empty($filter['value'])) {
                            $joiner = isset($filter['join']) && $filter['join'] === 'or' ? 'or' : 'and';
                            $query->addFilterQuery($filter['field'], $filter['value'], 'in', $joiner);
                        }
                    }
                }
            }
        }

        if (isset($request['excludedFields'])) {
            $excludedFields = is_array($request['excludedFields'])
                ? $request['excludedFields']
                : [$request['excludedFields']];
            $query->setExcludedFields($excludedFields);
        }

        return $query;
    }
}
