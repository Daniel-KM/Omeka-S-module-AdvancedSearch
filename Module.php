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
        /** @var \Search\Api\Representation\SearchPageRepresentation[] $api */
        $pages = $api->search('search_pages')->getContent();

        $isAdminRequest = $status->isAdminRequest();
        if ($isAdminRequest) {
            $settings = $services->get('Omeka\Settings');
            $adminSearchPages = $settings->get('search_pages', []);
            foreach ($pages as $page) {
                $pageId = $page->id();
                if (in_array($pageId, $adminSearchPages)) {
                    $router->addRoute(
                        'search-admin-page-' . $pageId,
                        [
                            'type' => \Laminas\Router\Http\Segment::class,
                            'options' => [
                                'route' => '/admin/' . $page->path(),
                                'defaults' => [
                                    '__NAMESPACE__' => 'Search\Controller',
                                    '__ADMIN__' => true,
                                    'controller' => \Search\Controller\IndexController::class,
                                    'action' => 'search',
                                    'id' => $pageId,
                                ],
                            ],
                        ]
                    );
                }
            }
        }

        // Public search pages are required to manage them at site level.
        foreach ($pages as $page) {
            $pageId = $page->id();
            $pageSlug = $page->path();
            $router->addRoute(
                'search-page-' . $pageId,
                [
                    'type' => \Laminas\Router\Http\Segment::class,
                    'options' => [
                        'route' => '/s/:site-slug/' . $pageSlug,
                        'defaults' => [
                            '__NAMESPACE__' => 'Search\Controller',
                            '__SITE__' => true,
                            'controller' => \Search\Controller\IndexController::class,
                            'action' => 'search',
                            'id' => $pageId,
                            // Store the page slug to simplify checks.
                            'page-slug' => $pageSlug,
                        ],
                    ],
                ]
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

        /** @var \Omeka\Mvc\Status $status */
        $status = $this->getServiceLocator()->get('Omeka\Status');
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
        $searchPage = $view->api()->searchOne('search_pages', ['id' => $searchPage])->getContent();
        if (!$searchPage) {
            return;
        }

        if ($status->isAdminRequest()) {
            $basePath = $view->plugin('basePath');
            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('css/search-admin-search.css', 'Search'));
            $view->headScript()
                ->appendScript(sprintf('var searchUrl = %s;', json_encode($basePath('admin/' . $searchPage->path()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))
                ->appendFile($assetUrl('js/search-admin-search.js', 'Search'), 'text/javascript', ['defer' => 'defer']);
        }

        $formAdapter = $searchPage->formAdapter();
        if (!$formAdapter) {
            return;
        }

        $partialHeaders = $formAdapter->getFormPartialHeaders();
        if (!$partialHeaders) {
            return;
        }

        // No echo: it should just be a preload.
        $view->partial($partialHeaders);
    }

    protected function installResources(): void
    {
        $services = $this->getServiceLocator();

        // TODO Move internal adapter in another module.
        // Create the internal adapter.
        $connection = $services->get('Omeka\Connection');
        $sql = <<<'SQL'
INSERT INTO `search_index`
(`name`, `adapter`, `settings`, `created`)
VALUES
('Internal', 'internal', ?, NOW());
SQL;
        $searchIndexSettings = ['resources' => ['items', 'item_sets']];
        $connection->executeQuery($sql, [
            json_encode($searchIndexSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $sql = <<<'SQL'
INSERT INTO `search_page`
(`index_id`, `name`, `path`, `form_adapter`, `settings`, `created`)
VALUES
('1', 'Internal', 'find', 'basic', ?, NOW());
SQL;
        $searchPageSettings = require __DIR__ . '/data//adapters/internal.php';
        $connection->executeQuery($sql, [
            json_encode($searchPageSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $messenger = new Messenger;
        $messenger->addNotice('The internal search engine is available. Enable it in the main settings (for admin) and in site settings (for public).'); // @translate
    }
}
