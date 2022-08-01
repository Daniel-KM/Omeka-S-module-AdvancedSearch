<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Mvc\Controller\Plugin\SearchResources as PluginSearchResources;
use Laminas\View\Helper\AbstractHelper;

class CleanQuery extends AbstractHelper
{
    /**
     * @var \AdvancedSearch\Mvc\Controller\Plugin\SearchResources
     */
    protected $searchResources;

    public function __construct(PluginSearchResources $searchResources)
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
