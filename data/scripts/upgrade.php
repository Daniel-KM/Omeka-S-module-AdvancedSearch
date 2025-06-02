<?php declare(strict_types=1);

namespace AdvancedSearch;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

$config = $services->get('Config');
$localConfig = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.68')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.68'
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
        $message = new PsrMessage(
            'The module {module} should be updated to version {version}.', // @translate
            ['module' => 'Reference', 'version' => '3.4.32.3']
        );
        $messenger->addWarning($message);
    }

    $message = new PsrMessage(
        'It is now possible to aggregate properties with the internal (sql) adapter. See config of the internal search engine.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to add a pagination per-page to the search page.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to use "not" in advanced filters.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to display the used search filters in the results header.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Some field types have been renamed for filters in the form. To use the core input elements, the type should be "Omeka" (or prepend "Omeka/" if needed). See the default config page.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.6.9', '<')) {
    $message = new PsrMessage(
        'It is now possible to query ressources with linked ressources in the standard advanced form.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.3.6.12', '<')) {
    $message = new PsrMessage(
        'A new option was added to display the resources mixed (by default) or item sets and items separately (old behavior).' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'The template for the sort selector has been updated.' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
        'A helper for the pagination per page has been added.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.3.6.15', '<')) {
    $message = new PsrMessage(
        'It’s now possible to search resources by multiples properties, and resources without class, template, item set, site or owner.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.3.6.16', '<')) {
    $message = new PsrMessage(
        'It’s now possible to use facet range with a double select (from/to). With internal sql engine, order is alphabetic only for now: it works for strings and simple four digits years or standard dates, not integer or variable dates. With Solr, only numbers and dates are supported. The theme may need to be updated.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'The property query types lower/greater have been updated to use alphabetic order, no more date-time only. For integer and date-time, use numeric data types.' // @translate
    );
    $messenger->addWarning($message);
    $message = new PsrMessage(
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

    $message = new PsrMessage(
        'It’s now possible to search values similar other ones (via {link}Soundex{link_end}, designed for British English phonetic).', // @translate
        ['link' => '<a href="https://en.wikipedia.org/wiki/Soundex">', 'link_end' => '</a>']
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.7', '<')) {
    $message = new PsrMessage(
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
        $message = new PsrMessage(
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

    $message = new PsrMessage(
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

    $message = new PsrMessage(
        'The performance was improved in many places, in particular for large databases.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to order results by a list of ids with argument "sort_by=ids".' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    $message = new PsrMessage(
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
    $message = new PsrMessage(
        'When full text is managed in alto files, it is now possible to search full text or record only.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.21', '<')) {
    $message = new PsrMessage(
        'It is now possible to search resources with duplicated values to help curation.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The speed of the derivative forms were improved and can be called directly from the search config with option "variant". Upgrade your theme if it was customized.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.22', '<')) {
    // Check themes that use old view helpers "FacetActive", "FacetCheckbox" and
    // "FacetLink".
    $checks = [
        'facetActive' => [
            "'facetActive'",
            '"facetActive"',
            '>facetActive(',
            '$facetActive(',
        ],
        'facetCheckbox' => [
            "'facetCheckbox'",
            '"facetCheckbox"',
            '>facetCheckbox(',
            '$facetCheckbox(',
        ],
        'facetLink' => [
            "'facetLink'",
            '"facetLink"',
            '>facetLink(',
            '$facetLink(',
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($checks as $name => $strings) {
        $results[$name] = $manageModuleAndResources->checkStringsInFiles($strings, 'themes/*/view/search/*');
    }
    $result = array_filter($results);
    if ($result) {
        $result = array_map('array_values', array_map('array_unique', $result));
        $message = new PsrMessage(
            'View helpers "FacetActive", "FacetCheckbox" and "FacetLink" and associate theme files were removed in favor of plural view helpers. Check your theme if you customized it. Matching files: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }
}

if (version_compare($oldVersion, '3.4.24', '<')) {
    /**
     * Migrate blocks of this module to new blocks of Omeka S v4.1.
     *
     * Replace filled settting "heading" by a specific block "Heading".
     * Replace filled settting "html" by a specific block "Html".
     * Move setting template to block layout template.
     *
     * @var \Laminas\Log\Logger $logger
     *
     * @see \Omeka\Db\Migrations\MigrateBlockLayoutData
     */

    // Check themes that use "$heading" and "$html" and "$cssClass" in block
    // Searching Form and "$html" and "$captionPosition" in block Media and ExternalContent.
    $strings = [
        'themes/*/view/common/block-layout/external-content*' => [
            '$html',
            '$captionPosition',
        ],
        'themes/*/view/common/block-layout/media-*' => [
            '$html',
            '$captionPosition',
        ],
        'themes/*/view/common/block-layout/*resource-text*' => [
            '$html',
            '$captionPosition',
        ],
        'themes/*/view/common/block-layout/searching-form*' => [
            '$heading',
            '$html',
            '$cssClass',
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($strings as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if ($result) {
            $results[] = $result;
        }
    }
    if ($results) {
        $message = new PsrMessage(
            'The variables "$heading", "$html" and "$cssClass" were removed from block Searching Form. Remove them in the following files before upgrade and automatic conversion: {json}', // @translate
            ['json' => json_encode($results, 448)]
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }

    $pageRepository = $entityManager->getRepository(\Omeka\Entity\SitePage::class);

    $viewHelpers = $services->get('ViewHelperManager');
    $escape = $viewHelpers->get('escapeHtml');
    $hasBlockPlus = $this->isModuleActive('BlockPlus');

    $pagesUpdated = [];
    $pagesUpdated2 = [];
    foreach ($pageRepository->findAll() as $page) {
        $pageSlug = $page->getSlug();
        $siteSlug = $page->getSite()->getSlug();
        $position = 0;
        foreach ($page->getBlocks() as $block) {
            $block->setPosition(++$position);
            $layout = $block->getLayout();
            if ($layout !== 'searchingForm') {
                continue;
            }
            $data = $block->getData() ?: [];

            $heading = $data['heading'] ?? '';
            if (strlen($heading)) {
                $b = new \Omeka\Entity\SitePageBlock();
                $b->setPage($page);
                $b->setPosition(++$position);
                if ($hasBlockPlus) {
                    $b->setLayout('heading');
                    $b->setData([
                        'text' => $heading,
                        'level' => 2,
                    ]);
                } else {
                    $b->setLayout('html');
                    $b->setData([
                        'html' => '<h2>' . $escape($heading) . '</h2>',
                    ]);
                }
                $entityManager->persist($b);
                $block->setPosition(++$position);
                $pagesUpdated[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['heading']);

            $html = $data['html'] ?? '';
            $hasHtml = !in_array(str_replace([' ', "\n", "\r", "\t"], '', $html), ['', '<div></div>', '<p></p>']);
            if ($hasHtml) {
                $b = new \Omeka\Entity\SitePageBlock();
                $b->setLayout('html');
                $b->setPage($page);
                $b->setPosition(++$position);
                $b->setData([
                    'html' => $html,
                ]);
                $entityManager->persist($b);
                $block->setPosition(++$position);
                $pagesUpdated[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['html']);

            $template = $data['template'] ?? '';
            $layoutData = $block->getLayoutData() ?? [];
            $existingTemplateName = $layoutData['template_name'] ?? null;
            $templateName = pathinfo($template, PATHINFO_FILENAME);
            $templateCheck = 'searching-form';
            if ($templateName
                && $templateName !== $templateCheck
                && (!$existingTemplateName || $existingTemplateName === $templateCheck)
            ) {
                $layoutData['template_name'] = $templateName;
                $pagesUpdated2[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['template']);

            if (empty($data['query'])) {
                $data['query'] = [];
            } elseif (!is_array($data['query'])) {
                $query = [];
                parse_str(ltrim($data['query'], "? \t\n\r\0\x0B"), $query);
                $data['query'] = array_filter($query, fn ($v) => $v !== '' && $v !== [] && $v !== null);
            }
            if (empty($data['query_filter'])) {
                $data['query_filter'] = [];
            } elseif (!is_array($data['query_filter'])) {
                $query = [];
                parse_str(ltrim($data['query_filter'], "? \t\n\r\0\x0B"), $query);
                $data['query_filter'] = array_filter($query, fn ($v) => $v !== '' && $v !== [] && $v !== null);
            }

            $data['search_config'] ??= $data['search_page'] ?? 'default';
            unset($data['search_page']);

            $block->setData($data);
            $block->setLayoutData($layoutData);
        }
    }

    $entityManager->flush();

    if ($pagesUpdated) {
        $result = array_map('array_values', $pagesUpdated);
        $message = new PsrMessage(
            'The settings "heading" and "html" were removed from block Searching Form. New blocks "Heading" or "Html" were prepended to all blocks that had a filled heading or html. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    if ($pagesUpdated2) {
        $result = array_map('array_values', $pagesUpdated2);
        $message = new PsrMessage(
            'The setting "template" was moved to the new block layout settings available since Omeka S v4.1. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());

        $message = new PsrMessage(
            'The template files for the block Searching Form should be moved from "view/common/block-layout" to "view/common/block-template" in your themes. You may check your themes for pages: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addError($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    $message = new PsrMessage(
        'New options were added in search config: the choice of the theme template (search/search) and an option to display the breadcrumbs.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.24', '<')) {
    // Add sort label.
    $sortLabel = $translate('Sort by');
    $sql = <<<SQL
        UPDATE `search_config`
        SET
            `settings` = REPLACE(`settings`, '"sort":{"fields":', '"sort":{"label":"$sortLabel","fields":')
        ;
        SQL;
    $connection->executeStatement($sql);

    // Add option to enable facets.
    $sql = <<<'SQL'
        UPDATE `search_config`
        SET
            `settings` = REPLACE(`settings`, '"display":{', '"display":{"facets":"after",')
        ;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.25', '<')) {
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('advancedsearch_redirect_itemset',
            $siteSettings->get('advancedsearch_redirect_itemset') ? 'first' : ''
        );
    }
    $message = new PsrMessage(
        'The option to redirect item set to search was improved to manage all pages.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.26', '<')) {
    // Fixed upgrade to 3.4.24.
    $sql = <<<'SQL'
        UPDATE `search_config`
        SET
            `settings` = REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            `settings`,
            '"after""', '"after","'),
            '""search_filters"', ',","search_filters"'),
            '"after"search_filters"', '"after","search_filters"'),
            ':",', ':"",'),
            ':"}', ':""}')
        ;
        SQL;
    $connection->executeStatement($sql);

    // Add option "default_number" for advanced filters.
    $sortLabel = $translate('Sort by');
    $sql = <<<'SQL'
        UPDATE `search_config`
        SET
            `settings` = REPLACE(`settings`, '"max_number":', '"default_number":1,"max_number":')
        ;
        SQL;
    $connection->executeStatement($sql);
}

// Normalize name of a column early.
/** @see https://forum.omeka.org/t/problem-from-failed-update-from-advanced-search-3-4-27-to-3-4-44/27400/2 */
$sql = <<<'SQL'
        ALTER TABLE `search_config`
        CHANGE `path` `slug` varchar(190) NOT NULL AFTER `name`;
        SQL;
try {
    $connection->executeStatement($sql);
} catch (\Exception $e) {
    // Already done.
}

if (version_compare($oldVersion, '3.4.28', '<')) {
    // TODO Move this in module Common in a new class "UpgradeTheme".
    // The previous version used shell commands, but they may be forbidden by some servers.
    $doUpgrade = !empty($config['advancedsearch_upgrade_3.4.28']);
    $stringsAndMessages = [
        'facetOptions' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    '$facetActives(null, $activeFacets, $options)',
                ],
            ],
            'message' => 'The template for facets was simplified. See view/search/facets-list.phtml. Matching templates: {json}', // @translate
            'commands' => [
                // TODO Missing replacements: facetsOptions.
                'str_replace' => [
                    'from' => [
                        <<<'PHP'
                        $facetActives(null, $activeFacets, $options)
                        PHP,
                        <<<'PHP'
                        $facetElements = $isFacetModeButton ? $plugins->get('facetCheckboxes') : $plugins->get('facetLinks');
                        PHP,
                        '// Facet checkbox can be used in any case anyway, the js checks it.' . "\n",
                        <<<'PHP'
                        $facetSelect = $plugins->get('facetSelect');
                        PHP . "\n",
                        <<<'PHP'
                        $facetSelectRange = $plugins->get('facetSelectRange');
                        PHP . "\n",
                        <<<'PHP'
                        $facetElementsTree = $isFacetModeButton ? $plugins->get('facetCheckboxesTree') : $plugins->get('facetLinksTree');
                        PHP . "\n",
                        <<<'PHP'
                        <?php $facetType = empty($options['facets'][$name]['type']) ? 'Checkbox' : $options['facets'][$name]['type']; ?>
                        PHP . "\n",
                        <<<'PHP'
                        <?php foreach ($facets as $name => $facetValues): ?>
                        PHP . "\n",
                        <<<'PHP'
                                        <?php if ($facetType === 'Select'): ?>
                                        <?= $facetSelect($name, $facetValues, $options) ?>
                                        <?php elseif ($facetType === 'SelectRange'): ?>
                                        <?= $facetSelectRange($name, $facetValues, $options) ?>
                                        <?php elseif ($facetType === 'Tree' || $facetType === 'Thesaurus'): ?>
                                        <?= $facetElementsTree($name, $facetValues, $options) ?>
                                        <?php else: ?>
                                        <?= $facetElements($name, $facetValues, $options) ?>
                                        <?php endif; ?>
                        PHP,
                        <<<'PHP'
                                    <?php if ($facetType === 'Select'): ?>
                                    <?= $facetSelect($name, $facetValues, $options) ?>
                                    <?php elseif ($facetType === 'SelectRange'): ?>
                                    <?= $facetSelectRange($name, $facetValues, $options) ?>
                                    <?php elseif ($facetType === 'Tree'): ?>
                                    <?= $facetElementsTree($name, $facetValues, $options) ?>
                                    <?php else: ?>
                                    <?= $facetElements($name, $facetValues, $options) ?>
                                    <?php endif; ?>
                        PHP,
                    ],
                    'to' => [
                        <<<'PHP'
                        $facetActives(null, $activeFacets, $searchConfig->setting('facet'))
                        PHP,
                        <<<'PHP'
                        $facetElements = $plugins->get('facetElements');
                        PHP,
                        '',
                        '',
                        '',
                        '',
                        '',
                        <<<'PHP'
                        <?php foreach ($facets as $name => $facetValues): ?>
                                        <?php $facetOptions = $searchFacets[$name]; ?>
                        PHP . "\n",
                        <<<'PHP'
                                        <?= $facetElements($name, $facetValues, $facetOptions) ?>
                        PHP,
                        <<<'PHP'
                                    <?= $facetElements($name, $facetValues, $facetOptions) ?>
                        PHP,
                    ],
                    'message' => 'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total} {file} partially updated.', // @translate
                ],
            ],
        ],
        'facet_filters' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    'facet_filters',
                ],
            ],
            'message' => 'The option "facet_filters" was removed. Update the theme to use facets instead. Matching templates: {json}', // @translate
        ],
        'facetLabel' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    'facetLabel(',
                ],
            ],
            'message' => 'The view helper "facetLabel()" was removed. Update the theme and get the label directly from the each facet config: replace `$facetLabel($name)` by `$facetOptions[\'label\'] ?: $name)`. Matching templates: {json}', // @translate
            'commands' => [
                // TODO Missing replacements: to get facetsOptions.
                'str_replace' => [
                    'from' => [
                        <<<'PHP'
                        $facetLabel = $plugins->get('facetLabel');
                        PHP . "\n",
                        <<<'PHP'
                        $facetLabel($name)
                        PHP,
                    ],
                    'to' => [
                        '',
                        <<<'PHP'
                        $facetOptions['label'] ?? $name)
                        PHP,
                    ],
                    'message' => 'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total} {file} partially updated.', // @translate
                ],
            ],
        ],
        'searchForm' => [
            'strings' => [
                'themes/*/view/common/block-layout/searching-form*' => [
                    'searchForm(',
                ],
                'themes/*/view/search/*' => [
                    'searchForm(',
                ],
            ],
            'message' => 'The view helper `searchForm()` was removed. You should replace it with `$searchConfig->renderForm([])`. Matching templates: {json}', // @translate
        ],
        'facets' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "search/facets'",
                ],
            ],
            'message' => 'The template "search/facets" was renamed "search/facets-list". Update it in your theme. Matching templates: {json}', // @translate
            'commands' => [
                'str_replace' => [
                    'from' => "search/facets'",
                    'to' => "search/facets-list'",
                ],
                'rename' => [
                    'from' => 'search/facets.phtml',
                    'to' => 'search/facets-list.phtml',
                ],
            ],
        ],
        'resource-list' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "search/resource-list'",
                ],
            ],
            'message' => 'The template "search/resource-list" was renamed "search/results". Update your theme. Matching templates: {json}', // @translate
            'commands' => [
                'str_replace' => [
                    'from' => "search/resource-list'",
                    'to' => "search/results'",
                ],
                'rename' => [
                    'from' => 'search/resource-list.phtml',
                    'to' => 'search/results.phtml',
                ],
            ],
        ],
        'results-header-footer' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "search/results-header'",
                    "search/results-footer'",
                ],
            ],
            'message' => 'The templates "search/results-header" and "search/results-footer" were replaced by "search/results-header-footer". Remove them in your theme. Matching templates: {json}', // @translate
        ],
        'per_pages' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "'per_pages'",
                ],
            ],
            'message' => 'The key "per_pages" was renamed "per_page". Update your theme. Matching templates: {json}', // @translate
            'commands' => [
                'str_replace' => [
                    'from' => "'per_pages'",
                    'to' => "'per_page'",
                ],
            ],
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($stringsAndMessages as $key => $stringsAndMessage) foreach ($stringsAndMessage['strings'] as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if (!$result) {
            continue;
        } elseif (!$doUpgrade || empty($stringsAndMessage['commands'])) {
            $results[$key][] = $result;
            continue;
        }
        // Process upgrade of files.
        // This feature is not documented in readme and not officialy supported,
        // so at your own risk!
        $total = count($stringsAndMessage['commands']);
        $i = 0;
        foreach ($stringsAndMessage['commands'] as $commandName => $commandArgs) switch ($commandName) {
            default:
                ++$i;
                // For debug only.
                throw new \Exception('Command undefined');
            case 'rename':
                ++$i;
                $j = 0;
                foreach ($result as $filename) {
                    $from = OMEKA_PATH . '/' . $filename;
                    $newFilename = str_replace($commandArgs['from'], $commandArgs['to'], $filename);
                    $to = OMEKA_PATH . '/' . $newFilename;
                    if ($from !== $to) {
                        $written = rename($from, $to);
                        if (!$written) {
                            $message = new PsrMessage(
                                'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total} {file} failed to be renamed {file_new}.', // @translate
                                ['name' => $commandName, 'index' => $i, 'total' => $total, 'key' => $key, 'count' => ++$j, 'count_total' => count($result), 'file' => $filename, 'file_new' => $newFilename]
                            );
                            $messenger->addError($message);
                            $logger->err($message->getMessage(), $message->getContext());
                        } else {
                            $message = new PsrMessage(
                                'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total} {file} successfully renamed {file_new}.', // @translate
                                ['name' => $commandName, 'index' => $i, 'total' => $total, 'key' => $key, 'count' => ++$j, 'count_total' => count($result), 'file' => $filename, 'file_new' => $newFilename]
                            );
                            $messenger->addSuccess($message);
                            $logger->notice($message->getMessage(), $message->getContext());
                        }
                    }
                }
                break;
            case 'str_replace':
                ++$i;
                $j = 0;
                foreach ($result as $filename) {
                    $filepath = OMEKA_PATH . '/' . $filename;
                    $content = file_get_contents($filepath);
                    if ($content) {
                        $content = str_replace($commandArgs['from'], $commandArgs['to'], $content);
                        $written = file_put_contents($filepath, $content);
                        if ($written === false) {
                            $message = new PsrMessage(
                                'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total}  {file} unwriteable.', // @translate
                                ['name' => $commandName, 'index' => $i, 'total' => $total, 'key' => $key, 'count' => ++$j, 'count_total' => count($result), 'file' => $filename]
                            );
                            $messenger->addError($message);
                            $logger->err($message->getMessage(), $message->getContext());
                        } else {
                            $message = new PsrMessage(
                                $commandArgs['message'] ?? 'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total}  {file} updated.', // @translate
                                ['name' => $commandName, 'index' => $i, 'total' => $total, 'key' => $key, 'count' => ++$j, 'count_total' => count($result), 'file' => $filename]
                            );
                            $messenger->addSuccess($message);
                            $logger->notice($message->getMessage(), $message->getContext());
                        }
                    } else {
                        $message = new PsrMessage(
                            'Command "{name}" #{index}/{total} for {key}: file #{count}/{count_total} {file} unreadable.', // @translate
                            ['name' => $commandName, 'index' => $i, 'total' => $total, 'key' => $key, 'count' => ++$j, 'count_total' => count($result), 'file' => $filename]
                        );
                        $messenger->addError($message);
                        $logger->err($message->getMessage(), $message->getContext());
                    }
                }
                break;
        }
    }
    if ($results) {
        $messages = [];
        $message = new PsrMessage(
            'The module may break the theme to manage new features, in particular for facets.' // @translate
        );
        $messages[] = $message;
        foreach ($results as $key => $result) {
            $message = new PsrMessage($stringsAndMessages[$key]['message'], ['json' => json_encode($result, 448)]);
            $logger->err($message->getMessage(), $message->getContext());
            $messages[] = $message;
        }
        $messenger->addErrors($messages);
    }

    $stringsAndMessages = [
        'searchingForm' => [
            'strings' => [
                'themes/*/common/search-form*' => [
                    'searchingForm(',
                ],
                'themes/*/view/common/block-layout/search*' => [
                    'searchingForm(',
                ],
                'themes/*/view/common/block-layout/searching-form*' => [
                    'searchingForm(',
                ],
                'themes/*/view/search/index/search*' => [
                    'searchingForm(',
                ],
            ],
            'message' => 'The view helper `searchingForm()` is deprecated. Replace it with `$searchConfig->renderForm([])`. Matching templates: {json}', // @translate
        ],
        'title' => [
            'strings' => [
                'themes/*/view/search/results.phtml' => [
                    '* @var string $title',
                ],
                'themes/*/view/search/resource-list.phtml' => [
                    '* @var string $title',
                ],
            ],
            'message' => 'The title of the result part is no more appended in template "search/resource-list" or "search/results", but in main search template. Update your theme. Matching templates: {json}', // @translate
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($stringsAndMessages as $key => $stringsAndMessage) foreach ($stringsAndMessage['strings'] as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if ($result) {
            $results[$key][] = $result;
        }
    }
    if ($results) {
        foreach ($results as $key => $result) {
            $message = new PsrMessage($stringsAndMessages[$key]['message'], ['json' => json_encode($result, 448)]);
            $logger->warn($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        }
    }

    // The process can be repeated in case of issue.

    // Update name of the navigation link.
    $sql = <<<'SQL'
        UPDATE `site`
        SET
            `navigation` = REPLACE(`navigation`, '"type":"searchPage"', '"type":"searchingPage"')
        ;
        SQL;
    $connection->executeStatement($sql);

    // Normalize name of a column.
    $sql = <<<'SQL'
        ALTER TABLE `search_config`
        CHANGE `path` `slug` varchar(190) NOT NULL AFTER `name`
        ;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already done.
    }

    // There may be issue with cache when changing a column name.
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }
    @clearstatcache(true);

    $message = new PsrMessage(
        'New options were added in search config: the choice of the theme template (search/search) and an option to display the breadcrumbs.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'New options were added to define the thumbnail.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to sort by relevance with the internal querier.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The default template for the list of results was updated to use css flex. Check your theme.' // @translate
    );
    $messenger->addWarning($message);

    if (array_key_exists('advancedsearch_skip_exception', $config)) {
        $message = new PsrMessage(
            'The key "{key}" can be removed from the file config/local.config.php.', // @translate
            ['key' => 'advancedsearch_skip_exception']
        );
        $messenger->addNotice($message);
    }
    if (array_key_exists('advancedsearch_upgrade_3.4.28', $config)) {
        $message = new PsrMessage(
            'The key "{key}" can be removed from the file config/local.config.php.', // @translate
            ['key' => 'advancedsearch_upgrade_3.4.28']
        );
        $messenger->addNotice($message);
    }
}

