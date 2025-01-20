<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau 2018-2025
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

use AdvancedSearch\Stdlib\SearchResources;
use AdvancedSearch\View\Helper\SearchFiltersTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\I18n\Translator\TranslatorAwareTrait;

class Advanced extends Fieldset
{
    use SearchFiltersTrait;
    use TranslatorAwareTrait;

    public function init(): void
    {
        $filterFields = $this->getFilterFields();
        if (empty($filterFields)) {
            return;
        }

        $filterOptions = $this->getOption('options') ?? [];

        $this
            ->setLabel('')
            ->setAttributes([
                'class' => 'filter',
            ]);

        $joiner = (bool) $this->getOption('field_joiner');
        $joinerNot = (bool) $this->getOption('field_joiner_not');
        if ($joiner) {
            $valueOptions = [
                'and' => 'and', // @translate
                'or' => 'or', // @translate
            ];
            if ($joinerNot) {
                $valueOptions['not'] = 'not'; // @translate
            }
            $this
                ->add([
                    'name' => 'join',
                    'type' => Element\Select::class,
                    'options' => [
                        'value_options' => $valueOptions,
                        'label_attributes' => [
                            'class' => 'search-join-label',
                        ],
                    ] + ($filterOptions['join']['options'] ?? []),
                    'attributes' => [
                        'value' => 'and',
                        // TODO Manage width for chosen select (but useless: the number of options is small).
                        // 'class' => 'chosen-select',
                    ] + ($filterOptions['join']['attributes'] ?? []),
                ]);
        }

        $this
            // No issue with input filter for select: there are always options.
            ->add([
                'name' => 'field',
                'type' => Element\Select::class,
                'options' => [
                    'value_options' => $filterFields,
                ] + ($filterOptions['field']['options'] ?? []),
                'attributes' => [
                    'value' => (string) key($filterFields),
                    // TODO Manage width for chosen select (but useless: the number of options is small).
                    // 'class' => 'chosen-select',
                ] + ($filterOptions['field']['attributes'] ?? []),
            ]);

        $operator = (bool) $this->getOption('field_operator');
        if ($operator) {
            $operators = $this->getOption('field_operators') ?: $this->getQueryTypesLabels();
            if ($joiner && $joinerNot) {
                $operators = array_diff_key($operators, array_flip(SearchResources::FIELD_QUERY['negative']));
            }
            $this
                ->add([
                    'name' => 'type',
                    'type' => Element\Select::class,
                    'options' => [
                        'value_options' => $operators,
                        'label_attributes' => [
                            'class' => 'search-type-label',
                        ],
                    ] + ($filterOptions['type']['options'] ?? []),
                    'attributes' => [
                        'value' => 'in',
                        // TODO Manage width for chosen select (but useless: the number of options is small).
                        // 'class' => 'chosen-select',
                    ] + ($filterOptions['type']['attributes'] ?? []),
                ]);
        }

        $this
            ->add([
                'name' => 'val',
                'type' => Element\Text::class,
                'options' => $filterOptions['val']['options'] ?? [],
                'attributes' => $filterOptions['val']['attributes'] ?? [],
            ])

            ->add([
                'name' => 'minus',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'search-filter-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-delete.
                    'class' => 'search-filter-action search-filter-minus fa fa-minus remove-value button',
                    'aria-label' => 'Remove this filter', // @translate
                ],
            ])
            /* // TODO Allow to insert a filter between two filters? Useless because order has no special meaning for now.
            ->add([
                'name' => 'plus',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'search-filter-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-add.
                    'class' => 'search-filter-action search-filter-plus fa fa-plus add-value button',
                    'aria-label' => 'Add a filter', // @translate
                ],
            ])
            */
        ;
    }

    /**
     * TODO The fields should be checked early, not here.
     */
    protected function getFilterFields(): array
    {
        $fields = $this->getOption('fields');
        if (!$fields) {
            return [];
        }
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $engineAdapter = $searchConfig ? $searchConfig->engineAdapter() : null;
        if (!$engineAdapter) {
            return [];
        }
        $availableFields = $engineAdapter->getAvailableFields();
        if (!$availableFields) {
            return [];
        }
        return array_intersect_key($fields, $availableFields);
    }
}
