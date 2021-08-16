<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau 2018-2021
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

namespace AdvancedSearch\Form\SearchFilter;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class Advanced extends Fieldset
{
    public function init(): void
    {
        $filterFields = $this->getFilterFields();
        if (empty($filterFields)) {
            return;
        }

        $this
            ->setLabel('')
            ->setAttributes([
                'class' => 'filter',
            ]);

        $joiner = $this->getOption('field_joiner');
        if ($joiner) {
            $this
                ->add([
                    'name' => 'join',
                    'type' => Element\Select::class,
                    'options' => [
                        'value_options' => [
                            'and' => 'and', // @translate
                            'or' => 'or', // @translate
                        ],
                        'label_attributes' => [
                            'class' => 'search-boolean-label',
                        ],
                    ],
                    'attributes' => [
                        'value' => 'and',
                        // TODO Manage width for chosen select (but useless: the number of options is small).
                        // 'class' => 'chosen-select',
                    ],
                ]);
        }

        $this
            // No issue with input filter for select: there are always options.
            ->add([
                'name' => 'field',
                'type' => Element\Select::class,
                'options' => [
                    'value_options' => $filterFields,
                ],
                'attributes' => [
                    'value' => (string) key($filterFields),
                    // TODO Manage width for chosen select (but useless: the number of options is small).
                    // 'class' => 'chosen-select',
                ],
            ]);

        $operator = $this->getOption('field_operator');
        if ($operator) {
            $this
                ->add([
                    'name' => 'type',
                    'type' => Element\Select::class,
                    'options' => [
                        'value_options' => [
                            'eq' => 'is exactly', // @translate
                            'neq' => 'is not exactly', // @translate
                            'in' => 'contains', // @translate
                            'nin' => 'does not contain', // @translate
                            'res' => 'is resource with ID', // @translate
                            'nres' => 'is not resource with ID', // @translate
                            'ex' => 'has any value', // @translate
                            'nex' => 'has no values', // @translate
                        ],
                        'label_attributes' => [
                            'class' => 'search-type-label',
                        ],
                    ],
                    'attributes' => [
                        'value' => 'in',
                        // TODO Manage width for chosen select (but useless: the number of options is small).
                        // 'class' => 'chosen-select',
                    ],
                ]);
        }

        $this
            ->add([
                'name' => 'value',
                'type' => Element\Text::class,
            ]);
    }

    /**
     * TODO The fields should be checked in
     */
    protected function getFilterFields(): array
    {
        $fields = $this->getOption('fields');
        if (empty($fields)) {
            return [];
        }
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        if (!$searchConfig) {
            return [];
        }
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }
        $availableFields = $searchAdapter->getAvailableFields($searchEngine);
        if (empty($availableFields)) {
            return [];
        }
        return array_intersect_key($fields, $availableFields);
    }
}
