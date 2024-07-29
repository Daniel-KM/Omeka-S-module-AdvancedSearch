<?php declare(strict_types=1);

namespace AdvancedSearch\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class SearchResources extends AbstractPlugin
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
     * Get SearchResources.
     */
    public function __invoke(): \AdvancedSearch\Stdlib\SearchResources
    {
        return $this->searchResources;
    }

    /**
     * Proxy to SearchResources.
     *
     * @see \AdvancedSearch\Stdlib\SearchResources
     *
     * @method self startOverrideRequest($request)
     * @method self endOverrideRequest($request)
     * @method array startOverrideQuery($query, $override)
     * @method array endOverrideQuery($query, $override = null)
     * @method self setAdapter($adapter)
     * @method void buildInitialQuery($qb, $query)
     * @method array cleanQuery($query)
     * @method self searchResourcesFullText($qb, $query)
     */
    public function __call(string $name, array $arguments)
    {
        return $this->searchResources->$name(...$arguments);
    }
}
