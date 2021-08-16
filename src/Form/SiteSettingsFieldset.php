<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use AdvancedSearch\Form\Element\OptionalMultiCheckbox;
use AdvancedSearch\Form\Element\OptionalSelect;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\View\Helper\Api;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * Warning: there is a core fieldset "Search".
     *
     * @var string
     */
    protected $label = 'Search module'; // @translate

    public function init(): void
    {
        $defaultSearchFields = $this->getDefaultSearchFields();
        $defaultSelectedFields = [];
        foreach ($defaultSearchFields as $key => $defaultSearchField) {
            if (!array_key_exists('default', $defaultSearchField) || $defaultSearchField['default'] === true) {
                $defaultSelectedFields[] = $key;
            }
            $defaultSearchFields[$key] = $defaultSearchField['label'] ?? $key;
        }

        $selectAllTerms = $settings->get('advancedsearch_restrict_used_terms', false);
        $searchFields = $settings->get('advancedsearch_search_fields', $defaultSelectedFields) ?: [];

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $this->api->search('search_configs')->getContent();

        $valueOptions = [];
        foreach ($searchConfigs as $searchConfig) {
            $valueOptions[$searchConfig->id()] = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->path());
        }

        $this
            ->add([
                'name' => 'advancedsearch_restrict_used_terms',
                'type' => \Laminas\Form\Element\Checkbox::class,
                'options' => [
                    'label' => 'Restrict to used properties and resources classes', // @translate
                    'info' => 'If checked, restrict the list of properties and resources classes to the used ones in advanced search form.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_restrict_used_terms',
                    'value' => $selectAllTerms,
                ],
            ])
            ->add([
                'name' => 'advancedsearch_search_fields',
                'type' => 'OptionalMultiCheckbox',
                'options' => [
                    'label' => 'Display only following fields', // @translate
                    'value_options' => $defaultSearchFields,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_search_fields',
                    'value' => $searchFields,
                ],
            ])

            ->add([
                'name' => 'advancedsearch_main_config',
                'type' => OptionalSelect::class,
                'options' => [
                    'label' => 'Default search page', // @translate
                    'value_options' => $valueOptions,
                    'empty_option' => 'Select the default search engine for the site…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_configs',
                'type' => OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Available search pages', // @translate
                    'value_options' => $valueOptions,
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

    public function setApi(Api $api): Fieldset
    {
        $this->api = $api;
        return $this;
    }
}
