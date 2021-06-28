<?php declare(strict_types=1);

namespace Search;

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
        ALTER TABLE search_page
        CHANGE `form` `form_adapter` varchar(255) NOT NULL
    ');
}

if (version_compare($oldVersion, '0.5.0', '<')) {
    // There is no "drop foreign key if exists", so check it.
    $sql = '';
    $sm = $connection->getSchemaManager();
    $keys = ['search_page_ibfk_1', 'index_id', 'IDX_4F10A34984337261', 'FK_4F10A34984337261'];
    $foreignKeys = $sm->listTableForeignKeys('search_page');
    foreach ($foreignKeys as $foreignKey) {
        if ($foreignKey && in_array($foreignKey->getName(), $keys)) {
            $sql .= 'ALTER TABLE search_page DROP FOREIGN KEY ' . $foreignKey->getName() . ';' . PHP_EOL;
        }
    }
    $indexes = $sm->listTableIndexes('search_page');
    foreach ($indexes as $index) {
        if ($index && in_array($index->getName(), $keys)) {
            $sql .= 'DROP INDEX ' . $index->getName() . ' ON search_page;' . PHP_EOL;
        }
    }

    $sql .= <<<'SQL'
ALTER TABLE search_index CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE settings settings LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
ALTER TABLE search_page CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE index_id index_id INT NOT NULL AFTER id, CHANGE settings settings LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
CREATE INDEX IDX_4F10A34984337261 ON search_page (index_id);
ALTER TABLE search_page ADD CONSTRAINT search_page_ibfk_1 FOREIGN KEY (index_id) REFERENCES search_index (id);
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
    $keys = ['search_page_ibfk_1', 'index_id', 'IDX_4F10A34984337261', 'FK_4F10A34984337261'];
    $foreignKeys = $sm->listTableForeignKeys('search_page');
    foreach ($foreignKeys as $foreignKey) {
        if ($foreignKey && in_array($foreignKey->getName(), $keys)) {
            $sql .= 'ALTER TABLE search_page DROP FOREIGN KEY ' . $foreignKey->getName() . ';' . PHP_EOL;
        }
    }
    $indexes = $sm->listTableIndexes('search_page');
    foreach ($indexes as $index) {
        if ($index && in_array($index->getName(), $keys)) {
            $sql .= 'DROP INDEX ' . $index->getName() . ' ON search_page;' . PHP_EOL;
        }
    }

    $sql .= <<<'SQL'
CREATE INDEX IDX_4F10A34984337261 ON search_page (index_id);
ALTER TABLE search_page ADD CONSTRAINT FK_4F10A34984337261 FOREIGN KEY (index_id) REFERENCES search_index (id) ON DELETE CASCADE;
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
        if (array_key_exists('search_page_id', $themeSettings)) {
            $siteSettings->set('search_main_page', $themeSettings['search_page_id']);
            unset($themeSettings['search_page_id']);
            $siteSettings->set($key, $themeSettings);
        }
    }
}

