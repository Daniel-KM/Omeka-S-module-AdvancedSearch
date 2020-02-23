<?php
namespace Search\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class SearchingFormFieldset extends Fieldset
{
    public function init()
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][heading]',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Block title', // @translate
                    'info' => 'Heading for the block, if any.', // @translate
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
                        'class' => 'chosen-select',
                    ],
                ]);
        }
    }
}
