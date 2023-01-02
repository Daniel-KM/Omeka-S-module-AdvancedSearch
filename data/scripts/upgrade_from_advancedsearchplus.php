<?php declare(strict_types=1);

namespace AdvancedSearch;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Module\Manager $moduleManager
 * @var \Omeka\Settings\Settings $settings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$connection = $services->get('Omeka\Connection');
$settings = $services->get('Omeka\Settings');
$moduleManager = $services->get('Omeka\ModuleManager');
$aspModule = $moduleManager->getModule('AdvancedSearchPlus');

if (!$aspModule) {
    return;
}

$messenger = $services->get('ControllerPluginManager')->get('messenger');

// Convert the settings.

$sql = <<<'SQL'
REPLACE INTO `setting` (`id`, `value`)
SELECT
    REPLACE(`setting`.`id`, "advancedsearchplus_", "advancedsearch_"),
    `setting`.`value`
FROM `setting`
WHERE
    `setting`.`id` LIKE "advancedsearchplus#_%" ESCAPE "#";
SQL;
$connection->executeStatement($sql);

// Convert the site settings.

$sql = <<<'SQL'
REPLACE INTO `site_setting` (`id`, `site_id`, `value`)
SELECT
    REPLACE(`site_setting`.`id`, "advancedsearchplus_", "advancedsearch_"),
    `site_setting`.`site_id`,
    `site_setting`.`value`
FROM `site_setting`
WHERE
    `site_setting`.`id` LIKE "advancedsearchplus#_%" ESCAPE "#";
SQL;
$connection->executeStatement($sql);

// Remove original data and module.

$sql = <<<'SQL'
# Uninstall data of the module Advanced Search Plus.

DELETE FROM `setting`
WHERE
    `setting`.`id` LIKE "advancedsearchplus#_%" ESCAPE "#";

DELETE FROM `site_setting`
WHERE
    `site_setting`.`id` LIKE "advancedsearchplus#_%" ESCAPE "#";

DELETE FROM `module` WHERE `id` = "AdvancedSearchPlus";
SQL;

$sqls = array_filter(array_map('trim', explode(";\n", $sql)));
foreach ($sqls as $sql) {
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        $messenger->addError($e->getMessage());
    }
}

$message = new Message(
    'The module "%s" was upgraded by module "%s" and uninstalled.', // @translate
    'Advanced Search Plus', 'Advanced Search'
);
$messenger->addWarning($message);
