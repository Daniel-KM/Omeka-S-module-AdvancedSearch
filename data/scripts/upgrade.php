<?php declare(strict_types=1);

namespace AdvancedSearch;

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
