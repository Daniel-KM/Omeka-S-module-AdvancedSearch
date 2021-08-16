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

namespace AdvancedSearch\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceTemplateSelect;
use Omeka\View\Helper\Setting;

class MainSearchForm extends Form
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var SiteRepresentation
     */
    protected $site;

    /**
     * @var Setting
     */
    protected $siteSetting;

    protected $formElementManager;

    public function init(): void
    {
        // The id is different from the Omeka search to avoid issues in js. The
        // css should be adapted.
        $this
            ->setAttributes([
                'id' => 'form-search',
                'class' => 'form-search',
            ]);

        // Omeka adds a csrf automatically in \Omeka\Form\Initializer\Csrf.
        // Remove the csrf, because it is useless for a search form and the url
        // is not copiable (see the core search form that doesn't use it).
        foreach ($this->getElements() as $element) {
            $name = $element->getName();
            if (substr($name, -4) === 'csrf') {
                $this->remove($name);
                break;
            }
        }

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('advancedsearch_config');
        // TODO Throw exception when search page is not set.
        $searchConfigSettings = $searchConfig ? $searchConfig->settings() : [];

        $defaultFieldsOrder = [
            'q',
            'itemSet',
            'resourceClass',
            'resourceTemplate',
            'text',
            'submit',
        ];
        if (empty($searchConfigSettings['form']['fields_order'])) {
            $fieldsOrder = $defaultFieldsOrder;
        } else {
            // Set the required fields.
            $fieldsOrder = array_unique(array_merge(array_values($searchConfigSettings['form']['fields_order']) + ['q', 'submit']));
            // Replace "filters" that is a sub-fieldset of "text".
            $index = array_search('filters', $fieldsOrder);
            if ($index !== false) {
                $fieldsOrder[$index] = 'text';
            }
        }

        $this
            ->add([
                'name' => 'q',
                'type' => Element\AdvancedSearch::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'q',
                ],
            ])
        ;

        if (!empty($searchConfigSettings['autosuggest']['enable'])) {
            $autoSuggestUrl = empty($searchConfigSettings['autosuggest']['url'])
                // TODO Use url helper.
                ? $this->basePath . ($this->site ? '/s/' . $this->site->slug() : '/admin') . '/' . ($searchConfig ? $searchConfig->path() : 'search') . '/suggest'
                : $searchConfigSettings['autosuggest']['url'];
            $elementQ = $this->get('q')
                ->setAttribute('class', 'autosuggest')
                ->setAttribute('data-autosuggest-url', $autoSuggestUrl);
            if (!empty($searchConfigSettings['autosuggest']['url_param_name'])) {
                $elementQ
                    ->setAttribute('data-autosuggest-param-name', $searchConfigSettings['autosuggest']['url_param_name']);
            }
        }

        $appendItemSetFieldset = !empty($searchConfigSettings['form']['item_set_filter_type'])
            && !empty($searchConfigSettings['form']['item_set_id_field']);
        if ($appendItemSetFieldset) {
            $this
                ->add($this->itemSetFieldset($searchConfigSettings['form']['item_set_filter_type']));
        }

        $appendResourceClassFieldset = !empty($searchConfigSettings['form']['resource_class_filter_type'])
            && !empty($searchConfigSettings['form']['resource_class_id_field']);
        if ($appendResourceClassFieldset) {
            $this
                ->add($this->resourceClassFieldset($searchConfigSettings['form']['resource_class_filter_type']));
        }

        $appendResourceTemplateFieldset = !empty($searchConfigSettings['form']['resource_template_filter_type'])
            && !empty($searchConfigSettings['form']['resource_template_id_field']);
        if ($appendResourceTemplateFieldset) {
            $this
                ->add($this->resourceTemplateFieldset());
        }

        $appendTextFieldset = !empty($searchConfigSettings['form']['filters_max_number'])
            && !empty($searchConfigSettings['form']['filters']);
        if ($appendTextFieldset) {
            $this
                ->add($this->textFieldset($searchConfigSettings['form']['filters_max_number']));
        }

        $this->appendSpecificFields();

        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                ],
            ])
        ;

        // When a priority is set, elements are always first, so reverse the
        // list and set a high priority.
        foreach (array_reverse($fieldsOrder) as $index => $elementOrFieldset) {
            if ($this->has($elementOrFieldset)) {
                $this->setPriority($elementOrFieldset, $index + 10000);
            }
        }

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

    protected function itemSetFieldset($filterType = 'select'): Fieldset
    {
        $fieldset = new Fieldset('itemSet');
        return $fieldset
            ->setAttributes([
                'id' => 'search-item-sets',
            ])
            ->add([
                'name' => 'ids',
                'type' => $filterType === 'multi-checkbox'
                    ? Element\MultiCheckbox::class
                    : Element\Select::class,
                'options' => [
                    // For normal users, it is not "item sets", but "collections".
                    'label' => 'Collections', // @translate
                    'value_options' => $this->getItemSetsOptions($filterType !== 'multi-checkbox'),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'item-sets-ids',
                    'multiple' => true,
                    'class' => $filterType === 'multi-checkbox' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
        ;
    }

    protected function resourceClassFieldset($filterType = 'select'): Fieldset
    {
        // For an unknown reason, the ResourceClassSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('resourceClass');
        $fieldset->setAttributes([
            'id' => 'search-resource-classes',
        ]);

        /** @var \Omeka\Form\Element\ResourceClassSelect $element */
        $element = $this->formElementManager->get(ResourceClassSelect::class);
        $element
            ->setOptions([
                'label' => 'Resource classes', // @translate
                'term_as_value' => true,
                'empty_option' => '',
                // TODO Manage list of resource classes by site.
                'used_terms' => true,
                'query' => ['used' => true],
                'disable_group_by_vocabulary' => $filterType === 'select_flat',
            ]);

        /** @deprecated (Omeka v3.1): use option "disable_group_by_vocabulary" */
        if ($filterType === 'select_flat'
            && version_compare(\Omeka\Module::VERSION, '3.1', '<')
        ) {
            $valueOptions = $element->getValueOptions();
            $result = [];
            foreach ($valueOptions as $name => $vocabulary) {
                if (!is_array($vocabulary)) {
                    $result[$name] = $vocabulary;
                    continue;
                }
                if (empty($vocabulary['options'])) {
                    $result[$vocabulary['value']] = $vocabulary['label'];
                    continue;
                }
                foreach ($vocabulary['options'] as $term) {
                    $result[$term['value']] = $term['label'];
                }
            }
            natcasesort($result);
            $element = new Element\Select;
            $element
                ->setOptions([
                    'label' => 'Resource classes', // @translate
                    'empty_option' => '',
                    'value_options' => $result,
                ]);
        }

        $element
            ->setName('ids')
            ->setAttributes([
                'id' => 'resource-classes-ids',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select resource classes…', // @translate
            ]);

        $fieldset
            ->add($element);
        return $fieldset;
    }

    protected function resourceTemplateFieldset(): Fieldset
    {
        // For an unknown reason, the ResourceTemplateSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('resourceTemplate');
        $fieldset->setAttributes([
            'id' => 'search-resource-templates',
        ]);

        /** @var \Omeka\Form\Element\ResourceTemplateSelect $element */
        $element = $this->formElementManager->get(ResourceTemplateSelect::class);
        $element
            ->setName('ids')
            ->setOptions([
                'label' => 'Resource templates', // @translate
                'empty_option' => '',
                'disable_group_by_owner' => true,
                'used' => true,
                'query' => ['used' => true],
            ])
            ->setAttributes([
                'id' => 'resource-templates-ids',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select resource templates…', // @translate
            ]);

        $hasValues = false;
        if ($this->siteSetting && $this->siteSetting->__invoke('advancedsearch_restrict_templates', false)) {
            $values = $this->siteSetting->__invoke('advancedsearch_apply_templates', []);
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

    protected function textFieldset($number = 1): Fieldset
    {
        $fieldset = new Fieldset('text');
        $fieldset->setAttributes([
            'id' => 'search-text-filters',
        ]);

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
                    'attributes' => [
                        'id' => 'search-filters',
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
     * It should include the specific input filters.
     */
    protected function appendSpecificFields(): Form
    {
        return $this;
    }

    protected function getItemSetsOptions($byOwner = false): array
    {
        $select = $this->getForm(\Omeka\Form\Element\ItemSetSelect::class, []);
        if ($this->site) {
            $select->setOptions([
                'query' => ['site_id' => $this->site->id(), 'sort_by' => 'dcterms:title', 'sort_order' => 'asc'],
                'disable_group_by_owner' => true,
            ]);
            // By default, sort is case sensitive. So use a case insensitive sort.
            $valueOptions = $select->getValueOptions();
            natcasesort($valueOptions);
        } else {
            $select->setOptions([
                'query' => ['sort_by' => 'dcterms:title', 'sort_order' => 'asc'],
                'disable_group_by_owner' => !$byOwner,
            ]);
            $valueOptions = $select->getValueOptions();
        }
        return $valueOptions;
    }

    protected function getFilterFieldset(): FilterFieldset
    {
        $options = $this->getOptions();
        return $this->getForm(FilterFieldset::class, $options);
    }

    protected function getForm(string $name, ?array $options = null): \Laminas\Form\ElementInterface
    {
        return $this->formElementManager->get($name, $options);
    }

    public function setBasePath(string $basePath): Form
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function setSite(?SiteRepresentation $site): Form
    {
        $this->site = $site;
        return $this;
    }

    public function setSiteSetting(?Setting $siteSetting = null): Form
    {
        $this->siteSetting = $siteSetting;
        return $this;
    }

    public function setFormElementManager($formElementManager): Form
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }
}
