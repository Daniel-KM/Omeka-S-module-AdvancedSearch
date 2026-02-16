<?php declare(strict_types=1);

namespace AdvancedSearchTest\Job;

use AdvancedSearch\Query;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for suggester indexation.
 *
 * These tests verify that:
 * 1. Suggestions are correctly indexed per site
 * 2. Global index (site_id = 0) contains all resources
 * 3. Per-site index contains only site resources
 * 4. Public/private visibility is correctly handled
 * 5. Suggestion queries return correct results
 *
 * @group suggester
 * @group job
 */
class IndexSuggestionsTest extends AbstractHttpControllerTestCase
{
    /**
     * @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected $searchEngine;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation
     */
    protected $suggester;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $site;

    /**
     * @var \Omeka\Api\Representation\ItemRepresentation[]
     */
    protected $items = [];

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAdmin();

        $services = $this->getApplication()->getServiceManager();
        $this->connection = $services->get('Omeka\Connection');

        // Create a test site
        $response = $this->api()->create('sites', [
            'o:title' => 'Test Site for Suggestions',
            'o:slug' => 'test-suggestions',
            'o:theme' => 'default',
        ]);
        $this->site = $response->getContent();

        // Create test items with various titles
        $this->createTestItems();

        // Create search engine with internal adapter
        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestSuggesterEngine',
            'o:engine_adapter' => 'internal',
            'o:settings' => [
                'resource_types' => ['items'],
            ],
        ]);
        $this->searchEngine = $response->getContent();

        // Create suggester
        $response = $this->api()->create('search_suggesters', [
            'o:name' => 'TestSuggester',
            'o:search_engine' => ['o:id' => $this->searchEngine->id()],
            'o:settings' => [
                'mode_index' => 'start',
                'mode_search' => 'start',
                'limit' => 25,
                'length' => 50,
                'fields' => [],
                'excluded_fields' => [],
            ],
        ]);
        $this->suggester = $response->getContent();
    }

    public function tearDown(): void
    {
        // Delete suggester and engine
        if ($this->suggester) {
            try {
                $this->api()->delete('search_suggesters', $this->suggester->id());
            } catch (\Exception $e) {
                // Ignore
            }
        }
        if ($this->searchEngine) {
            try {
                $this->api()->delete('search_engines', $this->searchEngine->id());
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Delete test items
        foreach ($this->items as $item) {
            try {
                $this->api()->delete('items', $item->id());
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Delete test site
        if ($this->site) {
            try {
                $this->api()->delete('sites', $this->site->id());
            } catch (\Exception $e) {
                // Ignore
            }
        }

        parent::tearDown();
    }

    protected function loginAdmin(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $auth = $services->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    protected function api(): \Omeka\Api\Manager
    {
        return $this->getApplication()->getServiceManager()->get('Omeka\ApiManager');
    }

    protected function getPropertyId(string $term): int
    {
        $services = $this->getApplication()->getServiceManager();
        $easyMeta = $services->get('Common\EasyMeta');
        return $easyMeta->propertyId($term);
    }

    protected function createTestItems(): void
    {
        $titles = [
            'Paris in Spring',
            'Paris at Night',
            'London Bridge',
            'London Eye',
            'New York City',
        ];

        foreach ($titles as $i => $title) {
            $isPublic = ($i < 3); // First 3 are public, last 2 are private
            $response = $this->api()->create('items', [
                'o:is_public' => $isPublic,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => $title,
                    ],
                ],
            ]);
            $item = $response->getContent();
            $this->items[] = $item;

            // Add first 3 items to the test site
            if ($i < 3) {
                $this->addItemToSite($item->id(), $this->site->id());
            }
        }
    }

    protected function addItemToSite(int $itemId, int $siteId): void
    {
        $sql = 'INSERT INTO `item_site` (`item_id`, `site_id`) VALUES (?, ?)';
        $this->connection->executeStatement($sql, [$itemId, $siteId]);
    }

    /**
     * Run the indexation job synchronously for testing.
     */
    protected function runIndexation(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $entityManager = $services->get('Omeka\EntityManager');

        // Create job entity with arguments
        $jobEntity = new \Omeka\Entity\Job();
        $jobEntity->setStatus(\Omeka\Entity\Job::STATUS_IN_PROGRESS);
        $jobEntity->setClass(\AdvancedSearch\Job\IndexSuggestions::class);
        $jobEntity->setArgs([
            'search_suggester_id' => $this->suggester->id(),
            'force' => true,
        ]);
        $entityManager->persist($jobEntity);
        $entityManager->flush();

        // Create job instance with required constructor arguments
        $job = new \AdvancedSearch\Job\IndexSuggestions($jobEntity, $services);

        // Run
        $job->perform();
    }

    // =========================================================================
    // TESTS
    // =========================================================================

    /**
     * Test that suggester can be created.
     */
    public function testSuggesterCreation(): void
    {
        $this->assertNotNull($this->suggester);
        $this->assertEquals('TestSuggester', $this->suggester->name());
    }

    /**
     * Test that indexation creates suggestions.
     */
    public function testIndexationCreatesSuggestions(): void
    {
        $this->runIndexation();

        // Check that suggestions were created
        $sql = 'SELECT COUNT(*) FROM `search_suggestion` WHERE `suggester_id` = ?';
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertGreaterThan(0, $count, 'Indexation should create suggestions');
    }

    /**
     * Test that global index (site_id = 0) is created.
     */
    public function testGlobalIndexCreated(): void
    {
        $this->runIndexation();

        // Check for global index entries (site_id = 0 means global)
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion_site` sss
            JOIN `search_suggestion` ss ON ss.`id` = sss.`suggestion_id`
            WHERE ss.`suggester_id` = ? AND sss.`site_id` = 0
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertGreaterThan(0, $count, 'Global index (site_id = 0) should be created');
    }

    /**
     * Test that per-site index is created.
     */
    public function testPerSiteIndexCreated(): void
    {
        $this->runIndexation();

        // Check for site-specific index entries
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion_site` sss
            JOIN `search_suggestion` ss ON ss.`id` = sss.`suggestion_id`
            WHERE ss.`suggester_id` = ? AND sss.`site_id` = ?
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id(), $this->site->id()])->fetchOne();

        $this->assertGreaterThan(0, $count, 'Per-site index should be created');
    }

    /**
     * Test that "Paris" suggestions are found.
     */
    public function testSuggestionsForParis(): void
    {
        $this->runIndexation();

        $sql = <<<SQL
            SELECT ss.`text`, sss.`total`, sss.`total_public`
            FROM `search_suggestion` ss
            JOIN `search_suggestion_site` sss ON sss.`suggestion_id` = ss.`id`
            WHERE ss.`suggester_id` = ?
              AND ss.`text` LIKE 'Paris%'
              AND sss.`site_id` = 0
            SQL;
        $results = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchAllAssociative();

        $this->assertNotEmpty($results, 'Should find suggestions starting with "Paris"');
    }

    /**
     * Test that total counts include private resources.
     */
    public function testTotalIncludesPrivate(): void
    {
        $this->runIndexation();

        // Get global totals
        $sql = <<<SQL
            SELECT SUM(sss.`total`) as total_all, SUM(sss.`total_public`) as total_public
            FROM `search_suggestion_site` sss
            JOIN `search_suggestion` ss ON ss.`id` = sss.`suggestion_id`
            WHERE ss.`suggester_id` = ? AND sss.`site_id` = 0
            SQL;
        $result = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchAssociative();

        // Total should be >= total_public (includes private items)
        $this->assertGreaterThanOrEqual(
            $result['total_public'],
            $result['total_all'],
            'Total should include private resources'
        );
    }

    /**
     * Test that site index only contains site items.
     */
    public function testSiteIndexOnlyContainsSiteItems(): void
    {
        $this->runIndexation();

        // "New York" is not in the site, so should not appear in site index
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            JOIN `search_suggestion_site` sss ON sss.`suggestion_id` = ss.`id`
            WHERE ss.`suggester_id` = ?
              AND ss.`text` LIKE 'New York%'
              AND sss.`site_id` = ?
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id(), $this->site->id()])->fetchOne();

        $this->assertEquals(0, $count, '"New York" should not be in site index');
    }

    /**
     * Test that "London" is in site index (item is in site).
     */
    public function testSiteItemInSiteIndex(): void
    {
        $this->runIndexation();

        // "London Bridge" is in the site
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            JOIN `search_suggestion_site` sss ON sss.`suggestion_id` = ss.`id`
            WHERE ss.`suggester_id` = ?
              AND ss.`text` LIKE 'London%'
              AND sss.`site_id` = ?
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id(), $this->site->id()])->fetchOne();

        $this->assertGreaterThan(0, $count, '"London" should be in site index');
    }

    /**
     * Test reindexation clears old data.
     */
    public function testReindexationClearsOldData(): void
    {
        // Run indexation twice
        $this->runIndexation();

        $sql = 'SELECT COUNT(*) FROM `search_suggestion` WHERE `suggester_id` = ?';
        $countBefore = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->runIndexation();

        $countAfter = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        // Counts should be the same (not doubled)
        $this->assertEquals($countBefore, $countAfter, 'Reindexation should not duplicate data');
    }

    // =========================================================================
    // STOPWORDS TESTS
    // =========================================================================

    /**
     * Test suggester with stopwords setting.
     */
    public function testSuggesterWithStopwords(): void
    {
        // Update suggester with stopwords (must include name for update).
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $this->suggester->name(),
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords' => ['the', 'a', 'le', 'la'],
                'stopwords_mode' => 'start_end',
            ]),
        ]);

        // Reload suggester.
        $this->suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();

        $stopwords = $this->suggester->setting('stopwords');
        $this->assertIsArray($stopwords);
        $this->assertContains('the', $stopwords);
        $this->assertContains('le', $stopwords);
    }

    /**
     * Test stopwords_mode setting.
     */
    public function testStopwordsModeSettings(): void
    {
        $name = $this->suggester->name();

        // Test start_end mode (default).
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $name,
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords_mode' => 'start_end',
            ]),
        ]);
        $suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();
        $this->assertEquals('start_end', $suggester->setting('stopwords_mode'));

        // Test start mode.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $name,
            'o:settings' => array_merge($suggester->settings(), [
                'stopwords_mode' => 'start',
            ]),
        ]);
        $suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();
        $this->assertEquals('start', $suggester->setting('stopwords_mode'));

        // Test end mode.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $name,
            'o:settings' => array_merge($suggester->settings(), [
                'stopwords_mode' => 'end',
            ]),
        ]);
        $suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();
        $this->assertEquals('end', $suggester->setting('stopwords_mode'));
    }

    /**
     * Test stopwords at end are filtered.
     *
     * Creates items with titles ending in stopwords and verifies they are excluded.
     */
    public function testStopwordsEndFiltering(): void
    {
        // Create an item with title ending in stopword
        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'Museum of the',
                ],
            ],
        ]);
        $itemWithStopword = $response->getContent();
        $this->items[] = $itemWithStopword;

        // Update suggester with stopwords.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $this->suggester->name(),
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords' => ['the', 'a', 'of'],
                'stopwords_mode' => 'end',
            ]),
        ]);
        $this->suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();

        $this->runIndexation();

        // "Museum of the" ends with "the" -> should be filtered
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ? AND ss.`text` = 'Museum of the'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertEquals(0, $count, 'Suggestion ending with stopword "the" should be filtered');

        // But "Museum" alone should exist (1 word extraction)
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ? AND ss.`text` = 'Museum'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertGreaterThan(0, $count, 'Single word "Museum" should exist');
    }

    /**
     * Test stopwords at start are filtered.
     */
    public function testStopwordsStartFiltering(): void
    {
        // Create an item with title starting with stopword
        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'The Grand Museum',
                ],
            ],
        ]);
        $itemWithStopword = $response->getContent();
        $this->items[] = $itemWithStopword;

        // Update suggester with stopwords - start mode only.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $this->suggester->name(),
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords' => ['the', 'a', 'le', 'la'],
                'stopwords_mode' => 'start',
            ]),
        ]);
        $this->suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();

        $this->runIndexation();

        // "The Grand" starts with "The" -> should be filtered
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ? AND ss.`text` = 'The Grand'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertEquals(0, $count, 'Suggestion starting with stopword "The" should be filtered');
    }

    /**
     * Test stopwords at both start and end are filtered with start_end mode.
     */
    public function testStopwordsStartEndFiltering(): void
    {
        // Create items - one starting with stopword, one ending with stopword,
        // one without stopwords at boundaries.
        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'A beautiful day',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();

        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'End of a',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();

        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'Beautiful sunny day',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();

        // Update suggester with stopwords - start_end mode.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $this->suggester->name(),
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords' => ['a', 'the'],
                'stopwords_mode' => 'start_end',
            ]),
        ]);
        $this->suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();

        $this->runIndexation();

        // "A beautiful" starts with "A" -> should be filtered.
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ? AND ss.`text` = 'A beautiful'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();
        $this->assertEquals(0, $count, 'Suggestion starting with "A" should be filtered');

        // "End of a" ends with "a" -> should be filtered.
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ? AND ss.`text` = 'End of a'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();
        $this->assertEquals(0, $count, 'Suggestion ending with "a" should be filtered');

        // "Beautiful sunny day" doesn't start or end with stopword -> should exist.
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ? AND ss.`text` = 'Beautiful sunny day'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();
        $this->assertGreaterThan(0, $count, '"Beautiful sunny day" should exist (no stopword at boundaries)');
    }

    /**
     * Test that stopwords with special regex characters are escaped.
     */
    public function testStopwordsRegexEscaping(): void
    {
        // Test that special characters in stopwords don't break regex.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $this->suggester->name(),
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords' => ['c++', 'c#', 'test.word', '(test)'],
                'stopwords_mode' => 'start_end',
            ]),
        ]);
        $this->suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();

        // This should not throw an exception
        $this->runIndexation();

        // If we get here without exception, the test passes
        $this->assertTrue(true, 'Stopwords with special regex characters should be escaped');
    }

    /**
     * Test empty stopwords list does not filter anything.
     */
    public function testEmptyStopwordsNoFiltering(): void
    {
        // Ensure no stopwords.
        $this->api()->update('search_suggesters', $this->suggester->id(), [
            'o:name' => $this->suggester->name(),
            'o:settings' => array_merge($this->suggester->settings(), [
                'stopwords' => [],
                'stopwords_mode' => 'start_end',
            ]),
        ]);
        $this->suggester = $this->api()->read('search_suggesters', $this->suggester->id())->getContent();

        $this->runIndexation();

        // All titles should be indexed
        $sql = 'SELECT COUNT(*) FROM `search_suggestion` WHERE `suggester_id` = ?';
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertGreaterThan(0, $count, 'With no stopwords, suggestions should be created');
    }

    // =========================================================================
    // CASE NORMALIZATION TESTS
    // =========================================================================

    /**
     * Test case normalization keeps majority version.
     *
     * When "Paris" appears more often than "paris", "Paris" should be kept.
     */
    public function testCaseNormalizationKeepsMajority(): void
    {
        // Create items with different case versions.
        // 3 items with "Paris" (uppercase P) and 1 with "paris" (lowercase).
        for ($i = 0; $i < 3; $i++) {
            $response = $this->api()->create('items', [
                'o:is_public' => true,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => 'Paris Museum',
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'paris museum',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();

        $this->runIndexation();

        // "Paris" should be kept (majority), not "paris".
        $sql = <<<SQL
            SELECT ss.`text` FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ?
              AND LOWER(ss.`text`) = 'paris'
            SQL;
        $result = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertEquals('Paris', $result, 'Majority version "Paris" should be kept');
    }

    /**
     * Test case normalization with lowercase majority.
     *
     * When "maison" appears more often than "Maison", "maison" should be kept.
     */
    public function testCaseNormalizationLowercaseMajority(): void
    {
        // Create items: 1 with "Maison" and 3 with "maison".
        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'Maison blanche',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();

        for ($i = 0; $i < 3; $i++) {
            $response = $this->api()->create('items', [
                'o:is_public' => true,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => 'maison blanche',
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        $this->runIndexation();

        // "maison blanche" should be kept (majority), not "Maison blanche".
        $sql = <<<SQL
            SELECT ss.`text` FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ?
              AND LOWER(ss.`text`) = 'maison blanche'
            SQL;
        $result = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertEquals('maison blanche', $result, 'Majority version "maison blanche" should be kept');
    }

    /**
     * Test case normalization merges counts.
     *
     * Total count should be sum of all case variants.
     * Note: Each item is indexed 3 times (1, 2, 3 word extractions).
     * For single word "NASA", all extractions give "NASA", so count = items × 3.
     */
    public function testCaseNormalizationMergesCounts(): void
    {
        // Create 2 items with "NASA" and 1 with "nasa".
        for ($i = 0; $i < 2; $i++) {
            $response = $this->api()->create('items', [
                'o:is_public' => true,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => 'NASA',
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        $response = $this->api()->create('items', [
            'o:is_public' => true,
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    'property_id' => $this->getPropertyId('dcterms:title'),
                    '@value' => 'nasa',
                ],
            ],
        ]);
        $this->items[] = $response->getContent();

        $this->runIndexation();

        // Total = 3 items × 3 extractions = 9.
        // The ratio is preserved: 6 from "NASA" (2×3), 3 from "nasa" (1×3).
        // Winner should be "NASA" (majority: 6 > 3).
        $sql = <<<SQL
            SELECT sss.`total`, ss.`text` FROM `search_suggestion` ss
            JOIN `search_suggestion_site` sss ON sss.`suggestion_id` = ss.`id`
            WHERE ss.`suggester_id` = ?
              AND LOWER(ss.`text`) = 'nasa'
              AND sss.`site_id` = 0
            SQL;
        $result = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchAssociative();

        // Total is sum of all variants.
        $this->assertEquals(9, $result['total'], 'Total should be sum of all case variants (9 = 3 items × 3 extractions)');
        $this->assertEquals('NASA', $result['text'], 'Majority version "NASA" should be kept');
    }

    /**
     * Test no duplicate suggestions after case normalization.
     */
    public function testNoDuplicatesAfterCaseNormalization(): void
    {
        // Create items with different case versions.
        $variants = ['Test Case', 'test case', 'TEST CASE', 'Test case'];
        foreach ($variants as $variant) {
            $response = $this->api()->create('items', [
                'o:is_public' => true,
                'dcterms:title' => [
                    [
                        'type' => 'literal',
                        'property_id' => $this->getPropertyId('dcterms:title'),
                        '@value' => $variant,
                    ],
                ],
            ]);
            $this->items[] = $response->getContent();
        }

        $this->runIndexation();

        // Should have only one suggestion for "test case" (case-insensitive).
        $sql = <<<SQL
            SELECT COUNT(*) FROM `search_suggestion` ss
            WHERE ss.`suggester_id` = ?
              AND LOWER(ss.`text`) = 'test case'
            SQL;
        $count = $this->connection->executeQuery($sql, [$this->suggester->id()])->fetchOne();

        $this->assertEquals(1, $count, 'Should have only one suggestion after case normalization');
    }
}
