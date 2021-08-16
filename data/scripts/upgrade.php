<?php declare(strict_types=1);

namespace AdvancedSearch;

use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Api\Manager $api
 * @var array $config
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$api = $services->get('Omeka\ApiManager');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';

if (version_compare($oldVersion, '0.1.1', '<')) {
    $connection->exec('
        ALTER TABLE advancedsearch_config
        CHANGE `form` `form_adapter` varchar(255) NOT NULL
    ');
}

if (version_compare($oldVersion, '0.5.0', '<')) {
    // There is no "drop foreign key if exists", so check it.
    $sql = '';
    $sm = $connection->getSchemaManager();
    $keys = ['advancedsearch_config_ibfk_1', 'index_id', 'IDX_4F10A34984337261', 'FK_4F10A34984337261'];
    $foreignKeys = $sm->listTableForeignKeys('advancedsearch_config');
    foreach ($foreignKeys as $foreignKey) {
        if ($foreignKey && in_array($foreignKey->getName(), $keys)) {
            $sql .= 'ALTER TABLE advancedsearch_config DROP FOREIGN KEY ' . $foreignKey->getName() . ';' . PHP_EOL;
        }
    }
    $engines = $sm->listTableIndexes('advancedsearch_config');
    foreach ($engines as $engine) {
        if ($engine && in_array($engine->getName(), $keys)) {
            $sql .= 'DROP INDEX ' . $engine->getName() . ' ON advancedsearch_config;' . PHP_EOL;
        }
    }

    $sql .= <<<'SQL'
ALTER TABLE advancedsearch_index CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE settings settings LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
ALTER TABLE advancedsearch_config CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE index_id index_id INT NOT NULL AFTER id, CHANGE settings settings LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
CREATE INDEX IDX_4F10A34984337261 ON advancedsearch_config (index_id);
ALTER TABLE advancedsearch_config ADD CONSTRAINT advancedsearch_config_ibfk_1 FOREIGN KEY (index_id) REFERENCES advancedsearch_index (id);
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '0.5.1', '<')) {
    // There is no "drop foreign key if exists", so check it.
    $sql = '';
    $sm = $connection->getSchemaManager();
    $keys = ['advancedsearch_config_ibfk_1', 'index_id', 'IDX_4F10A34984337261', 'FK_4F10A34984337261'];
    $foreignKeys = $sm->listTableForeignKeys('advancedsearch_config');
    foreach ($foreignKeys as $foreignKey) {
        if ($foreignKey && in_array($foreignKey->getName(), $keys)) {
            $sql .= 'ALTER TABLE advancedsearch_config DROP FOREIGN KEY ' . $foreignKey->getName() . ';' . PHP_EOL;
        }
    }
    $engines = $sm->listTableIndexes('advancedsearch_config');
    foreach ($engines as $engine) {
        if ($engine && in_array($engine->getName(), $keys)) {
            $sql .= 'DROP INDEX ' . $engine->getName() . ' ON advancedsearch_config;' . PHP_EOL;
        }
    }

    $sql .= <<<'SQL'
CREATE INDEX IDX_4F10A34984337261 ON advancedsearch_config (index_id);
ALTER TABLE advancedsearch_config ADD CONSTRAINT FK_4F10A34984337261 FOREIGN KEY (index_id) REFERENCES advancedsearch_index (id) ON DELETE CASCADE;
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '0.5.2', '<')) {
    $this->manageConfig('install');
    $this->manageMainSettings('install');
    $this->manageSiteSettings('install');
}

if (version_compare($oldVersion, '3.5.7', '<')) {
    // Ideally, each theme of the site should be checked, but it is useless since only one public theme requried Search.
    /** @var \Omeka\Site\Theme\Manager $themeManager */
    // $themeManager = $services->get('Omeka\Site\ThemeManager');
    $siteSettings = $services->get('Omeka\Settings\Site');
    /** @var \Omeka\Api\Representation\SiteRepresentation[] $sites */
    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $theme = $site->theme();
        $siteSettings->setTargetId($site->id());
        $key = 'theme_settings_' . $theme;
        $themeSettings = $siteSettings->get($key, []);
        if (array_key_exists('advancedsearch_config_id', $themeSettings)) {
            $siteSettings->set('advancedsearch_main_page', $themeSettings['advancedsearch_config_id']);
            unset($themeSettings['advancedsearch_config_id']);
            $siteSettings->set($key, $themeSettings);
        }
    }
}

