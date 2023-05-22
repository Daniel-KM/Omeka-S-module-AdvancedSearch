<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetCheckboxesTree extends AbstractFacet
{
    protected $partial = 'search/facet-checkboxes';

    protected $isTree = true;
}
