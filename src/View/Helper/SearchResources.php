<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Mvc\Controller\Plugin\SearchResources as PluginSearchResources;
use Laminas\View\Helper\AbstractHelper;

class SearchResources extends AbstractHelper
{
    /**
     * @var \AdvancedSearch\Mvc\Controller\Plugin\SearchResources
     */
    protected $searchResources;

    public function __construct(PluginSearchResources $searchResources)
    {
        $this->searchResources = $searchResources;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * The advanced search form returns all keys, so remove useless ones.
     */
    public function cleanQuery(array $query): array
    {
        return $this->searchResources->cleanQuery($query);
    }

    /**
     * Normalize the query for the date time argument.
     */
    public function normalizeQueryDateTime(array $query): array
    {
        return $this->searchResources->normalizeQueryDateTime($query);
    }
}
