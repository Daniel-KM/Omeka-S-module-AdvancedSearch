<?php declare(strict_types=1);

namespace AdvancedSearch\Job;

use AdvancedSearch\Api\Representation\SearchSuggesterRepresentation;
use AdvancedSearch\Entity\SearchSuggestion;
use Doctrine\Common\Collections\Criteria;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

/**
 * @todo This is an internal indexer, not the generic suggestion indexer.
 */
class IndexSuggestions extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 1000;

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

        $modeIndex = $suggester->setting('mode_index') ?: 'start';

        $processMode = $this->getArg('process_mode') === 'sql' ? 'sql' : 'orm';

        $this->logger->notice(new Message('Suggester #%d ("%s"): start of indexing (index mode: %s, process mode: %s).', // @translate
            $suggester->id(), $suggester->name(), $modeIndex, $processMode));

        $this->process($suggester, $processMode);

        $timeTotal = (int) (microtime(true) - $timeStart);

        $totalResults = $this->entityManager->getRepository(SearchSuggestion::class)->count(['suggester' => $suggester->id()]);

        $this->logger->notice(new Message('Suggester #%d ("%s"): end of indexing. %s suggestions indexed. Execution time: %s seconds.', // @translate
            $suggester->id(), $suggester->name(), $totalResults, $timeTotal
        ));
    }

    protected function process(SearchSuggesterRepresentation $suggester, string $processMode): self
    {
        $dql = 'DELETE FROM AdvancedSearch\Entity\SearchSuggestion s WHERE s.suggester = ' . $suggester->id();
        $query = $this->entityManager->createQuery($dql);
        $totalDeleted = $query->execute();

        $this->logger->notice(new Message('Suggester #%d ("%s"): %d suggestions deleted.', // @translate
            $suggester->id(), $suggester->name(), $totalDeleted
        ));

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
        $fields = $this->getPropertyIds($fields);
        $excludedFields = $suggester->setting('excuded_fields') ?: [];
        $excludedFields = $this->getPropertyIds($excludedFields);

        $modeIndex = $suggester->setting('mode_index') ?: 'start';

        if ($processMode === 'sql') {
            $modeIndex === 'contain' || $modeIndex === 'contain_full'
                ? $this->processContain($suggester, $resourceNames, $fields, $excludedFields, $modeIndex)
                : $this->processStart($suggester, $resourceNames, $fields, $excludedFields, $modeIndex);
        } else {
            $this->processOrm($suggester, $resourceNames, $fields, $excludedFields, $modeIndex);
        }

        return $this;
    }

    protected function processStart(
        SearchSuggesterRepresentation $suggester,
        array $resourceTypes,
        array $fields,
        array $excludedFields,
        string $modeIndex
    ): self {
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

        if ($excludedFields) {
            $sqlFields .= ' AND `value`.`property_id` NOT IN (:excluded_property_ids)';
            $bind['excluded_property_ids'] = $excludedFields;
            $types['excluded_property_ids'] = $this->connection::PARAM_INT_ARRAY;
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

        if ($modeIndex === 'start' || $modeIndex === 'start_full') {
            foreach ($sqlsVisibility as $column => $sqlVisibility) {
                for ($numberWords = 3; $numberWords >= 1; $numberWords--) {
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
            TRIM("$" FROM
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
            )))))))))))))))))))))))))
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
        }

        if ($modeIndex === 'full' || $modeIndex === 'start_full') {
            $sql .= $this->appendSqlFull($sqlFields);
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

    protected function processContain(
        SearchSuggesterRepresentation $suggester,
        array $resourceTypes,
        array $fields,
        array $excludedFields,
        string $modeIndex
    ): self {
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

        if ($excludedFields) {
            $sqlFields .= ' AND `value`.`property_id` NOT IN (:excluded_property_ids)';
            $bind['excluded_property_ids'] = $excludedFields;
            $types['excluded_property_ids'] = $this->connection::PARAM_INT_ARRAY;
        }

        $sql = <<<'SQL'
# Process listing in a temporary table: the table has no auto-increment id and size is not limited.
DROP TABLE IF EXISTS `_suggestions_temporary`;
CREATE TEMPORARY TABLE `_suggestions_temporary` (
    `text` LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci,
    `total_all` INT NOT NULL DEFAULT 1,
    `total_public` INT NOT NULL DEFAULT 0,
    PRIMARY KEY(`text`(190))
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

SQL;

        $sqlsVisibility = [
            'all' => '',
            'public' => 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1',
        ];

        if ($modeIndex === 'contain' || $modeIndex === 'contain_full') {
            foreach ($sqlsVisibility as $column => $sqlVisibility) {
                // Only one word for now.
                // Don't "insert ignore and distinct", increment on duplicate.
                $sql .= <<<SQL
# Create single words index (compute $column).
# TODO Divide values by 1000 and use a loop.
SET @pr = CONCAT(
    "INSERT INTO `_suggestions_temporary` (`text`) VALUES ('",
    REPLACE(
        (SELECT
            GROUP_CONCAT( DISTINCT
                TRIM(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                    REPLACE(
                        `value`.`value`,
                    "\n", " "),
                    "\r", " "),
                    # Security replacements.
                    '"', " "),
                    "'", " "),
                    "\\\\", " "),
                    "%", " "),
                    "_", " "),
                    "#", " "),
                    "?", " "),
                    "$", " "),
                    # More common separators to avoid too long data.
                    ",", " "),
                    ";", " "),
                    "!", " "),
                    "’", " "),
                    "  ", " ")
                )
                SEPARATOR " "
            ) AS data
            FROM `value`
            JOIN `resource`
                ON `resource`.`id` = `value`.`resource_id`
                AND `resource`.`resource_type` IN (:resource_types)
            WHERE `value`.`value` IS NOT NULL
                $sqlFields
                $sqlVisibility
        ),
        " ",
        "'),('"),
        "')",
        "ON DUPLICATE KEY UPDATE `_suggestions_temporary`.`total_$column` = `_suggestions_temporary`.`total_$column` + 1;"
    );
