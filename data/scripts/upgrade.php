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
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$config = $services->get('Config');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.61')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.61'
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
            $results[trim(basename($path), '*')] = $result;
        }
    }
    if ($results) {
        $message = new PsrMessage(
            'The variables "$heading", "$html" and "$cssClass" were removed from block Searching Form. Fix them in the following files before upgrading: {json}', // @translate
            ['json' => json_encode($results, 448)]
        );
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }

    $logger = $services->get('Omeka\Logger');
    $pageRepository = $entityManager->getRepository(\Omeka\Entity\SitePage::class);
    $blocksRepository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);

    $viewHelpers = $services->get('ViewHelperManager');
    $escape = $viewHelpers->get('escapeHtml');
    $hasBlockPlus = $this->isModuleActive('BlockPlus');

    $pagesUpdated = [];
    $pagesUpdated2 = [];
    foreach ($pageRepository->findAll() as $page) {
        $pageId = $page->getId();
        $pageSlug = $page->getSlug();
        $siteSlug = $page->getSite()->getSlug();
        $position = 0;
        foreach ($page->getBlocks() as $block) {
            $block->setPosition(++$position);
            $layout = $block->getLayout();
            if ($layout !== 'searchingForm') {
                continue;
            }
            $blockId = $block->getId();
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
            if ($templateName && $templateName !== 'searching-form' && (!$existingTemplateName || $existingTemplateName === 'searching-form')) {
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
            'The template files for the block Searching Form should be moved from "view/common/block-layout" to "view/common/block-template" in your themes. This process can be done automatically via a task of the module Easy Admin before upgrading the module (important: backup your themes first). You may check your themes for pages: {json}', // @translate
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
    $sql = <<<SQL
UPDATE `search_config`
SET
    `settings` = REPLACE(`settings`, '"display":{', '"display":{"facets":"after",')
;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.25', '<')) {
    $siteSettings = $services->get('Omeka\Settings\Site');
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
    $sql = <<<SQL
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
    $sql = <<<SQL
UPDATE `search_config`
SET
    `settings` = REPLACE(`settings`, '"max_number":', '"default_number":1,"max_number":')
;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.28', '<')) {
    $logger = $services->get('Omeka\Logger');
    $doUpgrade = !empty($config['advancedsearch_upgrade_3.4.28']);
    $stringsAndMessages = [
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
            'script' => [
                'rename' => <<<'SH'
                    find 'OMEKA_PATH/themes/' -type f -not -path '*/\.git/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' -wholename '*/view/search/facets.phtml' -exec rename -v 's~facets.phtml~facets-list.phtml~' '{}' \;
                    SH,
                'replace' => <<<'SH'
                    find 'OMEKA_PATH/themes/' -type f -not -path '*/\.git/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' -wholename '*/view/search/*.phtml' -exec sed -i "s~search/facets'~search/facets-list'~g" '{}' \;
                    SH,
            ],
        ],
        'resource-list' => [
            'strings' => [
                'themes/*/view/search/*' => [
                    "search/resource-list'",
                ],
            ],
            'message' => 'The template "search/resource-list" was renamed "search/results". Update your theme. Matching templates: {json}', // @translate
            'script' => [
                'rename' => <<<'SH'
                    find 'OMEKA_PATH/themes/' -type f -not -path '*/\.git/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' -wholename '*/view/search/resource-list.phtml' -exec rename -v 's~resource-list.phtml~results.phtml~' '{}' \;
                    SH,
                'replace' => <<<'SH'
                    find 'OMEKA_PATH/themes/' -type f -not -path '*/\.git/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' -wholename '*/view/search/*.phtml' -exec sed -i "s~search/resource-list'~search/results'~g" '{}' \;
                    SH,
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
            'script' => [
                'replace' => <<<'SH'
                    find 'OMEKA_PATH/themes/' -type f -not -path '*/\.git/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' -wholename '*/view/search/*.phtml' -exec sed -i "s~'per_pages'~'per_page'~g" '{}' \;
                    SH,
            ],
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($stringsAndMessages as $key => $stringsAndMessage) foreach ($stringsAndMessage['strings'] as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if (!$result) {
            continue;
        } elseif (!$doUpgrade) {
            $results[$key][trim(basename($path), '*')] = $result;
            continue;
        }
        if (!empty($stringsAndMessage['script'])) {
            /** @var \Omeka\Stdlib\Cli $cli */
            $cli = $services->get('Omeka\Cli');
            $total = count($stringsAndMessage['script']);
            $i = 0;
            foreach ($stringsAndMessage['script'] as $commandName => $command) {
                $command = str_replace('OMEKA_PATH', OMEKA_PATH, $command);
                $output = $cli->execute($command);
                // Errors are already logged only with proc_open(), not exec().
                if ($output === false) {
                    $message = new PsrMessage(
                        'Command "{name}" #{index}/{total} cannot be executed for {key}.', // @translate
                        ['name' => $commandName, 'index' => ++$i, 'total' => $total, 'key' => $key]
                    );
                    $messenger->addError($message);
                    $logger->err($message->getMessage(), $message->getContext());
                } elseif ($output) {
                    $message = new PsrMessage(
                        'Command "{name}" #{index}/{total} executed for {key}. Output: {output}', // @translate
                        ['name' => $commandName, 'index' => ++$i, 'total' => $total, 'key' => $key, 'output' => $output]
                    );
                    $messenger->addNotice($message);
                    $logger->notice($message->getMessage(), $message->getContext());
                } else {
                    $message = new PsrMessage(
                        'Command "{name}" #{index}/{total} executed for {key}.', // @translate
                        ['name' => $commandName, 'index' => ++$i, 'total' => $total, 'key' => $key]
                    );
                    $messenger->addNotice($message);
                    $logger->notice($message->getMessage(), $message->getContext());
                }
            }
        }
    }
    if ($results) {
        $messages = [];
        if (empty($config['advancedsearch_skip_exception'])) {
            $message = new PsrMessage(
                'The module may break the theme to manage new features, in particular for facets.
To avoid this check, add temporarily the key "advancedsearch_skip_exception" with value "true" in the file config/local.config.php.
To process **some** of them automatically, you should backup themes and files, then add temporarily the key "advancedsearch_upgrade_3.4.28" with value "true" in the file config/local.config.php.
The list of issues is available in logs too.' // @translate
            );
            $messages[] = $message;
        }
        foreach ($results as $key => $result) {
            $message = new PsrMessage($stringsAndMessages[$key]['message'], ['json' => json_encode($result, 448)]);
            $logger->err($message->getMessage(), $message->getContext());
            $messages[] = $message;
        }
        if (empty($config['advancedsearch_skip_exception'])) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(implode("\n\n", array_map(fn($message) => (string) $message->setTranslator($translator), $messages)));
        }
        $messenger->addErrors($messages);
        $message = new PsrMessage(
            'The key "{key}" can be removed from the file config/local.config.php.', // @translate
            ['key' => 'advancedsearch_skip_exception']
        );
        $messenger->addNotice($message);
        if (array_key_exists('advancedsearch_upgrade_3.4.28', $config)) {
            $message = new PsrMessage(
                'The key "{key}" can be removed from the file config/local.config.php.', // @translate
                ['key' => 'advancedsearch_upgrade_3.4.28']
            );
            $messenger->addNotice($message);
        }
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
            $results[$key][trim(basename($path), '*')] = $result;
        }
    }
    if ($results) {
        foreach ($results as $key => $result) {
            $message = new PsrMessage($stringsAndMessages[$key]['message'], ['json' => json_encode($result, 448)]);
            $logger->warn($message->getMessage(), $message->getContext());
            $messenger->addWarning($message);
        }
    }
}
