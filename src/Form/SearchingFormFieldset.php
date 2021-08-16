<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\View\Helper\Api;
use Omeka\View\Helper\Setting as SiteSetting;

class SearchingFormFieldset extends Fieldset
{
    /**
     * @var Api
     */
    protected $api;

    public function init(): void
    {
        $searchConfigs = $this->searchConfigs();

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
                'name' => 'o:block[__blockIndex__][o:data][advancedsearch_config]',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Search page', // @translate
                    'info' => 'The request below will be checked against the matching form below. Keys unknown by the form will be removed.', // @translate
                    'value_options' => $searchConfigs,
                ],
                'attributes' => [
                    'id' => 'searching-form-search-config',
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
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][query_filter]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Hidden filter query', // @translate
                    'info' => 'Limit the search to a specific subset of the resources. This query is merged with the default block query and with the user one.', // @translate
                ],
                'attributes' => [
                    'id' => 'searching-form-query-filter',
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

    protected function searchConfigs()
    {
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $this->api->search('search_configs')->getContent();

        $searchConfigs = [];
        foreach ($searchConfigs as $searchConfig) {
            $searchConfigs[$searchConfig->id()] = sprintf('%s (/%s)', $searchConfig->name(), $searchConfig->path());
        }

        $siteSetting = $this->siteSetting;
        $available = $siteSetting('search_configs', []);
        $searchConfigs = array_intersect_key($searchConfigs, array_flip($available));

        // Set the main search page as default.
        $default = $siteSetting('advancedsearch_main_page') ?: reset($available);
        if (isset($searchConfigs[$default])) {
            $searchConfigs = [$default => $searchConfigs[$default]] + $searchConfigs;
        }

        return $searchConfigs;
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
