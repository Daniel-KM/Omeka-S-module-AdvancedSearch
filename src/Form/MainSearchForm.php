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
use Laminas\Mvc\I18n\Translator;
use Laminas\View\Helper\EscapeHtml;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Manager as ApiManager;
use Omeka\Form\Element as OmekaElement;
use Omeka\Settings\Settings;
use Omeka\Settings\SiteSettings;

class MainSearchForm extends Form
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\View\Helper\EscapeHtml
     */
    protected $escapeHtml;

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
     * @var \Omeka\Settings\SiteSettings
     */
    protected $siteSettings;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

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

            $type = ucfirst(strtolower(basename($filter['type'])));

            // Don't create useless elements.
            // In particular, it allows to skip creation of selects, that is
            // slow for now in big databases.
            if ($hasVariant
                // The type may be missing.
                && !in_array($type, ['Hidden', 'Csrf'])
                // No need to check the field name here: "q", "rft" and "submit"
                // are managed separately.
                // TODO Required elements for "quick" cannot be checked for now.
            ) {
                continue;
            }

            $element = null;

            // TODO Use more native options of Laminas form elements or fill them directly via the ini.
            // TODO Pass any options? Early or lately? Label options?

            // TODO Remove deprecated types, the ones that use a fieldset: everything is an index multi-valued now.

            switch ($type) {
                default:
                    // Check for deprecated types.
                    $method = 'search' . str_replace(['Omeka\\', 'Omeka/', 'omeka\\', 'omeka/'], '', $filter['type']);
                    $element = method_exists($this, $method)
                        ? $this->$method($filter)
                        : $this->searchElement($filter);
                    break;
                case '':
                    // Use an input text by default.
                    $element = $this->searchElement($filter);
                    break;
                case 'Advanced':
                    $element = $this->searchAdvanced($filter);
                    break;
                case 'Checkbox':
                    $element = $this->searchCheckbox($filter);
                    break;
                case 'Daterange':
                    $element = $this->searchDateRange($filter);
                    break;
                case 'Csrf':
                case 'Hidden':
                    $element = $this->searchHidden($filter);
                    break;
                case 'Multicheckbox':
                    $values = $this->listValues($filter);
                    $element = $this->searchMultiCheckbox($filter, $values);
                    break;
                case 'Multiselect':
                    $values = $this->listValues($filter);
                    $element = $this->searchMultiSelect($filter, $values);
                    break;
                case 'Multiselectflat':
                    $values = $this->listValues($filter);
                    $element = $this->searchMultiSelectFlat($filter, $values);
                    break;
                case 'Multiselectgroup':
                    $values = $this->listValues($filter);
                    $element = $this->searchMultiSelectGroup($filter, $values);
                    break;
                case 'Multitext':
                    $values = $this->listValues($filter);
                    $element = $this->searchMultiSelectFlat($filter, $values);
                    break;
                case 'Number':
                    $values = $this->listValues($filter);
                    $element = $this->searchNumber($filter, $values);
                    break;
                case 'Radio':
                    $values = $this->listValues($filter);
                    $element = $this->searchRadio($filter, $values);
                    break;
                case 'Select':
                    $values = $this->listValues($filter);
                    $element = $this->searchSelect($filter, $values);
                    break;
                case 'Selectflat':
                    $values = $this->listValues($filter);
                    $element = $this->searchSelectFlat($filter, $values);
                    break;
                case 'Selectgroup':
                    $values = $this->listValues($filter);
                    $element = $this->searchSelectGroup($filter, $values);
                    break;
                case 'Text':
                    $element = $this->searchText($filter);
                    break;
                case 'Access':
                    $element = $this->searchAccess($filter);
                    break;
                case 'Itemsetstree':
                case 'Tree':
                    $values = $this->listValues($filter);
                    $element = $this->searchItemSetsTree($filter, $values);
                    break;
                case 'Thesaurus':
                    $values = $this->listValues($filter);
                    $element = $this->searchThesaurus($filter, $values);
                    break;
            }

            if ($element) {
                $element->setAttributes($this->elementAttributes);
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
                        'label' => $this->formSettings['form']['label_reset'] ?? 'Reset fields', // @translate
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
                        'label' => $this->formSettings['form']['label_submit'] ?? 'Search', // @translate
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

        // To use the label is the simplest to pass the button Plus inside the
        // fieldset of the collection. To add a second element to the form adds
        // it outside of the fieldset.
        // It is placed inside the legend, so a fake legend is appended to keep
        // view clean without css. A flex box order is added to move the button
        // after the advanced filters.
        if ($maxNumber === 1) {
            $element->setLabel($filter['label']);
        } else {
            $label = sprintf(<<<'HTML'
                %1$s</legend>
                <button type="button" name="plus" class="search-filter-action search-filter-plus fa fa-plus add-value button" aria-label="%2$s" value=""></button>
                <legend hidden="hidden">
                HTML,
                $this->escapeHtml->__invoke($filter['label']),
                $this->translator->translate('Add a filter') // @translate
            );
            $element
                ->setLabel($label)
                ->setLabelOption('disable_html_escape', true);
        }

        $element
            ->setOptions([
                // When there are more filters in the query used to fill form
                // than the default number, new filters will be added
                // automatically (allow_add).
                'count' => $defaultNumber,
                'should_create_template' => true,
                'allow_add' => true,
                'allow_remove' => true,
                'target_element' => $advanced,
                'required' => false,
            ])
            ->setAttributes([
                'id' => 'search-filters',
                'class' => 'search-filters-advanced',
                // TODO Remove this attribute data and use only search config?
                'data-count-default' => $defaultNumber,
                'data-count-max' => $maxNumber,
            ])
        ;

        return $element;
    }

    protected function searchCheckbox(array $filter): ?ElementInterface
    {
        $element = new Element\Checkbox($filter['field']);
        $element
            ->setLabel($filter['label'])
        ;
        if (array_key_exists('unchecked_value', $filter)) {
            $element
                ->setOption('unchecked_value', $filter['unchecked_value']);
        }
        if (array_key_exists('checked_value', $filter)) {
            $element
                ->setOption('checked_value', $filter['checked_value']);
        }
        return $element;
    }

    protected function searchDateRange(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset($filter['field']);
        $fieldset
            ->setLabel($filter['label'])
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
        $value = $filter['value'] ?? '';
        if (!is_scalar($value)) {
            $value = json_serialize($value, 320);
        }
        $element = new Element\Hidden($filter['field']);
        $element
            ->setValue($value)
        ;
        return $element;
    }

    protected function searchMultiCheckbox(array $filter, array $valueOptions): ?ElementInterface
    {
        $element = new CommonElement\OptionalMultiCheckbox($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setValueOptions($valueOptions)
        ;
        return $element;
    }

    protected function searchMultiSelect(array $filter, array $valueOptions): ?ElementInterface
    {
        $filter['attributes']['multiple'] = true;
        return $this->searchSelect($filter, $valueOptions);
    }

    protected function searchMultiSelectFlat(array $filter, array $valueOptions): ?ElementInterface
    {
        // Flat array if needed.
        $first = reset($valueOptions);
        if (is_array($first)) {
            $result = [];
            foreach ($valueOptions as $valuesGroup) {
                $result += $valuesGroup['options'] ?? [];
            }
            $valueOptions = $result;
        }
        $filter['attributes']['multiple'] = true;
        return $this->searchSelectFlat($filter, $valueOptions);
    }

    protected function searchMultiSelectGroup(array $filter, array $valueOptions): ?ElementInterface
    {
        $filter['attributes']['multiple'] = true;
        return $this->searchSelectFlat($filter, $valueOptions);
    }

    /**
     * Simplify the creation of repeatable text input fields.
     */
    protected function searchMultiText(array $filter): ?ElementInterface
    {
        $element = new AdvancedSearchElement\MultiText($filter['field']);
        $element
            ->setLabel($filter['label'])
        ;
        return $element;
    }

    protected function searchNumber(array $filter): ?ElementInterface
    {
        $element = new Element\Number($filter['field']);
        $element
            ->setLabel($filter['label'])
        ;
        if (array_key_exists('min', $filter)) {
            $element
                ->setAttribute('min', $filter['min']);
        }
        if (array_key_exists('max', $filter)) {
            $element
                ->setAttribute('max', $filter['max']);
        }
        if (array_key_exists('step', $filter)) {
            $element
                ->setAttribute('step', $filter['step']);
        }
        return $element;
    }

    protected function searchRadio(array $filter, array $valueOptions): ?ElementInterface
    {
        $element = new CommonElement\OptionalRadio($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setValueOptions($valueOptions)
        ;
        return $element;
    }

    protected function searchSelect(array $filter, array $valueOptions): ?ElementInterface
    {
        $valueOptions = ['' => ''] + $valueOptions;

        $element = new CommonElement\OptionalSelect($filter['field']);
        $element
            ->setLabel($filter['label'])
            ->setOptions([
                'value_options' => $valueOptions,
                'empty_option' => '',
            ])
        ;
        if (!isset($filter['attributes']['class'])) {
            $element
                ->setAttribute('class', 'chosen-select');
        }

        return $element;
    }

    protected function searchSelectFlat(array $filter, array $valueOptions): ?ElementInterface
    {
        // Flat array if needed.
        $first = reset($valueOptions);
        if (is_array($first)) {
            $result = [];
            foreach ($valueOptions as $valuesGroup) {
                $result += $valuesGroup['options'] ?? [];
            }
            $valueOptions = $result;
        }
        return $this->searchSelect($filter, $valueOptions);
    }

    protected function searchSelectGroup(array $filter, array $valueOptions): ?ElementInterface
    {
        return $this->searchSelect($filter, $valueOptions);
    }

    protected function searchText(array $filter): ?ElementInterface
    {
        $element = new Element\Text($filter['field']);
        $element
            ->setLabel($filter['label'])
        ;
        return $element;
    }

    /**
     * Manage hierachical item sets for module Item Sets Tree.
     *
     * @todo Find a way to replace specific element for item sets tree.
     */
    protected function searchItemSetsTree(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('item_sets_tree');
        $fieldset
            ->setAttributes([
                'id' => 'search-item-sets-tree',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->listItemSetsTree($filter['type'] !== 'MultiCheckbox'),
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
     * Manage hierachical values for module Thesaurus.
     *
     * @todo Find a way to replace specific element for thesaurus.
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
        $thesaurusId = (int) ($filterOptions['thesaurus'] ?? $filter['thesaurus'] ?? 0);
        if (!$thesaurusId) {
            return null;
        }

        $filterOptions['thesaurus'] = $thesaurusId;
        // Set ascendance to true by default, else the type Thesaurus is useless
        // anyway.
        // TODO The option "ascendance" is no more used in ThesaurusSelect.
        $filterOptions['ascendance'] = !in_array($filterOptions['ascendance'] ?? null, [0, false, '0', 'false'], true);

        // A thesaurus search should be like an advanced filter, because the
        // search is done on item id, not value text. The issue is only on
        // internal search, because the index may be managed in solr.
        // So the form adapter manage it directly via main key "thesaurus".

        $fieldset = new Fieldset('thesaurus');
        $fieldset
            ->setAttributes([
                'id' => 'search-thesaurus',
            ]);

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
     * Find owners.
     *
     * @deprecated The search type "Owner" is kept for compatibility with old url and should be replaced by an index and a standard html element.
     */
    protected function searchOwner(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('owner');
        $fieldset
            ->setAttributes([
                'id' => 'search-owners',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->listOwners(),
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

    /**
     * Find sites.
     *
     * @deprecated The search type "Site" is kept for compatibility with old url and should be replaced by an index and a standard html element.
     */
    protected function searchSite(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('site');
        $fieldset
            ->setAttributes([
                'id' => 'search-sites',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->listSites(),
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

    /**
     * Find resource classes.
     *
     * @deprecated The search type "ResourceClass" is kept for compatibility with old url and should be replaced by an index and a standard html element.
     */
    protected function searchResourceClass(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('class');
        $fieldset->setAttributes([
            'id' => 'search-classes',
        ]);

        $grouped = $filter['type'] !== 'SelectFlat';
        $valueOptions = $this->listResourceClasses($grouped);
        $element = $grouped
            ? $this->searchMultiSelectGroup($valueOptions)
            : $this->searchMultiSelectFlat($valueOptions);

        $fieldset
            ->add($element);

        return $fieldset;
    }

    /**
     * Find resource templates.
     *
     * @deprecated The search type "ResourceTemplate" is kept for compatibility with old url and should be replaced by an index and a standard html element.
     */
    protected function searchResourceTemplate(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('template');
        $fieldset->setAttributes([
            'id' => 'search-templates',
        ]);

        $grouped = $filter['type'] !== 'SelectFlat';
        $valueOptions = $this->listResourceTemplates($grouped);
        $element = $grouped
            ? $this->searchMultiSelectGroup($valueOptions)
            : $this->searchMultiSelectFlat($valueOptions);

        $fieldset
            ->add($element);

        return $fieldset;
    }

    /**
     * Find item sets.
     *
     * @deprecated The search type "ItemSet" is kept for compatibility with old url and should be replaced by an index and a standard html element.
     */
    protected function searchItemSet(array $filter): ?ElementInterface
    {
        $fieldset = new Fieldset('item_set');
        $fieldset
            ->setAttributes([
                'id' => 'search-item-sets',
            ])
            ->add([
                'name' => 'id',
                'type' => $filter['type'] === 'MultiCheckbox'
                    ? CommonElement\OptionalMultiCheckbox::class
                    : CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => $filter['label'], // @translate
                    'value_options' => $this->skipValues ? [] : $this->listItemSets($filter['type'] !== 'MultiCheckbox'),
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
     * Manage access levels for module Access.
     *
     * @deprecated The search type "Access" is kept for compatibility with old url and should be replaced by an index and a standard html element.
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
            ])
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

    /**
     * @todo Revert output for resource class and templates: flat by default.
     *
     * @param array $filter
     * @return array
     */
    protected function listValues(array $filter): array
    {
        if ($this->skipValues) {
            return [];
        }

        $options = $filter['value_options'] ?? null;
        if (is_array($options)) {
            // Avoid issue with duplicates.
            $options = array_filter(array_keys(array_flip($options)), 'strlen');
            return array_combine($options, $options);
        }

        $availableFields = in_array($this->variant, ['simple', 'csrf']) ? [] : $this->getAvailableFields();
        if (!$availableFields) {
            return [];
        }

        // Check specific fields against all available fields.
        // For speed, don't get available fields with variants "simple" and "csrf".
        // TODO Check the required options for variant "quick" to skip available fields early.
        $field = $filter['field'];
        $nativeField = $availableFields[$field]['from'] ?? null;

        switch ($nativeField) {
            case 'resource_name':
            case 'resource_type':
                return $this->listResourceNames();

            case 'id':
            case 'o:id':
                return $this->listResourceIdTitles();

            case 'is_public':
                return [
                    '0' => 'Is private', // @translate
                    '1' => 'Is public', // @translate
                ];

            case 'item_set/o:id':
                return $this->listItemSets();

            case 'owner/o:id':
                return $this->listOwners();

            case 'resource_class/o:id':
            case 'resource_class/o:term':
                $grouped = !in_array($filter['type'] ?? '', ['SelectGroup', 'MultiSelectGroup']);
                return $this->listResourceClasses($grouped);

            case 'resource_template/o:id':
                $grouped = !in_array($filter['type'] ?? '', ['SelectGroup', 'MultiSelectGroup']);
                return $this->listResourceTemplates($grouped);

            case 'site/o:id':
                return $this->listSites();

            case 'access':
                // For now, there is a special type for access.
                return $this->settings->get('access_property_levels', [
                    'free' => 'free',
                    'reserved' => 'reserved',
                    // 'protected' => 'protected',
                    'forbidden' => 'forbidden',
                ]);

            case 'item_sets_tree':
                // For now, there is a special type for item sets tree.
                return $this->listItemSetsTree(false);
                break;

            case 'thesaurus':
                // For now, there is a special type for thesaurus.
                $fieldset = $this->searchThesaurus($filter);
                return $fieldset
                    ? $fieldset->get('thesaurus')->getValueOptions()
                    : [];

            default:
                return $this->listValuesForField($field);
        }
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
    protected function listValuesForField(string $field): array
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

    protected function listItemSets($byOwner = false): array
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

    protected function listOwners(): array
    {
        /** @var \Omeka\Form\Element\UserSelect $select */
        $select = $this->formElementManager->get(\Omeka\Form\Element\UserSelect::class, []);
        return $select->getValueOptions();
    }

    protected function listResourceClasses(bool $grouped = false): array
    {
        // The select and module Search Solr use term by default,
        // but the internal adapter manages terms automatically.
        // TODO Clarify name for the list of values for resource class for internal.
        /** @var \Omeka\Form\Element\ResourceClassSelect $element */
        $element = $this->formElementManager->get(OmekaElement\ResourceClassSelect::class);
        $element
            ->setOptions([
                'label' => ' ',
                'term_as_value' => true,
                'empty_option' => '',
                // TODO Manage list of resource classes by site.
                'used_terms' => true,
                'query' => ['used' => true],
                'disable_group_by_vocabulary' => !$grouped,
            ]);
        return $element->getValueOptions();
    }

    protected function listResourceIdTitles(): array
    {
        if (!$this->searchConfig) {
            return $this->api->search('items', [], ['returnScalar' => 'title'])->getContent();
        }
        $engine = $this->searchConfig->engine();
        $engineTypes = $engine->setting('resources');
        if (!$engineTypes) {
            return $this->api->search('items', [], ['returnScalar' => 'title'])->getContent();
        }
        $result = [];
        foreach ($engineTypes as $resourceName) {
            // Don't use array_merge.
            $result = array_replace($result, $this->api->search($resourceName, [], ['returnScalar' => 'title'])->getContent());
        }
        return $result;
    }

    protected function listResourceNames(): array
    {
        $types = [
            'items' => 'Items',
            'item_sets' => 'Item sets',
            'media' => 'Media',
        ];
        if (!$this->searchConfig) {
            return ['items'];
        }
        $engine = $this->searchConfig->engine();
        $engineTypes = $engine->setting('resources');
        return $engineTypes
            ? array_intersect_key($types, array_flip($engineTypes))
            : ['items'];
    }

    protected function listResourceTemplates(bool $grouped = false): array
    {
        /** @var \Omeka\Form\Element\ResourceTemplateSelect $element */
        $element = $this->formElementManager->get(OmekaElement\ResourceTemplateSelect::class);
        $element
            ->setOptions([
                'label' => ' ',
                'empty_option' => '',
                'disable_group_by_owner' => !$grouped,
                'used' => true,
                'query' => ['used' => true],
            ]);
        $values = $element->getValueOptions();
        if (!$this->siteSettings
            || !$this->siteSettings->get('advancedsearch_restrict_templates', false)
        ) {
            return $values;
        }
        $appliedValues = $this->siteSettings->get('advancedsearch_apply_templates', []);
        if (!$appliedValues) {
            return $values;
        }
        if (!$grouped) {
            return array_intersect_key($values, array_flip($appliedValues));
        }
        $result = [];
        foreach ($values as $group => $valuesGroup) {
            $list = array_intersect_key($valuesGroup['options'] ?? [], $values);
            if ($list) {
                $result[$group] = $valuesGroup;
                $result[$group]['options'] = $list;
            }
        }
        return $result;
    }

    protected function listSites(): array
    {
        /** @var \Omeka\Form\Element\SiteSelect $select */
        $select = $this->formElementManager->get(\Omeka\Form\Element\SiteSelect::class, []);
        return $select->setOption('disable_group_by_owner', true)->getValueOptions();
    }

    /**
     * @todo Use form element itemSetsTreeSelect when exists (only a view helper for now).
     * @see \ItemSetsTree\ViewHelper\ItemSetsTreeSelect
     */
    protected function listItemSetsTree($byOwner = false): array
    {
        // Fallback when the module ItemSetsTree is not present.
        if (!$this->itemSetsTree) {
            return $this->listItemSets($byOwner);
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

    protected function getAvailableFields(): array
    {
        $adapter = $this->searchConfig ? $this->searchConfig->searchAdapter() : null;
        return $adapter ? $adapter->getAvailableFields() : [];
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

    public function setApi(ApiManager $api): Form
    {
        $this->api = $api;
        return $this;
    }

    public function setEasyMeta(EasyMeta $easyMeta): Form
    {
        $this->easyMeta = $easyMeta;
        return $this;
    }

    public function setEntityManager(EntityManager $entityManager): Form
    {
        $this->entityManager = $entityManager;
        return $this;
    }

    public function setEscapeHtml(EscapeHtml $escapeHtml): Form
    {
        $this->escapeHtml = $escapeHtml;
        return $this;
    }

    public function setFormElementManager(FormElementManager $formElementManager): Form
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    /**
     * @param \ItemSetsTree\ViewHelper\ItemSetsTree $itemSetsTree
     */
    public function setItemSetsTree($itemSetsTree): Form
    {
        $this->itemSetsTree = $itemSetsTree;
        return $this;
    }

    public function setSettings(Settings $settings): Form
    {
        $this->settings = $settings;
        return $this;
    }

    public function setSiteSettings(?SiteSettings $siteSettings = null): Form
    {
        $this->siteSettings = $siteSettings;
        return $this;
    }

    public function setTranslator(Translator $translator): Form
    {
        $this->translator = $translator;
        return $this;
    }
}
