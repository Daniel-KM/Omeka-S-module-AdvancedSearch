<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    use TraitCommonSettings;

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
        'search_general' => 'Search', // @translate
        'advanced_search' => 'Advanced Search (module)', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'advanced-search')
            ->setOption('element_groups', $this->elementGroups)

            ->initSearchFields()

            ->add([
                'name' => 'advancedsearch_cron_index',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Enable automatic reindexation (daily cron)', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_cron_index',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_fulltextsearch_alto',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search_general',
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
                    'label' => 'Default search page in admin side bar', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the search engine for the admin side bar…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_main_config_replace_quick',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Replace the quick search of the admin side bar by this search page', // @translate
                    'info' => 'By default, the search page is added below the quick search of Omeka, so the side bar has two search fields. When replaced, only the search page remains.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config_replace_quick',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_main_config_advanced_link',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Link to the advanced search below the search form', // @translate
                    'info' => 'The dialog loads the form only at the first click, so it does not slow down the pages. A theme that displays its own panel in the header can keep "no" and fetch the url of the form itself.', // @translate
                    'value_options' => [
                        'dialog' => 'Yes, in a dialog', // @translate
                        'page' => 'Yes, to the search page', // @translate
                        '' => 'No', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config_advanced_link',
                    'value' => 'dialog',
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
