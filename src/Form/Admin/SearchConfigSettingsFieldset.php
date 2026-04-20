<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use AdvancedSearch\FormAdapter\Manager as SearchFormAdapterManager;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;
use Omeka\Api\Manager as ApiManager;

class SearchConfigSettingsFieldset extends Fieldset implements InputFilterProviderInterface
{
    protected $label = 'Settings'; // @translate

    public function getInputFilterSpecification(): array
    {
        return [
            'o:name' => ['required' => true],
            'o:slug' => ['required' => true],
            'o:search_engine' => ['required' => true],
            'o:form_adapter' => ['required' => true],
        ];
    }

    protected ?ApiManager $apiManager = null;

    protected ?SearchFormAdapterManager $formAdapterManager = null;

    public function init(): void
    {
        $this
            ->setName('settings')
            ->setAttribute('id', 'settings')
            ->add([
                'name' => 'o:name',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Name', // @translate
                ],
                'attributes' => [
                    'id' => 'name',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:slug',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Slug', // @translate
                    'info' => 'The slug to the search form. The site slug will be automatically prepended.', // @translate
                ],
                'attributes' => [
                    'id' => 'slug',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:search_engine',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Search engine', // @translate
                    'value_options' => $this->getSearchEngineOptions(),
                    'empty_option' => 'Select a search engine below…', // @translate
                ],
                'attributes' => [
                    'id' => 'search_engine',
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'o:form_adapter',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Form', // @translate
                    'value_options' => $this->getFormsOptions(),
                    'empty_option' => 'Select a form below…', // @translate
                ],
                'attributes' => [
                    'id' => 'form_adapter',
                    'required' => true,
                ],
            ])
        ;
    }

    public function setApiManager(ApiManager $apiManager): self
    {
        $this->apiManager = $apiManager;
        return $this;
    }

    public function setFormAdapterManager(SearchFormAdapterManager $formAdapterManager): self
    {
        $this->formAdapterManager = $formAdapterManager;
        return $this;
    }

    protected function getSearchEngineOptions(): array
    {
        if (!$this->apiManager) {
            return [];
        }
        $options = [];
        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $this->apiManager->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            $options[$searchEngine->id()] = sprintf('%s (%s)', $searchEngine->name(), $searchEngine->engineAdapterLabel());
        }
        return $options;
    }

    protected function getFormsOptions(): array
    {
        if (!$this->formAdapterManager) {
            return [];
        }
        $options = [];
        foreach ($this->formAdapterManager->getRegisteredNames() as $name) {
            $options[$name] = $this->formAdapterManager->get($name)->getLabel();
        }
        return $options;
    }
}
