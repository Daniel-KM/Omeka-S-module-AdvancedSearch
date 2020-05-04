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

namespace Search\Form;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceTemplateSelect;
use Omeka\View\Helper\Setting;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class AdvancedForm extends Form
{
    /**
     * @var SiteRepresentation
     */
    protected $site;

    /**
     * @var Setting
     */
    protected $siteSetting;

    protected $formElementManager;

    public function init()
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->getOption('search_page');
        $searchPageSettings = $searchPage ? $searchPage->settings() : [];

        $this
            ->add([
                'name' => 'q',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'q',
                    'placeholder' => 'Search resources…', // @translate
                ],
            ])
        ;

        $appendItemSetFieldset = !empty($searchPageSettings['form']['item_set_filter_type'])
            && !empty($searchPageSettings['form']['item_set_id_field']);
        if ($appendItemSetFieldset) {
            $this
                ->add($this->itemSetFieldset($searchPageSettings['form']['item_set_filter_type']));
        }
        $appendResourceClassFieldset = !empty($searchPageSettings['form']['resource_class_filter_type'])
            && !empty($searchPageSettings['form']['resource_class_id_field']);
        if ($appendResourceClassFieldset) {
            $this
                ->add($this->resourceClassFieldset());
        }
        $appendResourceTemplateFieldset = !empty($searchPageSettings['form']['resource_template_filter_type'])
            && !empty($searchPageSettings['form']['resource_template_id_field']);
        if ($appendResourceTemplateFieldset) {
            $this
                ->add($this->resourceTemplateFieldset());
        }

        $appendTextFieldset = !empty($searchPageSettings['form']['filter_collection_number'])
            && !empty($searchPageSettings['form']['filters']);
        if ($appendTextFieldset) {
            $this
                ->add($this->textFieldset($searchPageSettings['form']['filter_collection_number']));
        }

        $this->appendSpecificFields();

        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'value' => 'Submit', // @translate
                    'type' => 'submit',
                ],
            ])
        ;

        $inputFilter = $this->getInputFilter();
        if ($appendItemSetFieldset) {
            $inputFilter
                ->get('itemSet')
                ->add([
                    'name' => 'ids',
                    'required' => false,
                ]);
        }
        if ($appendResourceClassFieldset) {
            $inputFilter
                ->get('resourceClass')
                ->add([
                    'name' => 'ids',
                    'required' => false,
                ]);
        }
        if ($appendResourceTemplateFieldset) {
            $inputFilter
                ->get('resourceTemplate')
                ->add([
                    'name' => 'ids',
                    'required' => false,
                ]);
        }
    }

    protected function itemSetFieldset($filterType = 'select')
    {
        $fieldset = new Fieldset('itemSet');
        $fieldset
            ->add([
                'name' => 'ids',
                'type' => $filterType === 'multi-checkbox'
                    ? Element\MultiCheckbox::class
                    : Element\Select::class,
                'options' => [
                    // For normal users, it is not "item sets", but "collections".
                    'label' => 'Collections', // @translate
                    'value_options' => $this->getItemSetsOptions(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'item-sets',
                    'multiple' => true,
                    'class' => $filterType === 'multi-checkbox' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
        ;
        return $fieldset;
    }

    protected function resourceClassFieldset()
    {
        // For an unknown reason, the ResourceClassSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('resourceClass');

        /** @var \Omeka\Form\Element\ResourceClassSelect $element */
        $element = $this->getFormElementManager()->get(ResourceClassSelect::class);
        $element
            ->setName('ids')
            ->setOptions([
                'label' => 'Resource classes', // @translate
                'term_as_value' => true,
                'empty_option' => '',
                // TODO Manage list of resource classes by site.
                'used_terms' => true,
            ])
            ->setAttributes([
                'id' => 'resource-classes',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select resource classes…', // @translate
            ]);

        $fieldset
            ->add($element);
        return $fieldset;
    }

    protected function resourceTemplateFieldset()
    {
        // For an unknown reason, the ResourceTemplateSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('resourceTemplate');

        /** @var \Omeka\Form\Element\ResourceTemplateSelect $element */
        $element = $this->getFormElementManager()->get(ResourceTemplateSelect::class);
        $element
            ->setName('ids')
            ->setOptions([
                'label' => 'Resource templates', // @translate
                'empty_option' => '',
                'disable_group_by_owner' => true,
            ])
            ->setAttributes([
                'id' => 'resource-templates',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select resource templates…', // @translate
            ]);

        $setting = $this->getSiteSetting();
        $hasValues = false;
        if ($setting && $setting('search_restrict_templates', false)) {
            $values = $setting('search_apply_templates', []);
            if ($values) {
                $values = array_intersect_key($element->getValueOptions(), array_flip($values));
                $hasValues = (bool) $values;
                if ($hasValues) {
                    $fieldset
                        ->add([
                            'name' => 'ids',
                            'type' => Element\Select::class,
                            'options' => [
                                'label' => 'Resource templates', // @translate
                                'value_options' => $values,
                                'empty_option' => '',
                            ],
                            'attributes' => [
                                'id' => 'resource-templates',
                                'multiple' => true,
                                'class' => 'chosen-select',
                                'data-placeholder' => 'Select resource templates…', // @translate
                            ],
                        ])
                    ;
                }
            }
        }

        if (!$hasValues) {
            $fieldset
                ->add($element);
        }

        return $fieldset;
    }

    protected function textFieldset($number = 1)
    {
        $fieldset = new Fieldset('text');
        $filterFieldset = $this->getFilterFieldset();
        if ($filterFieldset->count()) {
            $fieldset
                ->add([
                    'name' => 'filters',
                    'type' => Element\Collection::class,
                    'options' => [
                        'label' => 'Filters', // @translate
                        'count' => $number,
                        'should_create_template' => true,
                        'allow_add' => true,
                        'target_element' => $filterFieldset,
                        'required' => false,
                    ],
                ])
            ;
        }
        return $fieldset;
    }

    /**
     * Add specific fields.
     *
     * This method is used for forms that extend this form.
     */
    protected function appendSpecificFields()
    {
    }

    protected function getItemSetsOptions()
    {
        $site = $this->getSite();
        $select = $this->getForm(\Omeka\Form\Element\ItemSetSelect::class, []);
        $select->setOptions([
            'query' => ['site_id' => $site ? $site->id() : null],
            'disable_group_by_owner' => (bool) $site,
        ]);
        return $select->getValueOptions();
    }

    protected function getFilterFieldset()
    {
        $options = $this->getOptions();
        return $this->getForm(FilterFieldset::class, $options);
    }

    protected function getForm($name, $options)
    {
        return $this->getFormElementManager()
            ->get($name, $options);
    }

    /**
     * @param SiteRepresentation $site
     * @return self
     */
    public function setSite(SiteRepresentation $site = null)
    {
        $this->site = $site;
        return $this;
    }

    /**
     * @return \Omeka\Api\Representation\SiteRepresentation
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * @param Setting|null $siteSetting
     * @return self
     */
    public function setSiteSetting(Setting $siteSetting = null)
    {
        $this->siteSetting = $siteSetting;
        return $this;
    }

    /**
     * @return \Omeka\View\Helper\Setting|null
     */
    public function getSiteSetting()
    {
        return $this->siteSetting;
    }

    /**
     * @param Object $formElementManager
     * @return self
     */
    public function setFormElementManager($formElementManager)
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    public function getFormElementManager()
    {
        return $this->formElementManager;
    }
}
