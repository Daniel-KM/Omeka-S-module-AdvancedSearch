<?php declare(strict_types=1);

namespace AdvancedSearchTest\Stdlib;

use AdvancedSearch\Stdlib\SearchResources;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SearchResources::resolveValueLabels (module Table
 * integration).
 *
 * They mock the api and a TableRepresentation, so they don't require a real
 * Table nor a database. The method caches tables statically, so each scenario
 * uses a distinct table reference to avoid cross-test pollution.
 *
 * @group unit
 * @group table
 */
class ResolveValueLabelsTest extends TestCase
{
    public function testInlineOnlyWithoutTable(): void
    {
        $result = SearchResources::resolveValueLabels(['value_labels' => ['1' => 'Yes']], null);
        $this->assertSame(['1' => 'Yes'], $result);
    }

    public function testTableBySlug(): void
    {
        $api = $this->api('color-slug', ['r' => 'Red', 'g' => 'Green']);
        $result = SearchResources::resolveValueLabels(['value_labels_table' => 'color-slug'], $api);
        $this->assertSame(['r' => 'Red', 'g' => 'Green'], $result);
    }

    public function testTableById(): void
    {
        $api = $this->api('5', ['1' => 'On', '0' => 'Off']);
        $result = SearchResources::resolveValueLabels(['value_labels_table' => '5'], $api);
        $this->assertSame(['1' => 'On', '0' => 'Off'], $result);
    }

    public function testInlineOverridesTable(): void
    {
        $api = $this->api('mix-slug', ['1' => 'FromTable', '0' => 'Off']);
        $result = SearchResources::resolveValueLabels([
            'value_labels' => ['1' => 'Inline'],
            'value_labels_table' => 'mix-slug',
        ], $api);
        $this->assertSame('Inline', $result['1']);
        $this->assertSame('Off', $result['0']);
    }

    public function testAbsentTableFallsBackToInline(): void
    {
        // The api throws (module Table missing or table not found).
        $api = new class {
            public function search($resource, $query)
            {
                throw new \RuntimeException('No such resource.');
            }
        };
        $result = SearchResources::resolveValueLabels([
            'value_labels' => ['1' => 'Yes'],
            'value_labels_table' => 'absent-slug',
        ], $api);
        $this->assertSame(['1' => 'Yes'], $result);
    }

    public function testNullApiReturnsInline(): void
    {
        $result = SearchResources::resolveValueLabels([
            'value_labels' => ['1' => 'Yes'],
            'value_labels_table' => 'whatever-slug',
        ], null);
        $this->assertSame(['1' => 'Yes'], $result);
    }

    private function api(string $knownRef, array $codesAssociative): object
    {
        $table = new class($codesAssociative) {
            public function __construct(private array $codes)
            {
            }

            public function codesAssociative(): array
            {
                return $this->codes;
            }
        };
        $response = new class([$table]) {
            public function __construct(private array $content)
            {
            }

            public function getContent(): array
            {
                return $this->content;
            }
        };
        return new class($knownRef, $response) {
            public function __construct(private string $knownRef, private object $response)
            {
            }

            public function search($resource, $query)
            {
                $ref = isset($query['id']) ? (string) $query['id'] : ($query['slug'] ?? null);
                if ($ref !== $this->knownRef) {
                    throw new \RuntimeException('Table not found.');
                }
                return $this->response;
            }
        };
    }
}
