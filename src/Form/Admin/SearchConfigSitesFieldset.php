<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;
use Omeka\Api\Manager as ApiManager;

class SearchConfigSitesFieldset extends Fieldset implements InputFilterProviderInterface
{
    protected $label = 'Sites'; // @translate

    /**
     * @var \Omeka\Api\Manager
     */
    protected $apiManager;

    public function setApiManager(ApiManager $apiManager): self
    {
        $this->apiManager = $apiManager;
        return $this;
    }

    public function getInputFilterSpecification(): array
    {
        return [
            'manage_config_default' => ['required' => false],
            'manage_config_availability' => ['required' => false],
            'manage_config_default_admin' => ['required' => false],
            'manage_config_default_api' => ['required' => false],
        ];
    }

    public function init(): void
    {
        $this
            ->setName('sites')
            ->setAttribute('id', 'sites')
            ->add([
                'name' => 'manage_config_default',
                'type' => CommonElement\OptionalSiteSelect::class,
                'options' => [
                    'label' => 'Default search page for sites', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'all' => '[All sites]', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'manage_config_default',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select sites…', // @translate
                ],
            ])
            ->add([
                'name' => 'manage_config_availability',
                'type' => CommonElement\OptionalSiteSelect::class,
                'options' => [
                    'label' => 'Availability on sites', // @translate
                    'empty_option' => '',
                    'prepend_value_options' => [
                        'enable' => 'Make available in all sites', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'manage_config_availability',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select sites…', // @translate
                    // A search page is made available on all sites only when
                    // there is a single site, else the choice of the sites is
                    // an explicit decision.
                    'value' => $this->apiManager->search('sites', ['limit' => 0])->getTotalResults() === 1
                        ? ['enable']
                        : [],
                ],
            ])
            ->add([
                'name' => 'manage_config_default_admin',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Default search page for admin board', // @translate
                ],
                'attributes' => [
                    'id' => 'manage_config_default_admin',
                ],
            ])
            ->add([
                'name' => 'manage_config_default_api',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Default search page for the api', // @translate
                    'info' => 'The api uses the index of the engine of this page, so this option has an effect only when the engine is an external index, like Solr. With the internal engine, the api keeps querying the database.', // @translate
                ],
                'attributes' => [
                    'id' => 'manage_config_default_api',
                ],
            ])
            // The text is filled by the controller, that knows the config.
            ->add([
                'name' => 'manage_config_usage',
                'type' => CommonElement\Note::class,
                'options' => [
                    'label' => 'Current use of this search page', // @translate
                    'text' => '',
                    'disable_html_escape' => true,
                ],
            ])
        ;
    }
}
