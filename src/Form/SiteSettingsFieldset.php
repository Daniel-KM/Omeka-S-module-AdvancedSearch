<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SiteSettingsFieldset extends Fieldset
{
    use TraitCommonSettings;

    /**
     * @var array
     */
    protected $searchConfigs = [];

    /**
     * Warning: there is a core fieldset "Search" (before Omeka v4).
     *
     * @var string
     */
    protected $label = 'Advanced Search (module)'; // @translate

    protected $elementGroups = [
        'search' => 'Search', // @translate
        'advanced_search' => 'Advanced Search (module)', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'advanced-search')
            ->setOption('element_groups', $this->elementGroups)

            ->initSearchFields()

            ->add([
                'name' => 'advancedsearch_configs',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Available search pages', // @translate
                    'value_options' => $this->searchConfigs,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_configs',
                ],
            ])

            ->add([
                'name' => 'advancedsearch_main_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Default search page', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the default search engine for the site…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config',
                ],
            ])

            ->add([
                'name' => 'advancedsearch_hidden_query_filters_per_config',
                'type' => CommonElement\ArrayQueriesTextarea::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Hidden query filters per search page', // @translate
                    'info' => 'One filter per line, formatted as "search_config_slug = query_args" (e.g. "recherche = item_set_id[]=151"). Filters are merged with the search config "Hidden query filter" only on this site, so other sites sharing the same search page are unaffected.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_hidden_query_filters_per_config',
                    'rows' => 4,
                    'placeholder' => <<<'TXT'
                        find = item_set_id[]=151
                        bibliography = item_set_id[]=152
                        TXT,
                ],
            ])

            // TODO Move these options to redirect item set to search page or a search page setting?
            ->add([
                'name' => 'advancedsearch_item_sets_redirect_browse',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Item sets to redirect to item/browse', // @translate
                    'empty_option' => '',
                    'disable_inarray_validator' => true,
                    'prepend_value_options' => [
                        'all' => 'All item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_redirect_browse',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_item_sets_redirect_search',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Item sets to redirect to search', // @translate
                    'empty_option' => '',
                    'disable_inarray_validator' => true,
                    'prepend_value_options' => [
                        'all' => 'All item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_redirect_search',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_item_sets_redirect_search_first',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Item sets to redirect to search (display record only on first page, old default Omeka)', // @translate
                    'empty_option' => '',
                    'disable_inarray_validator' => true,
                    'prepend_value_options' => [
                        'all' => 'All item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_redirect_search_first',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_item_sets_redirect_page_url',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Item sets to redirect to a page or a url', // @translate
                    'info' => 'Set the item set id, then the sign "=", then a page slug or a url, relative or absolute.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_redirect_page_url',
                    'placeholder' => '151 = events', // @translate
                ],
            ])

            // Specific to sites.
            ->add([
                'name' => 'advancedsearch_item_sets_browse_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect page "browse item sets" to a search page', // @translate
                    'value_options' => [
                        '' => 'No redirect', // @translate
                        'default' => 'Default search page', // @translate
                    ] + $this->searchConfigs,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_browse_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_item_sets_browse_page',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect page "browse item sets" to a site page or a url', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_browse_page',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_items_browse_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect page "browse items" to a search page', // @translate
                    'value_options' => [
                        '' => 'No redirect', // @translate
                        'default' => 'Default search page', // @translate
                    ] + $this->searchConfigs,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_items_browse_config',
                ],
            ])

            // Resource blocks.

            ->add([
                'name' => 'advancedsearch_items_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (item): Config', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the search engine for items…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_items_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_items_template_form',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (item): Form template', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_items_template_form',
                    'placeholder' => 'search/search-form',
                ],
            ])

            ->add([
                'name' => 'advancedsearch_media_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (media): Config', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the search engine for medias…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_media_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_media_template_form',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (media): Form template', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_media_template_form',
                    'placeholder' => 'search/search-form',
                ],
            ])

            // This config is used for item set/show too.
            ->add([
                'name' => 'advancedsearch_item_sets_config',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (item set): Config', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the search engine for item sets…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_item_sets_template_form',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (item set): Form template', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_template_form',
                    'placeholder' => 'search/search-form',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_item_sets_scope',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Search (item set): Scope of the search', // @translate
                    'value_options' => [
                        '0' => 'Search in current item set', // @translate
                        '1' => 'Search in all resources', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_item_sets_scope',
                ],
            ])

            ->add([
                'name' => 'advancedsearch_resource_nav_types',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Resource navigation: active contexts', // @translate
                    'value_options' => [
                        'search' => 'Search results', // @translate
                        'collection' => 'Item set', // @translate
                        'selection' => 'User selection', // @translate
                        'series' => 'Series in page blocks', // @translate
                        'featured' => 'Featured (browse preview page block)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_resource_nav_types',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_resource_nav_limit',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Resource navigation: max results', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_resource_nav_limit',
                    'min' => '0',
                    'step' => '1',
                    'value' => '25',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_resource_nav_display',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Resource navigation: parts to display', // @translate
                    'value_options' => [
                        'type_label' => 'Type label (Search, Item set, Selection…)', // @translate
                        'context_label' => 'Context name (item set title, selection title, query)', // @translate
                        'position' => 'Position (n / total)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_resource_nav_display',
                    'value' => ['type_label', 'context_label', 'position'],
                ],
            ])
            ->add([
                'name' => 'advancedsearch_resource_nav_fallback_item_set',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Block Resource navigation: fall back to an item set on direct access', // @translate
                    'info' => 'When no browse context exists (no URL param, no session), show a navigation within an item set of the item. "First" picks the first item set; a property sorts the item sets by that property value and picks the first with a non-empty value.', // @translate
                    'empty_option' => 'Disabled', // @translate
                    'prepend_value_options' => [
                        'first' => 'First item set', // @translate
                    ],
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_resource_nav_fallback_item_set',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a fallback…', // @translate
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
