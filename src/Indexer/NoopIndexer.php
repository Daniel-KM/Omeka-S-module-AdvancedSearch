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

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator): IndexerInterface
    {
        return $this;
    }

    public function setSearchEngine(SearchEngineRepresentation $engine): IndexerInterface
    {
        return $this;
    }

    public function canIndex(string $resourceName): bool
    {
        return false;
    }

    public function clearIndex(?Query $query = null): IndexerInterface
    {
        return $this;
    }

    public function indexResource(Resource $resource): IndexerInterface
    {
        return $this;
    }

    public function indexResources(array $resources): IndexerInterface
    {
        return $this;
    }

    public function deleteResource(string $resourceName, $id): IndexerInterface
    {
        return $this;
    }
}
