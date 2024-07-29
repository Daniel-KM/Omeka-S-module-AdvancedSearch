<?php declare(strict_types=1);

namespace AdvancedSearch\Test\Indexer;

use AdvancedSearch\Indexer\AbstractIndexer;
use AdvancedSearch\Query;
use Omeka\Entity\Resource;

class TestIndexer extends AbstractIndexer
{
    public function canIndex(string $resourceType): bool
    {
        return true;
    }

    public function clearIndex(?Query $query = null): self
    {
    }

    public function indexResource(Resource $resource): self
    {
    }

    public function indexResources(array $resources): self
    {
    }

    public function deleteResource(string $resourceType, $id): self
    {
    }
}
