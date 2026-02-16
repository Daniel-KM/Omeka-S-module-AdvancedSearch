<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Api\Manager as ApiManager;
use Omeka\Form\Element as OmekaElement;

class SearchSuggesterForm extends Form
{
    /**
     * @var ApiManager
     */
    protected $apiManager;

    public function init(): void
    {
        $isAdd = (bool) $this->getOption('add');

        $this
            ->add([
                'name' => 'o:name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Name', // @translate
                ],
                'attributes' => [
                    'id' => 'name',
                    'required' => true,
                ],
            ])
        ;

        if ($isAdd) {
            $this
                ->add([
                    'name' => 'o:search_engine',
                    'type' => Element\Select::class,
                    'options' => [
                        'label' => 'Search engine', // @translate
                        'value_options' => $this->getSearchEngineOptions(),
                        'empty_option' => 'Select a search engine below…', // @translate
                    ],
                    'attributes' => [
                        'id' => 'search_engine',
                        'required' => true,
                    ],
                ]);
            // The search engine is required to set settings.
            return;
        }

        $this
            ->add([
                'name' => 'o:search_engine',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Search engine', // @translate
                    'value_options' => $this->getSearchEngineOptions(),
                    'empty_option' => 'Select a search engine below…', // @translate
                ],
                'attributes' => [
                    'id' => 'search_engine',
                    'readonly' => true,
                    'disabled' => true,
                    'info' => 'For Solr, the suggester can be set in the config.', // @translate
                ],
            ]);

        $this
            ->add([
                'name' => 'o:settings',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Suggester settings', // @translate
                ],
            ]);

        $fieldset = $this
            ->get('o:settings');

        $isInternal = (bool) $this->getOption('is_internal');
        if (!$isInternal) {
            return;
        }

        // TODO Add a default query to manage any suggestion on any field and suggestions on item set page.

        $fieldset
            ->add([
                'name' => 'sites',
                'type' => CommonElement\OptionalSiteSelect::class,
                'options' => [
                    'label' => 'Sites to index', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'admin' => 'Admin', // @translate
                        'all' => '[All sites]', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'sites',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select sites…', // @translate
                    'value' => ['admin', 'all'],
                ],
            ]);

        $fieldset
            ->add([
                'name' => 'mode_index',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Mode to index values', // @translate
                    'value_options' => [
                        'start' => 'First words of values', // @translate
                        'contain' => 'All words', // @translate
                        'full' => 'Full value', // @translate
                        'start_full' => 'First words of values and full value', // @translate
                        'contain_full' => 'All words and full value', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode_index',
                    'required' => false,
                    'value' => 'start',
                ],
            ])
            ->add([
                'name' => 'mode_search',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Mode to search suggestions', // @translate
                    'value_options' => [
                        'start' => 'Start of a word', // @translate
                        'contain' => 'In word', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'mode_search',
                    'required' => false,
                    'value' => 'start',
                ],
            ])
            ->add([
                'name' => 'limit',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Max number of results', // @translate
                ],
                'attributes' => [
                    'id' => 'limit',
                    'required' => false,
                    'value' => \Omeka\Stdlib\Paginator::PER_PAGE,
                    'min' => '0',
                ],
            ])
            ->add([
                'name' => 'length',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Max number of characters of a result', // @translate
                ],
                'attributes' => [
                    'id' => 'length',
                    'required' => false,
                    'value' => '50',
                    'min' => '1',
                    'max' => '190',
                ],
            ])
            ->add([
                'name' => 'fields',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Limit query to specific fields', // @translate
                    'info' => 'With the internal search engine, it is not recommended to use full text content.', // @translate
                    'value_options' => $this->getAvailableFields(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'fields',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select fields…', // @translate
                ],
            ])
            ->add([
                'name' => 'excluded_fields',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Exclude fields', // @translate
                    'info' => 'Allow to skip the full text content, that may be useless for suggestions.', // @translate
                    'value_options' => $this->getAvailableFields(),
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'excluded_fields',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select fields…', // @translate
                ],
            ])
            ->add([
                'name' => 'stopwords',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'label' => 'Stopwords', // @translate
                    'info' => 'Suggestions starting or ending with these words will be excluded. One word per line.', // @translate
                    'documentation' => 'https://github.com/stopwords-iso/stopwords-iso',
                ],
                'attributes' => [
                    'id' => 'stopwords',
                    'rows' => 5,
                    'value' => [
                        // English
                        'a', 'an', 'and', 'at', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with',
                        // French
                        'a', 'au', 'aux', 'd', 'de', 'des', 'du', 'en', 'et', 'l', 'la', 'le', 'les', 'un', 'une',
                    ],
                ],
            ])
            ->add([
                'name' => 'stopwords_mode',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Stopwords position', // @translate
                    'value_options' => [
                        'start_end' => 'Start and end (recommended)', // @translate
                        'start' => 'Start only', // @translate
                        'end' => 'End only', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'stopwords_mode',
                    'value' => 'start_end',
                ],
            ])
        ;
    }

    public function setApiManager(ApiManager $apiManager): self
    {
        $this->apiManager = $apiManager;
        return $this;
    }

    protected function getSearchEngineOptions(): array
    {
        $options = [];

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $this->apiManager->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            $options[$searchEngine->id()] =
            sprintf('%s (%s)', $searchEngine->name(), $searchEngine->engineAdapterLabel());
        }

        return $options;
    }

    protected function getAvailableFields(): array
    {
        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation $searchEngine */
        $searchEngine = $this->getOption('search_engine');
        $engineAdapter = $searchEngine ? $searchEngine->engineAdapter() : null;
        return $engineAdapter
            ? $engineAdapter->getAvailableFieldsForSelect()
            : [];
    }
}
