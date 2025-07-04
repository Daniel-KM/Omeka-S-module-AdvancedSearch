<?php declare(strict_types=1);

namespace AdvancedSearch\Querier;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Querier that doesn't answer anything.
 *
 * It is used to avoid a crash when a module dependency is missing.
 */
class NoopQuerier implements QuerierInterface
{
    use LoggerAwareTrait;

    public function setServiceLocator(ServiceLocatorInterface $services): self
    {
        return $this;
    }

    public function setSearchEngine(SearchEngineRepresentation $searchEngine): self
    {
        return $this;
    }

    public function setQuery(Query $query): QuerierInterface
    {
        $query->setQuerier($this);
        return $this;
    }

    public function query(): Response
    {
        return new Response();
    }

    public function querySuggestions(): Response
    {
        return new Response();
    }

    public function queryValues(string $field): array
    {
        return [];
    }

    public function queryAllResourceIds(?string $resourceType = null, bool $byResourceType = false): array
    {
        return [];
    }

    public function getPreparedQuery()
    {
        return null;
    }
}
