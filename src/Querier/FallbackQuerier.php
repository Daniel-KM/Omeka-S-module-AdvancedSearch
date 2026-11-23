<?php declare(strict_types=1);

namespace AdvancedSearch\Querier;

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Querier that falls back to the internal querier when the engine fails.
 *
 * The engine fails when its adapter is missing (module disabled or to upgrade)
 * or when its server is unavailable. Other errors, like an invalid query, are
 * thrown. The filters and facets on fields specific to the engine are ignored
 * by the internal querier.
 */
class FallbackQuerier implements QuerierInterface
{
    use LoggerAwareTrait;

    /**
     * @var ?QuerierInterface
     */
    protected $querier;

    /**
     * @var InternalQuerier
     */
    protected $internalQuerier;

    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * @var bool
     */
    protected $isFallback = false;

    /**
     * @param QuerierInterface|null $querier The querier of the engine, or null
     *   when its adapter is missing.
     */
    public function __construct(?QuerierInterface $querier, InternalQuerier $internalQuerier)
    {
        $this->querier = $querier;
        $this->internalQuerier = $internalQuerier;
    }

    public function setServiceLocator(ServiceLocatorInterface $services): self
    {
        $this->services = $services;
        $this->querier && $this->querier->setServiceLocator($services);
        $this->internalQuerier->setServiceLocator($services);
        return $this;
    }

    public function setSearchEngine(SearchEngineRepresentation $searchEngine): self
    {
        $this->searchEngine = $searchEngine;
        $this->querier && $this->querier->setSearchEngine($searchEngine);
        $this->internalQuerier->setSearchEngine($searchEngine);
        return $this;
    }

    public function setLogger(\Laminas\Log\LoggerInterface $logger): self
    {
        $this->logger = $logger;
        $this->querier && $this->querier->setLogger($logger);
        $this->internalQuerier->setLogger($logger);
        return $this;
    }

    public function setQuery(Query $query): self
    {
        $this->querier && $this->querier->setQuery($query);
        $this->internalQuerier->setQuery($query);
        return $this;
    }

    public function query(): Response
    {
        return $this->run(__FUNCTION__, []);
    }

    public function querySuggestions(): Response
    {
        return $this->run(__FUNCTION__, []);
    }

    public function queryValues(string $field, ?string $prefix = null, int $limit = 0): array
    {
        return $this->run(__FUNCTION__, [$field, $prefix, $limit]);
    }

    public function queryAllResourceIds(?string $resourceType = null, bool $byResourceType = false): array
    {
        return $this->run(__FUNCTION__, [$resourceType, $byResourceType]);
    }

    public function getPreparedQuery()
    {
        return $this->isFallback || !$this->querier
            ? $this->internalQuerier->getPreparedQuery()
            : $this->querier->getPreparedQuery();
    }

    /**
     * Check if the last request was processed by the internal querier.
     */
    public function isFallback(): bool
    {
        return $this->isFallback;
    }

    /**
     * Run a method with the querier of the engine, else the internal one.
     */
    protected function run(string $method, array $args)
    {
        if ($this->querier) {
            try {
                $this->isFallback = false;
                return $this->querier->$method(...$args);
            } catch (\Throwable $e) {
                // Other errors, like an invalid query, are not an outage.
                if (!$this->isOutage($e)) {
                    throw $e;
                }
                $reason = $e->getMessage();
            }
        } else {
            $reason = sprintf('Missing engine adapter "%s".', $this->searchEngine ? $this->searchEngine->engineAdapterName() : '');
        }
        $this->isFallback = true;
        $this->storeFailure($reason);
        return $this->internalQuerier->$method(...$args);
    }

    /**
     * Check if an error is an outage of the engine server.
     *
     * The server is unavailable when there is no http response (connection or
     * timeout, so a curl error code) or when it returns a server error (5xx).
     * The engine may fail before the query, for example when reading the
     * schema, so the whole chain of exceptions is checked.
     */
    protected function isOutage(\Throwable $e): bool
    {
        for ($exception = $e; $exception; $exception = $exception->getPrevious()) {
            if ($exception instanceof \Solarium\Exception\HttpException) {
                $code = (int) $exception->getCode();
                return $code < 100 || $code >= 500;
            }
        }
        return false;
    }

    /**
     * Log the failure and store it to warn the administrators.
     *
     * During an outage, the failure is logged and stored once a minute only.
     */
    protected function storeFailure(string $reason): void
    {
        if (!$this->services) {
            return;
        }

        $engineId = $this->searchEngine ? $this->searchEngine->id() : 0;
        $settings = $this->services->get('Omeka\Settings');
        $failures = $settings->get('advancedsearch_engine_fallback_last') ?: [];
        if (($failures[$engineId]['time'] ?? 0) > time() - 60) {
            return;
        }

        $this->logger && $this->logger->err(
            'Search engine #{engine_id} failed, so the internal engine is used: {reason}', // @translate
            ['engine_id' => $engineId, 'reason' => $reason]
        );

        $failures[$engineId] = ['time' => time(), 'reason' => mb_substr($reason, 0, 500)];
        $settings->set('advancedsearch_engine_fallback_last', $failures);
    }
}
