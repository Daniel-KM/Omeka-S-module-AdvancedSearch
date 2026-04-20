<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;

class SearchConfigSitesFieldset extends Fieldset implements InputFilterProviderInterface
{
    protected $label = 'Sites'; // @translate

    public function getInputFilterSpecification(): array
    {
        return [
            'manage_config_default' => ['required' => false],
            'manage_config_availability' => ['required' => false],
            'manage_config_default_admin' => ['required' => false],
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
                        'none' => '[No site]', // @translate
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
                    'value' => ['enable'],
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
        ;
    }
}
