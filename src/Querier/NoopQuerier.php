<?php declare(strict_types=1);

namespace Search\Querier;

use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Search\Api\Representation\SearchIndexRepresentation;
use Search\Query;
use Search\Response;

/**
 * Querier that doesn't answer anything.
 *
 * It is used to avoid a crash when a module dependency is missing.
 */
class NoopQuerier implements QuerierInterface
{
    use LoggerAwareTrait;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator): QuerierInterface
    {
        return $this;
    }

    public function setSearchIndex(SearchIndexRepresentation $index): QuerierInterface
    {
        return $this;
    }

    public function setQuery(Query $query): QuerierInterface
    {
        return $this;
    }

    public function query(): Response
    {
        return new Response;
    }

    public function getPreparedQuery()
    {
        return null;
    }
}
