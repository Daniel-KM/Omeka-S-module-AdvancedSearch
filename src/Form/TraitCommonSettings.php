<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;

trait TraitCommonSettings
{
    /**
     * @var array
     */
    protected $listSearchFields = [];

    protected function initSearchFields(): self
    {
        return $this
            ->add([
                'name' => 'advancedsearch_search_fields',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Fields for standard advanced search form', // @translate
                    'info' => 'The check box marked with a "*" are improvements of the standard search fields. They should be replaced by equivalent arguments of the module Advanced Search to avoid side effects.', // @translate
                    'value_options' => $this->listSearchFields,
                    'use_hidden_element' => true,
                ],
                'attributes' => [
                    'id' => 'advancedsearch_search_fields',
                ],
            ])
        ;
    }

    public function setListSearchFields(array $listSearchFields): self
    {
        $this->listSearchFields = $listSearchFields;
        return $this;
    }
}
