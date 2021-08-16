<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\View\Helper\Api;
use AdvancedSearch\Form\Element\OptionalMultiCheckbox;
use AdvancedSearch\Form\Element\OptionalSelect;

class SettingsFieldset extends Fieldset
{
    /**
     * @var Api
     */
    protected $api;

    protected $label = 'Advanced Search (admin board)'; // @translate

    public function init(): void
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation[] $pages */
        $pages = $this->api->search('search_pages')->getContent();

        $valueOptions = [];
        $apiOptions = [];
        foreach ($pages as $page) {
            $labelSearchPage = sprintf('%s (/%s)', $page->name(), $page->path());
            $valueOptions[$page->id()] = $labelSearchPage;
            if ($page->formAdapter() instanceof \Search\FormAdapter\ApiFormAdapter) {
                $apiOptions[$page->id()] = $labelSearchPage;
            }
        }

        $selectAllTerms = $settings->get('advancedsearchplus_restrict_used_terms', false);

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
            ]);

        $this->add([
            'name' => 'search_main_page',
            'type' => OptionalSelect::class,
            'options' => [
                'label' => 'Default search page (admin)', // @translate
                'info' => 'This search engine is used in the admin bar.', // @translate
                'value_options' => $valueOptions,
                'empty_option' => 'Select the search engine for the admin bar…', // @translate
            ],
            'attributes' => [
                'id' => 'search_main_page',
            ],
        ]);

        $this->add([
            'name' => 'search_pages',
            'type' => OptionalMultiCheckbox::class,
            'options' => [
                'label' => 'Available search pages', // @translate
                'value_options' => $valueOptions,
            ],
            'attributes' => [
                'id' => 'search_pages',
            ],
        ]);

        $this->add([
            'name' => 'search_api_page',
            'type' => OptionalSelect::class,
            'options' => [
                'label' => 'Page used for quick api search', // @translate
                'info' => 'The method apiSearch() allows to do a quick search in some cases. It requires a mapping done with the Omeka api and the selected index.', // @translate
                'value_options' => $apiOptions,
                'empty_option' => 'Select the page for quick api search…', // @translate
            ],
            'attributes' => [
                'id' => 'search_api_page',
            ],
        ]);

        $this->add([
            'name' => 'search_batch_size',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Search batch size for reindexation', // @translate
                'info' => 'Default is 100, but it can be adapted according to your resource average size, your mapping and your architecture.', // @translate
            ],
            'attributes' => [
                'id' => 'search_batch_size',
                'min' => 1,
            ],
        ]);
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->api = $api;
        return $this;
    }
}
