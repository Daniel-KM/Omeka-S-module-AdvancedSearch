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
        // TODO Currently disabled because the number of fields is too big (do a simple form).
        // $this->add($this->getAdvancedFieldsFieldset());

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
                'name' => 'item_set_id_field',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Item set id field', // @translate
                    'value_options' => $specialOptions,
                    'empty_option' => 'None', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'item_set_id_field',
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
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'resource_class_id_field',
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
                    'required' => false,
                    'class' => 'chosen-select',
                    'value' => 'resource_template_id_field',
                ],
            ])

            ->add([
                'name' => 'filter_value_joiner',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the joiner ("and" or "or") to the filters', // @translate
                ],
            ])
        ;
    }

    protected function getAdvancedFieldsFieldset()
    {
        $advancedFieldsFieldset = new Fieldset('advanced-fields');
        $advancedFieldsFieldset->setLabel('Advanced search fields'); // @translate
        $advancedFieldsFieldset->setAttribute('data-sortable', '1');
        $advancedFieldsFieldset->setAttribute('data-ordered', '0');

        $fields = $this->getAvailableFields();
        $weights = range(0, count($fields));
        $weight_options = array_combine($weights, $weights);
        $weight = 0;
        foreach ($fields as $field) {
            $fieldset = new Fieldset($field['name']);
            $fieldset->setLabel($this->getFieldLabel($field));

            $displayFieldset = new Fieldset('display');
            $displayFieldset
                ->add([
                    'name' => 'label',
                    'type' => Element\Text::class,
                    'options' => [
                        'label' => 'Label', // @translate
                    ],
                ]);
            $fieldset
                ->add($displayFieldset)

                ->add([
                    'name' => 'enabled',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => 'Enabled', // @translate
                    ],
                ])

                ->add([
                    'name' => 'weight',
                    'type' => Element\Select::class,
                    'options' => [
                        'label' => 'Weight', // @translate
                        'value_options' => $weight_options,
                    ],
                    'attributes' => [
                        'value' => $weight++,
                    ],
                ])
            ;

            $advancedFieldsFieldset->add($fieldset);
        }

        return $advancedFieldsFieldset;
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

    protected function getFieldLabel($field)
    {
        $searchPage = $this->getOption('search_page');
        $settings = $searchPage->settings();

        $name = $field['name'];
        $label = isset($field['label']) ? $field['label'] : null;
        if (isset($settings['form']['advanced-fields'][$name])) {
            $fieldSettings = $settings['form']['advanced-fields'][$name];

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
