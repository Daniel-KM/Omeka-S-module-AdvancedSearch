<?php declare(strict_types=1);

namespace AdvancedSearch\Job;

use AdvancedSearch\Api\Representation\SearchSuggesterRepresentation;
use AdvancedSearch\Entity\SearchSuggestion;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

/**
 * @todo This is an internal indexer, not the generic suggestion indexer.
 */
class IndexSuggestions extends AbstractJob
{
    const BATCH_SIZE = 100;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * List of property ids by term and id.
     *
     * @var array
     */
    protected $propertiesByTermsAndIds;

    public function perform(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\ORM\EntityManager $entityManager
         */
        $services = $this->getServiceLocator();

        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->connection = $this->entityManager->getConnection();
        $this->logger = $services->get('Omeka\Logger');
        $this->api = $services->get('ControllerPluginManager')->get('api');

        // The reference id is the job id for now.
        if (class_exists('Log\Stdlib\PsrMessage')) {
            $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
            $referenceIdProcessor->setReferenceId('search/suggester/job_' . $this->job->getId());
            $this->logger->addProcessor($referenceIdProcessor);
        }

        $suggesterId = $this->getArg('search_suggester_id');
        /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
        $suggester = $this->api->read('search_suggesters', $suggesterId)->getContent();

        $engine = $suggester->engine();
        $searchAdapter = $engine->adapter();
        if (!$searchAdapter || !($searchAdapter instanceof \AdvancedSearch\Adapter\InternalAdapter)) {
            $this->logger->err(new Message(
                'Suggester #%d ("%s"): Only search engine with the intenal adapter (sql) can be indexed currently.', // @translate
                $suggester->id(), $suggester->name()
            ));
            return;
        }

        $resourceNames = $engine->setting('resources', []);
        $mapResources = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'annotations' => \Annotate\Entity\Annotation::class,
        ];
        $resourceNames = array_intersect_key($mapResources, array_flip($resourceNames));
        if (!$resourceNames) {
            $this->logger->notice(new Message(
                'Suggester #%d ("%s"): there is no resource type to index or the indexation is not needed.', // @translate
                $suggester->id(), $suggester->name()
            ));
            return;
        }

        $totalJobs = $services->get('ControllerPluginManager')->get('totalJobs');
        $totalJobs = $totalJobs(self::class, true);
        $force = $this->getArg('force');
        if ($totalJobs > 1) {
            if (!$force) {
                $this->logger->err(new Message(
                    'Suggester #%d ("%s"): There are already %d other jobs "Index Suggestions" and the current one is not forced.', // @translate
                    $suggester->id(), $suggester->name(), $totalJobs - 1
                ));
                return;
            }
            $this->logger->warn(new Message(
                'There are already %d other jobs "Index Suggestions". Slowdowns may occur on the site.', // @translate
                $totalJobs - 1
            ));
        }

        $timeStart = microtime(true);

        $this->logger->info(new Message('Suggester #%d ("%s"): start of indexing', // @translate
            $suggester->id(), $suggester->name()));

        $this->process($suggester);

        $timeTotal = (int) (microtime(true) - $timeStart);

        $totalResults = $this->entityManager->getRepository(SearchSuggestion::class)->count(['suggester' => $suggester->id()]);

