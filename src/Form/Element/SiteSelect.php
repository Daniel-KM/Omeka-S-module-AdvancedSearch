<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

use Common\Form\Element\TraitGroupByOwner;
use Common\Form\Element\TraitOptionalElement;

class SiteSelect extends \Omeka\Form\Element\SiteSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions(): array
    {
        return $this->getValueOptionsFix();
    }
}