if (version_compare($oldVersion, '3.4.29', '<')) {
    // Updated search page.

    $message = new PsrMessage(
        'The html/css class names were simplified in the search page, in particular for facets. You should check your theme if you customized it.' // @translate
    );
    $messenger->addWarning($message);

    $stringsAndMessages = [
        'search-facet' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    'search-facet',
                ],
            ],
            'message' => 'The template for facets was simplified. See view/search/facets-list.phtml. Matching templates: {json}', // @translate
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($stringsAndMessages as $key => $stringsAndMessage) foreach ($stringsAndMessage['strings'] as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if (!$result) {
            continue;
        }
        $results[$key][] = $result;
    }
    if ($results) {
        foreach ($results as $key => $result) {
            $message = new PsrMessage($stringsAndMessages[$key]['message'], ['json' => json_encode($result, 448)]);
            $logger->err($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        }
    }

    // Convert each facet as array to configure each of them separately.
    // Move mapping config to mapper.
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('search_config.id', 'search_config.settings')
        ->from('search_config', 'search_config')
        ->where('search_config.settings IS NOT NULL')
        ->orderBy('search_config.id', 'asc');
    $searchConfigs = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigs as $id => $searchConfigSettings) {
        $searchConfigSettings = json_decode($searchConfigSettings, true);
        $facetConfig = $searchConfigSettings['facet'] ?? [];
        $facetMode = ($facetConfig['mode'] ?? null) === 'link' ? 'link' : 'button';
        // Invert option to filter available or all facets when option is set.
        if (isset($facetConfig['list']) || empty($facetConfig['display_list'])) {
            $facetList = ($facetConfig['list'] ?? '') === 'all' ? 'all' : 'available';
        } else {
            $facetList = ($facetConfig['display_list'] ?? '') === 'available' ? 'all' : 'available';
        }
        $facetConfigNew = [
            'label' => $facetConfig['label'] ?? $translate('Facets'),
            'label_no_facets' => $facetConfig['label_no_facets'] ?? $translate('No facets'),
            'mode' => $facetMode,
            'list' => $facetList,
            'display_active' => !empty($facetConfig['display_active']),
            'label_active_facets' => $facetConfig['label_active_facets'] ?? $translate('Active facets'),
            'display_submit' => $facetConfig['display_submit'] ?? 'above',
            'label_submit' => $facetConfig['label_submit'] ?? $translate('Apply facets'),
            'display_reset' => $facetConfig['display_reset'] ?? 'above',
            'label_reset' => $facetConfig['label_reset'] ?? $translate('Reset facets'),
            'facets' => [],
        ];
        $languages = $searchConfigSettings['facet']['languages'] ?? [];
        $order = $searchConfigSettings['facet']['order'] ?? '';
        $limit = (int) ($searchConfigSettings['facet']['limit'] ?? 25);
        $displayCount = !empty($searchConfigSettings['facet']['display_count']);
        foreach ($searchConfigSettings['facet']['facets'] ?? [] as $facet) {
            $field = $facet['field'] ?? $facet['name'] ?? null;
            if (!$field) {
                continue;
            }
            // Options are no more used for facets.
            $optionsOptions = empty($facet['options'])
                ? []
                : (is_scalar($facet['options']) ? ['options' => $facet['options']] : $facet['options']);
            $newFacet = [
                'field' => $field,
                'languages' => $facet['languages'] ?? $languages,
                'label' => $facet['label'] ?? $field,
                'type' => empty($facet['type']) ? 'Checkbox' : $facet['type'],
                'order' => $facet['order'] ?? $order,
                'limit' => $facet['limit'] ?? $limit,
                'display_count' => $facet['display_count'] ?? $displayCount,
                // Store the facet mode in each facet to simplify theme.
                'mode' => $facetMode,
            ];
            // Until this version, only two options were managed for facets:
            // thesaurus and main types, exclusively.
            if (strcasecmp($newFacet['type'], 'thesaurus') === 0) {
                if (empty($optionsOptions)) {
                    $newFacet['thesaurus'] = 0;
                } elseif (count($optionsOptions) === 1) {
                    $newFacet['thesaurus'] = (int) reset($optionsOptions);
                } else {
                    $newFacet['thesaurus'] = empty($optionsOptions['id']) && empty($optionsOptions['thesaurus'])
                        ? (int) reset($optionsOptions)
                        : (int) ($optionsOptions['thesaurus'] ?? $optionsOptions['id'] ?? 0);
                }
            } elseif ($optionsOptions) {
                $newFacet['main_types'] = $optionsOptions;
            }
            $facetConfigNew['facets'][$field] = $newFacet;
        }
        $searchConfigSettings['facet'] = $facetConfigNew;
        $connection->executeStatement(
            'UPDATE `search_config` SET `settings` = :settings WHERE `id` = :id',
            [
                'settings' => json_encode($searchConfigSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'id' => $id,
            ],
            [
                'settings' => \Doctrine\DBAL\ParameterType::STRING,
                'id' => \Doctrine\DBAL\ParameterType::INTEGER,
            ],
        );
    }

    $message = new PsrMessage(
        'It is now possible to have a specific config for each facet (index, type, size, display, etc.).' // @translate
    );
    $messenger->addSuccess($message);

    // Check for internal querier.
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('search_config.id')
        ->from('search_config', 'search_config')
        ->innerJoin('search_config', 'search_engine', 'search_engine', 'search_engine.id = search_config.engine_id')
        ->where($qb->expr()->eq('search_engine.adapter', ':adapter'))
        ->orderBy('search_engine.id', 'asc');
    $searchConfigs = $connection->executeQuery($qb, ['adapter' => 'internal'])->fetchFirstColumn();
    if ($searchConfigs) {
        $message = new PsrMessage(
            'The option to display available or all facets was inverted and the same for the internal engine.' // @translate
        );
        $messenger->addWarning($message);
    }
}