PREPARE stmt1 FROM @pr;
EXECUTE stmt1;

SQL;
            }
        }

        if ($modeIndex === 'full' || $modeIndex === 'contain_full') {
            $sql .= $this->appendSqlFull($sqlFields);
        }

        $sql .= <<<SQL
# Finalize creation of suggestions.
INSERT INTO `search_suggestion` (`suggester_id`, `text`, `total_all`, `total_public`)
SELECT DISTINCT
    :suggester_id,
    SUBSTRING(
        TRIM(
            # Security replacements.
            TRIM('"' FROM
            TRIM("'" FROM
            TRIM("\\\\" FROM
            TRIM("%" FROM
            TRIM("_" FROM
            TRIM("#" FROM
            # Cleaning replacements.
            TRIM("," FROM
            TRIM(";" FROM
            TRIM("!" FROM
            TRIM("?" FROM
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
               `text`
            )))))))))))))))))))))))
        ),
    1, 190) AS "val",
    `total_all`,
    `total_public`
FROM `_suggestions_temporary`
WHERE
    LENGTH(`text`) > 1;

# Remove useless rows.
DELETE FROM `search_suggestion`
WHERE `suggester_id` = :suggester_id
    AND LENGTH(`text`) <= 1;

DROP TABLE IF EXISTS `_suggestions_temporary`;

