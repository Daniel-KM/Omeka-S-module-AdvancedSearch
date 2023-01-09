<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use AdvancedSearch\Form\Element as AdvancedSearchElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var array
     */
    protected $searchConfigs = [];

    /**
     * @var array
     */
    protected $searchConfigsApi = [];

    /**
     * @var bool
     */
    protected $restrictUsedTerms = false;

    protected $label = 'Advanced Search (admin board)'; // @translate

    public function init(): void
    {
        $this
            ->setAttribute('id', 'advanced-search')
            /** @deprecated Since Omeka v3.1 */
            ->add([
                'name' => 'advancedsearch_restrict_used_terms',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Restrict to used properties and resources classes', // @translate
                    'info' => 'If checked, restrict the list of properties and resources classes to the used ones in advanced search form.', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_restrict_used_terms',
                    'value' => $this->restrictUsedTerms,
                ],
            ])
            ->add([
                'name' => 'advancedsearch_main_config',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Default search page (admin)', // @translate
                    'info' => 'This search engine is used in the admin bar.', // @translate
                    'value_options' => $this->searchConfigs,
                    'empty_option' => 'Select the search engine for the admin bar…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_main_config',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_configs',
                'type' => AdvancedSearchElement\OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Available search pages', // @translate
                    'value_options' => $this->searchConfigs,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_configs',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_api_config',
                'type' => AdvancedSearchElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Quick api search via external search engine', // @translate
                    'info' => 'The method apiSearch() allows to do a quick search in some cases. It requires a mapping done with the Omeka api and the selected index.', // @translate
                    'value_options' => $this->searchConfigsApi,
                    'empty_option' => 'Select the config for quick api search…', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_api_config',
                ],
            ])
            ->add([
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

    public function setSearchConfigs(array $searchConfigs): Fieldset
    {
        $this->searchConfigs = $searchConfigs;
        return $this;
    }

    public function setSearchConfigsApi(array $searchConfigsApi): Fieldset
    {
        $this->searchConfigsApi = $searchConfigsApi;
        return $this;
    }

    public function setRestrictUsedTerms(bool $restrictUsedTerms): Fieldset
    {
        $this->restrictUsedTerms = $restrictUsedTerms;
        return $this;
    }
}