if (version_compare($oldVersion, '3.4.31', '<')) {
    // Check for upgraded features.
    $stringsAndMessages = [
        'settings-search' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "etting('search',",
                ],
            ],
            'message' => 'The search config setting "search" was renamed "request". Check your theme. Matching templates: {json}', // @translate
        ],
        'settings-autosuggest' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "etting('autosuggest',",
                ],
            ],
            'message' => 'The search config setting "autosuggest" was renamed "q" and sub-settings too. Check your theme. Matching templates: {json}', // @translate
        ],
        'settings-per-page-list' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "etting('pagination', 'per_page')",
                ],
            ],
            'message' => 'The search config setting "pagination/per_page" was renamed "display/per_page_list". Check your theme. Matching templates: {json}', // @translate
        ],
        'settings-sort' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "etting('sort',",
                ],
            ],
            'message' => 'The search config setting "sort/fields" was renamed "display/sort_list" and label "sort/label" was renamed "display/label_sort". Check your theme. Matching templates: {json}', // @translate
        ],
        'resource-name' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    '$resourceName',
                ],
            ],
            'message' => 'The variable "$resourceName" was renamed "$resourceType". Check your theme. Matching templates: {json}', // @translate
        ],
        'search-sort-urls' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    'search-sort-urls',
                ],
            ],
            'message' => 'The template for sort-selector was simplified. See view/search/sort-selector.phtml. Check your theme. Matching templates: {json}', // @translate
        ],
        'searching-filters' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    'searchingFilters(',
                ],
            ],
            'message' => 'The use of the view helper searchingFilters() is deprecated. Use $searchConfig->renderSearchFilters() instead. Check your theme. Matching templates: {json}', // @translate
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($stringsAndMessages as $key => $stringsAndMessage) foreach ($stringsAndMessage['strings'] as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if (!$result) {
            continue;
        }
        $results[$key][] = $result;
    }
    if ($results) {
        foreach ($results as $key => $result) {
            $message = new PsrMessage($stringsAndMessages[$key]['message'], ['json' => json_encode($result, 448)]);
            $logger->err($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        }
    }

    // Add a unique name as key to filters.
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_engine', 'search_engine')
        // Only upgrade internal options.
        ->where('adapter = "internal"')
        ->orderBy('id', 'asc');
    $searchEngineSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchEngineSettings as $key => $searchEngineSetting) {
        $searchEngineSettings[$key] = json_decode($searchEngineSetting, true) ?: [];
    }

    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'engine_id')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsEngines = $connection->executeQuery($qb)->fetchAllKeyValue();

    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchEngineId = $searchConfigsEngines[$id] ?? null;
        // Renamed the main option for request.
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        $searchConfigSettings = ['request' => $searchConfigSettings['search'] ?? []] + $searchConfigSettings;
        unset($searchConfigSettings['search']);
        // Renamed the option "autosuggest" as "q".
        $autoSuggest = $searchConfigSettings['autosuggest'] ?? null;
        if ($autoSuggest) {
            // suggest limit is an old setting.
            $searchConfigSettings['q'] = $autoSuggest;
            $searchConfigSettings['q']['suggester'] = $autoSuggest['suggester'] ?? null;
            $searchConfigSettings['q']['suggest_url'] = $autoSuggest['url'] ?? null;
            $searchConfigSettings['q']['suggest_url_param_name'] = $autoSuggest['url_param_name'] ?? null;
            $searchConfigSettings['q']['suggest_limit'] = $autoSuggest['limit'] ?? null;
            $searchConfigSettings['q']['suggest_fill_input'] = $autoSuggest['fill_input'] ?? null;
            unset($searchConfigSettings['autosuggest'], $searchConfigSettings['q']['url'], $searchConfigSettings['q']['url_param_name'], $searchConfigSettings['q']['limit'], $searchConfigSettings['q']['fill_input']);
        }
        // Move a request settings to "q".
        $searchConfigSettings['q']['fulltext_search'] = $searchConfigSettings['request']['fulltext_search'] ?? null;
        unset($searchConfigSettings['request']['fulltext_search']);
        if (!empty($searchEngineSettings[$searchEngineId])) {
            // Append internal adapter settings to "q".
            $searchConfigSettings['q']['default_search_partial_word'] = !empty($searchEngineSettings[$searchEngineId]['adapter']['default_search_partial_word']);
            // Append internal adapter multi-fields to "index/aliases".
            $searchConfigSettings['index']['aliases'] = $searchEngineSettings[$searchEngineId]['adapter']['multifields'] ?? [];
        }
        // Renamed the option "sort".
        $searchConfigSettings['display']['label_sort'] = $searchConfigSettings['sort']['label'] ?? $searchConfigSettings['display']['label_sort'] ?? null;
        $searchConfigSettings['display']['sort_list'] = $searchConfigSettings['sort']['fields'] ?? $searchConfigSettings['display']['sort_list'] ?? [];
        unset($searchConfigSettings['sort']);
        // Add sort by relevance for internal engine.
        if (!empty($searchEngineSettings[$searchEngineId])) {
            $searchConfigSettings['display']['sort_list']['relevance desc'] ??= ['name' => 'relevance desc', 'label' => $translate('Relevance')];
        }
        // Set old options.
        $searchConfigSettings['display']['by_resource_type'] = true;
        $filters = [];
        foreach ($searchConfigSettings['form']['filters'] ?? [] as $key => $filter) {
            $field = $filter['field'];
            if (!$field) {
                // Normally not possible.
                continue;
            }

            $field = $filter['field'];
            $type = $filter['type'] ?? '';

            // Normally, there is only one advanced.
            if ($field === 'advanced' || $key === 'advanced' || mb_strtolower($type) === 'advanced') {
                // Key is always "advanced" for advanced filters, so no duplicate.
                $name = 'advanced';
                $type = 'Advanced';
                // Normalize some keys.
                $filter['default_number'] = isset($filter['default_number']) ? (int) $filter['default_number'] : 1;
                $filter['max_number'] = isset($filter['max_number']) ? (int) $filter['max_number'] : 10;
                $filter['field_joiner'] = isset($filter['field_joiner']) ? !empty($filter['field_joiner']) : true;
                $filter['field_joiner_not'] = isset($filter['field_joiner_not']) ? !empty($filter['field_joiner_not']) : true;
                $filter['field_operator'] = isset($filter['field_operator']) ? !empty($filter['field_operator']) : true;
                $filter['field_operators'] = isset($filter['field_operators']) ? (array) $filter['field_operators'] : [];
                // Move advanced fields as last key for end user.
                $filterFields = $filter['fields'] ?? [];
                unset($filter['fields']);
                $filter['fields'] = $filterFields;
            } else {
                // Normally, there is no name.
                // The key is numeric, except when the upgrade is done twice,
                $name = $filter['name'] ?? (is_numeric($key) ? $field : $key);
                $name = mb_strtolower(str_replace(['-', ':'], '_', $name));
                if (isset($filters[$name])) {
                    $name .= '_' . $key;
                }
                // Simplify Omeka types: they are now deducted from the field.
                if (mb_strtolower(mb_substr($type, 0, 5)) === 'omeka') {
                    $type = trim(substr($type, 5), '/');
                    // Force type for specific fields.
                    $cleanField = preg_replace('/[^a-z]+/u', '', strtolower($field));
                    if (substr($cleanField, 0, 12) === 'resourcename' || substr($cleanField, 0, 12) === 'resourcetype') {
                        $type = 'Select';
                    } elseif ((substr($cleanField, 0, 2) === 'id' || substr($cleanField, 0, 3) === 'oid')
                        && (substr($cleanField, 0, 10) !== 'identifier')
                    ) {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 8) === 'ispublic' || substr($cleanField, 0, 9) === 'oispublic') {
                        $type = 'Checkbox';
                    } elseif (substr($cleanField, 0, 5) === 'owner' || substr($cleanField, 0, 6) === 'oowner') {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 4) === 'site' || substr($cleanField, 0, 5) === 'osite') {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 13) === 'resourceclass' || substr($cleanField, 0, 14) === 'oresourceclass') {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 16) === 'resourcetemplate' || substr($cleanField, 0, 17) === 'oresourcetemplate') {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 7) === 'itemset' || substr($cleanField, 0, 8) === 'oitemset') {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 6) === 'access' || substr($cleanField, 0, 7) === 'oaccess') {
                        $type = 'Select';
                    } elseif (substr($cleanField, 0, 12) === 'itemsetstree' || substr($cleanField, 0, 13) === 'oitemsetstree') {
                        $type = 'Tree';
                    } elseif (substr($cleanField, 0, 9) === 'thesaurus' || substr($cleanField, 0, 10) === 'othesaurus') {
                        $type = 'Thesaurus';
                    } else {
                        $message = new PsrMessage(
                            'The search type "{type}" is no more managed and should be migrated manually in search config #{search_config_id}.', // @translate
                            ['type' => $filter['type'], 'search_config_id' => $id]
                        );
                        $messenger->addWarning($message);
                    }
                }
                // Renamed DateRange.
                if ($type === 'DateRange') {
                    $type = 'RangeDouble';
                    $filter['options']['first_digits'] = true;
                } elseif ($type === 'ItemSetsTree') {
                    $type = 'Tree';
                }
                // Manage specific options for filters.
                // Use the old process of MainSearchForm to get values options.
                if (array_key_exists('options', $filter)) {
                    if (strcasecmp($type, 'thesaurus') === 0) {
                        if (empty($filter['options']['id']) && empty($filter['options']['thesaurus'])) {
                            $filter['thesaurus'] = (int) reset($filter['options']);
                        } else {
                            $filter['thesaurus'] = (int) ($filter['options']['thesaurus'] ?? $filter['options']['id'] ?? 0);
                        }
                    } elseif (strcasecmp($type, 'checkbox') === 0 && count($filter['options']) === 2) {
                        $filter['unchecked_value'] = reset($filter['options']);
                        $filter['checked_value'] = end($filter['options']);
                    } elseif (strcasecmp($type, 'hidden') === 0) {
                        $filter['value'] = is_scalar($filter['options']) ? (string) $filter['options'] : reset($filter['options']);
                    } else {
                        if (is_string($filter['options'])) {
                            // TODO Explode may use another string than "|".
                            $filter['options'] = ['value_options' => array_filter(array_map('trim', explode('|', $filter['options'])), 'strlen')];
                        } elseif (!is_array($filter['options'])) {
                            $filter['options'] = ['value_options' => [(string) $filter['options'] => (string) $filter['options']]];
                        } else {
                            $filter['options']['value_options'] = $filter['options'];
                        }
                        // Avoid issue with duplicates.
                        // Check if options values contain only scalar first:
                        // issue may occur when the upgrade is done multiple
                        // times.
                        if ($filter['options']['value_options'] && !array_filter($filter['options']['value_options'], 'is_array')) {
                            $filter['options']['value_options'] = array_filter(array_keys(array_flip($filter['options']['value_options'])), 'strlen');
                        }
                    }
                }
            }
            unset($filter['options']);

            if (empty($type)) {
                unset($filter['type']);
            } else {
                $filter['type'] = $type;
            }
            unset($filter['name']);
            $filters[$name] = $filter;
        }

        // Manage advanced filters separately, except label, field and type.
        $advanced = $filters['advanced'] ?? [];
        if ($advanced) {
            $filters['advanced'] = [
                'field' => 'advanced',
                'label' => $advanced['label'] ?? '',
                'type' => 'Advanced',
            ];
            unset($advanced['field'], $advanced['label'], $advanced['type']);
        }

        $searchConfigSettings['form']['filters'] = $filters;
        $searchConfigSettings['form']['advanced'] = $advanced;

        foreach ($searchConfigSettings as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                unset($searchConfigSettings[$k]);
            } elseif (is_array($v)) {
                foreach ($v as $kk => $vv) {
                    if ($vv === null || $vv === '' || $vv === []) {
                        unset($searchConfigSettings[$k][$kk]);
                    }
                }
            }
        }

        $sql = 'UPDATE `search_config` SET `settings` = ? WHERE `id` = ?;';
        $connection->executeStatement($sql, [json_encode($searchConfigSettings, 320), $id]);
    }

    // Engines.
    $sql = <<<'SQL'
        UPDATE `search_engine`
        SET
            `settings` = REPLACE(`settings`, '"resources":', '"resource_types":')
        ;
        SQL;
    $connection->executeStatement($sql);

    // Suggest.
    $sql = 'UPDATE `search_suggester` SET `name` = ? WHERE `name` = ?;';
    $connection->executeStatement($sql, [$translate('Main index'), 'Internal suggester (sql)']);

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('advancedsearch_property_improved', true);
    }
    $settings->set('advancedsearch_property_improved', true);

    $message = new PsrMessage(
        'The form simple filters is now simpler to manage.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Each simple filter can have a specific input type instead of a html input text. When a specific type is set, it is automatically filled with values from the field.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Filters are now standard Omeka or Laminas html elements, so any options and attributes can be passed from the config.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The advanced filters was restructured and new options were added to manage most common needs.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The autosuggester is now auto-submitting the selected suggestion. An option was added to keep old behavior (fill the input and stay on form).' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The autosuggester can be enabled for any filter in advanced search form.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'it is now possible to display all resources (item sets, items, etc.) together in results or to separate them, like in previous versions.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'A new element was added to the standard advanced search for a thumbnail attached to a resource.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'A new element was added to the standard advanced search to filter properties. It allows to avoid to override the default element to search properties. An option is added in main settings and site settings to set them.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'You should check all your search engines: form, filters, results, sort, facets. It may be simpler to remove all your specific search files and to update only the css.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.32', '<')) {
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchEngineId = $searchConfigsEngines[$id] ?? null;
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        $facetMode = $searchConfigSettings['facet']['mode'] ?? '';
        if ($facetMode === 'link') {
            foreach ($searchConfigSettings['facet']['facets'] ?? [] as $key => $facet) {
                $facetType = $searchConfigSettings['facet']['facets'][$key]['type'] ?? '';
                if (in_array($facetType, ['', 'Checkbox'])) {
                    $searchConfigSettings['facet']['facets'][$key]['type'] = 'Link';
                } elseif (in_array($facetType, ['', 'Tree'])) {
                    $searchConfigSettings['facet']['facets'][$key]['type'] = 'TreeLink';
                } elseif (in_array($facetType, ['', 'Thesaurus'])) {
                    $searchConfigSettings['facet']['facets'][$key]['type'] = 'ThesaurusLink';
                }
                $searchConfigSettings['facet']['facets'][$key]['mode'] = 'link';
            }
            $sql = 'UPDATE `search_config` SET `settings` = ? WHERE `id` = ?;';
            $connection->executeStatement($sql, [json_encode($searchConfigSettings, 320), $id]);
        }
    }
}

