<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class CleanQuery extends AbstractHelper
{
    /**
     * @var \AdvancedSearch\Stdlib\SearchResources
     */
    protected $searchResources;

    public function __construct(\AdvancedSearch\Stdlib\SearchResources $searchResources)
    {
        $this->searchResources = $searchResources;
    }

    /**
     * The advanced search form returns all keys, so remove useless ones.
     */
    public function __invoke(array $query): array
    {
        return $this->searchResources->cleanQuery($query);
    }
}
