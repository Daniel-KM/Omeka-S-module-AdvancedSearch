<?php

/*
 * Copyright Daniel Berthereau 2018-2020
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

namespace Search\Form\Admin;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class AdvancedFormConfigFieldset extends Fieldset
{
    public function init()
    {
        $fieldOptions = $this->getFieldsOptions();

        // These fields may be overridden by the available fields.
        $specialOptions = [
            'is_public_field' => 'Is public', // @translate
            'item_set_id_field' => 'Item set id', // @translate
            'resource_class_id_field' => 'Resource class id', // @translate
            'resource_template_id_field' => 'Resource template id', // @translate
        ] + $fieldOptions;

        // Remove some of the available fields used by the internal adapter,
        // because here, it's about special options and for any adapter.
        unset($specialOptions['item_set_id']);
        unset($specialOptions['resource_class_id']);
        unset($specialOptions['resource_template_id']);

        $this
            ->add([
                'name' => 'item_set_filter_type',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Display item set filter', // @translate
                    'value_options' => [
                        '0' => 'No', // @translate
                        'select' => 'As select', // @translate
                        'multi-checkbox' => 'As multi checkbox', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'item_set_filter_type',
                    'required' => false,
                    'value' => '0',
                ],
            ])
            ->add([
                'name' => 'item_set_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Item set id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'item_set_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'item_set_id_field',
                ],
            ])

            ->add([
                'name' => 'resource_class_filter_type',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Display resource class filter', // @translate
                    'value_options' => [
                        '0' => 'No', // @translate
                        'select' => 'As select', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'resource_class_filter_type',
                    'required' => false,
                    'value' => '0',
                ],
            ])
            ->add([
                'name' => 'resource_class_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Resource class id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'resource_class_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'resource_class_id_field',
                ],
            ])

            ->add([
                'name' => 'resource_template_filter_type',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Display resource template filter', // @translate
                    'value_options' => [
                        '0' => 'No', // @translate
                        'select' => 'As select', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'resource_template_filter_type',
                    'required' => false,
                    'value' => '0',
                ],
            ])
            ->add([
                'name' => 'resource_template_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Resource template id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'id' => 'resource_template_id_field',
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'resource_template_id_field',
                ],
            ])

            ->add([
                'name' => 'is_public_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Is public field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'is_public_field',
                ],
            ])

            ->add([
                'name' => 'filter_collection_number',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Number of filter groups to display', // @translate
                    'info' => 'The filters may be managed via js for a better display.', // @translate
                ],
                'attributes' => [
                    'id' => 'filter_collection_number',
                    'required' => false,
                    'value' => '1',
                    'min' => '0',
                    'max' => '99',
                ],
            ])
            ->add([
                'name' => 'filter_value_joiner',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the joiner ("and" or "or") to the filters', // @translate
                ],
                'attributes' => [
                    'id' => 'filter_value_joiner',
                ],
            ])
            ->add([
                'name' => 'filter_value_type',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the type ("equal", "in", etc.) to the filters', // @translate
                ],
                'attributes' => [
                    'id' => 'filter_value_type',
                ],
            ])
            ->add([
                'name' => 'filters',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Filters', // @translate
                    'info' => 'List of filters that will be displayed in the search form. Format is "term | Label".', // @translate
                ],
                'attributes' => [
                    'id' => 'filters',
                    // field (term) | label (order means weight).
                    'placeholder' => 'dcterms:title | Title',
                    'rows' => 12,
                ],
            ])
            ->add([
                'name' => 'available_filters',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Available filters', // @translate
                    'info' => 'List of all available filters, among which some can be copied above.', // @translate
                ],
                'attributes' => [
                    'id' => 'available_filters',
                    'value' => $this->getFieldsOptionsAsText(),
                    'placeholder' => 'dcterms:title | Title',
                    'rows' => 12,
                ],
            ])
        ;
    }

    /**
     * Special method to fix the issue with the filters. See language in form.
     *
     * @todo Use a regular input filter.
     *
     * @param array $data
     * @return array
     */
    public function processInputFilters($data)
    {
        if (empty($data['form']['filters'])) {
            $data['form']['filters'] = [];
        } elseif (!is_array($data['form']['filters'])) {
            $fieldOptions = $this->getFieldsOptions();
            $fields = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $data['form']['filters']))));
            $data['form']['filters'] = [];
            foreach ($fields as $key => $value) {
                list($term, $label) = array_map('trim', explode('|', $value . '|'));
                if (isset($fieldOptions[$term])) {
                    $data['form']['filters'][$term] = [
                        'enabled' => true,
                        'weight' => $key + 1,
                        'display' => [
                            'label' => $label ?: $term,
                        ],
                    ];
                }
            }
        }

        unset($data['form']['available_filters']);

        return $data;
    }

    public function populateValues($data)
    {
        $fields = @$data['filters'] ?: [];
        if (is_array($fields)) {
            $fieldData = '';
            foreach ($fields as $name => $field) {
                if (!empty($field['enabled'])) {
                    $fieldData .= $name . ' | ' . $field['display']['label'] . "\n";
                }
            }
            $data['filters'] = $fieldData;
        }

        // Keep default available filters.
        unset($data['available_filters']);

        parent::populateValues($data);
    }

    protected function getAvailableFields()
    {
        $searchPage = $this->getOption('search_page');
        $searchAdapter = $searchPage->index()->adapter();
        return $searchAdapter->getAvailableFields($searchPage->index());
    }

    protected function getFieldsOptions()
    {
        $options = [];
        foreach ($this->getAvailableFields() as $name => $field) {
            if (isset($field['label'])) {
                $options[$name] = sprintf('%s (%s)', $field['label'], $name);
            } else {
                $options[$name] = $name;
            }
        }
        return $options;
    }

    protected function getFieldsOptionsAsText()
    {
        $data = '';
        foreach ($this->getAvailableFields() as $name => $field) {
            $data .= $name . ' | ' . $field['label'] . "\n";
        }
        return $data;
    }

    protected function getFieldLabel($field)
    {
        $searchPage = $this->getOption('search_page');
        $settings = $searchPage->settings();

        $name = $field['name'];
        $label = isset($field['label']) ? $field['label'] : null;
        if (isset($settings['form']['filters'][$name])) {
            $fieldSettings = $settings['form']['filters'][$name];

            if (isset($fieldSettings['display']['label'])
                && $fieldSettings['display']['label']
            ) {
                $label = $fieldSettings['display']['label'];
            }
        }
        return $label
            ? sprintf('%s (%s)', $label, $field['name'])
            : $field['name'];
    }
}