if (version_compare($oldVersion, '3.4.33', '<')) {
    // Display item sets first in results when they are separated.
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_engine', 'search_engine')
        ->orderBy('id', 'asc');
    $searchEngineSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchEngineSettings as $id => $searchEngineSettings) {
        $searchEngineSettings = json_decode($searchEngineSettings, true) ?: [];
        $resourceTypes = $searchEngineSettings['resource_types'] ?? ['items'];
        if ($pos = array_search('item_sets', $resourceTypes)) {
            unset($resourceTypes[$pos]);
            array_unshift($searchEngineSettings['resource_types'], 'item_sets');
            $searchEngineSettings['resource_types'] = array_values(array_unique($searchEngineSettings['resource_types']));
            $sql = 'UPDATE `search_engine` SET `settings` = ? WHERE `id` = ?;';
            $connection->executeStatement($sql, [json_encode($searchEngineSettings, 320), $id]);
        }
    }
}

if (version_compare($oldVersion, '3.4.34', '<')) {
    $message = new PsrMessage(
        'It is now possible to configure a search engine to index only public resources.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.35', '<')) {
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        // The old default was "first", but the new one is "browse".
        $redirect = $siteSettings->get('advancedsearch_redirect_itemset', 'browse') ?: 'browse';
        if ($redirect === 'first') {
            $siteSettings->set('advancedsearch_redirect_itemset_browse', []);
            $siteSettings->set('advancedsearch_redirect_itemset_search', []);
            $siteSettings->set('advancedsearch_redirect_itemset_search_first', ['all']);
            $siteSettings->set('advancedsearch_redirect_itemset_page_url', []);
            $siteSettings->set('advancedsearch_redirect_itemsets', ['default' => 'first']);
        } elseif ($redirect === 'all') {
            $siteSettings->set('advancedsearch_redirect_itemset_browse', []);
            $siteSettings->set('advancedsearch_redirect_itemset_search', ['all']);
            $siteSettings->set('advancedsearch_redirect_itemset_search_first', []);
            $siteSettings->set('advancedsearch_redirect_itemset_page_url', []);
            $siteSettings->set('advancedsearch_redirect_itemsets', ['default' => 'search']);
        } else {
            $siteSettings->set('advancedsearch_redirect_itemset_browse', ['all']);
            $siteSettings->set('advancedsearch_redirect_itemset_search', []);
            $siteSettings->set('advancedsearch_redirect_itemset_search_first', []);
            $siteSettings->set('advancedsearch_redirect_itemset_page_url', []);
            $siteSettings->set('advancedsearch_redirect_itemsets', ['default' => 'browse']);
        }
        // The old option is not removed for now for compatibility with old themes (search, links).
        // $siteSettings->delete('advancedsearch_redirect_itemset');
    }

    $message = new PsrMessage(
        'It is now possible to configure the redirect to search for each item set separately in site settings.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Any item set can be redirected to the search page or any site page or url.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.36', '<')) {
    $strings = [
        'name="reset"',
        "->has('reset')",
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $result = $manageModuleAndResources->checkStringsInFiles($strings, 'themes/*/view/search/*');
    if ($result) {
        $message = new PsrMessage(
            'The form element "reset" was renamed "form-reset" to avoid issues with javascript. You should fix your theme first: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $logger->err($message->getMessage(), $message->getContext());
        // throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }

    $settings->delete('advancedsearch_configs');

    // Fixes navigation issues: make search configs available when they are used
    // in site navigation.
    // Adapted from \Omeka\Site\Navigation\Translator::ToZend().
    $listNavSearchConfigs = null;
    $listNavSearchConfigs = function (array $linksIn, array &$navSearchConfigs) use (&$listNavSearchConfigs) {
        foreach ($linksIn as $data) {
            if ($data['type'] === 'searchingPage') {
                $navSearchConfigs[] = $data['data']['advancedsearch_config_id'];
            }
            if (!empty($data['links'])) {
                $listNavSearchConfigs($data['links'], $navSearchConfigs);
            }
        }
        return $navSearchConfigs;
    };
    // Navigation is not a scalar field, so use full site representation.
    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $navSearchConfigs = [];
        $listNavSearchConfigs($site->navigation() ?: [], $navSearchConfigs);
        if ($navSearchConfigs) {
            $siteSettings->setTargetId($site->id());
            $searchConfigIdsForSite = $siteSettings->get('advancedsearch_configs', []);
            $searchConfigIdsForSite = array_unique(array_filter(array_map('intval', $searchConfigIdsForSite)));
            sort($searchConfigIdsForSite);
            $newSearchConfigIdsForSite = array_unique(array_filter(array_map('intval', array_merge($searchConfigIdsForSite, $navSearchConfigs))));
            sort($newSearchConfigIdsForSite);
            if ($searchConfigIdsForSite !== $newSearchConfigIdsForSite) {
                $siteSettings->set('advancedsearch_configs', $newSearchConfigIdsForSite);
                $message = new PsrMessage(
                    'Some search page configs were enabled for site "{site_slug}", because the navigation uses them.', // @translate
                    ['site_slug' => $site->slug()]
                );
                $messenger->addWarning($message);
            }
        }
    }
}

if (version_compare($oldVersion, '3.4.37', '<')) {
    $strings = [
        "etting('display',",
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $result = $manageModuleAndResources->checkStringsInFiles($strings, 'themes/*/view/search/*');
    if ($result) {
        $message = new PsrMessage(
            'The search config setting "display" was renamed "results". Check your theme. Matching templates: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $logger->err($message->getMessage(), $message->getContext());
        $messenger->addError($message);
    }

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('advancedsearch_metadata_improved', true);
        $siteSettings->set('advancedsearch_media_type_improved', true);
    }
    $settings->set('advancedsearch_metadata_improved', true);
    $settings->set('advancedsearch_media_type_improved', true);

    $listSearchFields = $localConfig['advancedsearch']['search_fields'] ?: [];
    $defaultSelectedSearchFieldsAdmin = [];
    $defaultSelectedSearchFieldsSite = [];
    foreach ($listSearchFields as $key => $searchField) {
        if (!array_key_exists('default_admin', $searchField) || $searchField['default_admin'] === true) {
            $defaultSelectedSearchFieldsAdmin[] = $key;
        }
        if (!array_key_exists('default_site', $searchField) || $searchField['default_site'] === true) {
            $defaultSelectedSearchFieldsSite[] = $key;
        }
    }
    $settings->set('advancedsearch_search_fields', $defaultSelectedSearchFieldsAdmin);
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $current = $siteSettings->get('advancedsearch_search_fields', $defaultSelectedSearchFieldsSite);
        $siteSettings->set('advancedsearch_search_fields', $current ?: $defaultSelectedSearchFieldsSite);
    }

    $message = new PsrMessage(
        'New site settings and main settings were added to manage standard search form or improved search elements.' // @translate
    );
    $messenger->addSuccess($message);

    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        if (!isset($searchConfigSettings['results'])) {
            $searchConfigSettings = ['results' => $searchConfigSettings['display'] ?? []] + $searchConfigSettings;
            unset($searchConfigSettings['display']);
        }
        if (!isset($searchConfigSettings['form']['default'])) {
            $searchConfigSettings = ['form' => ['default' => ['name' => 'default'] + ($searchConfigSettings['form'] ?? [])]] + $searchConfigSettings;
        }
        $sql = 'UPDATE `search_config` SET `settings` = ? WHERE `id` = ?;';
        $connection->executeStatement($sql, [json_encode($searchConfigSettings, 320), $id]);
    }

    // Engines.
    $sql = <<<'SQL'
        UPDATE `search_engine`
        SET
            `settings` = REPLACE(`settings`, '"adapter":', '"engine_adapter":')
        ;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.38', '<')) {
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        $searchConfigSettings['form']['rft'] = $searchConfigSettings['q']['fulltext_search']
            ?? $searchConfigSettings['form']['rft'] ?? null;
        unset($searchConfigSettings['q']['fulltext_search']);
        if (empty($searchConfigSettings['index']['aliases']['full_text'])) {
            $searchConfigSettings['index']['aliases']['full_text'] = [
                'name' => 'full_text',
                'label' => $translate('Full text'), // @translate
                'fields' => [
                    'bibo:content',
                    'extracttext:extracted_text',
                ],
            ];
        }
        $sql = 'UPDATE `search_config` SET `settings` = ? WHERE `id` = ?;';
        $connection->executeStatement($sql, [json_encode($searchConfigSettings, 320), $id]);
    }

    $message = new PsrMessage(
        'The option "Record or Full text" is now configurable with alias "full_text" to define properties with full text.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.39', '<')) {
    $settings->delete('advancedsearch_property_improved');
    $settings->delete('advancedsearch_resource_metadata_improved');
    $settings->delete('advancedsearch_media_type_improved');
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->delete('advancedsearch_property_improved');
        $siteSettings->delete('advancedsearch_resource_metadata_improved');
        $siteSettings->delete('advancedsearch_media_type_improved');
    }
}

