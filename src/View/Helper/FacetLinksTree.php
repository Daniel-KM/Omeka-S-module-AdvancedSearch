<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

class FacetLinksTree extends AbstractFacet
{
    protected $partial = 'search/facet-links';

    protected $isTree = true;
}
