<?php declare(strict_types=1);

/**
 * Compare the internal (sql) and Solr queriers on standard Omeka api queries.
 *
 * Development harness for the query pivot: the same standard api query is run
 * through the Omeka api (reference) and through the Solr querier fed via the
 * hidden filters (the native resolution path), then the returned id sets are
 * compared. Discrepancies list the args and filter types Solr does not handle
 * natively yet.
 *
 * Usage (from the Omeka root or with --base):
 *   php modules/AdvancedSearch/data/scripts/compare-queriers.php
 *     [--base=/var/www/html] [--solr-engine=ID] [--only=label] [--json]
 *
 * The script is read-only. It runs anonymously, so both sides are limited to
 * public resources, which keeps the comparison consistent.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['base::', 'solr-engine::', 'only::', 'json', 'preload::']);
$base = $options['base'] ?? getcwd();
if (!file_exists($base . '/application/config/application.config.php')) {
    // Fallback: script placed inside modules/AdvancedSearch/data/scripts.
    $try = dirname(__DIR__, 4);
    if (file_exists($try . '/application/config/application.config.php')) {
        $base = $try;
    } else {
        fwrite(STDERR, "Omeka root not found. Use --base=/path/to/omeka\n");
        exit(1);
    }
}

// Preload patched class files (comma-separated paths) before the autoloader, so
chdir($base);
require $base . '/bootstrap.php';
$config = require $base . '/application/config/application.config.php';
$application = \Omeka\Mvc\Application::init($config);
$services = $application->getServiceManager();

// Preload patched class files after the module autoloaders are registered
// (their parents must be resolvable) but before the classes are used, so a
// locally modified class can be tested against a deployed instance without
// touching its modules.
foreach (array_filter(explode(',', $options['preload'] ?? '')) as $preload) {
    require_once trim($preload);
}

/** @var \Omeka\Api\Manager $api */
$api = $services->get('Omeka\ApiManager');
/** @var \Doctrine\DBAL\Connection $connection */
$connection = $services->get('Omeka\Connection');
/** @var \Common\Stdlib\EasyMeta $easyMeta */
$easyMeta = $services->get('Common\EasyMeta');

// 1. Find the Solr engine.
$solrEngineId = isset($options['solr-engine']) ? (int) $options['solr-engine'] : 0;
if (!$solrEngineId) {
    $solrEngineId = (int) $connection->fetchOne(
        "SELECT `id` FROM `search_engine` WHERE `adapter` = 'solarium' ORDER BY `id` ASC"
    );
}
if (!$solrEngineId) {
    fwrite(STDERR, "No solarium search engine found.\n");
    exit(1);
}
/** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation $solrEngine */
$solrEngine = $api->read('search_engines', $solrEngineId)->getContent();

// 2. Discover fixtures from real public data.
$fixture = [];
$fixture['item_set_id'] = (int) $connection->fetchOne(
    'SELECT iis.item_set_id FROM item_item_set iis
    INNER JOIN resource r ON r.id = iis.item_set_id AND r.is_public = 1
    INNER JOIN resource ri ON ri.id = iis.item_id AND ri.is_public = 1
    GROUP BY iis.item_set_id ORDER BY COUNT(*) DESC LIMIT 1'
);
$fixture['resource_class_id'] = (int) $connection->fetchOne(
    "SELECT r.resource_class_id FROM resource r
    WHERE r.resource_type = 'Omeka\\\\Entity\\\\Item' AND r.is_public = 1
        AND r.resource_class_id IS NOT NULL
    GROUP BY r.resource_class_id ORDER BY COUNT(*) DESC LIMIT 1"
);
$fixture['resource_class_term'] = $fixture['resource_class_id']
    ? $easyMeta->resourceClassTerm($fixture['resource_class_id'])
    : null;
$fixture['resource_template_id'] = (int) $connection->fetchOne(
    "SELECT r.resource_template_id FROM resource r
    WHERE r.resource_type = 'Omeka\\\\Entity\\\\Item' AND r.is_public = 1
        AND r.resource_template_id IS NOT NULL
    GROUP BY r.resource_template_id ORDER BY COUNT(*) DESC LIMIT 1"
);
$fixture['owner_id'] = (int) $connection->fetchOne(
    "SELECT r.owner_id FROM resource r
    WHERE r.resource_type = 'Omeka\\\\Entity\\\\Item' AND r.is_public = 1
        AND r.owner_id IS NOT NULL
    GROUP BY r.owner_id ORDER BY COUNT(*) DESC LIMIT 1"
);
// Pick a property that really holds public literal values on this base (a fixed
// term like dcterms:subject may be unused and would make the property tests
// vacuous: an empty value drops the filter row on both sides).
$propertyRow = $connection->fetchAssociative(
    'SELECT v.property_id, v.value, COUNT(*) AS total
    FROM value v
    INNER JOIN resource r ON r.id = v.resource_id AND r.is_public = 1
    WHERE v.value IS NOT NULL AND v.value != "" AND v.is_public = 1
        AND r.resource_type = "Omeka\\\\Entity\\\\Item"
        AND CHAR_LENGTH(v.value) BETWEEN 4 AND 80
    GROUP BY v.property_id, v.value
    ORDER BY total DESC LIMIT 1'
) ?: [];
$fixture['property_term'] = $propertyRow
    ? (string) $easyMeta->propertyTerm((int) $propertyRow['property_id'])
    : 'dcterms:subject';
