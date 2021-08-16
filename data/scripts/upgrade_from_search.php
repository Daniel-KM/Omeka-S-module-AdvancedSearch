<?php declare(strict_types=1);

namespace AdvancedSearch;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Module\Manager $moduleManager
 * @var \Omeka\Settings\Settings $settings
 */
$connection = $services->get('Omeka\Connection');
$settings = $services->get('Omeka\Settings');
$moduleManager = $services->get('Omeka\ModuleManager');
$searchModule = $moduleManager->getModule('Search');

if (!$searchModule) {
    return;
}

$messenger = new Messenger();

$oldVersion = $searchModule->getIni('version');
if (version_compare($oldVersion, '3.5.7', '<')) {
    $message = new Message(
        'Compatibility of this module with module "Search" of BibLibre has not been checked. Uninstall it first, or upgrade it with its fork at https://gitlab.com/Daniel-KM/Omeka-S-module-Search.' // @translate
    );
    $messenger->addWarning($message);
    return;
}
if (version_compare($oldVersion, '3.5.23.3', '<')) {
    $message = new Message(
        'The version of the module Search should be at least %s to be upgraded.', // @translate
        '3.5.23.3'
    );
    $messenger->addWarning($message);
    return;
}

// Check if module was really installed.
try {
    $connection->fetchAll('SELECT `id` FROM `search_index` LIMIT 1;');
} catch (\Exception $e) {
    return;
}

// Copy content from tables.

// Copy is used instead of rename. The tables are created during install.
$sql = <<<SQL
INSERT `search_engine` (`id`, `name`, `adapter`, `settings`, `created`, `modified`)
SELECT `id`, `name`, `adapter`, `settings`, `created`, `modified`
FROM `search_index`
ON DUPLICATE KEY UPDATE
    `search_engine`.`id` = `search_index`.`id`,
    `search_engine`.`name` = `search_index`.`name`  ,
    `search_engine`.`adapter` = `search_index`.`adapter`,
    `search_engine`.`settings` = `search_index`.`settings` ,
    `search_engine`.`created` = `search_index`.`created`,
    `search_engine`.`modified` = `search_index`.`modified`
;

INSERT `search_config` (`id`, `engine_id`, `name`, `path`, `form_adapter`, `settings`, `created`, `modified`)
SELECT `id`, `index_id`, `name`, `path`, `form_adapter`, `settings`, `created`, `modified`
FROM `search_page`
ON DUPLICATE KEY UPDATE
    `search_config`.`id` = `search_page`.`id`,
    `search_config`.`engine_id` = `search_page`.`index_id`,
    `search_config`.`name` = `search_page`.`name`,
    `search_config`.`path` = `search_page`.`path`,
    `search_config`.`form_adapter` = `search_page`.`form_adapter`,
    `search_config`.`settings` = `search_page`.`settings`,
    `search_config`.`created` = `search_page`.`created`,
    `search_config`.`modified` = `search_page`.`modified`
;
SQL;

$sqls = array_filter(array_map('trim', explode(";\n", $sql)));
foreach ($sqls as $sql) {
    try {
        $connection->executeUpdate($sql);
    } catch (\Exception $e) {
        $messenger->addError($e->getMessage());
    }
}

// Convert the settings.

$sql = <<<'SQL'
REPLACE INTO `setting` (`id`, `value`)
SELECT
    REPLACE(`setting`.`id`, "search_", "advancedsearch_"),
    `setting`.`value`
FROM `setting`
WHERE
    `setting`.`id` IN (
        "search_main_page",
        "search_pages",
        "search_api_page",
        "search_batch_size"
    );
SQL;
$connection->executeUpdate($sql);

// Convert the site settings.

$sql = <<<'SQL'
REPLACE INTO `site_setting` (`id`, `site_id`, `value`)
SELECT
    REPLACE(`site_setting`.`id`, "search_", "advancedsearch_"),
    `site_setting`.`site_id`,
    `site_setting`.`value`
