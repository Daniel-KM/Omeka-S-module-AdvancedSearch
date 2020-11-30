<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018
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

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class SearchPageConfigureForm extends Form
{
    protected $formElementManager;

    public function init(): void
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->getOption('search_page');
        $index = $searchPage->index();
        if (empty($index)) {
            return;
        }

        $this
            ->addMainSettings()
            ->addFacets()
            ->addSortFields()
            ->addFormFieldset();

        // Allow to manage the simple and visual form differently.
        $this
            ->add([
                'name' => 'form_class',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'value' => get_class($this),
                ],
            ]);

        // Input filters should be added after elements.
        $this
            ->addInputFilter();
    }

    protected function addMainSettings()
    {
        $this
            ->add([
                'name' => 'default_results',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Default results to display when landing on search page', // @translate
                    'value_options' => [
                        'none' => 'Nothing', // @translate
                        'query' => 'Results of the query below', // @translate
                        'default' => 'Default results of the search engine', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'default_results',
                    'required' => false,
                    'value' => 'default',
                ],
            ])
            ->add([
                'name' => 'default_query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default query', // @translate
                    'default' => 'The format of the query depends on the search form and the search engine.', // @translated
                ],
                'attributes' => [
                    'id' => 'default_query',
                ],
            ])
            /* // Removed, because hard to manage with redirection of item sets, block, direct queries, etc. Need to managed internal query differently.
            ->add([
                'name' => 'restrict_query_to_form',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Restrict query to form', // @translate
                    'info' => 'A form may have less fields than the search engine can manage. If set, the search is limited to the fields of the form. Else, all standard fields manageable by the querier are available.', // @translate
                ],
                'attributes' => [
                    'id' => 'restrict_query_to_form',
                ],
            ])
            */
        ;
        return $this;
    }

    protected function addFacets()
    {
        $this
            ->addFacetLimit()
            ->addFacetLanguages();

        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->getOption('search_page');
        $adapter = $searchPage->index()->adapter();

        $facets = new Fieldset('facets');
        $facets->setLabel('Facets'); // @translate
        $facets->setAttribute('data-sortable', '1');
        // TODO This attribute of the fieldset is not output for an unknown reason.
        $facets->setAttribute('data-ordered', '1');

        $facetFields = $adapter->getAvailableFacetFields($searchPage->index());
        $facetFields = $this->sortFields($facetFields, 'facets');
        $weights = range(0, count($facetFields));
        $weightOptions = array_combine($weights, $weights);
        $weight = 0;
        foreach ($facetFields as $field) {
            $fieldset = new Fieldset($field['name']);
            $fieldset->setLabel($this->getFacetFieldLabel($field));

            $displayFieldset = new Fieldset('display');
            $displayFieldset->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'value' => $field['label'] ?? '',
                ],
            ]);
            $fieldset->add($displayFieldset);

            $fieldset->add([
                'name' => 'enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enabled', // @translate
                ],
            ]);

            $fieldset->add([
                'name' => 'weight',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Weight', // @translate
                    'value_options' => $weightOptions,
                ],
                'attributes' => [
                    'value' => ++$weight,
                ],
            ]);

            $facets->add($fieldset);
        }

        $this->add($facets);
        return $this;
    }

    protected function addFacetLimit()
    {
        $this->add([
            'name' => 'facet_limit',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Facet limit', // @translate
                'info' => 'The maximum number of values fetched for each facet', // @translate
            ],
            'attributes' => [
                'value' => 10,
                'min' => 1,
                'required' => true,
            ],
        ]);
        return $this;
    }

    protected function addFacetLanguages(): void
    {
        $this
            ->add([
                'name' => 'facet_languages',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Get facets from specific languages', // @translate
                    'info' => 'Generally, facets are translated in the view, but in some cases, facet values may be translated directly in a multivalued property. Use "|" to separate multiple languages. Use "||" for values without language. When fields with languages (like subjects) and fields without language (like date) are facets, the empty language must be set to get results.', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_languages',
                    'placeholder' => 'fra|way|apy||',
                ],
            ]);
    }

    public function populateValues($data, $onlyBase = false): void
    {
        if (empty($data['facet_languages'])) {
            $data['facet_languages'] = '';
        } elseif (is_array($data['facet_languages'])) {
            $data['facet_languages'] = implode('|', $data['facet_languages']);
        }
        parent::populateValues($data, $onlyBase);
    }

    protected function addInputFilter()
    {
        return $this
            ->addMainSettingsInputFilter()
            ->addFacetLanguagesInputFilter();
    }

    protected function addMainSettingsInputFilter()
    {
        $this
            ->getInputFilter()
            ->add([
                'name' => 'default_results',
                'required' => false,
            ]);
        return $this;
    }

    protected function addFacetLanguagesInputFilter()
    {
        $this
            ->getInputFilter()
            ->add([
                'name' => 'facet_languages',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => function ($string) {
                                return strlen(trim($string))
                                    ? array_unique(array_map('trim', explode('|', $string)))
                                    : [];
                            },
                        ],
                    ],
                ],
            ]);
        return $this;
    }

    protected function addSortFields()
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->getOption('search_page');
        $adapter = $searchPage->index()->adapter();

        $sortFieldsFieldset = new Fieldset('sort_fields');
        $sortFieldsFieldset->setLabel('Sort fields'); // @translate
        $sortFieldsFieldset->setAttribute('data-sortable', '1');
        $sortFieldsFieldset->setAttribute('data-ordered', '1');

        $sortFields = $adapter->getAvailableSortFields($searchPage->index());
        $sortFields = $this->sortFields($sortFields, 'sort_fields');
        $weights = range(0, count($sortFields));
        $weightOptions = array_combine($weights, $weights);
        $weight = 0;
        foreach ($sortFields as $field) {
            $fieldset = new Fieldset($field['name']);
            $fieldset->setLabel($this->getSortFieldLabel($field));

            $displayFieldset = new Fieldset('display');
            $displayFieldset->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'value' => $field['label'] ?? '',
                ],
            ]);
            $fieldset->add($displayFieldset);

            $fieldset->add([
                'name' => 'enabled',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Enabled', // @translate
                ],
            ]);

            $fieldset->add([
                'name' => 'weight',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Weight', // @translate
                    'value_options' => $weightOptions,
                ],
                'attributes' => [
                    'value' => ++$weight,
                ],
            ]);

            $sortFieldsFieldset->add($fieldset);
        }

        $this->add($sortFieldsFieldset);
        return $this;
    }

    protected function addFormFieldset()
    {
        $formElementManager = $this->getFormElementManager();
        $searchPage = $this->getOption('search_page');

        $formAdapter = $searchPage->formAdapter();
        if (!isset($formAdapter)) {
            return $this;
        }

        $configFormClass = $formAdapter->getConfigFormClass();
        if (!isset($configFormClass)) {
            return $this;
        }

        $fieldset = $formElementManager->get($formAdapter->getConfigFormClass(), [
            'search_page' => $searchPage,
        ]);
        $fieldset->setName('form');
        $fieldset->setLabel('Form settings'); // @translate

        $this->add($fieldset);
        return $this;
    }

    /**
     * @param array $fields
     * @param string $type
     * @return array
     */
    protected function sortFields(array $fields, $type)
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->getOption('search_page');
        $settings = $searchPage->settings();
        if (empty($settings) || empty($settings[$type])) {
            return $fields;
        }
        // Remove the keys that exists in settings, but not in fields to sort.
        $order = array_intersect_key($settings[$type], $fields);
        // Order the fields.
        return array_replace($order, $fields);
    }

    /**
     * @param string $field
     * @param string $settingsKey
     * @return string
     */
    protected function getFieldLabel($field, $settingsKey)
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->getOption('search_page');
        $settings = $searchPage->settings();

        $name = $field['name'];
        $label = $field['label'] ?? null;
        if (isset($settings[$settingsKey][$name])) {
            $fieldSettings = $settings[$settingsKey][$name];
            if (isset($fieldSettings['display']['label'])) {
                $label = $fieldSettings['display']['label'];
            }
        }
        return $label ? sprintf('%s (%s)', $label, $field['name']) : $field['name'];
    }

    /**
     * @param string $field
     * @return string
     */
    protected function getFacetFieldLabel($field)
    {
        return $this->getFieldLabel($field, 'facets');
    }

    /**
     * @param string $field
     * @return string
     */
    protected function getSortFieldLabel($field)
    {
        return $this->getFieldLabel($field, 'sort_fields');
    }

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
