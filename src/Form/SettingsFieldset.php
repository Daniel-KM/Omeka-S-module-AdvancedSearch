<?php
namespace Search\Form;

use Omeka\View\Helper\Api;
use Zend\Form\Element;
use Zend\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var Api
     */
    protected $api;

    protected $label = 'Search (admin board)'; // @translate

    public function init()
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation[] $pages */
        $pages = $this->api->search('search_pages')->getContent();

        $valueOptions = [];
        foreach ($pages as $page) {
            $labelSearchPage = sprintf('%s (/%s)', $page->name(), $page->path());
            $valueOptions[$page->id()] = $labelSearchPage;
            if ($page->formAdapter() instanceof \Search\FormAdapter\ApiFormAdapter) {
                $apiOptions[$page->id()] = $labelSearchPage;
            }
        }

        $this->add([
            'name' => 'search_main_page',
            'type' => Element\Select::class,
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
            'type' => Element\MultiCheckbox::class,
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
            'type' => Element\Select::class,
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