if (version_compare($oldVersion, '3.5.8', '<')) {
    $defaultConfig = $config[strtolower(__NAMESPACE__)]['config'];
    $settings->set(
        'search_batch_size',
        $defaultConfig['search_batch_size']
    );

    // Reorder the search pages by weight to avoid to do it each time.
    // The api is not available for search pages during upgrade, so use sql.
    $sql = <<<'SQL'
SELECT `id`, `settings` FROM `search_page`;
SQL;
    $stmt = $connection->query($sql);
    $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    if ($result) {
        foreach ($result as $id => $searchPageSettings) {
            $searchPageSettings = json_decode($searchPageSettings, true) ?: [];
            if ($searchPageSettings) {
                foreach (['facets', 'sort_fields'] as $type) {
                    if (!isset($searchPageSettings[$type])) {
                        $searchPageSettings[$type] = [];
                    } else {
                        // @see \Search\Controller\Admin\SearchPageController::configureAction()
                        // Sort enabled first, then available, else sort by weigth.
                        uasort($searchPageSettings[$type], function ($a, $b) {
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
            $searchPageSettings = $connection->quote(json_encode($searchPageSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $sql = <<<SQL
UPDATE `search_page`
SET `settings` = $searchPageSettings
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
        $searchPages = $siteSettings->get('search_pages', []) ?: [];
        $searchPages = array_unique(array_filter(array_map('intval', $searchPages)));
        sort($searchPages);
        $siteSettings->set('search_pages', $searchPages);
    }
}

if (version_compare($oldVersion, '3.5.12.2', '<')) {
    $mainSearchPage = $settings->get('search_main_page');
    if ($mainSearchPage) {
        $mainSearchPage = basename($mainSearchPage);
        // The api for search_pages is not available during upgrade.
        $sql = <<<SQL
SELECT `id`
FROM `search_page`
WHERE `path` = :search_page;
SQL;
        $id = $connection->fetchColumn($sql, ['search_page' => $mainSearchPage], 0);
        $settings->set('search_main_page', $id ? (string) $id : null);
    }
}

if (version_compare($oldVersion, '3.5.14', '<')) {
    // Add new default options to settings of search pages.
    $sql = <<<'SQL'
SELECT `id`, `settings` FROM `search_page`;
SQL;
    $stmt = $connection->query($sql);
    $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    if ($result) {
        foreach ($result as $id => $searchPageSettings) {
            $searchPageSettings = json_decode($searchPageSettings, true) ?: [];
            $searchPageSettings += [
                'default_results' => 'default',
                'default_query' => '',
                'restrict_query_to_form' => '0',
            ];
            $searchPageSettings['form']['item_set_filter_type'] = 'multi-checkbox';
            $searchPageSettings['form']['resource_class_filter_type'] = 'select';
            $searchPageSettings['form']['resource_template_filter_type'] = 'select';
            $searchPageSettings['form']['filter_collection_number'] = '1';
            $searchPageSettings = $connection->quote(json_encode($searchPageSettings, 320));
            $sql = <<<SQL
UPDATE `search_page`
SET `settings` = $searchPageSettings
WHERE `id` = $id;
SQL;
            $connection->exec($sql);
        }
    }
}

if (version_compare($oldVersion, '3.5.16.3', '<')) {
    // @link https://www.doctrine-project.org/projects/doctrine-dbal/en/2.6/reference/types.html#array-types
    $sql = <<<'SQL'
ALTER TABLE `search_index`
CHANGE `settings` `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
CHANGE `modified` `modified` DATETIME DEFAULT NULL;
SQL;
    $connection->exec($sql);
    $sql = <<<'SQL'
ALTER TABLE `search_page`
CHANGE `settings` `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
CHANGE `modified` `modified` DATETIME DEFAULT NULL;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.5.21.3', '<')) {
    $sql = <<<'SQL'
SELECT `id`, `form_adapter`, `settings` FROM `search_page`;
SQL;
    $stmt = $connection->query($sql);
    $results = $stmt->fetchAll();
    foreach ($results as $result) {
        $id = $result['id'];
        // $formAdapter = $result['form_adapter'];
        $searchPageSettings = $result['settings'];
        $searchPageSettings = json_decode($searchPageSettings, true) ?: [];
        if (empty($searchPageSettings['search'])) {
            $searchPageSettings['search'] = [];
        }

        // For simplicity, keep old params as they are, and merge them with the
        // new one. It will be cleaned once the config form will be saved.

        // Move search main settings from root to [search].
        $searchPageSettings['search']['default_results'] = $searchPageSettings['default_results'] ?? 'default';
        $searchPageSettings['search']['default_query'] = $searchPageSettings['default_query'] ?? '';

        $searchPageSettings['form']['filters_max_number'] = $searchPageSettings['form']['filter_collection_number'] ?? 5;

        $searchPageSettings['form']['filters'] = $searchPageSettings['form']['filters'] ?? [];
        $searchPageSettings['form']['fields_order'] = $searchPageSettings['form']['fields_order'] ?? [];

        foreach ($searchPageSettings['form']['filters'] as $name => &$field) {
            if (empty($field['enabled'])) {
                unset($searchPageSettings['form']['filters'][$name]);
            } else {
                $field = $field['display']['label'];
            }
        }
        unset($field);

        $searchPageSettings['sort'] = [
            'fields' => $searchPageSettings['sort_fields'] ?? [],
        ];

        foreach ($searchPageSettings['sort']['fields'] as $name => &$field) {
            if (empty($field['enabled'])) {
                unset($searchPageSettings['sort']['fields'][$name]);
            } else {
                $field = $field['display']['label'];
            }
        }
        unset($field);

        $searchPageSettings['facet'] = [
            'facets' => $searchPageSettings['facets'] ?? [],
            'limit' => $searchPageSettings['facet_limit'] ?? 10,
            'languages' => $searchPageSettings['facet_languages'] ?? [],
            'mode' => $searchPageSettings['facet_mode'] ?? 'button',
        ];

        foreach ($searchPageSettings['facet']['facets'] as $name => &$field) {
            if (empty($field['enabled'])) {
                unset($searchPageSettings['facet']['facets'][$name]);
            } else {
                $field = $field['display']['label'];
            }
        }
        unset($field);

        unset(
            $searchPageSettings['form_class'],
            $searchPageSettings['default_results'],
            $searchPageSettings['default_query'],
            $searchPageSettings['facet_limit'],
            $searchPageSettings['facet_languages'],
            $searchPageSettings['facet_mode'],
            $searchPageSettings['facets'],
            $searchPageSettings['sort_fields'],
            $searchPageSettings['restrict_query_to_form']
        );

        $searchPageSettings = $connection->quote(json_encode($searchPageSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sql = <<<SQL
UPDATE `search_page`
SET `settings` = $searchPageSettings
WHERE `id` = $id;
SQL;
        $connection->exec($sql);
    }

    // Replace forms "Basic" and "Advanced" by "Main".
    $sql = <<<'SQL'
UPDATE `search_page`
SET `form_adapter` = "main"
WHERE `form_adapter` IN ("basic", "advanced");
SQL;
    $connection->exec($sql);

    $sql = <<<'SQL'
UPDATE `search_index`
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
}
