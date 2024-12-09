<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var array
     */
    protected $searchConfigs = [];

    /**
     * @var array
     */
    protected $searchConfigsApi = [];

    protected $label = 'Advanced Search (admin board)'; // @translate

    protected $elementGroups = [
        'search' => 'Search', // @translate
        'advanced_search' => 'Advanced Search (module)', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'advanced-search')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'advancedsearch_property_improved',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Support improved search of properties (not recommended: use filters)', // @translate
                    'info' => 'To override the default search elements is not recommended, so the improvements are now available in the element "filter".', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_property_improved',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_fulltextsearch_alto',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Add xml alto text to full text search', // @translate
                    'info' => 'Allow to search text stored in xml alto files without including it in a property.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_fulltextsearch_alto',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_main_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Default search page (admin)', // @translate
                    'info' => 'This search engine is used in the admin bar.', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the search engine for the admin bar…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_api_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Quick api search via external search engine', // @translate
                    'info' => 'The method apiSearch() allows to do a quick search in some cases. It requires a mapping done with the Omeka api and the selected index.', // @translate
                    'value_options' => $this->searchConfigsApi,
                    'empty_option' => 'Select the config for quick api search…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_api_config',
                ],
            ])
            // TODO Remove this option if there is no issue with async (except multiple search engines).
            ->add([
                'name' => 'advancedsearch_index_batch_edit',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Indexing after a batch edit process', // @translate
                    'info' => 'On some complex databases, an issue may occur after a batch process.', // @translate
                    'value_options' => [
                        // TODO Use async indexation when short batch edit and sync when background batch edit?
                        // 'auto' => 'Automatic', // @translate
                        'sync' => 'Synchronous', // @translate
                        'async' => 'Asynchronous', // @translate
                        'integrated' => 'Integrated', // @translate
                        'none' => 'None', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_index_batch_edit',
                ],
            ])
        ;
    }

    public function setSearchConfigs(array $searchConfigs): self
    {
        $this->searchConfigs = $searchConfigs;
        return $this;
    }

    public function setSearchConfigsApi(array $searchConfigsApi): self
    {
        $this->searchConfigsApi = $searchConfigsApi;
        return $this;
    }
}
