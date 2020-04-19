<?php
namespace Search\Form;

use Omeka\View\Helper\Api;
use Omeka\View\Helper\Setting as SiteSetting;
use Zend\Form\Element;
use Zend\Form\Fieldset;

class SearchingFormFieldset extends Fieldset
{
    /**
     * @var Api
     */
    protected $api;

    public function init()
    {
        $searchPages = $this->searchPages();

        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][heading]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Block title', // @translate
                    'info' => 'Heading for the block, if any.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-heading',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][search_page]',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Search page', // @translate
                    'value_options' => $searchPages,
                ],
                'attributes' => [
                    'id' => 'searching-form-search-page',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][display_results]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Display results', // @translate
                    'info' => 'If not set, display only the search form.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-display-results',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][query]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query', // @translate
                    'info' => 'Display resources using this search query. Important: use the query of the engine you use, not the browse preview one.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-query',
                ],
            ])
        ;

        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $this
                ->add([
                    'name' => 'o:block[__blockIndex__][o:data][template]',
                    'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                    'options' => [
                        'label' => 'Template to display', // @translate
                        'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "searching-form".', // @translate
                        'template' => 'common/block-layout/searching-form',
                    ],
                    'attributes' => [
                        'id' => 'searching-form-template',
                        'class' => 'chosen-select',
                    ],
                ]);
        }
    }

    protected function searchPages()
    {
        /** @var \Search\Api\Representation\SearchPageRepresentation[] $pages */
        $searchPages = $this->api->search('search_pages')->getContent();

        $pages = [];
        foreach ($searchPages as $searchPage) {
            $pages[$searchPage->id()] = sprintf('%s (/%s)', $searchPage->name(), $searchPage->path());
        }

        $siteSetting = $this->siteSetting;
        $available = $siteSetting('search_pages', []);
        $pages = array_intersect_key($pages, array_flip($available));

        // Set the main search page as default.
        $default = $siteSetting('search_main_page') ?: reset($available);
        if (isset($pages[$default])) {
            $pages = [$default => $pages[$default]] + $pages;
        }

        return $pages;
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->api = $api;
        return $this;
    }

    /**
     * @param SiteSetting $siteSetting
     */
    public function setSiteSetting(SiteSetting $siteSetting)
    {
        $this->siteSetting = $siteSetting;
        return $this;
    }
}
