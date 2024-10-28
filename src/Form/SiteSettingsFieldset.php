<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;
use Omeka\Settings\AbstractSettings;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var AbstractSettings
     */
    protected $settings = null;

    /**
     * @var array
     */
    protected $searchConfigs = [];

    /**
     * @var array
     */
    protected $defaultSearchFields = [];

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
        $defaultSelectedFields = [];
        foreach ($this->defaultSearchFields as $key => $defaultSearchField) {
            if (!array_key_exists('default', $defaultSearchField) || $defaultSearchField['default'] === true) {
                $defaultSelectedFields[] = $key;
            }
            $this->defaultSearchFields[$key] = $defaultSearchField['label'] ?? $key;
        }

        $searchFields = $this->settings->get('advancedsearch_search_fields') ?: $defaultSelectedFields;

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
                'name' => 'advancedsearch_search_fields',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Display only following fields', // @translate
                    'value_options' => $this->defaultSearchFields,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_search_fields',
                    'value' => $searchFields,
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

    public function setSettings(AbstractSettings $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function setSearchConfigs(array $searchConfigs): self
    {
        $this->searchConfigs = $searchConfigs;
        return $this;
    }

    public function setDefaultSearchFields(array $defaultSearchFields): self
    {
        $this->defaultSearchFields = $defaultSearchFields;
        return $this;
    }
}
