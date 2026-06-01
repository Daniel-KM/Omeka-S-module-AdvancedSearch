<?php declare(strict_types=1);

namespace AdvancedSearchTest\Querier;

use Omeka\Test\AbstractHttpControllerTestCase;
use ReflectionClass;

/**
 * Cover the digital_objects mapping wired into the AdvancedSearch indexing
 * pipeline.
 *
 * Touchpoints validated:
 *  - IndexSuggestions has digital_objects in both internal resource maps
 *  - IndexSearch::buildResourceQuery maps digital_objects to the table
 *  - GetSearchConfig helper registers the digital_objects config key
 *
 * Each check skips when the DigitalObject module is not installed.
 *
 * @group querier
 */
class DigitalObjectMappingTest extends AbstractHttpControllerTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\DigitalObject\Entity\DigitalObject::class)) {
            $this->markTestSkipped('Module DigitalObject not installed.');
        }
    }

    public function testIndexSuggestionsMapsDigitalObjects(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/Job/IndexSuggestions.php'
        );
        self::assertNotFalse($source);
        // Two internal maps in the file: both must reference DO.
        $matches = preg_match_all(
            '/digital_objects.+?DigitalObject\\\\Entity\\\\DigitalObject::class/',
            $source
        );
        self::assertGreaterThanOrEqual(2, $matches);
    }

    public function testIndexSearchMapsDigitalObjectsTable(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/Job/IndexSearch.php'
        );
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "'digital_objects' => 'digital_object'",
            $source
        );
    }

    public function testGetSearchConfigHelperHasDigitalObjectsKey(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/View/Helper/GetSearchConfig.php'
        );
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "'digital_objects' => 'advancedsearch_digital_objects_config'",
            $source
        );
    }

    /**
     * The InternalQuerier `queryValues()` filter expression uses INSTANCE OF on
     * the discriminator. Walk the source for the new scope filter block to
     * ensure DO is among the resolvable classes.
     */
    public function testInternalQuerierScopeFilterKnowsDigitalObjects(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/Querier/InternalQuerier.php'
        );
        self::assertNotFalse($source);
        self::assertStringContainsString(
            "'digital_objects' => \\DigitalObject\\Entity\\DigitalObject::class",
            $source
        );
        self::assertStringContainsString(
            'INSTANCE OF',
            $source
        );
    }
}
