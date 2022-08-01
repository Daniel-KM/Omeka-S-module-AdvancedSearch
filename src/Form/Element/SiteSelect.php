<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

class SiteSelect extends \Omeka\Form\Element\SiteSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions()
    {
        return $this->getValueOptionsFix();
    }
}
