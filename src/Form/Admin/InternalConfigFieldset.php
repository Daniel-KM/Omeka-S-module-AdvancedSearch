<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class InternalConfigFieldset extends Fieldset
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'default_search_partial_word',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Partial word search for main field (instead of standard full text search)', // @translate
                    'infos' => 'Currently, this mode does not allow to exclude properties for the main search field.', // @translate
                ],
                'attributes' => [
                    'id' => 'default_search_partial_word',
                ],
            ])
        ;
    }
}