if (version_compare($oldVersion, '3.4.40', '<')) {
    if (!$this->isModuleActive('Reference')) {
        $messenger->addWarning('The module Reference is required to use the facets with the default internal adapter, but not for the Solr adapter.'); // @translate
    } elseif (!$this->isModuleVersionAtLeast('Reference', '3.4.52')) {
        $messenger->addWarning(new PsrMessage(
            'The module {module} should be upgraded to version {version} or later.', // @translate
            ['module' => 'Reference', 'version' => '3.4.52']
        ));
    }
}

if (version_compare($oldVersion, '3.4.41', '<')) {
    // There may not be unique resource types in search configs (see previous upgrade 3.4.33).
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        $searchConfigSettings['resource_types'] = array_values(array_unique($searchConfigSettings['resource_types'] ?? []));
        $sql = 'UPDATE `search_config` SET `settings` = ? WHERE `id` = ?;';
        $connection->executeStatement($sql, [json_encode($searchConfigSettings, 320), $id]);
    }
}

if (version_compare($oldVersion, '3.4.42', '<')) {
    $message = new PsrMessage(
        'It is now possible to limit filters and facets to the language of the site.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.43', '<')) {
    // Fix item set pages.
    $this->finalizeSiteSettings();

    $settings->delete('advancedsearch_index_batch_edit');

    $message = new PsrMessage(
        'Json-ld data are appended to results when enabled. You may need to add triggers if the theme is customized.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.45', '<')) {
    $qb = $connection->createQueryBuilder();
    $qb
        ->select('id', 'settings')
        ->from('search_config', 'search_config')
        ->orderBy('id', 'asc');
    $searchConfigsSettings = $connection->executeQuery($qb)->fetchAllKeyValue();
    foreach ($searchConfigsSettings as $id => $searchConfigSettings) {
        $searchConfigSettings = json_decode($searchConfigSettings, true) ?: [];
        $k = 0;
        $filters = [];
        foreach ($searchConfigSettings['form']['filters'] ?? [] as $filter) {
            $field = $filter['field'];
            if (isset($filters[$field])) {
                $field = $field . '_' . ++$k;
            }
            $filters[$field] = $filter;
        }
        $searchConfigSettings['form']['filters'] = $filters;
        $k = 0;
        $facets = [];
        foreach ($searchConfigSettings['facet']['facets'] ?? [] as $facet) {
            $field = $facet['field'];
            if (isset($facets[$field])) {
                $field = $field . '_' . ++$k;
            }
            $facets[$field] = $facet;
        }
        $searchConfigSettings['facet']['facets'] = $facets;
        $sql = 'UPDATE `search_config` SET `settings` = ? WHERE `id` = ?;';
        $connection->executeStatement($sql, [json_encode($searchConfigSettings, 320), $id]);
    }
}