$fixture['subject'] = (string) ($propertyRow['value'] ?? '');
fwrite(STDERR, sprintf(
    "fixtures: property=%s value=%s | item_set=%d class=%d template=%d owner=%d\n",
    $fixture['property_term'],
    mb_substr($fixture['subject'], 0, 40),
    $fixture['item_set_id'],
    $fixture['resource_class_id'],
    $fixture['resource_template_id'],
    $fixture['owner_id']
));
$fixture['ids'] = array_map('intval', $connection->fetchFirstColumn(
    "SELECT id FROM resource
    WHERE resource_type = 'Omeka\\\\Entity\\\\Item' AND is_public = 1
    ORDER BY id ASC LIMIT 5"
));
$titleSample = (string) $connection->fetchOne(
    "SELECT r.title FROM resource r
    WHERE r.resource_type = 'Omeka\\\\Entity\\\\Item' AND r.is_public = 1
        AND r.title IS NOT NULL AND r.title != ''
    LIMIT 1"
);
$fixture['word'] = strtok(trim($titleSample), ' ') ?: 'a';
$fixture['media_type'] = (string) $connection->fetchOne(
    'SELECT m.media_type FROM media m
    INNER JOIN resource r ON r.id = m.id AND r.is_public = 1
    WHERE m.media_type IS NOT NULL AND m.media_type != ""
    GROUP BY m.media_type ORDER BY COUNT(*) DESC LIMIT 1'
);

// A property holding a public linked resource, for res/nres.
$linkedRow = $connection->fetchAssociative(
    'SELECT v.property_id, v.value_resource_id, COUNT(*) AS total
    FROM value v
    INNER JOIN resource r ON r.id = v.resource_id AND r.is_public = 1
    INNER JOIN resource lr ON lr.id = v.value_resource_id AND lr.is_public = 1
    WHERE v.is_public = 1 AND r.resource_type = "Omeka\\\\Entity\\\\Item"
    GROUP BY v.property_id, v.value_resource_id
    ORDER BY total DESC LIMIT 1'
) ?: [];
$fixture['linked_term'] = $linkedRow
    ? (string) $easyMeta->propertyTerm((int) $linkedRow['property_id'])
    : '';
$fixture['linked_id'] = (int) ($linkedRow['value_resource_id'] ?? 0);

// A property holding year-starting values, for the year types.
$dateRow = $connection->fetchAssociative(
    'SELECT v.property_id, v.value, COUNT(*) AS total
    FROM value v
    INNER JOIN resource r ON r.id = v.resource_id AND r.is_public = 1
    WHERE v.is_public = 1 AND r.resource_type = "Omeka\\\\Entity\\\\Item"
        AND v.value REGEXP "^[12][0-9]{3}"
    GROUP BY v.property_id, v.value
    ORDER BY total DESC LIMIT 1'
) ?: [];
$fixture['date_term'] = $dateRow
    ? (string) $easyMeta->propertyTerm((int) $dateRow['property_id'])
    : '';
$fixture['year'] = $dateRow ? (int) mb_substr((string) $dateRow['value'], 0, 4) : 0;

// A second populated property, for the multi-field row (aggregated alias).
$secondRow = $connection->fetchAssociative(
    'SELECT v.property_id, COUNT(*) AS total
    FROM value v
    INNER JOIN resource r ON r.id = v.resource_id AND r.is_public = 1
    INNER JOIN property pr ON pr.id = v.property_id
    INNER JOIN vocabulary vo ON vo.id = pr.vocabulary_id
    WHERE v.value IS NOT NULL AND v.value != "" AND v.is_public = 1
        AND r.resource_type = "Omeka\\\\Entity\\\\Item"
        AND v.property_id != ?
        AND CONCAT(vo.prefix, ":", pr.local_name)
            IN (SELECT source FROM solr_map)
    GROUP BY v.property_id
    ORDER BY total DESC LIMIT 1',
    [(int) ($propertyRow['property_id'] ?? 0)]
) ?: [];
$fixture['second_term'] = $secondRow
    ? (string) $easyMeta->propertyTerm((int) $secondRow['property_id'])
    : '';
fwrite(STDERR, sprintf(
    "fixtures2: second=%s linked=%s#%d date=%s#%d\n",
    $fixture['second_term'] ?? '',
    $fixture['linked_term'] ?? '',
    $fixture['linked_id'] ?? 0,
    $fixture['date_term'] ?? '',
    $fixture['year'] ?? 0
));

// 3. Golden queries: single source shared with the PHPUnit parity tests.
$fixture['title_sample'] = $titleSample;
$fixture['numeric_ts_pid'] = (int) $connection->fetchOne(
    'SELECT property_id FROM value WHERE type = "numeric:timestamp" LIMIT 1'
) ?: null;
$fixture['numeric_int_pid'] = (int) $connection->fetchOne(
    'SELECT property_id FROM value WHERE type = "numeric:integer" LIMIT 1'
) ?: null;