if (version_compare($oldVersion, '3.5.8', '<')) {
    $defaultConfig = $config[strtolower(__NAMESPACE__)]['config'];
    $settings->set(
        'advancedsearch_batch_size',
        $defaultConfig['advancedsearch_batch_size']
    );

    // Reorder the search pages by weight to avoid to do it each time.
    // The api is not available for search pages during upgrade, so use sql.
    $sql = <<<'SQL'
SELECT `id`, `settings` FROM `advancedsearch_config`;
SQL;
    $stmt = $connection->query($sql);
    $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    if ($result) {
        foreach ($result as $id => $searchConfigSettings) {
            $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
            if ($searchConfigSettings) {
                foreach (['facets', 'sort_fields'] as $type) {
                    if (!isset($searchConfigSettings[$type])) {
                        $searchConfigSettings[$type] = [];
                    } else {
                        // @see \AdvancedSearch\Controller\Admin\SearchConfigController::configureAction()
                        // Sort enabled first, then available, else sort by weigth.
                        uasort($searchConfigSettings[$type], function ($a, $b) {
                            // Sort by availability.
                            if (isset($a['enabled']) && isset($b['enabled'])) {
                                if ($a['enabled'] > $b['enabled']) {
                                    return -1;
                                } elseif ($a['enabled'] < $b['enabled']) {
                                    return 1;
                                }
                            } elseif (isset($a['enabled'])) {
                                return -1;
                            } elseif (isset($b['enabled'])) {
                                return 1;
                            }
                            // In other cases, sort by weight.
                            if (isset($a['weight']) && isset($b['weight'])) {
                                return $a['weight'] == $b['weight']
                                    ? 0
                                    : ($a['weight'] < $b['weight'] ? -1 : 1);
                            } elseif (isset($a['weight'])) {
                                return -1;
                            } elseif (isset($b['weight'])) {
                                return 1;
                            }
                            return 0;
                        });
                    }
                }
            }
            $searchConfigSettings = $connection->quote(json_encode($searchConfigSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $sql = <<<SQL
UPDATE `advancedsearch_config`
SET `settings` = $searchConfigSettings
WHERE `id` = $id;
SQL;
            $connection->exec($sql);
        }
    }
}

if (version_compare($oldVersion, '3.5.12', '<')) {
    $sql = <<<SQL
UPDATE `site_page_block`
SET `layout` = "searchingForm"
WHERE `layout` = "search";
SQL;
    $connection->exec($sql);

    $siteSettings = $services->get('Omeka\Settings\Site');
    /** @var \Omeka\Api\Representation\SiteRepresentation[] $sites */
    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $siteSettings->setTargetId($site->id());
        $searchConfigs = $siteSettings->get('search_configs', []) ?: [];
        $searchConfigs = array_unique(array_filter(array_map('intval', $searchConfigs)));
        sort($searchConfigs);
        $siteSettings->set('search_configs', $searchConfigs);
    }
}

if (version_compare($oldVersion, '3.5.12.2', '<')) {
    $mainSearchConfig = $settings->get('advancedsearch_main_page');
    if ($mainSearchConfig) {
        $mainSearchConfig = basename($mainSearchConfig);
        // The api for advancedsearch_configs is not available during upgrade.
        $sql = <<<SQL
SELECT `id`
FROM `advancedsearch_config`
WHERE `path` = :advancedsearch_config;
SQL;
        $id = $connection->fetchColumn($sql, ['advancedsearch_config' => $mainSearchConfig], 0);
        $settings->set('advancedsearch_main_page', $id ? (string) $id : null);
    }
}

if (version_compare($oldVersion, '3.5.14', '<')) {
    // Add new default options to settings of search pages.
    $sql = <<<'SQL'
SELECT `id`, `settings` FROM `advancedsearch_config`;
SQL;
    $stmt = $connection->query($sql);
    $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    if ($result) {
        foreach ($result as $id => $searchConfigSettings) {
            $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
            $searchConfigSettings += [
                'default_results' => 'default',
                'default_query' => '',
                'restrict_query_to_form' => '0',
            ];
            $searchConfigSettings['form']['item_set_filter_type'] = 'multi-checkbox';
            $searchConfigSettings['form']['resource_class_filter_type'] = 'select';
            $searchConfigSettings['form']['resource_template_filter_type'] = 'select';
            $searchConfigSettings['form']['filter_collection_number'] = '1';
            $searchConfigSettings = $connection->quote(json_encode($searchConfigSettings, 320));
            $sql = <<<SQL
UPDATE `advancedsearch_config`
SET `settings` = $searchConfigSettings
WHERE `id` = $id;
SQL;
            $connection->exec($sql);
        }
    }
}

if (version_compare($oldVersion, '3.5.16.3', '<')) {
    // @link https://www.doctrine-project.org/projects/doctrine-dbal/en/2.6/reference/types.html#array-types
    $sql = <<<'SQL'
ALTER TABLE `advancedsearch_index`
CHANGE `settings` `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
CHANGE `modified` `modified` DATETIME DEFAULT NULL;
SQL;
    $connection->exec($sql);
    $sql = <<<'SQL'
ALTER TABLE `advancedsearch_config`
CHANGE `settings` `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
CHANGE `modified` `modified` DATETIME DEFAULT NULL;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.5.21.3', '<')) {
    $sql = <<<'SQL'
SELECT `id`, `form_adapter`, `settings` FROM `advancedsearch_config`;
SQL;
    $stmt = $connection->query($sql);
    $results = $stmt->fetchAll();
    foreach ($results as $result) {
        $id = $result['id'];
        // $formAdapter = $result['form_adapter'];
        $searchConfigSettings = $result['settings'];
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        if (empty($searchConfigSettings['search'])) {
            $searchConfigSettings['search'] = [];
        }

        // For simplicity, keep old params as they are, and merge them with the
        // new one. It will be cleaned once the config form will be saved.

        // Move search main settings from root to [search].
        $searchConfigSettings['search']['default_results'] = $searchConfigSettings['default_results'] ?? 'default';
        $searchConfigSettings['search']['default_query'] = $searchConfigSettings['default_query'] ?? '';

        $searchConfigSettings['form']['filters_max_number'] = $searchConfigSettings['form']['filter_collection_number'] ?? 5;

        $searchConfigSettings['form']['filters'] = $searchConfigSettings['form']['filters'] ?? [];
        $searchConfigSettings['form']['fields_order'] = $searchConfigSettings['form']['fields_order'] ?? [];

        foreach ($searchConfigSettings['form']['filters'] as $name => &$field) {
            if (empty($field['enabled'])) {
                unset($searchConfigSettings['form']['filters'][$name]);
            } else {
                $field = $field['display']['label'];
            }
        }
        unset($field);

        $searchConfigSettings['sort'] = [
            'fields' => $searchConfigSettings['sort_fields'] ?? [],
        ];

        foreach ($searchConfigSettings['sort']['fields'] as $name => &$field) {
            if (empty($field['enabled'])) {
                unset($searchConfigSettings['sort']['fields'][$name]);
            } else {
                $field = $field['display']['label'];
            }
        }
        unset($field);

        $searchConfigSettings['facet'] = [
            'facets' => $searchConfigSettings['facets'] ?? [],
            'limit' => $searchConfigSettings['facet_limit'] ?? 10,
            'languages' => $searchConfigSettings['facet_languages'] ?? [],
            'mode' => $searchConfigSettings['facet_mode'] ?? 'button',
        ];

        foreach ($searchConfigSettings['facet']['facets'] as $name => &$field) {
            if (empty($field['enabled'])) {
                unset($searchConfigSettings['facet']['facets'][$name]);
            } else {
                $field = $field['display']['label'];
            }
        }
        unset($field);

        unset(
            $searchConfigSettings['form_class'],
            $searchConfigSettings['default_results'],
            $searchConfigSettings['default_query'],
            $searchConfigSettings['facet_limit'],
            $searchConfigSettings['facet_languages'],
            $searchConfigSettings['facet_mode'],
            $searchConfigSettings['facets'],
            $searchConfigSettings['sort_fields'],
            $searchConfigSettings['restrict_query_to_form']
        );

        $searchConfigSettings = $connection->quote(json_encode($searchConfigSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sql = <<<SQL
UPDATE `advancedsearch_config`
SET `settings` = $searchConfigSettings
WHERE `id` = $id;
SQL;
        $connection->exec($sql);
    }

    // Replace forms "Basic" and "Advanced" by "Main".
    $sql = <<<'SQL'
UPDATE `advancedsearch_config`
SET `form_adapter` = "main"
WHERE `form_adapter` IN ("basic", "advanced");
SQL;
    $connection->exec($sql);

    $sql = <<<'SQL'
UPDATE `advancedsearch_index`
SET `name` = "Internal (sql)"
WHERE `name` = "Internal";
SQL;
    $connection->exec($sql);

    $messenger = new Messenger();
    $message = new Message(
        'The default search forms "Basic" and "Advanced" have been removed and replaced by a "Main" form. It is recommended to check it and to rename templates if they are customized in a theme.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'The default input type for main search form field "q" is now "search" instead of "text". Check your css or use $this->formText() in your theme.' // @translate
    );
    $messenger->addWarning($message);
    $message = new Message(
        'The search page form defines new keys and use sub settings, in particular for facets. Check your theme if it was customized.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.5.22.3', '<')) {
    $messenger = new Messenger();
    $message = new Message(
        'You may enable the auto-suggestion in the search page settings.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.5.23.3', '<')) {
    $sql = <<<'SQL'
SELECT `id`, `settings` FROM `advancedsearch_config`;
SQL;
    $default = [
        'search' => [],
        'autosuggest' => [],
        'form' => [],
        'sort' => [],
        'facet' => [],
    ];
    $stmt = $connection->query($sql);
    $results = $stmt->fetchAll();
    foreach ($results as $result) {
        $id = $result['id'];
        $searchConfigSettings = $result['settings'];
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        $searchConfigSettings = array_replace($default, $searchConfigSettings);

        if (!empty($searchConfigSettings['form']['filters'])) {
            foreach ($searchConfigSettings['form']['filters'] as $name => &$val) {
                $val = ['name' => $name, 'label' => $val];
            }
            unset($val);
        }

        foreach ($searchConfigSettings['sort']['fields'] as $name => &$val) {
            $val = ['name' => $name, 'label' => $val];
        }
        unset($val);

        foreach ($searchConfigSettings['facet']['facets'] as $name => &$val) {
            $val = ['name' => $name, 'label' => $val];
        }
        unset($val);

        $searchConfigSettings = $connection->quote(json_encode($searchConfigSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sql = <<<SQL
UPDATE `advancedsearch_config`
SET `settings` = $searchConfigSettings
WHERE `id` = $id;
SQL;
        $connection->exec($sql);
    }
}
