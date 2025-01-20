<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class SearchResources extends AbstractHelper
{
    /**
     * @var \AdvancedSearch\Stdlib\SearchResources
     */
    protected $searchResourcesService;

    public function __construct(\AdvancedSearch\Stdlib\SearchResources $searchResources)
    {
        $this->searchResourcesService = $searchResources;
    }

    /**
     * Get SearchResources.
     */
    public function __invoke(): \AdvancedSearch\Stdlib\SearchResources
    {
        return $this->searchResourcesService;
    }
}