FROM `site_setting`
WHERE
    `site_setting`.`id` IN (
        "search_main_page",
        "search_pages",
        "search_api_page"
    );
SQL;
$connection->executeUpdate($sql);

// Do some renaming for settings.

$sql = <<<'SQL'
REPLACE INTO `setting` (`id`, `value`)
SELECT
    REPLACE(
        REPLACE(
            REPLACE(`setting`.`id`,
                "advancedsearch_main_page",
                "advancedsearch_main_config"
            ),
            "advancedsearch_pages",
            "advancedsearch_configs"
        ),
        "advancedsearch_api_page",
        "advancedsearch_api_config"
    ),
    `setting`.`value`
FROM `setting`
WHERE
    `setting`.`id` IN (
        "advancedsearch_main_page",
        "advancedsearch_pages",
        "advancedsearch_api_page"
    );
SQL;
$connection->executeUpdate($sql);

// Do some renaming for site settings.

$sql = <<<'SQL'
REPLACE INTO `site_setting` (`id`, `site_id`, `value`)
SELECT
    REPLACE(
        REPLACE(
            REPLACE(`site_setting`.`id`,
                "advancedsearch_main_page",
                "advancedsearch_main_config"
            ),
            "advancedsearch_pages",
            "advancedsearch_configs"
        ),
        "advancedsearch_api_page",
        "advancedsearch_api_config"
    ),
    `site_setting`.`site_id`,
    `site_setting`.`value`
FROM `site_setting`
WHERE
    `site_setting`.`id` IN (
        "advancedsearch_main_page",
        "advancedsearch_pages",
        "advancedsearch_api_page"
    );
SQL;
$connection->executeUpdate($sql);

// Remove original data and module.

$sql = <<<'SQL'
# Uninstall data of the module Search.

DELETE FROM `setting`
WHERE
    `setting`.`id` IN (
        "advancedsearch_main_page",
        "advancedsearch_pages",
        "advancedsearch_api_page",
        "search_main_page",
        "search_pages",
        "search_api_page",
        "search_batch_size"
    );

DELETE FROM `site_setting`
WHERE
    `site_setting`.`id` IN (
        "advancedsearch_main_page",
        "advancedsearch_pages",
        "advancedsearch_api_page",
        "search_main_page",
        "search_pages",
        "search_api_page"
    );

DROP TABLE IF EXISTS `search_page`;
DROP TABLE IF EXISTS `search_index`;

DELETE FROM `module` WHERE `id` = "Search";
SQL;

$sqls = array_filter(array_map('trim', explode(";\n", $sql)));
foreach ($sqls as $sql) {
    try {
        $connection->executeUpdate($sql);
    } catch (\Exception $e) {
        $messenger->addError($e->getMessage());
    }
}

// Upgrade search page config settings.

$inputs = [
    'checkbox' => 'Checkbox',
    'multi_checkbox' => 'MultiCheckbox',
    'multi-checkbox' => 'MultiCheckbox',
    'multicheckbox' => 'MultiCheckbox',
    'radio' => 'Radio',
    'select' => 'Select',
    'select_flat' => 'SelectFlat',
    'selectflat' => 'SelectFlat',
];

