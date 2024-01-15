<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

use Common\Form\Element\TraitGroupByOwner;
use Common\Form\Element\TraitOptionalElement;

class ResourceTemplateSelect extends \Omeka\Form\Element\ResourceTemplateSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions(): array
    {
        return $this->getValueOptionsFix();
    }
}
