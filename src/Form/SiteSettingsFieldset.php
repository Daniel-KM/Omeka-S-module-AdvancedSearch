<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\View\Helper\Api;
use AdvancedSearch\Form\Element\OptionalMultiCheckbox;
use AdvancedSearch\Form\Element\OptionalSelect;

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

        $selectAllTerms = $settings->get('advancedsearchplus_restrict_used_terms', false);
        $searchFields = $settings->get('advancedsearchplus_search_fields', $defaultSelectedFields) ?: [];

        /** @var \Search\Api\Representation\SearchConfigRepresentation[] $pages */
        $pages = $this->api->search('search_configs')->getContent();

        $valueOptions = [];
        foreach ($pages as $page) {
            $valueOptions[$page->id()] = sprintf('%s (/%s)', $page->name(), $page->path());
        }

        $this
            ->add([
                'name' => 'advancedsearchplus_restrict_used_terms',
                'type' => \Laminas\Form\Element\Checkbox::class,
                'options' => [
                    'label' => 'Restrict to used properties and resources classes', // @translate
                    'info' => 'If checked, restrict the list of properties and resources classes to the used ones in advanced search form.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearchplus_restrict_used_terms',
                    'value' => $selectAllTerms,
                ],
            ])
            ->add([
                'name' => 'advancedsearchplus_search_fields',
                'type' => 'OptionalMultiCheckbox',
                'options' => [
                    'label' => 'Display only following fields', // @translate
                    'value_options' => $defaultSearchFields,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearchplus_search_fields',
                    'value' => $searchFields,
                ],
            ])

            ->add([
                'name' => 'search_main_page',
                'type' => OptionalSelect::class,
                'options' => [
                    'label' => 'Default search page', // @translate
                    'value_options' => $valueOptions,
                    'empty_option' => 'Select the default search engine for the site…', // @translate
                ],
                'attributes' => [
                    'id' => 'search_main_page',
                ],
            ])
            ->add([
                'name' => 'search_configs',
                'type' => OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Available search pages', // @translate
                    'value_options' => $valueOptions,
                ],
                'attributes' => [
                    'id' => 'search_configs',
                ],
            ])
            // TODO Move the option to redirect item set to search page or a search page setting?
            ->add([
                'name' => 'search_redirect_itemset',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Redirect item set page to search', // @translate
                    'info' => 'By default, item-set/show is redirected to item/browse. This option redirects it to the search page.', // @translate
                ],
                'attributes' => [
                    'id' => 'search_redirect_itemset',
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
