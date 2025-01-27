<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;
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
            // TODO Move these options to redirect item set to search page or a search page setting?
            ->add([
                'name' => 'advancedsearch_redirect_itemset_browse',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect item sets to item/browse', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'all' => 'All item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_redirect_itemset_browse',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_redirect_itemset_search',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect item sets to search', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'all' => 'All item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_redirect_itemset_search',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_redirect_itemset_search_first',
                'type' => CommonElement\OptionalItemSetSelect::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect item sets to search (display record only on first page, old default Omeka)', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'all' => 'All item sets', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'advancedsearch_redirect_itemset_search_first',
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select item sets…', // @translate
                ],
            ])
            ->add([
                'name' => 'advancedsearch_redirect_itemset_page_url',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect any item set to a page or a url', // @translate
                    'info' => 'Set the item set id, then the sign "=", then a page slug or a url, relative or absolute.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_redirect_itemset_page_url',
                    'placeholder' => '151 = events', // @translate
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