require_once __DIR__ . '/../../tests/AdvancedSearchTest/Querier/QuerierParityQueries.php';
$golden = \AdvancedSearchTest\Querier\QuerierParityQueries::all($fixture);

// Flatten to the runner shape (label => api query with __ordered marker), so
// the runner below is unchanged.
$queries = [];
foreach ($golden as $label => $entry) {
    $query = $entry['query'];
    if ($entry['ordered']) {
        $query['__ordered'] = $entry['ordered'];
    }
    $queries[$label] = $query;
}


if (!empty($options['only'])) {
    $queries = array_intersect_key($queries, array_flip(array_map('trim', explode(',', $options['only']))));
}

// 4. Runner helpers.

$runInternal = function (array $query, ?int $limit) use ($api): array {
    if ($limit) {
        $query['per_page'] = $limit;
        $query['page'] = 1;
    }
    return array_map('intval', array_values(
        $api->search('items', $query, ['returnScalar' => 'id'])->getContent()
    ));
};

$runSolr = function (array $query, ?int $limit) use ($solrEngine): array {
    // The production path of the api redirection: the standard api query is
    // the pivot, normalized generically (see ApiSearch).
    $searchQuery = \AdvancedSearch\Query::fromApiQuery($query);
    $searchQuery->setResourceTypes(['items']);
    $querier = $solrEngine->querier()->setQuery($searchQuery);
    if ($limit) {
        $searchQuery->setLimitPage(1, $limit);
        $response = $querier->query();
        $results = $response->getResults('items') ?: $response->getResults('resources');
        return array_map('intval', array_column($results ?: [], 'id'));
    }
    $ids = $querier->queryAllResourceIds('items');
    sort($ids);
    return array_map('intval', $ids);
};

// 5. Run and compare.
$report = [];
foreach ($queries as $label => $query) {
    $ordered = $query['__ordered'] ?? null;
    $xfail = $query['__xfail'] ?? null;
    unset($query['__ordered'], $query['__xfail']);
    $row = ['label' => $label, 'query' => $query];
    try {
        $refIds = $runInternal($query, $ordered);
        if (!$ordered) {
            sort($refIds);
        }
        $row['internal'] = count($refIds);
    } catch (\Throwable $e) {
        $row['status'] = 'ERROR internal: ' . $e->getMessage();
        $report[] = $row;
        continue;
    }
    try {
        $solrIds = $runSolr($query, $ordered);
        $row['solr'] = count($solrIds);
    } catch (\Throwable $e) {
        $row['status'] = 'ERROR solr: ' . $e->getMessage();
        $report[] = $row;
        continue;
    }
    if ($ordered) {
        $row['status'] = $refIds === $solrIds ? 'OK' : 'DIFF-ORDER';
        if ($row['status'] !== 'OK') {
            $row['internal_ids'] = $refIds;
            $row['solr_ids'] = $solrIds;
        }
    } else {
        $missing = array_values(array_diff($refIds, $solrIds));
        $extra = array_values(array_diff($solrIds, $refIds));
        $row['status'] = (!$missing && !$extra) ? 'OK' : 'DIFF';
        if ($missing) {
            $row['missing_in_solr'] = array_slice($missing, 0, 8);
        }
        if ($extra) {
            $row['extra_in_solr'] = array_slice($extra, 0, 8);
        }
    }
    // A documented expected divergence: the diff is normal (XFAIL); a pass
    // means the expectation is stale (XPASS, to review).
    if ($xfail) {
        $row['status'] = $row['status'] === 'OK' ? 'XPASS' : 'XFAIL';
        $row['xfail'] = $xfail;
    }
    $report[] = $row;
}

// 6. Output.
if (isset($options['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit(0);
}

$ok = 0;
$expected = 0;
printf("%-26s %-10s %9s %9s  %s\n", 'query', 'status', 'internal', 'solr', 'diff');
foreach ($report as $row) {
    $status = $row['status'];
    $ok += (int) ($status === 'OK');
    $expected += (int) ($status === 'XFAIL');
    $diff = '';
    if (!empty($row['missing_in_solr'])) {
        $diff .= 'missing:' . implode(',', $row['missing_in_solr']) . ' ';
    }
    if (!empty($row['extra_in_solr'])) {
        $diff .= 'extra:' . implode(',', $row['extra_in_solr']);
    }
    if (!empty($row['xfail'])) {
        $diff = trim($diff . ' [' . $row['xfail'] . ']');
    }
    if (strncmp($status, 'ERROR', 5) === 0) {
        $diff = mb_substr($status, 0, 120);
        $status = 'ERROR';
    }
    printf(
        "%-26s %-10s %9s %9s  %s\n",
        $row['label'],
        $status,
        $row['internal'] ?? '-',
        $row['solr'] ?? '-',
        $diff
    );
}
printf(
    "\n%d OK + %d XFAIL / %d — engine #%d (%s)\n",
    $ok,
    $expected,
    count($report),
    $solrEngine->id(),
    $solrEngine->name()
);
