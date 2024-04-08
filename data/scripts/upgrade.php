<?php declare(strict_types=1);

namespace AdvancedSearch;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.55')) {
    $message = new Message(
        'The module %1$s should be upgraded to version %2$s or later.', // @translate
        'Common', '3.4.55'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.3.6.2', '<')) {
    $this->checkDependencies();

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
        $connection->executeStatement($sql);
    }
}

if (version_compare($oldVersion, '3.3.6.3', '<')) {
    // During upgrade, no search can be done via api or entity manager, so use
    // connection.
    // $searchConfigPaths = $api->search('search_configs', [], ['returnScalar' => 'path'])->getContent();
    $sql = <<<'SQL'
SELECT `id`, `path` FROM `search_config` ORDER BY `id` ASC;
SQL;
    $searchConfigPaths = $connection->fetchAllAssociative($sql);
    $searchConfigPaths = array_column($searchConfigPaths, 'path', 'id');
    $settings->set('advancedsearch_all_configs', $searchConfigPaths);
}

// End of support of direct upgrade of modules Search and derivative modules.

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
        '"owner_id_field"',
        '"owner_id"'
        ),
        '"site_id_field"',
        '"site_id"'
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
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
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
    if (file_exists(__DIR__ . '/../../data/search_engines/internal.php')) {
        $searchEngineConfig = require __DIR__ . '/../../data/search_engines/internal.php';
    } else {
        $searchEngineConfig = [];
    }
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
        $searchEngineSettings = json_decode($searchEngineSettings, true) ?: [];
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

if (version_compare($oldVersion, '3.3.6.9', '<')) {
    $message = new Message(
        'It is now possible to query ressources with linked ressources in the standard advanced form.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.6.12', '<')) {
    $message = new Message(
        'A new option was added to display the resources mixed (by default) or item sets and items separately (old behavior).' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'The template for the sort selector has been updated.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'A helper for the pagination per page has been added.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.3.6.15', '<')) {
    $message = new Message(
        'It’s now possible to search resources by multiples properties, and resources without class, template, item set, site or owner.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.3.6.16', '<')) {
    $message = new Message(
        'It’s now possible to use facet range with a double select (from/to). With internal sql engine, order is alphabetic only for now: it works for strings and simple four digits years or standard dates, not integer or variable dates. With Solr, only numbers and dates are supported. The theme may need to be updated.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new Message(
        'The property query types lower/greater have been updated to use alphabetic order, no more date-time only. For integer and date-time, use numeric data types.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'Update your theme to support new features for facets (active facets, button apply facets with id="apply-facets", list of facet values).' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.6.19', '<')) {
    $sql = <<<'SQL'
UPDATE `site_page_block`
SET
    `data` = REPLACE(data, '"search_page":', '"search_config":')
WHERE
    `data` LIKE '%"search_page":%'
;
SQL;
    $connection->executeStatement($sql);

    $message = new Message(
        'It’s now possible to search values similar other ones (via %1$sSoundex%2$s, designed for British English phonetic).', // @translate
        '<a href="https://en.wikipedia.org/wiki/Soundex">', '</a>'
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    $message = new Message(
        'Some new options were added to manage facets.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.10', '<')) {
    $sql = <<<'SQL'
UPDATE `search_config`
SET
    `settings` = REPLACE(`settings`, '"display_button"', '"display_submit"')
;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.11', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `search_config` CHANGE `created` `created` datetime NOT NULL DEFAULT NOW() AFTER `settings`;
ALTER TABLE `search_engine` CHANGE `created` `created` datetime NOT NULL DEFAULT NOW() AFTER `settings`;
ALTER TABLE `search_suggester` CHANGE `created` `created` datetime NOT NULL DEFAULT NOW() AFTER `settings`;
SQL;
    $connection->executeStatement($sql);

    /** @var \Omeka\Module\Manager $moduleManager */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('Reference');
    $hasReference = $module
        && version_compare($module->getIni('version'), '3.4.43', '<');
    if ($hasReference) {
        $message = new Message(
            'It is recommended to upgrade the module "Reference" to improve performance.' // @translate
        );
        $messenger->addWarning($message);
    }
}

if (version_compare($oldVersion, '3.4.12', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `search_config` CHANGE `created` `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `settings`;
ALTER TABLE `search_engine` CHANGE `created` `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `settings`;
ALTER TABLE `search_suggester` CHANGE `created` `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `settings`;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.14', '<')) {
    /** @see https://github.com/omeka/omeka-s/pull/2096 */
    try {
        $connection->executeStatement('ALTER TABLE `resource` ADD INDEX `idx_public_type_id_title` (`is_public`,`resource_type`,`id`,`title` (190));');
    } catch (\Exception $e) {
        // Index exists.
    }
    try {
        $connection->executeStatement('ALTER TABLE `value` ADD INDEX `idx_public_resource_property` (`is_public`,`resource_id`,`property_id`);');
    } catch (\Exception $e) {
        // Index exists.
    }

    /** @see https://github.com/omeka/omeka-s/pull/2105 */
    try {
        $connection->executeStatement('ALTER TABLE `resource` ADD INDEX `is_public` (`is_public`);');
    } catch (\Exception $e) {
        // Index exists.
    }
    try {
        $connection->executeStatement('ALTER TABLE `value` ADD INDEX `is_public` (`is_public`);');
    } catch (\Exception $e) {
        // Index exists.
    }
    try {
        $connection->executeStatement('ALTER TABLE `site_page` ADD INDEX `is_public` (`is_public`);');
    } catch (\Exception $e) {
        // Index exists.
    }

    $settings->set('advancedsearch_index_batch_edit', $settings->get('advancedsearch_disable_index_batch_edit') ? 'none' : 'sync');
    $settings->delete('advancedsearch_disable_index_batch_edit');

    $message = new Message(
        'A new settings allows to skip indexing after a batch process because an issue can occurs in some cases.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.15', '<')) {
    $sql = <<<'SQL'
DELETE FROM `site_setting`
WHERE `id` = "advancedsearch_restrict_used_terms";
SQL;
    $connection->executeStatement($sql);

    $message = new Message(
        'The performance was improved in many places, in particular for large databases.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'It is now possible to order results by a list of ids with argument "sort_by=ids".' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    $message = new Message(
        'It is now possible to do a standard search with a sub-query, for example to get all items with creators born in 1789.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.19', '<')) {
    // Repeated because of an issue in previous version.
    $settings->delete('advancedsearch_restrict_used_terms');
    $sql = <<<'SQL'
DELETE FROM `site_setting`
WHERE `id` = "advancedsearch_restrict_used_terms";
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.20', '<')) {
    $message = new Message(
        'When full text is managed in alto files, it is now possible to search full text or record only.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.21', '<')) {
    $message = new Message(
        'It is now possible to search resources with duplicated values to help curation.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new Message(
        'The speed of the derivative forms were improved and can be called directly from the search config with option "variant". Upgrade your theme if it was customized.' // @translate
    );
    $messenger->addSuccess($message);
}
