<?php declare(strict_types=1);

namespace AdvancedSearchTest;

use AdvancedSearch\Job\IndexSearch;
use AdvancedSearch\Module;
use Laminas\EventManager\Event;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the deferred indexation introduced with the indexing
 * suspension option (#7): engine selection, deferral and batch create.
 *
 * The module is driven with a mocked service locator (api + a fake
 * DeferredJobDispatch that records defer() calls), so no database, no real
 * search engine and no job dispatch are required.
 *
 * @group unit
 * @group indexation
 */
class IndexingDeferralTest extends TestCase
{
    public function testIndexableEnginesExcludeDisabledAndNonIndexable(): void
    {
        $module = $this->module([
            $this->engine(1, true, true, ['items']),   // kept
            $this->engine(2, false, true, ['items']),  // indexing disabled
            $this->engine(3, true, false, ['items']),  // cannot index items
            $this->engine(4, true, true, ['item_sets']), // items not in types
        ]);

        $ids = $this->invoke($module, 'indexableSearchEngineIds', ['items']);
        $this->assertSame([1], $ids);
    }

    public function testDeferIndexResourceDispatchesOneIndexJob(): void
    {
        $recorder = $this->recorder();
        $module = $this->module([$this->engine(1, true, true, ['items'])], $recorder);

        $this->invoke($module, 'deferIndexResource', ['items', 99]);

        $this->assertCount(1, $recorder->calls);
        $call = $recorder->calls[0];
        $this->assertSame(IndexSearch::class, $call['jobClass']);
        $this->assertSame('advancedsearch_index_search_items', $call['key']);
        $this->assertSame(99, $call['params']);
    }

    public function testNoDeferralWhenNoIndexableEngine(): void
    {
        $recorder = $this->recorder();
        $module = $this->module([$this->engine(1, false, true, ['items'])], $recorder);

        $this->invoke($module, 'deferIndexResource', ['items', 99]);

        $this->assertCount(0, $recorder->calls);
    }

    public function testPostBatchCreateDefersEachCreatedResource(): void
    {
        $recorder = $this->recorder();
        $module = $this->module([$this->engine(1, true, true, ['items'])], $recorder);

        $event = new Event();
        $event->setParam('request', new class {
            public function getResource(): string
            {
                return 'items';
            }
        });
        $event->setParam('response', new class {
            public function getContent(): array
            {
                return [
                    new class {
                        public function id(): int
                        {
                            return 101;
                        }
                    },
                    new class {
                        public function id(): int
                        {
                            return 102;
                        }
                    },
                ];
            }
        });

        $module->postBatchCreateSearchEngine($event);

        $this->assertSame([101, 102], array_column($recorder->calls, 'params'));
    }

    private function module(array $engines, ?object $recorder = null): Module
    {
        $api = new class($engines) {
            public function __construct(private array $engines)
            {
            }

            public function search($resource, $query = [])
            {
                $engines = $this->engines;
                return new class($engines) {
                    public function __construct(private array $engines)
                    {
                    }

                    public function getContent(): array
                    {
                        return $this->engines;
                    }
                };
            }
        };

        $services = new ServiceManager();
        $services->setService('Omeka\ApiManager', $api);
        $services->setService('Common\DeferredJobDispatch', $recorder ?? $this->recorder());

        $module = new Module();
        $module->setServiceLocator($services);
        return $module;
    }

    private function engine(int $id, bool $enabled, bool $canIndex, array $resourceTypes): object
    {
        $indexer = new class($canIndex) {
            public function __construct(private bool $canIndex)
            {
            }

            public function canIndex($resourceType): bool
            {
                return $this->canIndex;
            }
        };
        return new class($id, $enabled, $indexer, $resourceTypes) {
            public function __construct(
                private int $id,
                private bool $enabled,
                private object $indexer,
                private array $resourceTypes
            ) {
            }

            public function id(): int
            {
                return $this->id;
            }

            public function indexer(): object
            {
                return $this->indexer;
            }

            public function setting($key, $default = null)
            {
                if ($key === 'is_indexing_enabled') {
                    return $this->enabled;
                }
                if ($key === 'resource_types') {
                    return $this->resourceTypes;
                }
                return $default;
            }
        };
    }

    private function recorder(): object
    {
        return new class {
            public array $calls = [];

            public function defer(string $jobClass, string $key, $params = null, ?callable $argBuilder = null): void
            {
                $this->calls[] = ['jobClass' => $jobClass, 'key' => $key, 'params' => $params];
            }
        };
    }

    private function invoke(object $object, string $method, array $args)
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }
}
