<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

class ResourceTemplateSelect extends \Omeka\Form\Element\ResourceTemplateSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions()
    {
        return $this->getValueOptionsFix();
    }
}
