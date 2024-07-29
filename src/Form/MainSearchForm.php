<?php declare(strict_types=1);

/*
 * Copyright Daniel Berthereau 2018-2024
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
use Common\Form\Element as CommonElement;
use Common\Stdlib\EasyMeta;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\Form\FormElementManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Form\Element as OmekaElement;
use Omeka\Settings\Settings;
use Omeka\View\Helper\Setting;

class MainSearchForm extends Form
{
    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Form\FormElementManager
     */
    protected $formElementManager;

    /**
     * @var \ItemSetsTree\ViewHelper\ItemSetsTree
     */
    protected $itemSetsTree;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Omeka\View\Helper\Setting
     */
    protected $siteSetting;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $site;

    /**
     * @var array
     */
    protected $elementAttributes = [];

    /**
     * @var array
     */
    protected $formSettings = [];

    /**
     * @var array
     */
    protected $listInputFilters = [];

    /**
     * @var bool
     */
    protected $skipValues = false;

    /**
     * Variant may be "quick" or "simple", or "csrf" (internal use).
     *
     * @var string
     */
    protected $variant = null;

    public function init(): void
    {
        // The attribute "form" is appended to all fields to simplify themes,
        // unless the settings skip it.

        // The id is different from the Omeka search to avoid issues in js. The
        // css should be adapted.
        $this
            ->setAttributes([
                'id' => 'form-search',
                'class' => 'search-form form-search',
            ]);

        $this->searchConfig = $this->getOption('search_config');

        $this->skipValues = (bool) $this->getOption('skip_values');

        $this->variant = $this->getOption('variant');
        $hasVariant = in_array($this->variant, ['quick', 'simple', 'csrf']);

        $this->formSettings = $this->searchConfig ? $this->searchConfig->settings() : [];

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

        // TODO Currently, the csrf is removed, so it is never checked.
        if ($this->variant === 'csrf') {
            return;
        }

        // Check specific fields against all available fields.
        // For speed, don't get available fields with variants "simple" and "csrf".
        // TODO Check the required options for variant "quick" to skip available fields early.
        $availableFields = in_array($this->variant, ['simple', 'csrf']) ? [] : $this->getAvailableFields();

        $this->elementAttributes = empty($this->formSettings['form']['attribute_form'])
            ? []
            : ['form' => 'form-search'];

        // The main query is always the first element and submit the last one.
        // TODO Allow to order and to skip "q" (include it as a standard filter).

        $this
            ->add([
                'name' => 'q',
                'type' => Element\Search::class,
                'options' => [
                    'label' => $hasVariant ? null : 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'q',
                    'data-type-field' => 'q',
                    'placeholder' => 'Search',
                    'aria-label' => 'Search',
                ] + $this->elementAttributes,
            ])
        ;

        $autoSuggestUrl = $this->formSettings['autosuggest']['url'] ?? null;
        if (!$autoSuggestUrl) {
            $suggester = $this->formSettings['autosuggest']['suggester'] ?? null;
            if ($suggester) {
                // TODO Use url helper?
                $autoSuggestUrl = $this->basePath
                    . ($this->site ? '/s/' . $this->site->slug() : '/admin')
                    . '/' . ($this->searchConfig ? $this->searchConfig->slug() : 'search')
                    . '/suggest';
            }
        }
        if ($autoSuggestUrl) {
            $elementQ = $this->get('q')
                ->setAttribute('class', 'autosuggest')
                ->setAttribute('data-autosuggest-url', $autoSuggestUrl);
            if (!empty($this->formSettings['autosuggest']['fill_input'])) {
                $elementQ
                    ->setAttribute('data-autosuggest-fill-input', '1');
            }
            if (empty($suggester) && !empty($this->formSettings['autosuggest']['url_param_name'])) {
                $elementQ
                    ->setAttribute('data-autosuggest-param-name', $this->formSettings['autosuggest']['url_param_name']);
            }
        }

        // Add the button for record or full text search.
        $recordOrFullText = in_array($this->variant, ['simple', 'csrf']) ? null : ($this->formSettings['search']['fulltext_search'] ?? null);
        $this->appendRecordOrFullText($recordOrFullText);

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

            // Don't create useless elements.
            // In particular, it allows to skip creation of selects, that is
            // slow for now in big databases.
            if ($hasVariant
                // The type may be missing.
                && !in_array($type, ['Hidden', 'hidden', \Laminas\Form\Element\Hidden::class, 'Csrf', 'csrf', \Laminas\Form\Element\Csrf::class])
                // No need to check the field name here: "q", "rft" and "submit"
                // are managed separately.
                // TODO Required elements for "quick" cannot be checked for now.
            ) {
                continue;
            }

            $element = null;

            // Manage exceptions for special fields, mostly for internal engine.
            // TODO In fact, they are standard fields with autosuggestion, so it will be fixed when autosuggestion (or short list) will be added.
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
                    case 'access':
                        $element = $this->searchAccess($filter);
                        break;
                    case 'item_sets_tree':
                        $element = $this->searchItemSetsTree($filter);
                        break;
                    case 'thesaurus':
                        $element = $this->searchThesaurus($filter);
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

        if (!empty($this->formSettings['form']['button_reset']) && !in_array($this->variant, ['quick', 'simple', 'csrf'])) {
            $this
                ->add([
                    'name' => 'reset',
                    'type' => Element\Button::class,
                    'options' => [
                        'label' => $this->formSettings['form']['label_reset'] ?: 'Reset fields', // @translate
                        'label_attributes' => [
                            'class' => 'search-reset',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'search-reset',
                        'type' => 'reset',
                        'class' => 'search-reset',
                    ] + $this->elementAttributes,
                ]);
        }

        if (!empty($this->formSettings['form']['button_submit'])) {
            $this
                ->add([
                    'name' => 'submit',
                    'type' => Element\Button::class,
                    'options' => [
                        'label' => $this->formSettings['form']['label_submit'] ?: 'Search', // @translate
                        'label_attributes' => [
                            'class' => 'search-submit',
                        ],
                    ],
                    'attributes' => [
                        'id' => 'search-submit',
                        'type' => 'submit',
                        'class' => 'search-submit',
                    ] + $this->elementAttributes,
                ]);
        }

        $this->appendInputFilters();
    }

    /**
     * Add a simple filter to limit search to record or not.
     */
    protected function appendRecordOrFullText(?string $recordOrFullText): self
    {
        switch ($recordOrFullText) {
            case 'fulltext_checkbox':
                $element = new Element\Checkbox('rft');
                $element
                    ->setLabel('Search full text') // @translate
                    ->setOptions([
                        'unchecked_value' => 'record',
                        'checked_value' => 'all',
                    ])
                    ->setAttributes($this->elementAttributes)
                    ->setAttribute('id', 'rft')
                ;
                return $this->add($element);
            case 'record_checkbox':
                $element = new Element\Checkbox('rft');
                $element
                    ->setLabel('Record only') // @translate
                    ->setOptions([
                        'unchecked_value' => 'all',
                        'checked_value' => 'record',
                    ])
                    ->setAttributes($this->elementAttributes)
                    ->setAttribute('id', 'rft')
                ;
                return $this->add($element);
            case 'fulltext_radio':
                $element = new CommonElement\OptionalRadio('rft');
                $element
                    // The empty label allows to have a fieldset wrapping radio.
                    ->setLabel(' ')
                    ->setValueOptions([
                        'all' => 'Full text', // @ŧranslate
                        'record' => 'Record only', // @ŧranslate
                    ])
                    ->setAttributes($this->elementAttributes)
                    ->setAttribute('id', 'rft')
                    ->setValue('all')
                ;
                return $this->add($element);
            case 'record_radio':
                $element = new CommonElement\OptionalRadio('rft');
                $element
                    // The empty label allows to have a fieldset wrapping radio.
                    ->setLabel(' ')
                    ->setValueOptions([
                        'record' => 'Record only', // @ŧranslate
                        'all' => 'Full text', // @ŧranslate
                    ])
                    ->setAttributes($this->elementAttributes)
                    ->setAttribute('id', 'rft')
                    ->setValue('record')
                ;
                return $this->add($element);
            default:
                return $this;
        }
    }

    /**
     * Add a default input element, represented as a text input.
     */
    protected function searchElement(array $filter): ?ElementInterface
    {
        $element = new Element($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttributes($this->elementAttributes)
            ->setAttribute('data-field-type', $filter['type'])
        ;
        return $element;
    }

    protected function searchAdvanced(array $filter): ?ElementInterface
    {
        // TODO Use the advanced settings directly from the search config.
        if (empty($this->formSettings['form']['advanced'])) {
            return null;
        }

        $advanced = $this->formSettings['form']['advanced'];

        $defaultNumber = isset($advanced['default_number']) ? (int) $advanced['default_number'] : 1;
        $maxNumber = isset($advanced['max_number']) ? (int) $advanced['max_number'] : 10;
        if (!$defaultNumber && !$maxNumber) {
            return null;
        }

        $filter += $advanced;
        $filter['search_config'] = $this->getOption('search_config');

        /** @var \AdvancedSearch\Form\SearchFilter\Advanced $advanced */
        $advanced = $this->formElementManager->get(SearchFilter\Advanced::class, $filter);
        if (!$advanced->count()) {
            return null;
        }

        $element = new Element\Collection('filter');
        $element
            ->setLabel((string) $filter['label'])
            ->setOptions([
                'label' => $filter['label'],
                // TODO The max number is required to fill the current query?
                'count' => max($defaultNumber, $maxNumber),
                'should_create_template' => true,
                'allow_add' => true,
                'target_element' => $advanced,
                'required' => false,
            ])
            ->setAttributes([
                'id' => 'search-filters',
                'class' => 'search-filters-advanced',
                'data-field-type' => 'filter',
                // TODO Remove this attribute data and use only search config.
                'data-count-default' => $defaultNumber,
                'data-count-max' => $maxNumber,
            ] + $this->elementAttributes)
        ;

        return $element;
    }

    protected function searchCheckbox(array $filter): ?ElementInterface
    {
        $element = new Element\Checkbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttributes($this->elementAttributes)
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
            ->setAttributes($this->elementAttributes)
            ->setAttribute('data-field-type', 'daterange')
            ->add([
                'name' => 'from',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'From', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'YYYY', // @translate
                ] + $this->elementAttributes,
            ])
            ->add([
                'name' => 'to',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'To', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'YYYY', // @translate
                ] + $this->elementAttributes,
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
            ->setAttributes($this->elementAttributes)
        ;
        return $element;
    }

    protected function searchMultiCheckbox(array $filter): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $element = new CommonElement\OptionalMultiCheckbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setValueOptions($valueOptions)
            ->setAttributes($this->elementAttributes)
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
            ->setAttributes($this->elementAttributes)
            ->setAttribute('data-field-type', 'multitext')
        ;
        return $element;
    }

    protected function searchNumber(array $filter): ?ElementInterface
    {
        $element = new Element\Number($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setAttributes($this->elementAttributes)
            ->setAttribute('data-field-type', 'number')
        ;
        return $element;
    }

    protected function searchRadio(array $filter): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $element = new CommonElement\OptionalRadio($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setValueOptions($valueOptions)
            ->setAttributes($this->elementAttributes)
            ->setAttribute('data-field-type', 'radio')
        ;
        return $element;
    }

    protected function searchSelect(array $filter, $fieldType = 'select'): ?ElementInterface
    {
        $valueOptions = $this->prepareValueOptions($filter);
        $valueOptions = ['' => ''] + $valueOptions;

        $attributes = $filter['attributes'] ?? [];
        $attributes += $this->elementAttributes;
        $attributes['class'] ??= 'chosen-select';
        $attributes['placeholder'] ??= '';
        $attributes['data-placeholder'] ??= ' ';
        $attributes['data-field-type'] = $fieldType;

        $element = new CommonElement\OptionalSelect($filter['field']);
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
            ->setAttributes($this->elementAttributes)
            ->setAttribute('data-field-type', 'text')
        ;
        return $element;
    }

    /**
     * Manage hierachical values for module Thesaurus.
     *
     * @see \AdvancedSearch\Form\MainSearchForm::searchThesaurus()
     * @see \AdvancedSearch\View\Helper\AbstractFacetTree::thesaurusQuick()
     */
    protected function searchThesaurus(array $filter): ?ElementInterface
    {
        // No fallback when the thesaurus select is not present, because the
        // collection id or the customvocab id are not known.
        if (!$this->formElementManager->has(\Thesaurus\Form\Element\ThesaurusSelect::class)) {
            // TODO Add a fallback for ThesaurusSelect.
            return null;
        }

        $filterOptions = $filter['options'];
        if (empty($filterOptions['id']) && empty($filterOptions['thesaurus'])) {
            $thesaurusId = (int) reset($filterOptions);
            $k = key($filterOptions);
            unset($filterOptions[$k]);
        } else {
            $thesaurusId = (int) ($filterOptions['thesaurus'] ?? $filterOptions['id'] ?? 0);
            unset($filterOptions['id'], $filterOptions['thesaurus']);
        }
        if (!$thesaurusId) {
            return null;
        }
        $filterOptions['thesaurus'] = $thesaurusId;
        // Set ascendance to true by default, else the type Thesaurus is useless
        // anyway.
        $filterOptions['ascendance'] = !in_array($filterOptions['ascendance'] ?? null, [0, false, '0', 'false'], true);

        // A thesaurus search should be like an advanced filter, because the
        // search is done on item id, not value text. The issue is only on
        // internal search, because the index may be managed in solr.
        // So the form adapter manage it directly via main key "thesaurus".

        $fieldset = new Fieldset('thesaurus');
        $fieldset
            ->setAttributes([
                'id' => 'search-thesaurus',
                'data-field-type' => 'thesaurus',
            ] + $this->elementAttributes);

        /** @var \Thesaurus\Form\Element\ThesaurusSelect::class $element */
        $element = $this->formElementManager->get(\Thesaurus\Form\Element\ThesaurusSelect::class);
        $element
            ->setName($filter['field'])
            ->setOptions([
                'label' => $filter['label'], // @translate
                'empty_option' => '',
            ] + $filterOptions)
            ->setAttributes([
                'id' => 'search-thesaurus',
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => ' ',
            ] + $this->elementAttributes)
        ;

        $fieldset
            ->add($element);

        return $fieldset;
    }

    /**
     * The resource type is the main type for end user, but the name in omeka.
     */
    protected function searchResourceType(array $filter): ?ElementInterface
    {
        $element = $filter['type'] === 'MultiCheckbox'
            ? new CommonElement\OptionalMultiCheckbox('resource_type')
            : new CommonElement\OptionalSelect('resource_type');
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
                'multiple' => true,
                'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                'data-placeholder' => 'Select resource type…', // @translate
            ] + $this->elementAttributes)
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
                'data-field-type' => $filter['type'] === 'MultiText' ? 'multitext' : 'text',
            ] + $this->elementAttributes)
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
                'data-field-type' => 'checkbox',
            ] + $this->elementAttributes)
        ;

        return $element;
    }

    protected function searchOwner(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('owner');
        $fieldset
            ->setAttributes([
                'id' => 'search-owners',
                'data-field-type' => 'owner',
            ] + $this->elementAttributes)
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->getOwnerOptions(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-owner-id',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select owners…', // @translate
                ] + $this->elementAttributes,
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
                'data-field-type' => 'site',
            ] + $this->elementAttributes)
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->getSiteOptions(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-site-id',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select sites…', // @translate
                ] + $this->elementAttributes,
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
            'data-field-type' => 'class',
        ] + $this->elementAttributes);

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
            && !$this->skipValues
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
            $element = new CommonElement\OptionalSelect;
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
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select classes…', // @translate
            ] + $this->elementAttributes);

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
            'data-field-type' => 'template',
        ] + $this->elementAttributes);

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
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select templates…', // @translate
            ] + $this->elementAttributes);

        $hasValues = false;
        if ($this->siteSetting
            && $this->siteSetting->__invoke('advancedsearch_restrict_templates', false)
            && !$this->skipValues
        ) {
            $values = $this->siteSetting->__invoke('advancedsearch_apply_templates', []);
            if ($values) {
                $values = array_intersect_key($element->getValueOptions(), array_flip($values));
                $hasValues = (bool) $values;
                if ($hasValues) {
                    $fieldset
                        ->add([
                            'name' => 'id',
                            'type' => CommonElement\OptionalSelect::class,
                            'options' => [
                                'label' => $filter['label'], // @translate
                                'value_options' => $values,
                                'empty_option' => '',
                            ],
                            'attributes' => [
                                'id' => 'search-template-id',
                                'multiple' => true,
                                'class' => 'chosen-select',
                                'data-placeholder' => 'Select templates…', // @translate
                            ] + $this->elementAttributes,
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
                'data-field-type' => 'itemset',
            ] + $this->elementAttributes)
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->getItemSetsOptions($filter['type'] !== 'MultiCheckbox'),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-item-set-id',
                    'multiple' => true,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    // End users understand "collections" more than "item sets".
                    'data-placeholder' => 'Select collections…', // @translate
                ] + $this->elementAttributes,
            ])
        ;

        return $fieldset;
    }

    /**
     * Manage hierachical item sets for module Item Sets Tree.
     */
    protected function searchItemSetsTree(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('item_sets_tree');
        $fieldset
            ->setAttributes([
                'id' => 'search-item-sets-tree',
                'data-field-type' => 'itemset',
            ] + $this->elementAttributes)
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->getItemSetsTreeOptions($filter['type'] !== 'MultiCheckbox'),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-item-sets-tree',
                    'multiple' => false,
                    'class' => $filter['type'] === 'MultiCheckbox' ? '' : 'chosen-select',
                    // End users understand "collections" more than "item sets".
                    'data-placeholder' => 'Select collections…', // @translate
                ] + $this->elementAttributes,
            ])
        ;

        return $fieldset;
    }

    /**
     * Manage access levels for module Access.
     */
    protected function searchAccess(array $filter): ?ElementInterface
    {
        $valueOptions = $this->settings->get('access_property_levels', [
            'free' => 'free',
            'reserved' => 'reserved',
            // 'protected' => 'protected',
            'forbidden' => 'forbidden',
        ]);
        unset($valueOptions['protected']);

        $fieldset = new Fieldset('access');
        $fieldset
            ->setAttributes([
                'id' => 'search-access',
                'data-field-type' => 'access',
            ] + $this->elementAttributes)
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'Radio'
                    ? CommonElement\OptionalRadio::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $valueOptions,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'search-access',
                    // 'multiple' => false,
                    'class' => $filter['type'] === 'Radio' ? '' : 'chosen-select',
                    'data-placeholder' => 'Select access…', // @translate
                ] + $this->elementAttributes,
            ])
        ;

        return $fieldset;
    }

    protected function searchTree(array $filter): ?ElementInterface
    {
        return $this->searchItemSetsTree($filter);
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
        if ($this->skipValues) {
            return [];
        }

        $options = $filter['options'];
        if ($options === null || $options === [] || $options === '') {
            return $this->listValuesForProperty($filter['field']);
        }
        if (is_string($options)) {
            // TODO Explode may use another string than "|".
            $options = array_filter(array_map('trim', explode('|', $options)), 'strlen');
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
     * Note: In version previous 3.4.15, the module Reference was used, that
     * managed languages, but a lot slower for big databases.
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
            ?? $this->easyMeta->propertyTerm($field)
            ?? $multifields[$field]['fields']
            ?? $field;

        // Simplified from References::listDataForProperty().
        /** @see \Reference\Mvc\Controller\Plugin\References::listDataForProperties() */
        $fields = reset($fields);
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        $propertyIds = $this->easyMeta->propertyIds($fields);
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

    /**
     * @todo Use form element itemSetsTreeSelect when exists (only a view helper for now).
     * @see \ItemSetsTree\ViewHelper\ItemSetsTreeSelect
     */
    protected function getItemSetsTreeOptions($byOwner = false): array
    {
        // Fallback when the module ItemSetsTree is not present.
        if (!$this->itemSetsTree) {
            return $this->getItemSetsOptions($byOwner);
        }

        if ($this->formElementManager->has(\ItemSetsTree\Form\Element\ItemSetsTreeSelect::class)) {
            /** @var \ItemSetsTree\Form\Element\ItemSetsTreeSelect $element */
            $element = $this->formElementManager->get(\ItemSetsTree\Form\Element\ItemSetsTreeSelect::class);
            return $element->getValueOptions();
        }

        $options = [];
        if ($this->site) {
            $options['site_id'] = $this->site->id();
        }

        $itemSetsTree = $this->itemSetsTree->getItemSetsTree(null, $options);

        $itemSetsTreeValueOptions = null;
        $itemSetsTreeValueOptions = function ($itemSetsTree, $depth = 0) use (&$itemSetsTreeValueOptions): array {
            $valueOptions = [];
            foreach ($itemSetsTree as $itemSetsTreeNode) {
                $itemSet = $itemSetsTreeNode['itemSet'];
                $valueOptions[$itemSet->id()] = [
                    'value' => $itemSet->id(),
                    'label' => str_repeat('‒', $depth) . ' ' . $itemSet->displayTitle(),
                ];
                $valueOptions = array_merge($valueOptions, $itemSetsTreeValueOptions($itemSetsTreeNode['children'], $depth + 1));
            }
            return $valueOptions;
        };

        $valueOptions = $itemSetsTreeValueOptions($itemSetsTree);

        return array_column($valueOptions, 'label', 'value');
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
        $adapter = $this->searchConfig->searchAdapter();
        return $adapter ? $adapter->getAvailableFields() : [];
    }

    public function setBasePath(string $basePath): Form
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function setEasyMeta(EasyMeta $easyMeta): Form
    {
        $this->easyMeta = $easyMeta;
        return $this;
    }

    public function setItemSetsTree($itemSetsTree): Form
    {
        $this->itemSetsTree = $itemSetsTree;
        return $this;
    }

    public function setSite(?SiteRepresentation $site): Form
    {
        $this->site = $site;
        return $this;
    }

    public function setSettings(Settings $settings): Form
    {
        $this->settings = $settings;
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
}
