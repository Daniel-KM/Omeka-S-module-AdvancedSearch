<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau 2018-2023
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

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\Form\FormElementManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Form\Element as OmekaElement;
use Omeka\View\Helper\Setting;
use Reference\Mvc\Controller\Plugin\References;

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

    /**
     * @var \Laminas\Form\FormElementManager
     */
    protected $formElementManager;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Reference\Mvc\Controller\Plugin\References|null
     */
    protected $references;

    /**
     * @var array
     */
    protected $formSettings = [];

    /**
     * @var array
     */
    protected $listInputFilters = [];

    public function init(): void
    {
        // The id is different from the Omeka search to avoid issues in js. The
        // css should be adapted.
        $this
            ->setAttributes([
                'id' => 'form-search',
                'class' => 'search-form form-search',
            ]);

        // The attribute "form" is appended to all fields to simplify themes.

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
        unset($name);

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $this->formSettings = $searchConfig ? $searchConfig->settings() : [];

        // Check specific fields against all available fields.
        $availableFields = $this->getAvailableFields();

        // The main query is always the first element and submit the last one.
        // TODO Allow to order and to skip "q" (include it as a standard filter).

        $this
            ->add([
                'name' => 'q',
                'type' => Element\Search::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'q',
                    'form' => 'form-search',
                    'data-type-field' => 'q',
                ],
            ])
        ;

        $autoSuggestUrl = $this->formSettings['autosuggest']['url'] ?? null;
        if (!$autoSuggestUrl) {
            $suggester = $this->formSettings['autosuggest']['suggester'] ?? null;
            if ($suggester) {
                // TODO Use url helper.
                $autoSuggestUrl = $this->basePath . ($this->site ? '/s/' . $this->site->slug() : '/admin') . '/' . ($searchConfig ? $searchConfig->path() : 'search') . '/suggest';
            }
        }
        if ($autoSuggestUrl) {
            $elementQ = $this->get('q')
                ->setAttribute('class', 'autosuggest')
                ->setAttribute('data-autosuggest-url', $autoSuggestUrl);
            if (empty($suggester) && !empty($this->formSettings['autosuggest']['url_param_name'])) {
                $elementQ
                    ->setAttribute('data-autosuggest-param-name', $this->formSettings['autosuggest']['url_param_name']);
            }
        }

        foreach ($this->formSettings['form']['filters'] ?? [] as $filter) {
            if (empty($filter['field'])) {
                continue;
            }
            if (!isset($filter['type'])) {
                $filter['type'] = '';
            }
            if (!isset($filter['options'])) {
                $filter['options'] = [];
            }
            if (!isset($filter['label'])) {
                $filter['label'] = '';
            }

            $field = $filter['field'];
            $type = $filter['type'];

            $element = null;

            // Manage exceptions for special fields, mostly for internal engine.
            // TODO In fact, they are standard field with autosuggestion, so it will be fixed when autosuggestion (or short list) will be added.
            $isSpecialField = substr($filter['type'], 0, 5) === 'Omeka';
            if ($isSpecialField) {
                if (!isset($availableFields[$field]['from'])) {
                    continue;
                }
                $filter['type'] = trim(substr($filter['type'], 5), '/');
                switch ($availableFields[$field]['from']) {
                    case 'resource_name':
                    case 'resource_type':
                        $element = $this->searchResourceType($filter);
                        break;
                    case 'id':
                    case 'o:id':
                        $element = $this->searchId($filter);
                        break;
                    case 'is_public':
                        $element = $this->searchIsPublic($filter);
                        break;
                    case 'owner/o:id':
                        $element = $this->searchOwner($filter);
                        break;
                    case 'site/o:id':
                        $element = $this->searchSite($filter);
                        break;
                    // The select and module Search Solr use term by default,
                    // but the internal adapter manages terms automatically.
                    // TODO Clarify the select for resource class for internal.
                    case 'resource_class/o:id':
                    case 'resource_class/o:term':
                        $element = $this->searchResourceClass($filter);
                        break;
                    case 'resource_template/o:id':
                        $element = $this->searchResourceTemplate($filter);
                        break;
                    case 'item_set/o:id':
                        $element = $this->searchItemSet($filter);
                        break;
                    case 'item_sets_tree':
                        $element = $this->searchItemSetsTree($filter);
                        break;
                    default:
                        $method = 'search' . $type;
                        $element = method_exists($this, $method)
                            ? $this->$method($filter)
                            : $this->searchElement($filter);
                        break;
                }
            } else {
                $method = 'search' . $type;
                $element = method_exists($this, $method)
                    ? $this->$method($filter)
                    : $this->searchElement($filter);
            }

            if ($element) {
                $this
                    ->add($element);
            }
        }

        if (!empty($this->formSettings['form']['button_reset'])) {
            $this
                ->add([
                    'name' => 'reset',
                    'type' => Element\Button::class,
                    'options' => [
                        'label' => 'Reset fields', // @translate
                        'label_attributes' => [
                            'class' => 'search-reset',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'reset',
                        'form' => 'form-search',
                        'type' => 'reset',
                        'class' => 'search-reset',
                    ],
                ]);
        }

        $this
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Search', // @translate
                    'label_attributes' => [
                        'class' => 'search-submit',
                    ],
                ],
                'attributes' => [
                    'id' => 'submit',
                    'form' => 'form-search',
                    'type' => 'submit',
                    'class' => 'search-submit',
                ],
            ])
        ;

        $this->appendInputFilters();
    }

    /**
     * Add a default input element, represented as a text input.
     */
    protected function searchElement(array $filter): ?ElementInterface
    {
        $element = new Element($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', $filter['type'])
        ;
        return $element;
    }

    protected function searchAdvanced(array $filter): ?ElementInterface
    {
        if (empty($filter['max_number'])) {
            return null;
        }

        $filter['search_config'] = $this->getOption('search_config');

        /** @var \AdvancedSearch\Form\SearchFilter\Advanced $advanced */
        $advanced = $this->formElementManager->get(SearchFilter\Advanced::class, $filter);
        if (!$advanced->count()) {
            return null;
        }

        $element = new Element\Collection('filter');
        $element
            ->setLabel((string) $filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'filter')
            ->setOptions([
                'label' => $filter['label'],
                'count' => (int) $filter['max_number'],
                'should_create_template' => true,
                'allow_add' => true,
                'target_element' => $advanced,
                'required' => false,
            ])
            ->setAttributes([
                'id' => 'search-filters',
                'form' => 'form-search',
            ])
        ;

        return $element;
    }

    protected function searchCheckbox(array $filter): ?ElementInterface
    {
        $element = new Element\Checkbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'checkbox')
        ;
        if (!empty($filter['options']) && count($filter['options']) === 2) {
            $element->setOptions([
                'unchecked_value' => $filter['options'][0],
                'checked_value' => $filter['options'][1],
            ]);
        }
        return $element;
    }

    protected function searchDateRange(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset($filter['field']);
        $fieldset
            ->setLabel($filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'daterange')
            ->add([
                'name' => 'from',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'From', // @translate
                ],
                'attributes' => [
                    'form' => 'form-search',
                    'placeholder' => 'YYYY', // @translate
                ],
            ])
            ->add([
                'name' => 'to',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'To', // @translate
                ],
                'attributes' => [
                    'form' => 'form-search',
                    'placeholder' => 'YYYY', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    protected function searchHidden(array $filter): ?ElementInterface
    {
        $value = $filter['options'] === [] ? '' : reset($filter['options']);
        $element = new Element\Hidden($filter['field']);
        $element
            ->setValue($value)
            ->setAttribute('form', 'form-search')
        ;
        return $element;
    }

    protected function searchMultiCheckbox(array $filter): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $element = new AdvancedSearchElement\OptionalMultiCheckbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setValueOptions($valueOptions)
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'multicheckbox')
        ;
        return $element;
    }

    protected function searchMultiSelect(array $filter): ?ElementInterface
    {
        $filter['attributes']['multiple'] = true;
        return $this->searchSelect($filter, 'multiselect');
    }

    protected function searchMultiSelectFlat(array $filter): ?ElementInterface
    {
        $filter['attributes']['multiple'] = true;
        return $this->searchSelectFlat($filter, 'multiselectflat');
    }

    /**
     * Simplify the creation of repeatable text input fields.
     */
    protected function searchMultiText(array $filter): ?ElementInterface
    {
        $element = new AdvancedSearchElement\MultiText($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'multitext')
        ;
        return $element;
    }

    protected function searchNumber(array $filter): ?ElementInterface
    {
        $element = new Element\Number($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'number')
        ;
        return $element;
    }

    protected function searchRadio(array $filter): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $element = new AdvancedSearchElement\OptionalRadio($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setValueOptions($valueOptions)
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'radio')
        ;
        return $element;
    }

    protected function searchSelect(array $filter, $fieldType = 'select'): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $valueOptions = ['' => ''] + $valueOptions;

        $attributes = $filter['attributes'] ?? [];
        $attributes['form'] = 'form-search';
        $attributes['class'] ??= 'chosen-select';
        $attributes['placeholder'] ??= '';
        $attributes['data-placeholder'] ??= ' ';
        $attributes['data-field-type'] = $fieldType;

        $element = new AdvancedSearchElement\OptionalSelect($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setOptions([
                'value_options' => $valueOptions,
                'empty_option' => '',
            ])
            ->setAttributes($attributes)
        ;
        return $element;
    }

    protected function searchSelectFlat(array $filter): ?ElementInterface
    {
        return $this->searchSelect($filter, 'selectflat');
    }

    protected function searchText(array $filter): ?ElementInterface
    {
        $element = new Element\Text($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttribute('form', 'form-search')
            ->setAttribute('data-field-type', 'text')
        ;
        return $element;
    }

    /**
     * The resource type is the main type for end user, but the name in omeka.
     */
    protected function searchResourceType(array $filter): ?ElementInterface
    {
        $element = $filter['type'] === 'MultiCheckbox'
            ? AdvancedSearchElement\OptionalMultiCheckbox('resource_type')
            : AdvancedSearchElement\OptionalSelect('resource_type');
        $element
            ->setOptions([
                'label' => $filter['label'], // @translate
                'value_options' => [
                    'items' => 'Items',
                    'item_sets' => 'Item sets',
                ],
                'empty_option' => '',
            ])
            ->setAttributes([
                'id' => 'search-resource-type',
                'form' => 'form-search',
                'multiple' => true,
                'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                'data-placeholder' => 'Select resource type…', // @translate
            ])
        ;

        return $element;
    }

    protected function searchId(array $filter): ?ElementInterface
    {
        $element = $filter['type'] === 'MultiText'
            ? new AdvancedSearchElement\MultiText('id')
            : new Element\Text('id');
        $element
            ->setOptions([
                'label' => $filter['label'], // @translate
            ])
            ->setAttributes([
                'id' => 'search-id',
                'form' => 'form-search',
                'data-field-type' => $filter['type'] === 'MultiText' ? 'multitext' : 'text',
            ])
        ;

        return $element;
    }

    protected function searchIsPublic(array $filter): ?ElementInterface
    {
        $element = new Element\Checkbox('is_public');
        $element
            ->setOptions([
                'label' => $filter['label'], // @translate
            ])
            ->setAttributes([
                'id' => 'search-is-public',
                'form' => 'form-search',
                'data-field-type' => 'checkbox',
            ])
        ;

        return $element;
    }

    protected function searchOwner(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('owner');
        $fieldset
            ->setAttributes([
                'id' => 'search-owners',
                'form' => 'form-search',
                'data-field-type' => 'owner',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? AdvancedSearchElement\OptionalMultiCheckbox::class
                    : AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->getOwnerOptions(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-owner-id',
                    'form' => 'form-search',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select owners…', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    protected function searchSite(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('site');
        $fieldset
            ->setAttributes([
                'id' => 'search-sites',
                'form' => 'form-search',
                'data-field-type' => 'site',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? AdvancedSearchElement\OptionalMultiCheckbox::class
                    : AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->getSiteOptions(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-site-id',
                    'form' => 'form-search',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select sites…', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    protected function searchResourceClass(array $filter): ?ElementInterface
    {
        // For an unknown reason, the ResourceClassSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('class');
        $fieldset->setAttributes([
            'id' => 'search-classes',
            'form' => 'form-search',
            'data-field-type' => 'class',
        ]);

        /** @var \Omeka\Form\Element\ResourceClassSelect $element */
        $element = $this->formElementManager->get(OmekaElement\ResourceClassSelect::class);
        $element
            ->setOptions([
                'label' => $filter['label'], // @translate
                'term_as_value' => true,
                'empty_option' => '',
                // TODO Manage list of resource classes by site.
                'used_terms' => true,
                'query' => ['used' => true],
                'disable_group_by_vocabulary' => $filter['type'] === 'SelectFlat',
            ]);

        /* @deprecated (Omeka v3.1): use option "disable_group_by_vocabulary" */
        if ($filter['type'] === 'SelectFlat'
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
            $element = new AdvancedSearchElement\OptionalSelect;
            $element
                ->setOptions([
                    'label' => $filter['label'], // @translate
                    'empty_option' => '',
                    'value_options' => $result,
                ]);
        }

        $element
            ->setName('id')
            ->setAttributes([
                'id' => 'search-class-id',
                'form' => 'form-search',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select classes…', // @translate
            ]);

        $fieldset
            ->add($element);

        $this->listInputFilters[] = 'resource_classes';

        return $fieldset;
    }

    protected function searchResourceTemplate(array $filter): ?ElementInterface
    {
        // For an unknown reason, the ResourceTemplateSelect can not be added
        // directly to a fieldset (factory is not used).

        $fieldset = new Fieldset('template');
        $fieldset->setAttributes([
            'id' => 'search-templates',
            'form' => 'form-search',
            'data-field-type' => 'template',
        ]);

        /** @var \Omeka\Form\Element\ResourceTemplateSelect $element */
        $element = $this->formElementManager->get(OmekaElement\ResourceTemplateSelect::class);
        $element
            ->setName('id')
            ->setOptions([
                'label' => $filter['label'], // @translate
                'empty_option' => '',
                'disable_group_by_owner' => true,
                'used' => true,
                'query' => ['used' => true],
            ])
            ->setAttributes([
                'id' => 'search-template-id',
                'form' => 'form-search',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select templates…', // @translate
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
                            'name' => 'id',
                            'type' => AdvancedSearchElement\OptionalSelect::class,
                            'options' => [
                                'label' => $filter['label'], // @translate
                                'value_options' => $values,
                                'empty_option' => '',
                            ],
                            'attributes' => [
                                'id' => 'search-template-id',
                                'form' => 'form-search',
                                'multiple' => true,
                                'class' => 'chosen-select',
                                'data-placeholder' => 'Select templates…', // @translate
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

        $this->listInputFilters[] = 'resource_templates';

        return $fieldset;
    }

    protected function searchItemSet(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('item_set');
        $fieldset
            ->setAttributes([
                'id' => 'search-item-sets',
                'form' => 'form-search',
                'data-field-type' => 'itemset',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? AdvancedSearchElement\OptionalMultiCheckbox::class
                    : AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->getItemSetsOptions($filter['type'] !== 'MultiCheckbox'),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-item-set-id',
                    'form' => 'form-search',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    // End users understand "collections" more than "item sets".
                    'data-placeholder' => 'Select collections…', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    protected function searchItemSetsTree(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('item_sets_tree');
        $fieldset
            ->setAttributes([
                'id' => 'search-item-sets-tree',
                'form' => 'form-search',
                'data-field-type' => 'itemset',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? AdvancedSearchElement\OptionalMultiCheckbox::class
                    : AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->getItemSetsTreeOptions($filter['type'] !== 'MultiCheckbox'),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-item-sets-tree',
                    'form' => 'form-search',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    // End users understand "collections" more than "item sets".
                    'data-placeholder' => 'Select collections…', // @translate
                ],
            ])
        ;

        return $fieldset;
    }

    /**
     * Add input filters.
     *
     * It is recommended to use an optional element to avoid input filters: this
     * is a search form, everything is generally optional.
     */
    protected function appendInputFilters(): Form
    {
        $inputFilter = $this->getInputFilter();
        if (in_array('resource_classes', $this->listInputFilters)) {
            $inputFilter
                ->get('class')
                ->add([
                    'name' => 'id',
                    'required' => false,
                ]);
        }
        if (in_array('resource_templates', $this->listInputFilters)) {
            $inputFilter
                ->get('template')
                ->add([
                    'name' => 'id',
                    'required' => false,
                ]);
        }
        return $this;
    }

    /**
     * @todo Improve DataTextarea or explode options here with a ":".
     */
    protected function prepareValueOptions(array $filter): array
    {
        $options = $filter['options'];
        if ($options === null || $options === [] || $options === '') {
            return $this->listValuesForProperty($filter['field']);
        }
        if (is_string($options)) {
            $options = array_filter(array_map('trim', explode($options)), 'strlen');
        } elseif (!is_array($options)) {
            return [(string) $options => $options];
        }
        // Avoid issue with duplicates.
        $options = array_filter(array_keys(array_flip($options)), 'strlen');
        return array_combine($options, $options);
    }

    /**
     * Get an associative list of all unique values of a property.
     *
     * @todo Use the real search engine, not the internal one.
     * @todo Use a suggester for big lists.
     * @todo Support any resources, not only item.
     *
     * @todo Factorize with \AdvancedSearch\Querier\InternalQuerier::fillFacetResponse()
     * @see \AdvancedSearch\Querier\InternalQuerier::fillFacetResponse()
     */
    protected function listValuesForProperty(string $field): array
    {
        // Check if the field is a special or a multifield.

        $searchEngine = $this->getOption('search_config')->engine();

        $metadataFieldsToNames = [
            'resource_name' => 'resource_type',
            'resource_type' => 'resource_type',
            'is_public' => 'is_public',
            'owner_id' => 'o:owner',
            'site_id' => 'o:site',
            'resource_class_id' => 'o:resource_class',
            'resource_template_id' => 'o:resource_template',
            'item_set_id' => 'o:item_set',
        ];

        // Convert multi-fields into a list of property terms.
        // Normalize search query keys as omeka keys for items and item sets.
        $multifields = $searchEngine->settingAdapter('multifields', []);
        $fields = [];
        $fields[$field] = $metadataFieldsToNames[$field]
            ?? $this->getPropertyTerm($field)
            ?? $multifields[$field]['fields']
            ?? $field;

        if ($this->references) {
            $list = $this->references->__invoke(
                $fields,
                $this->site ? ['site_id' => $this->site->id()] : [],
                ['output' => 'associative']
            )->list();
            $list = array_keys(reset($list)['o:references']);
        } else {
            // Simplified from References::listDataForProperty().
            $fields = reset($fields);
            if (!is_array($fields)) {
                $fields = [$fields];
            }
            $propertyIds = array_intersect_key($this->getPropertyIds(), array_flip($fields));
            if (!$propertyIds) {
                return [];
            }
            $qb = $this->entityManager->createQueryBuilder();
            $expr = $qb->expr();
            $qb
                ->select('COALESCE(value.value, valueResource.title, value.uri) AS val')
                ->from(\Omeka\Entity\Value::class, 'value')
                // This join allow to check visibility automatically too.
                ->innerJoin(\Omeka\Entity\Item::class, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
                // The values should be distinct for each type.
                ->leftJoin(\Omeka\Entity\Item::class, 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
                ->andWhere($expr->in('value.property', ':properties'))
                ->setParameter('properties', implode(',', $propertyIds))
                ->groupBy('val')
                ->orderBy('val', 'asc')
            ;
            $list = array_column($qb->getQuery()->getScalarResult(), 'val', 'val');
            // Fix false empty duplicate or values without title.
            $list = array_keys(array_flip($list));
            unset($list['']);
        }
        return array_combine($list, $list);
    }

    protected function getItemSetsOptions($byOwner = false): array
    {
        /** @var \Omeka\Form\Element\ItemSetSelect $select */
        $select = $this->formElementManager->get(\Omeka\Form\Element\ItemSetSelect::class, []);
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

    protected function getItemSetsTreeOptions($byOwner = false): array
    {
        if (!$this->formElementManager->has('itemSetsTreeSelect')) {
            return [];
        }

        /** @var \Omeka\Form\Element\ItemSetSelect $select */
        $select = $this->formElementManager->get(\Omeka\Form\Element\ItemSetSelect::class, []);
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

    protected function getOwnerOptions(): array
    {
        /** @var \Omeka\Form\Element\UserSelect $select */
        $select = $this->formElementManager->get(\Omeka\Form\Element\UserSelect::class, []);
        return $select->getValueOptions();
    }

    protected function getSiteOptions(): array
    {
        /** @var \Omeka\Form\Element\SiteSelect $select */
        $select = $this->formElementManager->get(\Omeka\Form\Element\SiteSelect::class, []);
        return $select->setOption('disable_group_by_owner', true)->getValueOptions();
    }

    protected function getAvailableFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        if (empty($searchAdapter)) {
            return [];
        }
        return $searchAdapter->setSearchEngine($searchEngine)->getAvailableFields();
    }

    /**
     * Get a property term or id.
     */
    protected function getPropertyTerm($termOrId): ?string
    {
        return $this->getOption('search_config')->getServiceLocator()->get('ViewHelperManager')
            ->get('easyMeta')->propertyTerms($termOrId);
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds(): array
    {
        return $this->getOption('search_config')->getServiceLocator()->get('ViewHelperManager')
            ->get('easyMeta')->propertyIds();
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

    public function setFormElementManager(FormElementManager $formElementManager): Form
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    public function setEntityManager(EntityManager $entityManager): Form
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    public function setReferences(?References $references): Form
    {
        $this->references = $references;
        return $this;
    }
}
