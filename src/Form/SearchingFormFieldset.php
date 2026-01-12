<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SearchingFormFieldset extends Fieldset
{
    /**
     * @var array
     */
    protected $searchConfigs = [];

    public function init(): void
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][search_config]',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Search config page', // @translate
                    'value_options' => [
                        'default' => 'Search config of the site', // @translate
                    ] + $this->searchConfigs,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'searching-form-search-config',
                    'class' => 'chosen-select',
                    'required' => true,
                    'data-placeholder' => 'Select a search engine…', // @translate
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][display_results]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display results', // @translate
                    'info' => 'If not set, display only the search form.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-display-results',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][properties]',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Properties to display for each result', // @translate
                    'info' => 'List of property terms to display below each result, one by line.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-properties',
                    'rows' => 5,
                    'placeholder' => <<<TXT
                        dcterms:creator
                        dcterms:date
                        dcterms:subject
                        TXT,
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][query]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query', // @translate
                    'info' => 'Display resources using this search query. Important: use the format of the query of the engine (standard api request or solr).', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-query',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][query_filter]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Hidden filter query', // @translate
                    'info' => 'Limit the search to a specific subset of the resources. This query is merged with the default block query and with the user one.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-query-filter',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][link]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Link to display', // @translate
                    'info' => 'Formatted as "/url/full/path Label of the link".', // @translate
                ],
                'attributes' => [
                    'id' => 'search-form-link',
                ],
            ])
        ;
    }

    public function setSearchConfigs(array $searchConfigs): self
    {
        $this->searchConfigs = $searchConfigs;
        return $this;
    }
}
