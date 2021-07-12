<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2021
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Search;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Entity\Resource;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Search\Indexer\IndexerInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    /**
     * @var bool
     */
    protected $isBatchUpdate;

    public function init(ModuleManager $moduleManager): void
    {
        /** @var \Laminas\ModuleManager\Listener\ServiceListenerInterface $serviceListerner */
        $serviceListener = $moduleManager->getEvent()->getParam('ServiceManager')
            ->get('ServiceListener');

        $serviceListener->addServiceManager(
            'Search\AdapterManager',
            'search_adapters',
            Feature\AdapterProviderInterface::class,
            'getSearchAdapterConfig'
        );
        $serviceListener->addServiceManager(
            'Search\FormAdapterManager',
            'search_form_adapters',
            Feature\FormAdapterProviderInterface::class,
            'getSearchFormAdapterConfig'
        );
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addAclRules();
        $this->addRoutes();
    }

    protected function postInstall(): void
    {
        $messenger = new Messenger;
        $optionalModule = 'Reference';
        if (!$this->isModuleActive($optionalModule)) {
            $messenger->addWarning('The module Reference is required to use the facets with the default internal adapter, but not for the Solr adapter.'); // @translate
        }

        $this->installResources();
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addHeaders']
        );

        // Listeners for the indexing for items, item sets and media.

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'updateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'updateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchIndex']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.create.post',
            [$this, 'updateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.update.post',
            [$this, 'updateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchIndex']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.post',
            [$this, 'updateSearchIndexMedia']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.delete.pre',
            [$this, 'preUpdateSearchIndexMedia']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchIndexMedia']
        );

        // Listeners for sites.

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'addSearchPageToSite']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }

    protected function addAclRules(): void
    {
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            // This first rule duplicates the second, but is needed for a site.
            ->allow(
                null,
                [
                    \Search\Controller\IndexController::class,
                    \Search\Api\Adapter\SearchPageAdapter::class,
                    \Search\Api\Adapter\SearchIndexAdapter::class,
                ],
                ['read', 'search']
            )
            ->allow(
                null,
                [
                    \Search\Controller\IndexController::class,
                    \Search\Api\Adapter\SearchPageAdapter::class,
                    \Search\Api\Adapter\SearchIndexAdapter::class,
                ]
            )
            ->allow(
                null,
                [
                    \Search\Entity\SearchPage::class,
                    \Search\Entity\SearchIndex::class,
                ],
                ['read']
            );
    }

    protected function addRoutes(): void
    {
        $services = $this->getServiceLocator();

        /** @var \Omeka\Mvc\Status $status */
        $status = $services->get('Omeka\Status');
        if ($status->isApiRequest()) {
            return;
        }

        $router = $services->get('Router');
        if (!$router instanceof \Laminas\Router\Http\TreeRouteStack) {
            return;
        }

        $api = $services->get('Omeka\ApiManager');
        /** @var \Search\Api\Representation\SearchPageRepresentation[] $searchPages */
        $searchPages = $api->search('search_pages')->getContent();

        $isAdminRequest = $status->isAdminRequest();
        if ($isAdminRequest) {
            $settings = $services->get('Omeka\Settings');
            $adminSearchPages = $settings->get('search_pages', []);
            foreach ($searchPages as $searchPage) {
                $searchPageId = $searchPage->id();
                if (in_array($searchPageId, $adminSearchPages)) {
                    $router->addRoute(
                        'search-admin-page-' . $searchPageId,
                        [
                            'type' => \Laminas\Router\Http\Segment::class,
                            'options' => [
                                'route' => '/admin/' . $searchPage->path(),
                                'defaults' => [
                                    '__NAMESPACE__' => 'Search\Controller',
                                    '__ADMIN__' => true,
                                    'controller' => \Search\Controller\IndexController::class,
                                    'action' => 'search',
                                    'id' => $searchPageId,
                                ],
                            ],
                            'may_terminate' => true,
                            'child_routes' => [
                                'suggest' => [
                                    'type' => \Laminas\Router\Http\Literal::class,
                                    'options' => [
                                        'route' => '/suggest',
                                        'defaults' => [
                                            '__NAMESPACE__' => 'Search\Controller',
                                            '__ADMIN__' => true,
                                            'controller' => \Search\Controller\IndexController::class,
                                            'action' => 'suggest',
                                            'id' => $searchPageId,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    );
                }
            }
        }

        // Public search pages are required to manage them at site level.
        foreach ($searchPages as $searchPage) {
            $searchPageId = $searchPage->id();
            $searchPageSlug = $searchPage->path();
            $router->addRoute(
                'search-page-' . $searchPageId,
                [
                    'type' => \Laminas\Router\Http\Segment::class,
                    'options' => [
                        'route' => '/s/:site-slug/' . $searchPageSlug,
                        'defaults' => [
                            '__NAMESPACE__' => 'Search\Controller',
                            '__SITE__' => true,
                            'controller' => \Search\Controller\IndexController::class,
                            'action' => 'search',
                            'id' => $searchPageId,
                            // Store the page slug to simplify checks.
                            'page-slug' => $searchPageSlug,
                        ],
                    ],
                    'may_terminate' => true,
                    'child_routes' => [
                        'suggest' => [
                            'type' => \Laminas\Router\Http\Literal::class,
                            'options' => [
                                'route' => '/suggest',
                                'defaults' => [
                                    '__NAMESPACE__' => 'Search\Controller',
                                    '__SITE__' => true,
                                    'controller' => \Search\Controller\IndexController::class,
                                    'action' => 'suggest',
                                    'id' => $searchPageId,
                                    // Store the page slug to simplify checks.
                                    'page-slug' => $searchPageSlug,
                                ],
                            ],
                        ],
                    ],
                ],
            );
        }
    }

    /**
     * Prepare a batch update to process it one time only for performance.
     *
     * This process avoids a bug too.
     * When there is a batch update, with modules SearchSolr and NumericDataTypes,
     * a bug occurs on the second call to update when the process is done in
     * admin ui via batch edit selected resources and when one of the selected
     * resources has a resource template: a resource template property is
     * created, but it must not exist, since the event is not related to the
     * resource templates (only read them). The issue occurs when SearchSolr
     * tries to read values from the representation (item values extraction),
     * but only when the module NumericDataTypes is used. The new ResourceTemplateProperty
     * is visible via the method \Omeka\Api\Adapter\AbstractEntityAdapter::detachAllNewEntities()
     * after the first update.
     * This issue doesn't occurs in background batch edit all (see \Omeka\Controller\Admin\itemController::batchEditAllAction()
     * and \Omeka\Job\BatchUpdate::perform()). But, conversely, when this option
     * is set, it doesn't work any more for a background process, so a check is
     * done to check if this is a background event.
     * @todo Find where the resource template property is created. This issue may disappear de facto in a future version.
     *
     * @todo Clean the process with the fix in Omeka 3.1.
     *
     * @param Event $event
     */
    public function preBatchUpdateSearchIndex(Event $event): void
    {
        // This is a background job if there is no route match.
        $routeMatch = $this->getServiceLocator()->get('application')->getMvcEvent()->getRouteMatch();
        $this->isBatchUpdate = !empty($routeMatch);
    }

    public function postBatchUpdateSearchIndex(Event $event): void
    {
        if (!$this->isBatchUpdate) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $requestResource = $request->getResource();
        $response = $event->getParam('response');
        $resources = $response->getContent();

        /** @var \Search\Api\Representation\SearchIndexRepresentation[] $searchIndexes */
        $searchIndexes = $api->search('search_indexes')->getContent();
        foreach ($searchIndexes as $searchIndex) {
            if (in_array($requestResource, $searchIndex->setting('resources', []))) {
                $indexer = $searchIndex->indexer();
                try {
                    $indexer->indexResources($resources);
                } catch (\Exception $e) {
                    $services = $this->getServiceLocator();
                    $logger = $services->get('Omeka\Logger');
                    $logger->err(new Message(
                        'Unable to batch index metadata for search index "%s": %s', // @translate
                        $searchIndex->name(), $e->getMessage()
                    ));
                    $messenger = $services->get('ControllerPluginManager')->get('messenger');
                    $messenger->addWarning(new Message(
                        'Unable to batch update the search index "%s": see log.', // @translate
                        $searchIndex->name()
                    ));
                }
            }
        }

        $this->isBatchUpdate = false;
    }

    public function preUpdateSearchIndexMedia(Event $event): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $request = $event->getParam('request');
        $media = $api->read('media', $request->getId())->getContent();
        $data = $request->getContent();
        $data['itemId'] = $media->item()->id();
        $request->setContent($data);
    }

    public function updateSearchIndex(Event $event): void
    {
        if ($this->isBatchUpdate) {
            return;
        }
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $requestResource = $request->getResource();

        /** @var \Search\Api\Representation\SearchIndexRepresentation[] $searchIndexes */
        $searchIndexes = $api->search('search_indexes')->getContent();
        foreach ($searchIndexes as $searchIndex) {
            if (in_array($requestResource, $searchIndex->setting('resources', []))) {
                $indexer = $searchIndex->indexer();
                if ($request->getOperation() == 'delete') {
                    $id = $request->getId();
                    $this->deleteIndexResource($indexer, $requestResource, $id);
                } else {
                    $resource = $response->getContent();
                    $this->updateIndexResource($indexer, $resource);
                }
            }
        }
    }

    public function updateSearchIndexMedia(Event $event): void
    {
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $itemId = $request->getValue('itemId');
        $item = $itemId
            ? $api->read('items', $itemId, [], ['responseContent' => 'resource'])->getContent()
            : $response->getContent()->getItem();

        /** @var \Search\Api\Representation\SearchIndexRepresentation[] $searchIndexes */
        $searchIndexes = $api->search('search_indexes')->getContent();
        foreach ($searchIndexes as $searchIndex) {
            if (in_array('items', $searchIndex->setting('resources', []))) {
                $indexer = $searchIndex->indexer();
                $this->updateIndexResource($indexer, $item);
            }
        }
    }

    /**
     * Delete the search index for a resource.
     *
     * @param IndexerInterface $indexer
     * @param string $resourceName
     * @param int $id
     */
    protected function deleteIndexResource(IndexerInterface $indexer, $resourceName, $id): void
    {
        try {
            $indexer->deleteResource($resourceName, $id);
        } catch (\Exception $e) {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $logger->err(new Message(
                'Unable to delete the search index for resource #%d: %s', // @translate
                $id, $e->getMessage()
            ));
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(new Message(
                'Unable to delete the search index for the deleted resource #%d: see log.', // @translate
                $id
            ));
        }
    }

    /**
     * Update the search index for a resource.
     *
     * @param IndexerInterface $indexer
     * @param Resource $resource
     */
    protected function updateIndexResource(IndexerInterface $indexer, Resource $resource): void
    {
        try {
            $indexer->indexResource($resource);
        } catch (\Exception $e) {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $logger->err(new Message(
                'Unable to index metadata of resource #%d for search: %s', // @translate
                $resource->getId(), $e->getMessage()
            ));
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(new Message(
                'Unable to update the search index for resource #%d: see log.', // @translate
                $resource->getId()
            ));
        }
    }

    public function handleSiteSettings(Event $event): void
    {
        // This is an exception, because there is already a fieldset named
        // "search" in the core, so it should be named "search_module".

        $services = $this->getServiceLocator();
        $settingsType = 'site_settings';
        $settings = $services->get('Omeka\Settings\Site');

        $site = $services->get('ControllerPluginManager')->get('currentSite');
        $id = $site()->id();

        $this->initDataToPopulate($settings, $settingsType, $id);

        $data = $this->prepareDataToPopulate($settings, $settingsType);
        if (is_null($data)) {
            return;
        }

        $space = 'search_module';

        $fieldset = $services->get('FormElementManager')->get(\Search\Form\SiteSettingsFieldset::class);
        $fieldset->setName($space);
        $form = $event->getTarget();
        $form->add($fieldset);
        $form->get($space)->populateValues($data);
    }

    /**
     * Add the headers.
     *
     * @param Event $event
     */
    public function addHeaders(Event $event): void
    {
        // The admin search field is added via a js hack, because the admin
        // layout doesn't use a partial or a trigger for the sidebar.

        $view = $event->getTarget();

        $plugins = $view->getHelperPluginManager();
        /** @var \Omeka\Mvc\Status $status */
        $status = $plugins->get('status');
        if ($status->isSiteRequest()) {
            $params = $view->params()->fromRoute();
            if ($params['controller'] === \Search\Controller\IndexController::class) {
                $searchPage = @$params['id'];
            } else {
                $searchPage = $view->siteSetting('search_main_page');
            }
        } elseif ($status->isAdminRequest()) {
            $searchPage = $view->setting('search_main_page');
        } else {
            return;
        }

        if (!$searchPage) {
            return;
        }

        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $plugins->get('api')->searchOne('search_pages', ['id' => $searchPage])->getContent();
        if (!$searchPage) {
            return;
        }

        $formAdapter = $searchPage->formAdapter();
        $partialHeaders = $formAdapter ? $formAdapter->getFormPartialHeaders() : null;

        if ($status->isAdminRequest()) {
            $basePath = $plugins->get('basePath');
            $assetUrl = $plugins->get('assetUrl');
            $searchUrl = $basePath('admin/' . $searchPage->path());
            if ($searchPage->subSetting('autosuggest', 'enable')) {
                $autoSuggestUrl = $searchPage->subSetting('autosuggest', 'url') ?: $searchUrl . '/suggest';
            }
            $plugins->get('headLink')
                ->appendStylesheet($assetUrl('css/search-admin-search.css', 'Search'));
            $plugins->get('headScript')
                ->appendScript(sprintf('var searchUrl = %s;', json_encode($searchUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                   . (isset($autoSuggestUrl) ? sprintf("\nvar searchAutosuggestUrl=%s;", json_encode($autoSuggestUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : '')
                )
                ->appendFile($assetUrl('js/search-admin-search.js', 'Search'), 'text/javascript', ['defer' => 'defer']);
        }

        if (!$partialHeaders) {
            return;
        }

        // No echo: it should just be a preload.
        $view->vars()->offsetSet('searchPage', $searchPage);
        $view->partial($partialHeaders);
    }

    public function addSearchPageToSite(Event $event): void
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         *
         * @var \Omeka\Api\Representation\SiteRepresentation $site
         * @var \Search\Api\Representation\SearchPageRepresentation $searchPage
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $api = $services->get('ControllerPluginManager')->get('api');
        $site = null;
        $searchPage = null;

        // Take the search page of the default site or the first site, else the
        // default search page.
        $defaultSite = (int) $settings->get('default_site');
        if ($defaultSite) {
            $site = $api->searchOne('sites', ['id' => $defaultSite])->getContent();
        }
        if ($site) {
            $siteSettings->setTargetId($site->id());
            $searchPageId = (int) $siteSettings->get('search_main_page');
        } else {
            $searchPageId = (int) $settings->get('search_main_page');
        }
        if ($searchPageId) {
            $searchPage = $api->searchOne('search_pages', ['id' => $searchPageId])->getContent();
        }
        if (!$searchPage) {
            $searchPage = $api->searchOne('search_pages')->getContent();
        }
        if (!$searchPage) {
            $searchPageId = $this->createDefaultSearchPage();
            $searchPage = $api->searchOne('search_pages', ['id' => $searchPageId])->getContent();
        }

        /** @var \Omeka\Entity\Site $site */
        $site = $event->getParam('response')->getContent();

        $siteSettings->setTargetId($site->getId());
        $siteSettings->set('search_main_page', $searchPage->id());
        $siteSettings->set('search_pages', [$searchPage->id()]);
        $siteSettings->set('search_redirect_itemset', true);
    }

    protected function installResources(): void
    {
        $this->createDefaultSearchPage();
    }

    protected function createDefaultSearchPage(): int
    {
        // Note: during installation or upgrade, the api may not be available
        // for the search api adapters, so use direct sql queries.

        $services = $this->getServiceLocator();

        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $messenger = new Messenger;

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        // Check if the internal index exists.
        $sqlSearchIndexId = <<<'SQL'
SELECT `id`
FROM `search_index`
WHERE `adapter` = "internal"
ORDER BY `id`;
SQL;
        $searchIndexId = (int) $connection->fetchColumn($sqlSearchIndexId);

        if (!$searchIndexId) {
            // Create the internal adapter.
            $sql = <<<'SQL'
INSERT INTO `search_index`
(`name`, `adapter`, `settings`, `created`)
VALUES
('Internal (sql)', 'internal', ?, NOW());
SQL;
            $searchIndexSettings = ['resources' => ['items', 'item_sets']];
            $connection->executeQuery($sql, [
                json_encode($searchIndexSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $searchIndexId = $connection->fetchColumn($sqlSearchIndexId);
            $message = new Message(
                'The internal search engine (sql) is available. Configure it in the %ssearch manager%s.', // @translate
                sprintf('<a href="%s">', $urlHelper('admin/search')),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        // Check if the default search page exists.
        $sqlSearchPageId = <<<SQL
SELECT `id`
FROM `search_page`
WHERE `index_id` = $searchIndexId
ORDER BY `id`;
SQL;
        $searchPageId = (int) $connection->fetchColumn($sqlSearchPageId);

        if (!$searchPageId) {
            $sql = <<<SQL
INSERT INTO `search_page`
(`index_id`, `name`, `path`, `form_adapter`, `settings`, `created`)
VALUES
($searchIndexId, 'Default', 'find', 'main', ?, NOW());
SQL;
            $searchPageSettings = require __DIR__ . '/data//search_pages/main.php';
            $connection->executeQuery($sql, [
                json_encode($searchPageSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $message = new Message(
                'The default search page is available. Configure it in the %ssearch manager%s, in the main settings (for admin) and in site settings (for public).', // @translate
                sprintf('<a href="%s">', $urlHelper('admin/search')),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        return $searchPageId;
    }
}