SQL;

        $this->connection->executeQuery($sql, $bind, $types);

        return $this;
    }

    protected function appendSqlFull(string $sqlFields): string
    {
        $sql = '';
        $sqlsVisibility = [
            'all' => '',
            'public' => 'AND `resource`.`is_public` = 1 AND `value`.`is_public` = 1',
        ];
        foreach ($sqlsVisibility as $column => $sqlVisibility) {
            // Don't "insert ignore and distinct", increment on duplicate.
            $sql .= <<<SQL
# Create full value index (compute $column).
INSERT INTO `_suggestions_temporary` (`text`)
SELECT
    TRIM(SUBSTRING(REPLACE(REPLACE(`value`.`value`, "\n", " "), "\r", " "), 1, 190))
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
        return $sql;
    }

    protected function processOrm(
        SearchSuggesterRepresentation $suggester,
        array $resourceTypes,
        array $fields,
        array $excludedFields,
        string $modeIndex
    ): self {
        $criteria = new Criteria;
        $expr = $criteria->expr();

        // Expr is not null does not exist.
        $criteria
            ->where($expr->neq('value', null));

        /* // Cannot be added, because criteria does not match not-in memory.
        if ($resourceTypes) {
            // in() with string is messy.
            // https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/inheritance-mapping.html#query-the-type
            if (count($resourceTypes) === 1) {
                $criteria
                    ->andWhere($expr->memberOf('resource', $resourceTypes));
            } elseif (count($resourceTypes) === 2) {
                $resourceTypes = array_values($resourceTypes);
                $criteria
                    ->andWhere($expr->orX(
                        $expr->memberOf('resource', $resourceTypes[0]),
                        $expr->memberOf('resource', $resourceTypes[1])
                    ));
            }
        }
        */

        if ($fields) {
            $criteria
                ->andWhere($expr->in('property', $fields));
        }

        if ($excludedFields) {
            $criteria
                ->andWhere($expr->notIn('property', $excludedFields));
        }

        $criteria
            ->orderBy(['id' => 'ASC'])
            ->setFirstResult(null)
            ->setMaxResults(self::SQL_LIMIT);

        $valueRepository = $this->entityManager->getRepository(\Omeka\Entity\Value::class);
        $collection = $valueRepository->matching($criteria);

        $totalToProcess = $collection->count();
        $this->logger->notice(new Message(
            'Indexing suggestions for %d resource values.', // @translate
            $totalToProcess
        ));

        // The suggestions are empty.
        $suggesterId = $suggester->id();
        $suggester = $suggester->getEntity();
        $suggestionRepository = $this->entityManager->getRepository(\AdvancedSearch\Entity\SearchSuggestion::class);

        $replacements = [
            // Security replacements.
            "\n" => ' ',
            "\r" => ' ',
            "'" => ' ',
            '"' => ' ',
            '\\' => ' ',
            '%' => ' ',
            '_' => ' ',
            '#' => ' ',
            '?' => ' ',
            '$' => ' ',
            # Cleaning replacements.
            ',' => ' ',
            ';' => ' ',
            '!' => ' ',
            // Keep urls, ark/doi.
            // ':' => ' ',
            // '.' => ' ',
            '[' => ' ',
            ']' => ' ',
            '<' => ' ',
            '>' => ' ',
            '(' => ' ',
            ')' => ' ',
            '{' => ' ',
            '}' => ' ',
            '=' => ' ',
            '&' => ' ',
            '’' => ' ',
            '  ' => ' ',
        ];
        $replacementsFull = [
            "\n" => ' ',
            "\r" => ' ',
            '\\' => ' ',
        ];

        // Since the fixed medias are no more available in the database, the
        // loop should take care of them, so a check is done on it.
        // Some new values may have been added during process, so don't check
        // the total to process, but the last id.
        $lastId = 0;

        $baseCriteria = $criteria;
        $offset = 0;
        $totalProcessed = 0;
        while (true) {
            $criteria = clone $baseCriteria;
            $criteria
                ->andWhere($expr->gt('id', $lastId));
            $values = $valueRepository->matching($criteria);
            if (!$values->count() || $offset >= $totalToProcess || $totalProcessed >= $totalToProcess) {
                break;
            }

            if ($this->shouldStop()) {
                $this->logger->warn(new Message(
                    'Job stopped: %d/%d processed (index mode: %s).', // @translate
                    $totalProcessed, $totalToProcess, $modeIndex
                ));
                return $this;
            }

            if ($totalProcessed) {
                $this->logger->info(new Message(
                    '%d/%d values processed.', // @translate
                    $totalProcessed, $totalToProcess
                ));
            }

            // Get it each loop because of the entity manager clearing clearing.
            $suggester = $this->entityManager->getRepository(\AdvancedSearch\Entity\SearchSuggester::class)->find($suggesterId);

            $suggestionCriteria = new Criteria($expr->eq('suggester', $suggester));
            $suggestions = $suggestionRepository->matching($suggestionCriteria);

            /** @var \Omeka\Entity\Value $value */
            foreach ($values as $value) {
                $lastId = $value->getId();
                ++$totalProcessed;

                $resource = $value->getResource();
                if (!isset($resourceTypes[$resource->getResourceName()])) {
                    continue;
                }

                $stringValue = $value->getValue();

                // TODO Skip words without meaning like "the".
                $list = [];
                if ($modeIndex === 'start' || $modeIndex === 'start_full') {
                    $string = str_replace(array_keys($replacements), array_values($replacements), strip_tags($stringValue));
                    $prevPart = '';
                    foreach (array_filter(explode(' ', $string), 'strlen') as $part) {
                        $part = trim($part, ".: \t\n\r\0\x0B");
                        if (strlen($part) > 1) {
                            $fullPart = $prevPart . $part;
                            $list[] = $fullPart;
                            if (count($list) >= 3) {
                                break;
                            }
                            $prevPart = $fullPart . ' ';
                        }
                    }
                } elseif ($modeIndex === 'contain' || $modeIndex === 'contain_full') {
                    $string = str_replace(array_keys($replacements), array_values($replacements), strip_tags($stringValue));
                    foreach (array_filter(explode(' ', $string), 'strlen') as $part) {
                        $part = trim($part, ".: \t\n\r\0\x0B");
                        if (strlen($part) > 1) {
                            $list[] = $part;
                        }
                    }
                }

                if (strpos($modeIndex, 'full') !== false) {
                    $list[] = str_replace(array_keys($replacementsFull), array_values($replacementsFull), strip_tags($stringValue));
                }

                if (!count($list)) {
                    continue;
                }

                $list = array_unique($list);

                // TODO Check if a double loop (all then public only) is quicker: it will avoid a load of resource.
                $isPublic = $value->isPublic()
                    && $resource->isPublic();

                foreach ($list as $part) {
                    $part = mb_substr($part, 0, 190);
                    $suggestCriteria = clone $suggestionCriteria;
                    $suggestCriteria
                        ->andWhere($expr->eq('text', $part));

                    $existingSuggestions = $suggestions->matching($suggestCriteria);
                    if ($existingSuggestions->count()) {
                        $suggestion = $existingSuggestions->first();
                    } else {
                        $suggestion = new SearchSuggestion();
                        $suggestion
                            ->setSuggester($suggester)
                            ->setText($part);
                        $suggestions->add($suggestion);
                        $this->entityManager->persist($suggestion);
                    }
                    $suggestion->setTotalAll($suggestion->getTotalAll() + 1);
                    if ($isPublic) {
                        $suggestion->setTotalPublic($suggestion->getTotalPublic() + 1);
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            unset($values);

            $offset += self::SQL_LIMIT;
        }

        $this->logger->warn(new Message(
            'End of process: %d/%d processed (index mode: %s).', // @translate
            $totalProcessed, $totalToProcess, $modeIndex
        ));

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
