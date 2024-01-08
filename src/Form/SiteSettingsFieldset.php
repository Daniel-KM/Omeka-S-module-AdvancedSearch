<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
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
            // TODO Move the option to redirect item set to search page or a search page setting?
            ->add([
                'name' => 'advancedsearch_redirect_itemset',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'advanced_search',
                    'label' => 'Redirect item set page to search', // @translate
                    'info' => 'By default, item-set/show is redirected to item/browse. This option redirects it to the search page.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_redirect_itemset',
                    'value' => true,
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