// Add new default options to settings of search pages.
$sql = <<<'SQL'
SELECT `id`, `settings` FROM `search_config`;
SQL;
$stmt = $connection->executeQuery($sql);
$result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
foreach ($result as $id => $searchConfigSettings) {
    $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
    $searchConfigSettings['resource_fields'] = [
        'item_set_id_field' => $searchConfigSettings['form']['item_set_id_field'] ?? 'item_set_id_field',
        'resource_class_id_field' => $searchConfigSettings['form']['resource_class_id_field'] ?? 'resource_class_id_field',
        'resource_template_id_field' => $searchConfigSettings['form']['resource_template_id_field'] ?? 'resource_template_id_field',
        'is_public_field' => $searchConfigSettings['form']['is_public_field'] ?? 'is_public_field',
    ];

    $form = $searchConfigSettings['form'] ?? [];
    $filters = $form['filters'] ?? [];
    $searchConfigSettings['form']['filters'] = [];

    $defaultOrder = ['q', 'itemSet', 'resourceClass', 'resourceTemplate', 'filters', 'submit'];
    $fieldsOrder = array_unique(array_merge($form['fields_order'] ?? [], $defaultOrder));
    foreach ($fieldsOrder as $order) switch ($order) {
        default:
        case 'q':
        case 'submit':
            continue 2;
        case 'itemSet':
            if (!empty($inputs[strtolower($form['item_set_filter_type'] ?? '')])) {
                $searchConfigSettings['form']['filters'][] = [
                    'field' => $form['items_set_id_field'] ?? 'items_set_id_field',
                    'label' => 'Collection',
                    'type' => $inputs[strtolower($form['item_set_filter_type'])] ?? 'Select',
                ];
            }
            break;
        case 'resourceClass':
            if (!empty($inputs[strtolower($form['resource_class_filter_type'] ?? '')])) {
                $searchConfigSettings['form']['filters'][] = [
                    'field' => $form['resource_class_id_field'] ?? 'resource_class_id_field',
                    'label' => 'Class',
                    'type' => $inputs[strtolower($form['resource_class_filter_type'])] ?? 'SelectFlat',
                ];
            }
            break;
        case 'resourceTemplate':
            if (!empty($inputs[strtolower($form['resource_template_filter_type'] ?? '')])) {
                $searchConfigSettings['form']['filters'][] = [
                    'field' => $form['resource_template_id_field'] ?? 'resource_template_id_field',
                    'label' => 'Template',
                    'type' => $inputs[strtolower($form['resource_template_filter_type'] ?? '')] ?? 'Radio',
                ];
            }
            break;
        case 'filters':
            if ($filters) {
                foreach ($filters as $keyFilter => &$filter) {
                    $filter['value'] = $keyFilter;
                    unset($filter['name']);
                }
                unset($filter);
                $searchConfigSettings['form']['filters'][] = [
                    'field' => 'advanced',
                    'label' => 'Filters',
                    'type' => 'Advanced',
                    'fields' => $filters,
                    'max_number' => $form['filters_max_number'] ?? '5',
                    'field_joiner' => $form['filter_value_joiner'] ?? true,
                    'field_operator' => $form['filter_value_type'] ?? true,
                ];
            }
            break;
    }

    unset(
        $searchConfigSettings['form']['item_set_id_field'],
        $searchConfigSettings['form']['resource_class_id_field'],
        $searchConfigSettings['form']['resource_template_id_field'],
        $searchConfigSettings['form']['is_public_field'],
        $searchConfigSettings['form']['item_set_filter_type'],
        $searchConfigSettings['form']['resource_class_filter_type'],
        $searchConfigSettings['form']['resource_template_filter_type'],
        $searchConfigSettings['form']['filters_max_number'],
        $searchConfigSettings['form']['filter_value_joiner'],
        $searchConfigSettings['form']['filter_value_type'],
        $searchConfigSettings['form']['fields_order']
    );

    $searchConfigSettings = $connection->quote(json_encode($searchConfigSettings, 320));
    $sql = <<<SQL
UPDATE `search_config`
SET `settings` = $searchConfigSettings
WHERE `id` = $id;
SQL;
    $connection->executeUpdate($sql);

    // All old adapters are now managed by the main one, unless the api.
    // They should be updated manually.
    $sql = <<<SQL
UPDATE `search_config`
SET `form_adapter` = "main"
WHERE `form_adapter` NOT IN ("api");
SQL;
    $connection->executeUpdate($sql);
}

$message = new Message(
    'The module "%s" was upgraded by module "%s" and uninstalled.', // @translate
    'Search', 'Advanced Search'
);
$messenger->addWarning($message);
