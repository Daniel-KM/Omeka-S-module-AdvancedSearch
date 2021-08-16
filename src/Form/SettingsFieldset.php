<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use AdvancedSearch\Form\Element\OptionalMultiCheckbox;
use AdvancedSearch\Form\Element\OptionalSelect;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\View\Helper\Api;

class SettingsFieldset extends Fieldset
{
    /**
     * @var Api
     */
    protected $api;

    protected $label = 'Advanced Search (admin board)'; // @translate

    public function init(): void
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $this->api->search('search_configs')->getContent();

        $valueOptions = [];
        $apiOptions = [];
        foreach ($searchConfigs as $searchConfig) {
            $labelSearchConfig = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->path());
            $valueOptions[$searchConfig->id()] = $labelSearchConfig;
            if ($searchConfig->formAdapter() instanceof \AdvancedSearch\FormAdapter\ApiFormAdapter) {
                $apiOptions[$searchConfig->id()] = $labelSearchConfig;
            }
        }

        $selectAllTerms = $settings->get('advancedadvancedsearch_restrict_used_terms', false);

        $this
            ->add([
                'name' => 'advancedadvancedsearch_restrict_used_terms',
                'type' => \Laminas\Form\Element\Checkbox::class,
                'options' => [
                    'label' => 'Restrict to used properties and resources classes', // @translate
                    'info' => 'If checked, restrict the list of properties and resources classes to the used ones in advanced search form.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedadvancedsearch_restrict_used_terms',
                    'value' => $selectAllTerms,
                ],
            ]);

        $this->add([
            'name' => 'advancedsearch_main_page',
            'type' => OptionalSelect::class,
            'options' => [
                'label' => 'Default search page (admin)', // @translate
                'info' => 'This search engine is used in the admin bar.', // @translate
                'value_options' => $valueOptions,
                'empty_option' => 'Select the search engine for the admin bar…', // @translate
            ],
            'attributes' => [
                'id' => 'advancedsearch_main_page',
            ],
        ]);

        $this->add([
            'name' => 'search_configs',
            'type' => OptionalMultiCheckbox::class,
            'options' => [
                'label' => 'Available search pages', // @translate
                'value_options' => $valueOptions,
            ],
            'attributes' => [
                'id' => 'search_configs',
            ],
        ]);

        $this->add([
            'name' => 'advancedsearch_api_page',
            'type' => OptionalSelect::class,
            'options' => [
                'label' => 'Page used for quick api search', // @translate
                'info' => 'The method apiSearch() allows to do a quick search in some cases. It requires a mapping done with the Omeka api and the selected index.', // @translate
                'value_options' => $apiOptions,
                'empty_option' => 'Select the page for quick api search…', // @translate
            ],
            'attributes' => [
                'id' => 'advancedsearch_api_page',
            ],
        ]);

        $this->add([
            'name' => 'advancedsearch_batch_size',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Search batch size for reindexation', // @translate
                'info' => 'Default is 100, but it can be adapted according to your resource average size, your mapping and your architecture.', // @translate
            ],
            'attributes' => [
                'id' => 'advancedsearch_batch_size',
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
