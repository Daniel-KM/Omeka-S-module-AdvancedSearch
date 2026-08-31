<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2018-2026
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

namespace AdvancedSearch\Form\Admin;

use AdvancedSearch\EngineAdapter\Internal;
use Common\Form\Element as CommonElement;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\Mvc\I18n\Translator;
use Omeka\Form\Element as OmekaElement;

class SearchConfigConfigureForm extends Form implements EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * @var \Laminas\Form\FormElementManager
     */
    protected $formElementManager;

    /**
     * @var array
     */
    protected $suggesters = [];

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    /**
     * @var array
     */
    protected $thumbnailTypes = [];

    public function init(): void
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');
        $searchEngine = $searchConfig->searchEngine();
        if (empty($searchEngine)) {
            return;
        }

        // TODO Add a method or an event to modify or append specific field to each fieldset.
        $engineAdapter = $searchEngine->engineAdapter();
        $isInternalEngine = $engineAdapter && $engineAdapter instanceof Internal;

        // This is the settings for the search config, not the search form one.

        // TODO Simplify the form with js, storing the whole form one time via ini or json or just add a button import/export.
        // TODO See UserProfile and https://docs.laminas.dev/laminas-form/v3/form-creation/creation-via-factory/

        $this
            ->setAttribute('id', 'search-config-configure-form');

        // Tab 1: general settings (name, slug, engine, form adapter).
        $this->add([
            'type' => \AdvancedSearch\Form\Admin\SearchConfigSettingsFieldset::class,
            'name' => 'settings',
            'options' => [
                'label' => 'Settings', // @translate
            ],
        ]);

        // Tab 2: sites usage (default + availability).
        $this->add([
            'type' => \AdvancedSearch\Form\Admin\SearchConfigSitesFieldset::class,
            'name' => 'sites',
            'options' => [
                'label' => 'Sites', // @translate
            ],
        ]);

        // Settings for the search engine. Can be overwritten by a specific
        // form.

        $this
            ->add([
                'name' => 'index',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Indexes', // @translate
                ],
            ])
            ->get('index')

            ->add([
                'name' => 'aliases',
                'type' => CommonElement\DataTextarea::class,
                'options' => [
                    'label' => 'Aliases and aggregated fields', // @translate
                    'info' => 'List of fields that refers to one or multiple indexes (properties with internal search engine, native indexes with Solr), formatted "name = label", then the list of properties and an empty line. The name must not be a property term or a reserved keyword. With query args below, they can be seen as simple shortcuts of filters easy to implement in forms.', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch/-/blob/master/data/configs/search_engine.internal.php',
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_options' => [
                        'name' => null,
                        'label' => null,
                        'fields' => ',',
                    ],
                    'data_text_mode' => 'last_is_list',
                ],
                'attributes' => [
                    'id' => 'index_aliases',
                    'required' => false,
                    'rows' => 12,
                    'placeholder' => <<<'STRING'
                            author = Author
                            dcterms:creator
                            dcterms:contributor

                            title = Title
                            dcterms:title
                            dcterms:alternative

                            date = Date
                            dcterms:date
                            dcterms:created
                            dcterms:issued
                            STRING,
                ],
            ])
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'query_args',
                'options' => [
                    'label' => 'Query arguments for fields', // @translate
                    'info' => 'Define the query arguments to use with simple fields. Defaults are "type" = "eq" and "join" = "and". Type may be any of the query type of filters. Join may be "and", "or", "not".', // @translate
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch',
                    'ini_typed_mode' => true,
                ],
                'attributes' => [
                    'id' => 'index_query_args',
                    'required' => false,
                    'rows' => 12,
                    'placeholder' => <<<'STRING'
                            [title]
                            type = in

                            [author]
                            type = res
                            STRING,
                ],
            ])
        ;

        $this
            ->add([
                'name' => 'request',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Request', // @translate
                ],
            ])
            ->get('request')
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
            // TODO Use UrlQuery instead of Text for the default query to avoid conversion each time.
            ->add([
                'name' => 'default_query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Default query', // @translate
                    'info' => 'The format of the query depends on the search form and the search engine.', // @translate
                ],
                'attributes' => [
                    'id' => 'default_query',
                ],
            ])
            ->add([
                'name' => 'default_query_post',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Complementary default query', // @translate
                    'info' => 'Mainly used to specify a default sort when request is empty, but other args are possible (default pagination, selected facets…).', // @translate
                ],
                'attributes' => [
                    'id' => 'default_query_post',
                ],
            ])
            ->add([
                'name' => 'hidden_query_filters',
                'type' => CommonElement\UrlQuery::class,
                'options' => [
                    'label' => 'Hidden query filter to limit results', // @translate
                    'info' => 'These args are appended to all queries. The format of the query depends on the search form and the search engine.', // @translate
                ],
                'attributes' => [
                    'id' => 'hidden_query_filters',
                ],
            ])
            ->add([
                'name' => 'validate_form',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Validate user query (useless in most of the cases)', // @translate
                ],
                'attributes' => [
                    'id' => 'validate_form',
                ],
            ])
            ->add([
                'name' => 'query_default_field',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query default field', // @translate
                    'info' => 'Optional. Specifies a default search field in case it is not made explicit in the query.', // @translate
                ],
                'attributes' => [
                    'id' => 'query_default_field',
                ],
            ])
        ;

        // TODO Make option "q" a standard filter.
        $this
            // The main search field is "q", not "fulltext_search" or "search".
            ->add([
                'name' => 'q',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Search field', // @translate
                ],
            ])
            ->get('q')
            ->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'q_label',
                    'data-common' => '1',
                    'required' => false,
                    'value' => 'Search', // @translate
                ],
            ])
            ->add([
                'name' => 'suggester',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Suggester', // @translate
                    'value_options' => $this->getOption('suggesters') ?: $this->suggesters,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'q_suggester',
                    'data-advanced-section' => $this->translator->translate('Autosuggestion'), // @translate
                    'multiple' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => ' ',
                ],
            ])
            ->add([
                'name' => 'suggest_url',
                'type' => CommonElement\OptionalUrl::class,
                'options' => [
                    'label' => 'Direct endpoint for suggester', // @translate
                    // @see https://solr.apache.org/guide/suggester.html#suggest-request-handler-parameters
                    'info' => 'This url allows to use an external endpoint to manage keywords and is generally quicker. Needed params should be appended.', // @translate
                ],
                'attributes' => [
                    'id' => 'q_suggest_url',
                    'data-advanced-section' => $this->translator->translate('Autosuggestion'), // @translate
                ],
            ])
            ->add([
                'name' => 'suggest_url_param_name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Optional query param name for direct endpoint', // @translate
                    'info' => 'For a direct Solr endpoint, it should be "suggest.q", else "q" is used by default.', // @translate
                ],
                'attributes' => [
                    'id' => 'q_suggest_url_param_name',
                    'data-advanced-section' => $this->translator->translate('Autosuggestion'), // @translate
                ],
            ])
            ->add([
                'name' => 'suggest_fill_input',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Stay on form when selecting a suggestion (no auto-submit)', // @translate
                ],
                'attributes' => [
                    'id' => 'q_suggest_fill_input',
                    'data-advanced-section' => $this->translator->translate('Autosuggestion'), // @translate
                ],
            ])
            ->add([
                'name' => 'remove_diacritics',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Process request without diacritic', // @translate
                ],
                'attributes' => [
                    'id' => 'q_remove_diacritics',
                    'data-advanced-section' => $this->translator->translate('Query processing'), // @translate
                ],
            ])
        ;

        // TODO Add the check in adapter interface, or allow specific fieldset by adapter.

        if ($isInternalEngine) {
            $this
                ->get('q')
                ->add([
                    'name' => 'default_search_partial_word',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => 'Partial word search for main field (instead of standard full text search)', // @translate
                        'info' => 'Currently, this mode does not allow to exclude properties for the main search field.', // @translate
                    ],
                    'attributes' => [
                        'id' => 'q_default_search_partial_word',
                    'data-advanced-section' => $this->translator->translate('Query processing'), // @translate
                    ],
                ])
            ;
        }

        $this
            ->get('q')
            ->add([
                'type' => CommonElement\IniTextarea::class,
                'name' => 'options',
                'options' => [
                    'label' => 'Options', // @translate
                    'info' => 'List of specific Omeka and Laminas options.', // @translate
                    'ini_typed_mode' => true,
                    'pairs_editor' => [
                        'key_label' => $this->translator->translate('Option'), // @translate
                        'value_label' => $this->translator->translate('Value'), // @translate
                        'sortable' => false,
                    ],
                ],
                'attributes' => [
                    'id' => 'q_options',
                    'data-advanced-section' => $this->translator->translate('Advanced'), // @translate
                    'required' => false,
                    'placeholder' => '',
                ],
            ])
            ->add([
                'type' => CommonElement\ArrayTextarea::class,
                'name' => 'attributes',
                'options' => [
                    'label' => 'Html attributes', // @translate
                    'info' => 'Attributes to add to the input field, for example `class = "my-specific-class"`, data, etc.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'key_label' => $this->translator->translate('Attribute'), // @translate
                        'value_label' => $this->translator->translate('Value'), // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'q_attributes',
                    'data-advanced-section' => $this->translator->translate('Advanced'), // @translate
                    'required' => false,
                    'placeholder' => '',
                ],
            ]);

        // Settings for the form querier (advanced form and filters).

        /** @var \AdvancedSearch\Form\Admin\SearchConfigFilterFieldset $filterFieldset */
        $filterFieldset = $this->formElementManager->get(SearchConfigFilterFieldset::class, [
            'search_config' => $searchConfig,
            'translator' => $this->translator,
        ]);

        $this
            ->add([
                'name' => 'form',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Filters', // @translate
                ],
            ])
            ->get('form')

            ->add([
                'name' => 'button_submit',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add a button "submit"', // @translate
                ],
                'attributes' => [
                    'id' => 'button_submit',
                    'data-common' => '1',
                    'data-inline' => 'submit',
                    'value' => true,
                ],
            ])
            ->add([
                'name' => 'label_submit',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for submit', // @translate
                ],
                'attributes' => [
                    'id' => 'label_submit',
                    'data-common' => '1',
                    'data-inline' => 'submit',
                    'data-show-if' => 'button_submit',
                    'required' => false,
                    'value' => 'Search', // @translate
                    'placeholder' => 'Search', // @translate
                ],
            ])
            ->add([
                'name' => 'button_reset',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add a button "reset"', // @translate
                ],
                'attributes' => [
                    'id' => 'button_reset',
                    'data-common' => '1',
                    'data-inline' => 'reset',
                ],
            ])
            ->add([
                'name' => 'label_reset',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for reset', // @translate
                ],
                'attributes' => [
                    'id' => 'label_reset',
                    'data-common' => '1',
                    'data-inline' => 'reset',
                    'data-show-if' => 'button_reset',
                    'required' => false,
                    'value' => 'Reset fields', // @translate
                    'placeholder' => 'Reset fields', // @translate
                ],
            ])
            ->add([
                'name' => 'attribute_form',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add attribute "form" to input elements', // @translate
                ],
                'attributes' => [
                    'id' => 'attribute_form',
                    'data-advanced-section' => $this->translator->translate('Advanced'), // @translate
                ],
            ])
            // TODO Make option "rft" a standard filter.
            ->add([
                'name' => 'filters',
                'type' => Element\Collection::class,
                'options' => [
                    'label' => 'Filters', // @translate
                    'info' => 'The filters are the fields of the search form, used before submitting the search.', // @translate
                    'count' => 0,
                    'allow_add' => true,
                    'allow_remove' => true,
                    'should_create_template' => true,
                    'template_placeholder' => '__index__',
                    'create_new_objects' => true,
                    'target_element' => $filterFieldset,
                ],
                'attributes' => [
                    'id' => 'form_filters',
                    'required' => false,
                    'class' => 'form-fieldset-collection',
                    'data-label-index' => $this->translator->translate('Filter {index}'), // @translate
                    'data-label-new' => $this->translator->translate('New filter'), // @translate
                ],
            ])
            ->add([
                'name' => 'plus',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'config-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-add.
                    'class' => 'config-fieldset-action config-fieldset-plus fa fa-plus add-value button',
                    'aria-label' => 'Add a filter', // @translate
                ],
            ])

        ;

        // Settings for the results.

        $this
            ->add([
                'name' => 'results',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Results', // @translate
                ],
            ])
            ->get('results')
            ->add([
                'name' => 'label_default',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label without query', // @translate
                ],
                'attributes' => [
                    'id' => 'results_label_default',
                    'data-subtab' => 'general',
                    'data-advanced-section' => $this->translator->translate('Labels'), // @translate
                    'value' => 'Search', // @translate
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'label_results',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for results', // @translate
                ],
                'attributes' => [
                    'id' => 'results_label_results',
                    'data-subtab' => 'general',
                    'data-advanced-section' => $this->translator->translate('Labels'), // @translate
                    'value' => 'Search results', // @translate
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'label_no_results',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for no results', // @translate
                ],
                'attributes' => [
                    'id' => 'results_label_no_results',
                    'data-subtab' => 'general',
                    'data-advanced-section' => $this->translator->translate('Labels'), // @translate
                    'value' => 'No results', // @translate
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'by_resource_type',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Separate resources by type (item set, items, etc.)', // @translate
                ],
                'attributes' => [
                    'id' => 'by_resource_type',
                    'data-subtab' => 'general',
                    'data-common' => '1',
                ],
            ])
            ->add([
                'name' => 'template',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Theme template (phtml file)', // @translate
                    'info' => 'The template of the theme used to render the page. Default is search/search. Rarely used.', // @translate
                ],
                'attributes' => [
                    'id' => 'template',
                    'data-subtab' => 'general',
                    'data-advanced-section' => $this->translator->translate('Advanced'), // @translate
                ],
            ])
            ->add([
                'name' => 'autoscroll',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Auto-scroll to results', // @translate
                    'info' => 'When enabled, the page will scroll to the results section after form submission. Useful when the search form is not at the top of the page.', // @translate
                ],
                'attributes' => [
                    'id' => 'autoscroll',
                    'data-subtab' => 'general',
                    'data-advanced-section' => $this->translator->translate('Advanced'), // @translate
                ],
            ])
            ->add([
                'name' => 'breadcrumbs',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Breadcrumbs', // @translate
                ],
                'attributes' => [
                    'id' => 'breadcrumbs',
                    'data-subtab' => 'general',
                    'data-common' => '1',
                ],
            ])
            ->add([
                'name' => 'search_filters',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'List of query filters', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'search_filters',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'header',
                ],
            ])
            ->add([
                'name' => 'search_filters_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Query filters display', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'readonly' => 'Simple text', // @translate
                        'link_remove' => 'Append a cross to remove the filter', // @translate
                        'links' => 'Whole label removes the filter', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'search_filters_mode',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Used filters'), // @translate
                    'value' => 'link_remove',
                ],
            ])
            ->add([
                'name' => 'active_facets_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Active facets display', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'readonly' => 'Simple text', // @translate
                        'link_remove' => 'Append a cross to remove the facet', // @translate
                        'links' => 'Whole label removes the facet', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'active_facets_mode',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Used facets'), // @translate
                    'value' => 'link_remove',
                ],
            ])
            ->add([
                'name' => 'search_filters_field_label',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the field label in active filters', // @translate
                ],
                'attributes' => [
                    'id' => 'search_filters_field_label',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Used filters'), // @translate
                    'value' => '1',
                ],
            ])
            ->add([
                'name' => 'active_facets',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'List of active facets', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'active_facets',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'none',
                ],
            ])
            ->add([
                'name' => 'active_facets_field_label',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the field label in active facets', // @translate
                ],
                'attributes' => [
                    'id' => 'active_facets_field_label',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Used facets'), // @translate
                    'value' => '1',
                ],
            ])
            ->add([
                'name' => 'total_results',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Total results', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'total_results',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'header',
                ],
            ])
            ->add([
                'name' => 'search_form_simple',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Simple search form (main field only)', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'search_form_simple',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'none',
                ],
            ])
            ->add([
                'name' => 'search_form_quick',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Quick search form (main field only, alternative style)', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'search_form_quick',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'none',
                ],
            ])
            ->add([
                'name' => 'paginator',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Paginator', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'paginator',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'header',
                ],
            ])
            ->add([
                'name' => 'per_page',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Pagination per page', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'per_page',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'header',
                ],
            ])
            ->add([
                'name' => 'sort',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Sort', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'sort',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'header',
                ],
            ])
            ->add([
                'name' => 'grid_list',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Grid / list', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'header' => 'Results header', // @translate
                        'footer' => 'Results footer', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'grid_list',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'header',
                ],
            ])
            ->add([
                'name' => 'grid_list_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Grid / list default mode', // @translate
                    'value_options' => [
                        'auto' => 'Auto (previous user choice)', // @translate
                        'grid' => 'Grid', // @translate
                        'list' => 'List', // @translate
                        'grid_only' => 'Only grid', // @translate
                        'list_only' => 'Only list', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'grid_list_mode',
                    'data-subtab' => 'header',
                    'data-common' => '1',
                    'value' => 'auto',
                ],
            ])
            ->add([
                'name' => 'map_display',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display map (module Mapping)', // @translate
                    'info' => 'Add a map view to display search results on a map. Requires the Mapping module to be installed and active.', // @translate
                ],
                'attributes' => [
                    'id' => 'map_display',
                    'data-subtab' => 'general',
                    'data-common' => '1',
                ],
            ])
            ->add([
                'name' => 'thumbnail_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Resource thumbnail', // @translate
                    'value_options' => [
                        'default' => 'Default resource thumbnail', // @translate
                        'none' => 'Never', // @translate
                        'all' => 'Always', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'thumbnail_mode',
                    'data-subtab' => 'card',
                    'data-common' => '1',
                    'value' => 'default',
                ],
            ])
            ->add([
                'name' => 'thumbnail_type',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Thumbnail type', // @translate
                    'value_options' => array_combine($this->thumbnailTypes, $this->thumbnailTypes),
                ],
                'attributes' => [
                    'id' => 'thumbnail_type',
                    'data-subtab' => 'card',
                    'data-common' => '1',
                    'value' => 'medium',
                ],
            ])
            ->add([
                'name' => 'allow_html',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow html in result values', // @translate
                ],
                'attributes' => [
                    'id' => 'allow_html',
                    'data-subtab' => 'card',
                    'data-common' => '1',
                ],
            ])
            ->add([
                'name' => 'properties',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Properties to display for each result', // @translate
                    'info' => 'The values of these properties are displayed below each result, in this order. The label of the property is used when no label is set.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'keys' => [
                            'header' => $this->translator->translate('Title (heading)'), // @translate
                            'body' => $this->translator->translate('Description (body)'), // @translate
                        ],
                        'key_source' => '#form_filter_field',
                        'key_skip' => ['advanced'],
                        'key_pattern' => '^[a-zA-Z][a-zA-Z0-9]*:[a-zA-Z][a-zA-Z0-9]*$',
                        'key_label' => $this->translator->translate('Property'), // @translate
                        'value_label' => $this->translator->translate('Label (optional)'), // @translate
                        'key_fill' => false,
                    ],
                ],
                'attributes' => [
                    'id' => 'properties',
                    'data-subtab' => 'card',
                    'data-common' => '1',
                    'rows' => 5,
                    'placeholder' => <<<'TXT'
                        dcterms:creator
                        dcterms:date
                        dcterms:subject
                        TXT,
                ],
            ])
            ->add([
                'name' => 'properties_grid',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Properties to display in grid mode, when different', // @translate
                    'info' => 'A grid card is smaller: it may display fewer properties. Leave empty to use the same list.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'keys' => [
                            'header' => $this->translator->translate('Title (heading)'), // @translate
                            'body' => $this->translator->translate('Description (body)'), // @translate
                        ],
                        'key_source' => '#form_filter_field',
                        'key_skip' => ['advanced'],
                        'key_pattern' => '^[a-zA-Z][a-zA-Z0-9]*:[a-zA-Z][a-zA-Z0-9]*$',
                        'key_label' => $this->translator->translate('Property'), // @translate
                        'value_label' => $this->translator->translate('Label (optional)'), // @translate
                        'key_fill' => false,
                    ],
                ],
                'attributes' => [
                    'id' => 'properties_grid',
                    'data-subtab' => 'card',
                    'data-common' => '1',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'pagination_per_page',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Pagination per page (use site settings by default)', // @translate
                ],
                'attributes' => [
                    'id' => 'pagination_per_page',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Pagination'), // @translate
                    'required' => false,
                    'value' => '0',
                    'min' => '0',
                    // 'max' => '1000',
                    'step' => '1',
                ],
            ])

            // TODO Add the style of pagination (prev/next or list of pages).

            ->add([
                'name' => 'per_page_list',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Labels for results per page', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'key_label' => $this->translator->translate('Number'), // @translate
                        'value_label' => $this->translator->translate('Label'), // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'per_page_list',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Pagination'), // @translate
                    'placeholder' => <<<'STRING'
                        10 = Results by 10
                        25 = Results by 25
                        50 = Results by 50
                        100 = Results by 100
                        STRING,
                    'rows' => 6,
                ],
            ])

            // field (term + asc/desc) = label (+ asc/desc) (order means weight).
            ->add([
                'name' => 'label_sort',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Sort label', // @translate
                ],
                'attributes' => [
                    'id' => 'label_sort',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Sort'), // @translate
                ],
            ])

            ->add([
                'name' => 'sort_list',
                'type' => CommonElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Sort selector', // @translate
                    'info' => 'The sort options offered to the visitor, in this order. Format is "field direction = label".', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'pairs_editor' => [
                        'keys' => $this->availableSortFieldsFlat($searchConfig),
                        'key_label' => $this->translator->translate('Sort field'), // @translate
                        'value_label' => $this->translator->translate('Label'), // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'sort_list',
                    'data-subtab' => 'header',
                    'data-advanced-section' => $this->translator->translate('Sort'), // @translate
                    'rows' => 6,
                    'placeholder' => 'dcterms:date asc = Date',
                ],
            ])
        ;

        // Settings for the results (facets).
        // TODO Add the count or not.

        /** @var \AdvancedSearch\Form\Admin\SearchConfigFacetFieldset $facetFieldset */
        $facetFieldset = $this->formElementManager->get(SearchConfigFacetFieldset::class, [
            'search_config' => $searchConfig,
            'translator' => $this->translator,
        ]);

        $this
            ->add([
                'name' => 'facet',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Facets', // @translate
                ],
            ])
            ->get('facet')
            ->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label above the list of facets', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_label_facets',
                    'data-common' => '1',
                    'value' => 'Facets',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'label_no_facets',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label "No facets"', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_label_no_facets',
                    'data-advanced-section' => $this->translator->translate('Display'), // @translate
                    'value' => 'No facets', // @translate
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'position',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Position of the block of facets', // @translate
                    'value_options' => [
                        'none' => 'No', // @translate
                        'before' => 'Left of the results', // @translate
                        'after' => 'Right of the results', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_position',
                    'data-common' => '1',
                    'value' => 'before',
                ],
            ])
            ->add([
                'name' => 'mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Facet mode', // @translate
                    'value_options' => [
                        'button' => 'Send request with a button', // @translate
                        'js' => 'Send request directly (use checkbox and js)', // @translate
                        'link' => 'Send request directly (use a link looking like a checkbox)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_mode',
                    'data-common' => '1',
                    'required' => false,
                    'value' => 'button',
                ],
            ])
            ->add([
                'name' => 'list',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Values displayed in each facet', // @translate
                    'info' => 'With the internal search engine, the option "all values" may be slow when there are facets and filters for item sets or sites.', // @translate
                    'value_options' => [
                        'available' => 'Values with results only', // @translate
                        'all' => 'All values, even with 0 results (see info)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_list',
                    'data-advanced-section' => $this->translator->translate('Display'), // @translate
                    'required' => false,
                    'value' => 'available',
                ],
            ])
            ->add([
                'name' => 'display_active',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the list of active facets', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_display_active',
                    'data-advanced-section' => $this->translator->translate('Used facets'), // @translate
                    'required' => false,
                    'value' => true,
                ],
            ])
            ->add([
                'name' => 'display_active_field_label',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the field label in active facets (sidebar)', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_display_active_field_label',
                    'data-advanced-section' => $this->translator->translate('Used facets'), // @translate
                    'required' => false,
                    'value' => false,
                ],
            ])
            ->add([
                'name' => 'label_active_facets',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label "Active facets"', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_label_active_facets',
                    'data-advanced-section' => $this->translator->translate('Used facets'), // @translate
                    'value' => 'Active facets', // @translate
                ],
            ])
            ->add([
                'name' => 'display_submit',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Position of the button "Apply facets"', // @translate
                    'value_options' => [
                        'none' => 'None', // @translate
                        'above' => 'Above facets', // @translate
                        'below' => 'Below facets', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_display_submit',
                    'data-common' => '1',
                    'required' => false,
                    'value' => 'above',
                ],
            ])
            ->add([
                'name' => 'label_submit',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for submit', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_label_submit',
                    'data-common' => '1',
                    'required' => false,
                    'value' => 'Apply facets', // @translate
                    'placeholder' => 'Apply facets', // @translate
                ],
            ])
            ->add([
                'name' => 'display_reset',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Position of the button "Reset facets"', // @translate
                    'value_options' => [
                        'none' => 'None', // @translate
                        'above' => 'Above facets', // @translate
                        'below' => 'Below facets', // @translate
                        'both' => 'Both', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_display_reset',
                    'data-common' => '1',
                    'required' => false,
                    'value' => 'above',
                ],
            ])
            ->add([
                'name' => 'label_reset',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for reset', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_label_reset',
                    'data-common' => '1',
                    'required' => false,
                    'value' => 'Reset facets', // @translate
                    'placeholder' => 'Reset facets', // @translate
                ],
            ])
            ->add([
                'name' => 'display_expand_all',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Global "Expand all / Collapse all" toggle above facets', // @translate
                    'value_options' => [
                        'none' => 'None', // @translate
                        'expand' => 'Display toggle, expand all by default', // @translate
                        'collapse' => 'Display toggle, collapse all by default', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'facet_display_expand_all',
                    'data-advanced-section' => $this->translator->translate('Display'), // @translate
                    'required' => false,
                    'value' => 'none',
                ],
            ])
            ->add([
                'name' => 'display_refine',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display the input field to refine search', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_display_refine',
                    'data-advanced-section' => $this->translator->translate('Refine'), // @translate
                    'required' => false,
                    'value' => true,
                ],
            ])
            ->add([
                'name' => 'label_refine',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label for refine', // @translate
                ],
                'attributes' => [
                    'id' => 'facet_label_refine',
                    'data-advanced-section' => $this->translator->translate('Refine'), // @translate
                    'data-show-if' => 'facet_display_refine',
                    'required' => false,
                    'value' => 'Refine search', // @translate
                    'placeholder' => 'Refine search', // @translate
                ],
            ])
            ->add([
                'name' => 'facets',
                'type' => Element\Collection::class,
                'options' => [
                    'label' => 'Facets', // @translate
                    'info' => 'The facets are displayed in the page of results, after the search, in order to refine them.', // @translate
                    'count' => 0,
                    'allow_add' => true,
                    'allow_remove' => true,
                    'should_create_template' => true,
                    'template_placeholder' => '__index__',
                    'create_new_objects' => true,
                    'target_element' => $facetFieldset,
                ],
                'attributes' => [
                    'id' => 'facet_facets',
                    'required' => false,
                    'class' => 'form-fieldset-collection',
                    'data-label-index' => $this->translator->translate('Facet {index}'), // @translate
                    'data-label-new' => $this->translator->translate('New facet'), // @translate
                ],
            ])
            ->add([
                'name' => 'plus',
                'type' => Element\Button::class,
                'options' => [
                    'label' => ' ',
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'config-fieldset-action-label',
                    ],
                ],
                'attributes' => [
                    // Don't use o-icon-add.
                    'class' => 'config-fieldset-action config-fieldset-plus fa fa-plus add-value button',
                    'aria-label' => 'Add a facet', // @translate
                ],
            ])
        ;

        $this
            ->addFormFieldset();

        // Modules can append their own top-level fieldsets, displayed as tabs,
        // in particular the engine specific settings (e.g. SearchSolr): the
        // reserved fieldset name "engine" is read for the query relevance
        // (field boosts, minimum match, tie breaker).
        $event = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($event);

        $this
            ->prepareInputFilters();
    }

    protected function prepareInputFilters(): Form
    {
        // Input filters should be added after elements.
        $inputFilter = $this->getInputFilter();

        // A check is done because the specific form may remove them.

        if ($inputFilter->has('form')) {
            $inputFilter
                ->get('form')
                ->add([
                    'name' => 'default_number',
                    'required' => false,
                ])
                ->add([
                    'name' => 'max_number',
                    'required' => false,
                ])
            ;
        }

        if ($inputFilter->has('results')) {
            $inputFilter
                ->get('results')
                ->add([
                    'name' => 'label_sort',
                    'required' => false,
                ])
            ;
        }

        if ($inputFilter->has('facet')) {
            $inputFilter
                ->get('facet')
                ->add([
                    'name' => 'label',
                    'required' => false,
                ])
                ->add([
                    'name' => 'label_no_facets',
                    'required' => false,
                ])
            ;
        }

        return $this;
    }

    public function isValid(): bool
    {
        $valid = parent::isValid();

        // Validate piecewise scale on RangeDouble in filters and facets. Mode
        // "linear" needs no validation. SelectRange is a pair of selects, not a
        // slider; Range simple uses the native HTML5 input type=range without a
        // numeric companion, so piecewise rendering is not
        // supported there for now (TODO).
        $data = $this->getData();
        $scaleTypes = ['RangeDouble'];

        $checks = [
            ['form', 'filters', $scaleTypes, ['Range', 'RangeDouble']],
            ['facet', 'facets', $scaleTypes, ['RangeDouble']],
        ];

        foreach ($checks as [$top, $sub, $allowedTypes, $intervalTypes]) {
            if (empty($data[$top][$sub]) || !is_array($data[$top][$sub])) {
                continue;
            }
            foreach ($data[$top][$sub] as $name => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = $item['type'] ?? '';
                $fieldEnd = trim((string) ($item['field_end'] ?? ''));
                if ($fieldEnd !== '') {
                    if (!in_array($type, $intervalTypes, true)) {
                        $valid = false;
                        $this->setMessages([
                            $top => [$sub => [$name => ['field_end' => [
                                sprintf(
                                    'Interval end field is only available for types %s.', // @translate
                                    implode(', ', $intervalTypes)
                                ),
                            ]]]],
                        ]);
                    } elseif (trim((string) ($item['field'] ?? '')) === '') {
                        $valid = false;
                        $this->setMessages([
                            $top => [$sub => [$name => ['field_end' => [
                                'Interval end field requires a start field ("Field").', // @translate
                            ]]]],
                        ]);
                    }
                }
                if (($item['scale_mode'] ?? 'linear') !== 'piecewise') {
                    continue;
                }
                if (!in_array($type, $allowedTypes, true)) {
                    $valid = false;
                    $this->setMessages([
                        $top => [$sub => [$name => ['scale_mode' => [
                            sprintf(
                                'Piecewise scale is only available for types %s.', // @translate
                                implode(', ', $allowedTypes)
                            ),
                        ]]]],
                    ]);
                    continue;
                }
                $error = $this->validateScaleBreakpoints($item['scale_breakpoints'] ?? []);
                if ($error !== null) {
                    $valid = false;
                    $this->setMessages([
                        $top => [$sub => [$name => ['scale_breakpoints' => [
                            sprintf('%s: %s', $name, $error), // @translate
                        ]]]],
                    ]);
                }
            }
        }

        return $valid;
    }

    protected function validateScaleBreakpoints(array $breakpoints): ?string
    {
        if (count($breakpoints) < 2) {
            return 'At least 2 breakpoints are required.'; // @translate
        }
        // Keys "min" and "max" are placeholders resolved at render time from
        // the field min/max (attributes or data). Internal values must be
        // numeric.
        $hasMin = false;
        $hasMax = false;
        $numericValues = [];
        $numericPositions = [];
        $minPosition = null;
        $maxPosition = null;
        foreach ($breakpoints as $v => $p) {
            if (!is_numeric($p)) {
                return 'Positions must be numeric.'; // @translate
            }
            $pos = (float) $p;
            if ($pos < 0.0 || $pos > 100.0) {
                return 'Positions must be between 0 and 100.'; // @translate
            }
            if ($v === 'min') {
                if ($hasMin) {
                    return 'Only one "min" placeholder is allowed.'; // @translate
                }
                $hasMin = true;
                $minPosition = $pos;
            } elseif ($v === 'max') {
                if ($hasMax) {
                    return 'Only one "max" placeholder is allowed.'; // @translate
                }
                $hasMax = true;
                $maxPosition = $pos;
            } elseif (is_numeric($v)) {
                $numericValues[] = (float) $v;
                $numericPositions[] = $pos;
            } else {
                return 'Keys must be numeric or one of "min" / "max".'; // @translate
            }
        }
        if ($hasMin && $minPosition !== 0.0) {
            return '"min" placeholder must have position 0.'; // @translate
        }
        if ($hasMax && $maxPosition !== 100.0) {
            return '"max" placeholder must have position 100.'; // @translate
        }
        // First position must be 0 (either via "min" or first numeric). Last
        // must be 100 (either via "max" or last numeric).
        if (!$hasMin) {
            // Smallest numeric position must be 0.
            if ($numericPositions === [] || min($numericPositions) !== 0.0) {
                return 'First position must be 0 (or use "min" placeholder).'; // @translate
            }
        }
        if (!$hasMax) {
            if ($numericPositions === [] || max($numericPositions) !== 100.0) {
                return 'Last position must be 100 (or use "max" placeholder).'; // @translate
            }
        }
        if ($numericValues) {
            array_multisort($numericValues, $numericPositions);
            $last = count($numericValues) - 1;
            for ($i = 1; $i <= $last; $i++) {
                if ($numericValues[$i] <= $numericValues[$i - 1]) {
                    return 'Values must be strictly increasing.'; // @translate
                }
                if ($numericPositions[$i] <= $numericPositions[$i - 1]) {
                    return 'Positions must be strictly increasing.'; // @translate
                }
            }
            // Min/max placeholder positions must not overlap with numeric ones
            // when both are used.
            if ($hasMin && in_array(0.0, $numericPositions, true)) {
                return 'A numeric breakpoint cannot share position 0 with "min".'; // @translate
            }
            if ($hasMax && in_array(100.0, $numericPositions, true)) {
                return 'A numeric breakpoint cannot share position 100 with "max".'; // @translate
            }
        }
        return null;
    }

    protected function addFormFieldset(): self
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->getOption('search_config');

        $formAdapter = $searchConfig->formAdapter();
        if (!$formAdapter) {
            return $this;
        }

        $configFormClass = $formAdapter->getConfigFormClass();
        if (!$configFormClass) {
            return $this;
        }

        /** @var \Laminas\Form\Fieldset $fieldset */
        $fieldset = $this->formElementManager
            ->get($configFormClass, ['search_config' => $searchConfig]);

        if (method_exists($fieldset, 'skipDefaultElementsOrFieldsets')) {
            foreach ($fieldset->skipDefaultElementsOrFieldsets() as $skip) {
                $this->remove($skip);
            }
        }

        $this->add($fieldset);

        return $this;
    }

    public function setFormElementManager($formElementManager): self
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    public function setSuggesters(array $suggesters): self
    {
        $this->suggesters = $suggesters;
        return $this;
    }

    public function setThumbnailTypes(array $thumbnailTypes): self
    {
        $this->thumbnailTypes = $thumbnailTypes;
        return $this;
    }

    /**
     * The available sort fields as a flat list "name => default label".
     */
    protected function availableSortFieldsFlat($searchConfig): array
    {
        $engineAdapter = $searchConfig ? $searchConfig->engineAdapter() : null;
        if (!$engineAdapter) {
            return [];
        }
        $result = [];
        foreach ($engineAdapter->getAvailableSortFields() as $name => $labelOrGroup) {
            if (is_array($labelOrGroup) && isset($labelOrGroup['options'])) {
                foreach ($labelOrGroup['options'] as $optionName => $optionLabel) {
                    $optionLabel = is_array($optionLabel) ? ($optionLabel['label'] ?? $optionName) : $optionLabel;
                    $result[$optionName] = $this->translator->translate((string) $optionLabel);
                }
                continue;
            }
            $label = is_array($labelOrGroup) ? ($labelOrGroup['label'] ?? $name) : $labelOrGroup;
            $result[$name] = $this->translator->translate((string) $label);
        }
        return $result;
    }

    public function setTranslator(Translator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }
}
