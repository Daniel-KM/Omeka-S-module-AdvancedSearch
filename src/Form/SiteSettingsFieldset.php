<?php declare(strict_types=1);

namespace Search\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\View\Helper\Api;
use Search\Form\Element\OptionalMultiCheckbox;
use Search\Form\Element\OptionalSelect;

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
        /** @var \Search\Api\Representation\SearchPageRepresentation[] $pages */
        $pages = $this->api->search('search_pages')->getContent();

        $valueOptions = [];
        foreach ($pages as $page) {
            $valueOptions[$page->id()] = sprintf('%s (/%s)', $page->name(), $page->path());
        }

        $this
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
                'name' => 'search_pages',
                'type' => OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Available search pages', // @translate
                    'value_options' => $valueOptions,
                ],
                'attributes' => [
                    'id' => 'search_pages',
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
