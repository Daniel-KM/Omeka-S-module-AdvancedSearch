<?php declare(strict_types=1);

namespace AdvancedSearch\Indexer;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Query;
use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Resource;

class NoopIndexer implements IndexerInterface
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

    public function canIndex(string $resourceType): bool
    {
        return false;
    }

    public function clearIndex(?Query $query = null): self
    {
        return $this;
    }

    public function indexResource(Resource $resource): self
    {
        return $this;
    }

    public function indexResources(array $resources): self
    {
        return $this;
    }

    public function deleteResource(string $resourceType, $id): self
    {
        return $this;
    }
}
