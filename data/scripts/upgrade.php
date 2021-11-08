<?php declare(strict_types=1);

namespace AdvancedSearch;

use Omeka\Stdlib\Message;
use Omeka\Mvc\Controller\Plugin\Messenger;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $oldVersion
 * @var string $newVersion
 *
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Api\Manager $api
 * @var array $config
 * @var \Omeka\Settings\Settings $settings
 */
// $entityManager = $services->get('Omeka\EntityManager');
$connection = $services->get('Omeka\Connection');
// $api = $services->get('Omeka\ApiManager');
// $config = require dirname(__DIR__, 2) . '/config/module.config.php';
$settings = $services->get('Omeka\Settings');

if (version_compare($oldVersion, '3.3.6.2', '<')) {
    $this->checkDependency();

    $sqls = <<<'SQL'
CREATE TABLE `search_suggester` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `engine_id` INT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    INDEX IDX_F64D915AE78C9C0A (`engine_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE `search_suggestion` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `suggester_id` INT NOT NULL,
    `text` VARCHAR(190) NOT NULL,
    `total_all` INT NOT NULL,
    `total_public` INT NOT NULL,
    INDEX IDX_536C3D170913F08 (`suggester_id`),
    INDEX search_text_idx (`text`, `suggester_id`),
    FULLTEXT INDEX IDX_536C3D13B8BA7C7 (`text`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

ALTER TABLE `search_suggester` ADD CONSTRAINT FK_F64D915AE78C9C0A FOREIGN KEY (`engine_id`) REFERENCES `search_engine` (`id`) ON DELETE CASCADE;

ALTER TABLE `search_suggestion` ADD CONSTRAINT FK_536C3D170913F08 FOREIGN KEY (`suggester_id`) REFERENCES `search_suggester` (`id`) ON DELETE CASCADE;
SQL;
    foreach (array_filter(explode(";\n", $sqls)) as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.3.6.3', '<')) {
    // During upgrade, no search can be done via api or entity manager, so use
    // connection.
    // $searchConfigPaths = $api->search('search_configs', [], ['returnScalar' => 'path'])->getContent();
    $sql = <<<'SQL'
SELECT `id`, `path` FROM `search_config` ORDER BY `id`;
SQL;
    $searchConfigPaths = $connection->fetchAll($sql);
    $searchConfigPaths = array_column($searchConfigPaths, 'path', 'id');
    $settings->set('advancedsearch_all_configs', $searchConfigPaths);
}

if (version_compare($oldVersion, '3.3.6.7', '<')) {
    $sql = <<<SQL
UPDATE `search_config`
SET
    `settings` =
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
            `settings`,
        '"resource_name"',
        '"resource_type"'
        ),
        '"resource_field"',
        '"id"'
        ),
        '"is_public_field"',
        '"is_public"'
        ),
        '"site_id_field"',
        '"site_id"'
        ),
        '"owner_id_field"',
        '"owner_id"'
        ),
        '"resource_class_id_field"',
        '"resource_class_id"'
        ),
        '"resource_template_id_field"',
        '"resource_template_id"'
        ),
        '"items_set_id_field"',
        '"item_set_id"'
        ),
        '"item_set_id_field"',
        '"item_set_id"'
        ),
        '"items_set_id"',
        '"item_set_id"'
        )
    ;
SQL;
    $connection->executeStatement($sql);

    // Add the core type when needed.
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('search_config.id', 'search_config.settings')
        ->from('search_config', 'search_config')
        ->innerJoin('search_config', 'search_engine', 'search_engine', 'search_engine.id = search_config.engine_id')
        ->where('search_engine.adapter = "internal"')
        ->orderBy('search_config.id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchConfigSettings = json_decode($searchConfigSettings,  true) ?: [];
        foreach ($searchConfigSettings['form']['filters'] ?? [] as $key => $filter) {
            if (in_array($filter['field'], [
                'site_id',
                'owner_id',
                'resource_class_id',
                'resource_template_id',
                'item_set_id',
            ])) {
                $searchConfigSettings['form']['filters'][$key]['type'] = trim('Omeka/' . $filter['type'], '/');
            }
        }
        $sql = <<<'SQL'
UPDATE `search_config`
SET
    `settings` = ?
WHERE
    `id` = ?
;
SQL;
        $connection->executeStatement($sql, [
            json_encode($searchConfigSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    // Add the default search partial process for internal engine.
    // Add the default multi-fields to internal engine.
    $searchEngineConfig = require __DIR__ . '/../../data/search_engines/internal.php';
    $defaultAdapterSettings = $searchEngineConfig['o:settings']['adapter']
        ?? ['default_search_partial_word' => false, 'multifields' => []];
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_engine', 'search_engine')
        ->where('adapter = "internal"')
        ->orderBy('id', 'asc');
    $searchEnginesSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchEnginesSettings as $id => $searchEngineSettings) {
        $searchEngineSettings = json_decode($searchEngineSettings,  true) ?: [];
        $searchEngineSettings['adapter'] = array_replace(
            $defaultAdapterSettings,
            $searchEngineSettings['adapter'] ?? []
        );

        $sql = <<<'SQL'
UPDATE `search_engine`
SET
    `settings` = ?
WHERE
    `id` = ?
;
SQL;
        $connection->executeStatement($sql, [
            json_encode($searchEngineSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $id,
        ]);
    }

    $messenger = new Messenger();

    /** @var \Omeka\Module\Manager $moduleManager */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('Reference');
    $version = $module ? $module->getIni('version') : null;
    if ($version && version_compare($version, '3.4.32.3', '<')) {
        $message = new Message(
            'The module %s should be updated to version %s.', // @translate
            'Reference', '3.4.32.3'
        );
        $messenger->addWarning($message);
    }

    $message = new Message(
        'It is now possible to aggregate properties with the internal (sql) adapter. See config of the internal search engine.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'It is now possible to add a pagination per-page to the search page.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'It is now possible to use "not" in advanced filters.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'It is now possible to display the used search filters in the results header.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'Some field types have been renamed for filters in the form. To use the core input elements, the type should be "Omeka" (or prepend "Omeka/" if needed). See the default config page.' // @translate
    );
    $messenger->addWarning($message);
}