        $this->logger->info(new Message('Suggester #%d ("%s"): end of indexing. %s suggestions indexed. Execution time: %s seconds.', // @translate
            $suggester->id(), $suggester->name(), $totalResults, $timeTotal
        ));
    }

    protected function process(SearchSuggesterRepresentation $suggester): self
    {
        $dql = 'DELETE FROM AdvancedSearch\Entity\SearchSuggestion s WHERE s.suggester = ' . $suggester->id();
        $query = $this->entityManager->createQuery($dql);
        $totalDeleted = $query->execute();

        $this->logger->notice(new Message('Suggester #%d ("%s"): %d suggestions deleted.', // @translate
            $suggester->id(), $suggester->name(), $totalDeleted));

        $resourceNames = $suggester->engine()->setting('resources', []);
        $mapResources = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'annotations' => \Annotate\Entity\Annotation::class,
        ];
        $resourceNames = array_intersect_key($mapResources, array_flip($resourceNames));

        // FIXME Fields are not only properties, but titles, classes and templates.
        $fields = $suggester->setting('fields') ?: [];

        $mode = $suggester->setting('mode') === 'contain' ? 'contain' : 'start';
        $mode === 'start'
            ? $this->processStart($suggester, $resourceNames, $fields)
            : $this->processContain($suggester, $resourceNames, $fields);

        return $this;
    }

    protected function processStart(SearchSuggesterRepresentation $suggester, array $resourceTypes, array $fields): self
    {
        $bind = [
            'suggester_id' => $suggester->id(),
            'resource_types' => array_values($resourceTypes),
        ];
        $types = [
            'resource_types' => $this->connection::PARAM_STR_ARRAY,
        ];

        if ($fields) {
            $sqlFields = 'AND `value`.`property_id` IN (:properties)';
            $bind['properties'] = $fields;
            $types['properties'] = $this->connection::PARAM_INT_ARRAY;
        } else {
            $sqlFields = '';
        }

        $sql = <<<'SQL'
# Process listing in a temporary table: the table has no auto-increment id.
DROP TABLE IF EXISTS `_suggestions_temporary`;
CREATE TEMPORARY TABLE `_suggestions_temporary` (
    `text` VARCHAR(190) NOT NULL COLLATE utf8mb4_unicode_ci,
    `total_all` INT NOT NULL DEFAULT 1,
    `total_public` INT NOT NULL DEFAULT 0,
    PRIMARY KEY(`text`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

SQL;

        $sqlsVisibility = [
            'all' => '',
            'public' => 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1',
        ];
        foreach ($sqlsVisibility as $column => $sqlVisibility) {
            for ($numberWords = 2; $numberWords >= 1; $numberWords--) {
                // Don't "insert ignore and distinct", increment on duplicate.
                $sql .= <<<SQL
# Create $numberWords words index (compute $column).
INSERT INTO `_suggestions_temporary` (`text`)
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
            TRIM("’" FROM
            TRIM(
                SUBSTRING_INDEX(
                    CONCAT(TRIM(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " ")), " "),
                    " ",
                    $numberWords
                )
            ))))))))))))))))))))))))
        ),
    1, 190)
FROM `value` AS `value`
JOIN `resource`
    ON `resource`.`id` = `value`.`resource_id`
    AND `resource`.`resource_type` IN (:resource_types)
WHERE `value`.`value` IS NOT NULL
    $sqlFields
    $sqlVisibility
ON DUPLICATE KEY UPDATE `_suggestions_temporary`.`total_$column` = `_suggestions_temporary`.`total_$column` + 1;

SQL;
            }
        }

        $sql .= <<<SQL
# Finalize creation of suggestions.
INSERT INTO `search_suggestion` (`suggester_id`, `text`, `total_all`, `total_public`)
SELECT DISTINCT
    :suggester_id,
    `text`,
    `total_all`,
    `total_public`
FROM `_suggestions_temporary`
WHERE
    LENGTH(`text`) > 1;
DROP TABLE IF EXISTS `_suggestions_temporary`;

SQL;

        $this->connection->executeQuery($sql, $bind, $types);

        return $this;
    }

    protected function processContain(SearchSuggesterRepresentation $suggester, array $resourceTypes, array $fields): self
    {
        return $this;
    }

    /**
     * Get property ids by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getPropertyIds(array $termOrIds): array
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return array_values(array_intersect_key($this->propertiesByTermsAndIds, array_flip($termOrIds)));
    }

    /**
     * Prepare the list of properties and used properties.
     */
    protected function prepareProperties(): void
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select([
                    'DISTINCT property.id AS id',
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'property.id',
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
                ->addGroupBy('property.id')
            ;
            $stmt = $this->connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $properties = array_map('intval', array_column($properties, 'id', 'term'));
            $this->propertiesByTermsAndIds = array_replace($properties, array_combine($properties, $properties));
        }
    }
}
