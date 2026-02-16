<?php declare(strict_types=1);

namespace AdvancedSearch\Job;

use AdvancedSearch\Api\Representation\SearchSuggesterRepresentation;
use AdvancedSearch\Entity\SearchSuggestion;
use Omeka\Job\AbstractJob;

/**
 * Index suggestions for the internal search engine.
 *
 * Optimized for per-site indexing:
 * - Global (site_id = NULL): all resources (public + private) for admin
 * - Per site: both total (all) and total_public (public only)
 *
 * @todo Incremental update: when a resource is added/modified/deleted, update
 * the suggestion counts accordingly instead of full reindexation:
 * - On resource create: extract suggestions from values, increment totals
 * - On resource update: diff old/new values, adjust totals
 * - On resource delete: decrement totals, remove suggestions with total=0
 * - On site assignment change: move counts between sites
 */
class IndexSuggestions extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $modeIndex;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->easyMeta = $services->get('Common\EasyMeta');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->connection = $this->entityManager->getConnection();

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('search/suggester/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $suggesterId = $this->getArg('search_suggester_id');
        /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
        $suggester = $this->api->read('search_suggesters', $suggesterId)->getContent();

        $searchEngine = $suggester->searchEngine();
        $engineAdapter = $searchEngine->engineAdapter();
        if (!$engineAdapter || !($engineAdapter instanceof \AdvancedSearch\EngineAdapter\Internal)) {
            $this->logger->err(
                'Suggester #{search_suggester_id} ("{name}"): Only search engine with the internal adapter (sql) can be indexed.', // @translate
                ['search_suggester_id' => $suggester->id(), 'name' => $suggester->name()]
            );
            return;
        }

        $resourceTypes = $searchEngine->setting('resource_types', []);
        $mapResources = [
            'resources' => \Omeka\Entity\Resource::class,
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'value_annotations' => \Omeka\Entity\ValueAnnotation::class,
            'annotations' => \Annotate\Entity\Annotation::class,
        ];
        $resourceClasses = array_intersect_key($mapResources, array_flip($resourceTypes));
        if (!$resourceClasses) {
            $this->logger->notice(
                'Suggester #{search_suggester_id} ("{name}"): there is no resource type to index.', // @translate
                ['search_suggester_id' => $suggester->id(), 'name' => $suggester->name()]
            );
            return;
        }

        $listJobStatusesByIds = $services->get('ControllerPluginManager')->get('listJobStatusesByIds');
        $listJobStatusesByIds = $listJobStatusesByIds(self::class, true, null, $this->job->getId());
        $force = $this->getArg('force');
        if (count($listJobStatusesByIds)) {
            if (!$force) {
                $this->logger->err(
                    'Suggester #{search_suggester_id} ("{name}"): There are already {total} other jobs "Index Suggestion" and the current one is not forced.', // @translate
                    [
                        'search_suggester_id' => $suggester->id(),
                        'name' => $suggester->name(),
                        'total' => count($listJobStatusesByIds),
                    ]
                );
                return;
            }
            $this->logger->warn(
                'There are already {total} other jobs "Index Suggestions". Slowdowns may occur.', // @translate
                ['total' => count($listJobStatusesByIds)]
            );
        }

        $timeStart = microtime(true);
        $this->modeIndex = $suggester->setting('mode_index') ?: 'start';

        $this->logger->notice(
            'Suggester #{search_suggester_id} ("{name}"): start of indexing (mode: {mode}).', // @translate
            ['search_suggester_id' => $suggester->id(), 'name' => $suggester->name(), 'mode' => $this->modeIndex]
        );

        $this->process($suggester);

        $timeTotal = (int) (microtime(true) - $timeStart);
        $totalResults = $this->entityManager->getRepository(SearchSuggestion::class)->count(['suggester' => $suggester->id()]);

        $this->logger->notice(
            'Suggester #{search_suggester_id} ("{name}"): end of indexing. {total} suggestions indexed. Execution time: {duration} seconds.', // @translate
            ['search_suggester_id' => $suggester->id(), 'name' => $suggester->name(), 'total' => $totalResults, 'duration' => $timeTotal]
        );
    }

    protected function process(SearchSuggesterRepresentation $suggester): self
    {
        // TODO Index value annotations with resources (for now, they can't be selected individually in the config).

        $suggesterId = $suggester->id();

        // Delete existing suggestions for this suggester.
        $sql = 'DELETE FROM `search_suggestion` WHERE `suggester_id` = :suggester_id';
        $this->connection->executeStatement($sql, ['suggester_id' => $suggesterId]);

        $this->logger->info('Suggester #{id}: old suggestions deleted.', ['id' => $suggesterId]);

        // Get configuration.
        $resourceTypes = $suggester->searchEngine()->setting('resource_types', []);
        $mapResourcesToClasses = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'value_annotations' => \Omeka\Entity\ValueAnnotation::class,
            'annotations' => \Annotate\Entity\Annotation::class,
        ];
        $resourceClasses = in_array('resources', $resourceTypes)
            ? []
            : array_intersect_key($mapResourcesToClasses, array_flip($resourceTypes));

        $fields = $suggester->setting('fields') ?: [];
        $fields = $this->easyMeta->propertyIds($fields);
        $excludedFields = $suggester->setting('excluded_fields') ?: [];
        $excludedFields = $this->easyMeta->propertyIds($excludedFields);

        // Build SQL conditions.
        $bind = ['suggester_id' => $suggesterId];
        $types = ['suggester_id' => \Doctrine\DBAL\ParameterType::INTEGER];

        $sqlResourceTypes = '';
        if ($resourceClasses) {
            $sqlResourceTypes = 'AND `resource`.`resource_type` IN (:resource_types)';
            $bind['resource_types'] = array_values($resourceClasses);
            $types['resource_types'] = $this->connection::PARAM_STR_ARRAY;
        }

        $sqlFields = '';
        if ($fields) {
            $sqlFields = 'AND `value`.`property_id` IN (:properties)';
            $bind['properties'] = array_values($fields);
            $types['properties'] = $this->connection::PARAM_INT_ARRAY;
        }
        if ($excludedFields) {
            $sqlFields .= ' AND `value`.`property_id` NOT IN (:excluded_property_ids)';
            $bind['excluded_property_ids'] = array_values($excludedFields);
            $types['excluded_property_ids'] = $this->connection::PARAM_INT_ARRAY;
        }

        // Get all site IDs.
        $siteIds = $this->connection->executeQuery('SELECT `id` FROM `site`')->fetchFirstColumn();

        // Create temporary table for suggestions with both total and total_public.
        // site_id = 0 means global (all sites).
        $sql = <<<'SQL'
            DROP TABLE IF EXISTS `_suggestions_temp`;
            CREATE TEMPORARY TABLE `_suggestions_temp` (
                `text` VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
                `site_id` INT NOT NULL DEFAULT 0,
                `total` INT NOT NULL DEFAULT 0,
                `total_public` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`text`, `site_id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            SQL;
        $this->connection->executeStatement($sql);

        // 1. Index global (site_id = NULL): all resources (public + private).
        $this->logger->info('Suggester #{id}: indexing global (all resources)...', ['id' => $suggesterId]);
        $this->indexSuggestions($sqlResourceTypes, $sqlFields, null, $bind, $types);

        // 2. Index per site: both total (all) and total_public (public only).
        foreach ($siteIds as $siteId) {
            if ($this->shouldStop()) {
                $this->logger->warn('Job stopped by user.');
                return $this;
            }
            $this->logger->info('Suggester #{id}: indexing site #{site_id}...', ['id' => $suggesterId, 'site_id' => $siteId]);
            $this->indexSuggestions($sqlResourceTypes, $sqlFields, (int) $siteId, $bind, $types);
        }

        // Transfer from temporary table to final tables.
        $sql = <<<SQL
            INSERT INTO `search_suggestion` (`suggester_id`, `text`)
            SELECT DISTINCT :suggester_id, `text`
            FROM `_suggestions_temp`
            WHERE LENGTH(`text`) > 1
            ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);
            SQL;
        $this->connection->executeStatement($sql, $bind, $types);

        // site_id = 0 means global (all sites).
        $sql = <<<SQL
            INSERT INTO `search_suggestion_site` (`suggestion_id`, `site_id`, `total`, `total_public`)
            SELECT s.`id`, t.`site_id`, t.`total`, t.`total_public`
            FROM `_suggestions_temp` t
            JOIN `search_suggestion` s ON s.`text` = t.`text` AND s.`suggester_id` = :suggester_id
            WHERE LENGTH(t.`text`) > 1
            ON DUPLICATE KEY UPDATE `total` = VALUES(`total`), `total_public` = VALUES(`total_public`);
            SQL;
        $this->connection->executeStatement($sql, $bind, $types);

        // Cleanup.
        $this->connection->executeStatement('DROP TABLE IF EXISTS `_suggestions_temp`');

        return $this;
    }

    /**
     * Index suggestions for a specific site or global (null).
     *
     * For global (null): indexes all resources (public + private) into total.
     * For site: indexes all site resources into total, public only into total_public.
     */
    protected function indexSuggestions(
        string $sqlResourceTypes,
        string $sqlFields,
        ?int $siteId,
        array $bind,
        array $types
    ): void {
        if ($siteId === null) {
            // Global: all resources (public + private), no site filter.
            // Use 0 as sentinel value (will be converted to NULL in final table).
            $sqlSite = '';
            $siteValue = '0';

            // Index all resources into total column.
            $this->indexWithMode($sqlResourceTypes, $sqlFields, $sqlSite, $siteValue, '', 'total', $bind, $types);
            // For global, total_public = count of public resources.
            $sqlVisibility = 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1';
            $this->indexWithMode($sqlResourceTypes, $sqlFields, $sqlSite, $siteValue, $sqlVisibility, 'total_public', $bind, $types);
        } else {
            // Site: filter by item_site.
            $sqlSite = 'JOIN `item_site` ON `item_site`.`item_id` = `resource`.`id` AND `item_site`.`site_id` = ' . $siteId;
            $siteValue = (string) $siteId;

            // Index all site resources (public + private) into total column.
            $this->indexWithMode($sqlResourceTypes, $sqlFields, $sqlSite, $siteValue, '', 'total', $bind, $types);
            // Index public only into total_public column.
            $sqlVisibility = 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1';
            $this->indexWithMode($sqlResourceTypes, $sqlFields, $sqlSite, $siteValue, $sqlVisibility, 'total_public', $bind, $types);
        }
    }

    /**
     * Index suggestions with the configured mode (start, contain, full).
     */
    protected function indexWithMode(
        string $sqlResourceTypes,
        string $sqlFields,
        string $sqlSite,
        string $siteValue,
        string $sqlVisibility,
        string $column,
        array $bind,
        array $types
    ): void {
        // For start mode: extract 1, 2, 3 word combinations.
        if ($this->modeIndex === 'start' || $this->modeIndex === 'start_full' || !$this->modeIndex) {
            for ($numberWords = 3; $numberWords >= 1; $numberWords--) {
                // Don't "insert ignore and distinct", increment on duplicate.
                $sql = <<<SQL
                    INSERT INTO `_suggestions_temp` (`text`, `site_id`, `$column`)
                    SELECT
                        SUBSTRING(
                            TRIM(
                                # Security replacements.
                                TRIM('"' FROM
                                TRIM("'" FROM
                                TRIM("\\\\" FROM
                                TRIM("%" FROM
                                TRIM("_" FROM
                                TRIM("#" FROM
                                TRIM("?" FROM
                                # Cleaning replacements.
                                TRIM("," FROM
                                TRIM(";" FROM
                                TRIM("!" FROM
                                TRIM(":" FROM
                                TRIM("." FROM
                                TRIM("[" FROM
                                TRIM("]" FROM
                                TRIM("<" FROM
                                TRIM(">" FROM
                                TRIM("(" FROM
                                TRIM(")" FROM
                                TRIM("{" FROM
                                TRIM("}" FROM
                                TRIM("=" FROM
                                TRIM("&" FROM
                                TRIM(
                                    SUBSTRING_INDEX(
                                        CONCAT(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", ' '), "\r", ' ')), ' '),
                                        ' ',
                                        $numberWords
                                    )
                                )))))))))))))))))))))))
                            ),
                            1, 190
                        ),
                        $siteValue,
                        1
                    FROM `value`
                    JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
                        $sqlResourceTypes
                    $sqlSite
                    WHERE `value`.`value` IS NOT NULL
                        $sqlFields
                        $sqlVisibility
                    ON DUPLICATE KEY UPDATE `_suggestions_temp`.`$column` = `_suggestions_temp`.`$column` + 1;
                    SQL;
                $this->connection->executeStatement($sql, $bind, $types);
            }
        }

        // For full mode: also add complete trimmed values.
        if ($this->modeIndex === 'full' || $this->modeIndex === 'start_full' || $this->modeIndex === 'contain_full') {
            $sql = <<<SQL
                INSERT INTO `_suggestions_temp` (`text`, `site_id`, `$column`)
                SELECT
                    TRIM(SUBSTRING(REPLACE(REPLACE(`value`.`value`, "\n", ' '), "\r", ' '), 1, 190)),
                    $siteValue,
                    1
                FROM `value`
                JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
                    $sqlResourceTypes
                $sqlSite
                WHERE `value`.`value` IS NOT NULL
                    $sqlFields
                    $sqlVisibility
                ON DUPLICATE KEY UPDATE `_suggestions_temp`.`$column` = `_suggestions_temp`.`$column` + 1;
                SQL;
            $this->connection->executeStatement($sql, $bind, $types);
        }
    }
}
