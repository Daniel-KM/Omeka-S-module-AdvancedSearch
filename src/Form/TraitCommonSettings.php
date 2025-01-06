<?php declare(strict_types=1);

namespace AdvancedSearch\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;

trait TraitCommonSettings
{
    /**
     * @var array
     */
    protected $listSearchFields = [];

    protected function initImprovedSearch(): self
    {
        return $this
            ->add([
                'name' => 'advancedsearch_property_improved',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Support improved search of properties (deprecated: use filters)', // @translate
                    'info' => 'To override the default search elements is not recommended, so the improvements are now available in the element "filter".', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_property_improved',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_metadata_improved',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Support improved search of resource, without owner, class, template, or item set', // @translate
                    'info' => 'To override the default search elements is not recommended', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_metadata_improved',
                ],
            ])
            ->add([
                'name' => 'advancedsearch_media_type_improved',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Support improved search of media types, main and multiple media types', // @translate
                    'info' => 'To override the default search elements is not recommended', // @translate
                ],
                'attributes' => [
                    'id' => 'advancedsearch_media_type_improved',
                ],
            ])
        ;
    }

    protected function initSearchFields(): self
    {
        return $this
            ->add([
                'name' => 'advancedsearch_search_fields',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'search',
                    'label' => 'Fields for standard advanced search form', // @translate
                    'info' => 'The fields marked with a "*" are available only when matching option is set above.', // @translate
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
