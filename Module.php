<?php declare(strict_types=1);

/**
 * Advanced Search
 *
 * Improve search with new fields, auto-suggest, filters, facets, specific pages, etc.
 *
 * @copyright BibLibre, 2016-2017
 * @copyright Daniel Berthereau, 2017-2022
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
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
namespace AdvancedSearch;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use AdvancedSearch\Indexer\IndexerInterface;
use AdvancedSearch\Mvc\Controller\Plugin\SearchResources;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Entity\Resource;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

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

        // Keep old name for compatibility with other modules.
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

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $moduleManager = $services->get('Omeka\ModuleManager');

        // Check upgrade from old module Search if any.
        $module = $moduleManager->getModule('Search');
        if (!$module || in_array($module->getState(), [
            \Omeka\Module\Manager::STATE_NOT_INSTALLED,
            \Omeka\Module\Manager::STATE_NOT_FOUND ,
            \Omeka\Module\Manager::STATE_INVALID_MODULE,
            \Omeka\Module\Manager::STATE_INVALID_INI ,
            \Omeka\Module\Manager::STATE_INVALID_OMEKA_VERSION,
        ])) {
            return;
        }

        $version = (string) $module->getIni('version');
        if (version_compare($version, '3.5.7', '<')) {
            // Check the module Search of BibLibre.
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                'Compatibility of this module with module "Search" of BibLibre has not been checked. Uninstall it first, or upgrade it with its fork at https://gitlab.com/Daniel-KM/Omeka-S-module-Search.' // @translate
            );
        }
        if (version_compare($version, '3.5.23.3', '<')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                'To be automatically upgraded and replaced by this module, the module "Search" should be updated first to version 3.5.23.3 or greater.' // @translate
            );
        }

        $module = $moduleManager->getModule('AdvancedSearch');
        $version = $module ? (string) $module->getIni('version') : null;
        if (version_compare($version, '3.3.6.6', '>')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                'To be automatically upgraded and replaced by this module, use version 3.3.6.6 or below.' // @translate
            );
        }
    }

    protected function postInstall(): void
    {
        $messenger = new Messenger;
        $optionalModule = 'Reference';
        if (!$this->isModuleActive($optionalModule)) {
            $messenger->addWarning('The module Reference is required to use the facets with the default internal adapter, but not for the Solr adapter.'); // @translate
        }

        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');

        // Upgrade from old modules AdvancedSearchPlus and Search.

        $module = $moduleManager->getModule('AdvancedSearch');
        $version = $module ? $module->getIni('version') : null;
        if (version_compare($version, '3.3.6.6', '<=')) {
            $module = $moduleManager->getModule('AdvancedSearchPlus');
            if ($module && in_array($module->getState(), [
                \Omeka\Module\Manager::STATE_ACTIVE,
                \Omeka\Module\Manager::STATE_NOT_ACTIVE,
                \Omeka\Module\Manager::STATE_NEEDS_UPGRADE,
            ])) {
                try {
                    $filepath = $this->modulePath() . '/data/scripts/upgrade_from_advancedsearchplus.php';
                    require_once $filepath;
                } catch (\Exception $e) {
                    $message = new Message(
                        'An error occurred during migration of module "%s". Check the config and uninstall it manually.', // @translate
                        'AdvancedSearchPlus'
                    );
                    $messenger->addError($message);
                }
            }

            $module = $moduleManager->getModule('Search');
            if ($module && in_array($module->getState(), [
                \Omeka\Module\Manager::STATE_ACTIVE,
                \Omeka\Module\Manager::STATE_NOT_ACTIVE,
                \Omeka\Module\Manager::STATE_NEEDS_UPGRADE,
            ])) {
                try {
                    $filepath = $this->modulePath() . '/data/scripts/upgrade_from_search.php';
                    require_once $filepath;
                } catch (\Exception $e) {
                    $message = new Message(
                        'An error occurred during migration of module "%s". Check the config and uninstall it manually.', // @translate
                        'Search'
                    );
                    $messenger->addError($message);
                }
            }
        } else {
            $messenger->addWarning('The modules Search, Advanced Search Plus, PSL Search Form, Search Solr cannot be upgraded with a version of Advanced Search greater than 3.3.6.6.'); // @translate
            $this->installResources();
        }

        // The module is automatically disabled when Search is uninstalled.
        $module = $moduleManager->getModule('SearchSolr');
        if ($module && in_array($module->getState(), [
            \Omeka\Module\Manager::STATE_ACTIVE,
            \Omeka\Module\Manager::STATE_NOT_ACTIVE,
            \Omeka\Module\Manager::STATE_NEEDS_UPGRADE,
        ])) {
            $version = $module->getIni('version');
            if (version_compare($version, '3.5.27.3', '<')) {
                $message = new Message(
                    'The module %s should be upgraded to version %s or later.', // @translate
                    'SearchSolr', '3.5.27.3'
                );
                $messenger->addWarning($message);
            } elseif ($module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                $message = new Message(
                    'The module %s can be reenabled.', // @translate
                    'SearchSolr'
                );
                $messenger->addNotice($message);
            }
        }

        // The module is automatically disabled when Search is uninstalled.
        $module = $moduleManager->getModule('PslSearchForm');
        if ($module) {
            $sql = 'DELETE FROM `module` WHERE `id` = "PslSearchForm";';
            $connection = $services->get('Omeka\Connection');
            $connection->executeStatement($sql);
            $message = new Message(
                'The module "%s" was upgraded by module "%s" and uninstalled.', // @translate
                'PslSearchForm', 'Advanced Search'
            );
            $messenger->addWarning($message);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addHeaders']
        );

        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            \Generateur\Api\Adapter\GenerationAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            // Improve search by property: remove properties from query, process
            // normally, then process properties normally in api.search.query.
            // This process is required because it is not possible to override
            // the method buildPropertyQuery() in AbstractResourceEntityAdapter.
            // The point is the same to search resource without template, class,
            // item set, site and owner.
            // Because this event does not apply when initialize = false, the
            // api manager has a delegator that does the same.
            $sharedEventManager->attach(
                $adapter,
                'api.search.pre',
                [$this, 'startOverrideQuery'],
                // Let any other module, except core, to search properties.
                -100
            );
            // Add the search query filters for resources.
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'endOverrideQuery'],
                // Process before any other module in order to reset query.
                +100
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\Element\PropertySelect::class,
            'form.vocab_member_select.query',
            [$this, 'onFormVocabMemberSelectQuery']
        );
        $sharedEventManager->attach(
            \Omeka\Form\Element\ResourceClassSelect::class,
            'form.vocab_member_select.query',
            [$this, 'onFormVocabMemberSelectQuery']
        );

        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
            // TODO Add user.
        ];
        foreach ($controllers as $controller) {
            // Add the search field to the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );
        }
        $controllers = [
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
        ];
        foreach ($controllers as $controller) {
            // Specify fields to add to the advanced search form.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearchPost'],
                -100
            );
        }

        // The search pages use the core process to display used filters.
        $sharedEventManager->attach(
            \AdvancedSearch\Controller\IndexController::class,
            'view.search.filters',
            [$this, 'filterSearchFilters']
        );

        // Listeners for the indexing of items, item sets and media.
        // Let other modules to update data before indexing.

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngine'],
            -100
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngine'],
            -100
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngineMedia'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.delete.pre',
            [$this, 'preUpdateSearchEngineMedia'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngineMedia'],
            -100
        );

        // Listeners for sites.

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'addSearchConfigToSite']
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
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            // All can search and suggest, only admins can admin (by default).
            ->allow(
                null,
                [
                    \AdvancedSearch\Controller\IndexController::class,
                ]
            )
            // To search require read/search access to adapter.
            ->allow(
                null,
                [
                    \AdvancedSearch\Api\Adapter\SearchConfigAdapter::class,
                    \AdvancedSearch\Api\Adapter\SearchEngineAdapter::class,
                    \AdvancedSearch\Api\Adapter\SearchSuggesterAdapter::class,
                ],
                ['read', 'search']
            )
            // To search require read access to entities.
            ->allow(
                null,
                [
                    \AdvancedSearch\Entity\SearchConfig::class,
                    \AdvancedSearch\Entity\SearchEngine::class,
                    \AdvancedSearch\Entity\SearchSuggester::class,
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

        $settings = $services->get('Omeka\Settings');
        $searchConfigs = $settings->get('advancedsearch_all_configs', []);

        // A specific check to manage site admin or public site.
        $siteSlug = $status->getRouteParam('site-slug');

        $isAdminRequest = $status->isAdminRequest();
        if ($isAdminRequest) {
            $baseRoutes = ['search-admin-page-'];
            // Quick check if this is a site admin page. The list is required to
            // create the navigation.
            if ($siteSlug) {
                $baseRoutes[] = 'search-page-';
            }
            $adminSearchConfigs = $settings->get('advancedsearch_configs', []);
            $adminSearchConfigs = array_intersect_key($searchConfigs, array_flip($adminSearchConfigs));
            foreach ($baseRoutes as $baseRoute) foreach ($adminSearchConfigs as $searchConfigId => $searchConfigSlug) {
                $router->addRoute(
                    $baseRoute . $searchConfigId,
                    [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/admin/' . $searchConfigSlug,
                            'defaults' => [
                                '__NAMESPACE__' => 'AdvancedSearch\Controller',
                                '__ADMIN__' => true,
                                'controller' => \AdvancedSearch\Controller\IndexController::class,
                                'action' => 'search',
                                'id' => $searchConfigId,
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'suggest' => [
                                'type' => \Laminas\Router\Http\Literal::class,
                                'options' => [
                                    'route' => '/suggest',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller',
                                        '__ADMIN__' => true,
                                        'controller' => \AdvancedSearch\Controller\IndexController::class,
                                        'action' => 'suggest',
                                        'id' => $searchConfigId,
                                    ],
                                ],
                            ],
                        ],
                    ]
                );
            }
            return;
        }

        if (!$siteSlug) {
            return;
        }

        // Use of the api requires to check authentication and roles, but roles
        // are not yet all loaded (guest, annotator, etc.).
        // Anyway, it's just a route and a check is done in the controller.
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        $site = $entityManager
            ->getRepository(\Omeka\Entity\Site::class)
            ->findOneBy(['slug' => $siteSlug]);
        if (!$site) {
            return;
        }

        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site->getId());
        $siteSearchConfigs = $siteSettings->get('advancedsearch_configs', []);
        $siteSearchConfigs = array_intersect_key($searchConfigs, array_flip($siteSearchConfigs));
        foreach ($siteSearchConfigs as $searchConfigId => $searchConfigSlug) {
            $router->addRoute(
                // The urls use "search-page-" to simplify migration.
                'search-page-' . $searchConfigId,
                [
                    'type' => \Laminas\Router\Http\Segment::class,
                    'options' => [
                        'route' => '/s/:site-slug/' . $searchConfigSlug,
                        'defaults' => [
                            '__NAMESPACE__' => 'AdvancedSearch\Controller',
                            '__SITE__' => true,
                            'controller' => \AdvancedSearch\Controller\IndexController::class,
                            'action' => 'search',
                            'id' => $searchConfigId,
                            // Store the page slug to simplify checks.
                            'page-slug' => $searchConfigSlug,
                        ],
                    ],
                    'may_terminate' => true,
                    'child_routes' => [
                        'suggest' => [
                            'type' => \Laminas\Router\Http\Literal::class,
                            'options' => [
                                'route' => '/suggest',
                                'defaults' => [
                                    '__NAMESPACE__' => 'AdvancedSearch\Controller',
                                    '__SITE__' => true,
                                    'controller' => \AdvancedSearch\Controller\IndexController::class,
                                    'action' => 'suggest',
                                    'id' => $searchConfigId,
                                    // Store the page slug to simplify checks.
                                    'page-slug' => $searchConfigSlug,
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Clean useless fields and store some keys to process them one time only.
     *
     * @see \AdvancedSearch\Api\ManagerDelegator::search()
     * @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::startOverrideQuery()
     */
    public function startOverrideQuery(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');

        /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::startOverrideQuery() */
        $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('searchResources')
            ->startOverrideRequest($request);
    }

    /**
     * Reset original fields and process search after core.
     *
     * @see \AdvancedSearch\Api\ManagerDelegator::search()
     * @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::endOverrideQuery()
     */
    public function endOverrideQuery(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();

        /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::endOverrideQuery() */
        $searchResources = $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('searchResources');

        $searchResources
            ->endOverrideRequest($request)
            ->setAdapter($adapter)
            // Process the query for overridden keys.
            ->buildInitialQuery($qb, $request->getContent());
    }

    public function onFormVocabMemberSelectQuery(Event $event): void
    {
        $selectElement = $event->getTarget();
        if ($selectElement->getOption('used_terms')) {
            $query = $event->getParam('query', []);
            $query['used'] = true;
            $event->setParam('query', $query);
        }
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event): void
    {
        // Adapted from the advanced-search/properties.phtml template.

        // The advanced search form can be used anywhere, so load it in all cases.
        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headScript()
            ->appendFile($assetUrl('js/search.js', 'AdvancedSearch'), 'text/javascript', ['defer' => 'defer']);
        if ($view->status()->isAdminRequest()) {
            // For the main search field in the left sidebar in admin.
            $view->headLink()
                ->appendStylesheet($assetUrl('css/advanced-search-admin.css', 'AdvancedSearch'));
            $view->headScript()
                ->appendFile($assetUrl('js/advanced-search-admin.js', 'AdvancedSearch'), 'text/javascript', ['defer' => 'defer']);
        } else {
            $view->headLink()
                ->prependStylesheet($assetUrl('vendor/chosen-js/chosen.min.css', 'Omeka'));
            $view->headScript()
                ->appendFile($assetUrl('vendor/chosen-js/chosen.jquery.js', 'Omeka'), 'text/javascript', ['defer' => 'defer']);
        }

        $query = $event->getParam('query', []);

        $partials = $event->getParam('partials', []);
        $resourceType = $event->getParam('resourceType');

        if ($resourceType === 'media') {
            $query['item_set_id'] = isset($query['item_set_id']) ? (array) $query['item_set_id'] : [];
            $partials[] = 'common/advanced-search/media-item-sets';
        }

        $query['datetime'] = $query['datetime'] ?? '';
        $partials[] = 'common/advanced-search/date-time';

        $partials[] = 'common/advanced-search/visibility';

        if ($resourceType === 'item') {
            $query['has_media'] = $query['has_media'] ?? '';
            $partials[] = 'common/advanced-search/has-media';
        }

        if ($resourceType === 'item' || $resourceType === 'media') {
            $query['has_original'] = $query['has_original'] ?? '';
            $partials[] = 'common/advanced-search/has-original';
            $query['has_thumbnails'] = $query['has_thumbnails'] ?? '';
            $partials[] = 'common/advanced-search/has-thumbnails';
        }

        if ($resourceType === 'item') {
            $query['media_types'] = isset($query['media_types']) ? (array) $query['media_types'] : [];
            $partials[] = 'common/advanced-search/media-type';
        }

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearchPost(Event $event): void
    {
        $view = $event->getTarget();
        $partials = $event->getParam('partials', []);
        $defaultSearchFields = $this->getDefaultSearchFields();
        $searchFields = $view->siteSetting('advancedsearch_search_fields', $defaultSearchFields) ?: [];
        foreach ($partials as $key => $partial) {
            if (isset($defaultSearchFields[$partial]) && !in_array($partial, $searchFields)) {
                unset($partials[$key]);
            }
        }

        $event->setParam('partials', $partials);
    }

    protected function getDefaultSearchFields()
    {
        $config = $this->getServiceLocator()->get('Config');
        return $config['advancedsearch']['search_fields'];
    }

    /**
     * Filter search filters.
     *
     * @see \Omeka\View\Helper\SearchFilters
     * @see \AdvancedSearch\View\Helper\SearchFilters
     * @param Event $event
     */
    public function filterSearchFilters(Event $event): void
    {
        $query = $event->getParam('query', []);
        if (empty($query)) {
            return;
        }

        $filters = $event->getParam('filters');

        $this->baseUrl = (string) $event->getParam('baseUrl');

        /** @var \AdvancedSearch\Mvc\Controller\Plugin\SearchResources $searchResources */
        $searchResources = $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('searchResources');

        $this->query = $searchResources->cleanQuery($query);
        unset(
            $this->query['page'],
            $this->query['offset'],
            $this->query['submit'],
            $this->query['__searchConfig'],
            $this->query['__searchQuery']
        );

        // TODO Clarify main search filters and searching filters.
        if (isset($query['__searchConfig'])) {
            $filters = $this->filterSearchingFilters($query, $filters);
        }

        $event->setParam('filters', $filters);
    }

    /**
     * Manage specific arguments of the module searching form.
     *
     * @todo Should use the form adapter (but only main form is really used).
     * @see \AdvancedSearch\FormAdapter\AbstractFormAdapter
     * @todo Move filterSearchingFilters() to a view helper.
     */
    protected function filterSearchingFilters(array $query, array $filters): array
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $translate = $plugins->get('translate');
        $api = $plugins->get('api');

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $query['__searchConfig'];
        $searchEngine = $searchConfig->engine();
        $searchAdapter = $searchEngine->adapter();
        $availableFields = empty($searchAdapter)
            ? []
            : $searchAdapter->setSearchEngine($searchEngine)->getAvailableFields();
        $searchFormSettings = $searchConfig->setting('form') ?: [];

        // Manage all fields, included those not in the form in order to support
        // queries for long term. But use labels set in the form if any.
        $formFieldLabels = array_column($searchFormSettings['filters'] ?? [], 'label', 'field');
        $availableFieldLabels = array_combine(array_keys($availableFields), array_column($availableFields ?? [], 'label'));
        $fieldLabels = array_replace($availableFieldLabels, array_filter($formFieldLabels));

        // @see \AdvancedSearch\FormAdapter\AbstractFormAdapter::toQuery()
        // This function manages only one level, so check value when needed.
        // TODO Simplify queries (or make clear distinction between standard and old way).
        $flatArray = function ($value): array {
            if (!is_array($value)) {
                return [$value];
            }
            $firstKey = key($value);
            if (is_numeric($firstKey)) {
                return $value;
            }
            return is_array(reset($value)) ? $value[$firstKey] : [$value[$firstKey]];
        };

        $flatArrayValueResourceIds = function ($value, array $titles): array {
            if (is_array($value)) {
                $firstKey = key($value);
                if (is_numeric($firstKey)) {
                    $values = $value;
                } else {
                    $values = is_array(reset($value)) ? $value[$firstKey] : [$value[$firstKey]];
                }
            } else {
                $values = [$value] ;
            }
            $values = array_unique($values);
            $values = array_combine($values, $values);
            return array_replace($values, $titles);
        };

        foreach ($this->query as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            switch ($key) {
                case 'q':
                    $filterLabel = $translate('Query'); // @translate
                    $filters[$filterLabel][$this->urlQuery($key)] = $query['q'];
                    break;

                // Resource type is "items", "item_sets", etc.
                case 'resource_type':
                    $resourceTypes = [
                        'items' => $translate('Items'),
                        'item_sets' => $translate('Item sets'),
                    ];
                    $filterLabel = $translate('Resource type'); // @translate
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $resourceTypes[$subValue] ?? $subValue;
                    }
                    break;

                // Resource id.
                case 'id':
                    $filterLabel = $translate('Resource id'); // @translate
                    foreach (array_filter(array_map('intval', $flatArray($value))) as $subKey => $subValue) {
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                    }
                    break;

                case 'site':
                    $filterLabel = $translate('Site');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($flatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('sites', $subValue)->getContent()->title();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown site');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'owner':
                    $filterLabel = $translate('User');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($flatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('users', $subValue)->getContent()->name();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown user');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'class':
                    $filterLabel = $translate('Class'); // @translate
                    $isId = is_array($value) && key($value) === 'id';
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        if (is_numeric($subValue)) {
                            try {
                                $filterValue = $translate($api->read('resource_classes', $subValue)->getContent()->label());
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown class'); // @translate
                            }
                        } else {
                            $filterValue = $translate($api->searchOne('resource_classes', ['term' => $subValue])->getContent());
                            $filterValue = $filterValue ? $filterValue->label() : $translate('Unknown class');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'template':
                    $filterLabel = $translate('Template'); // @translate
                    $isId = is_array($value) && key($value) === 'id';
                    foreach ($flatArray($value) as $subKey => $subValue) {
                        if (is_numeric($subValue)) {
                            try {
                                $filterValue = $translate($api->read('resource_templates', $subValue)->getContent()->label());
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown template'); // @translate
                            }
                        } else {
                            $filterValue = $translate($api->searchOne('resource_templates', ['label' => $subValue])->getContent());
                            $filterValue = $filterValue ? $filterValue->label() : $translate('Unknown template');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'item_set':
                    $filterLabel = $translate('Item set');
                    $isId = is_array($value) && key($value) === 'id';
                    foreach (array_filter($flatArray($value), 'is_numeric') as $subKey => $subValue) {
                        try {
                            $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown item set');
                        }
                        $urlQuery = $isId ? $this->urlQueryId($key, $subKey) : $this->urlQuery($key, $subKey);
                        $filters[$filterLabel][$urlQuery] = $filterValue;
                    }
                    break;

                case 'filter':
                    $value = array_filter($value, 'is_array');
                    if (!count($value)) {
                        break;
                    }

                    $queryTypes = [
                        'eq' => $translate('is exactly'), // @translate
                        'neq' => $translate('is not exactly'), // @translate
                        'in' => $translate('contains'), // @translate
                        'nin' => $translate('does not contain'), // @translate
                        'ex' => $translate('has any value'), // @translate
                        'nex' => $translate('has no values'), // @translate
                        'exs' => $translate('has a single value'), // @translate
                        'nexs' => $translate('has not a single value'), // @translate
                        'exm' => $translate('has multiple values'), // @translate
                        'nexm' => $translate('has not multiple values'), // @translate
                        'list' => $translate('is in list'), // @translate
                        'nlist' => $translate('is not in list'), // @translate
                        'sw' => $translate('starts with'), // @translate
                        'nsw' => $translate('does not start with'), // @translate
                        'ew' => $translate('ends with'), // @translate
                        'new' => $translate('does not end with'), // @translate
                        // 'res' => $translate('is resource with ID'), // @translate
                        // 'nres' => $translate('is not resource with ID'), // @translate
                        'res' => $translate('is'), // @translate
                        'nres' => $translate('is not'), // @translate
                        'lex' => $translate('is a linked resource'), // @translate
                        'nlex' => $translate('is not a linked resource'), // @translate
                        // 'lres' => $translate('is linked with resource with ID'), // @translate
                        // 'nlres' => $translate('is not linked with resource with ID'), // @translate
                        'lres' => $translate('is linked with'), // @translate
                        'nlres' => $translate('is not linked with'), // @translate
                        'tp' => $translate('has main type'), // @translate
                        'ntp' => $translate('has not main type'), // @translate
                        'dtp' => $translate('has data type'), // @translate
                        'ndtp' => $translate('has not data type'), // @translate
                        'gt' => $translate('greater than'), // @translate
                        'gte' => $translate('greater than or equal'), // @translate
                        'lte' => $translate('lower than or equal'), // @translate
                        'lt' => $translate('lower than'), // @translate
                    ];

                    // Get all resources titles with one query.
                    $vrTitles = [];
                    $vrIds = [];
                    foreach ($value as $queryRow) {
                        if (is_array($queryRow)
                            && isset($queryRow['type'])
                            && !empty($queryRow['value'])
                            && in_array($queryRow['type'], SearchResources::PROPERTY_QUERY['value_subject'])
                        ) {
                            is_array($queryRow['value'])
                                ? $vrIds = array_merge($vrIds, array_values($queryRow['value']))
                                : $vrIds[] = $queryRow['value'];
                        }
                    }
                    $vrIds = array_unique(array_filter(array_map('intval', $vrIds)));
                    if ($vrIds) {
                        // Currently, "resources" cannot be searched, so use adapter
                        // directly. Rights are managed.
                        /** @var \Doctrine\ORM\EntityManager $entityManager */
                        $services = $this->getServiceLocator();
                        $entityManager = $services->get('Omeka\EntityManager');
                        $qb = $entityManager->createQueryBuilder();
                        $qb
                            ->select('omeka_root.id', 'omeka_root.title')
                            ->from(\Omeka\Entity\Resource::class, 'omeka_root')
                            ->where($qb->expr()->in('omeka_root.id', ':ids'))
                            ->setParameter('ids', $vrIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
                        $vrTitles = array_column($qb->getQuery()->getScalarResult(), 'title', 'id');
                    }

                    // To get the name of the advanced fields, a loop should be done for now.
                    $searchFormAdvancedLabels = [];
                    foreach ($searchFormSettings['filters'] as $searchFormFilter) {
                        if ($searchFormFilter['type'] === 'Advanced') {
                            $searchFormAdvancedLabels = array_column($searchFormFilter['fields'], 'label', 'value');
                            break;
                        }
                    }
                    $fieldFiltersLabels = array_replace($fieldLabels, array_filter($searchFormAdvancedLabels));

                    $index = 0;
                    foreach ($value as $subKey => $queryRow) {
                        $queryType = $queryRow['type'] ?? 'eq';
                        if (!isset(SearchResources::PROPERTY_QUERY['reciprocal'][$queryType])) {
                            continue;
                        }

                        $joiner = $queryRow['join'] ?? 'and';
                        $value = $queryRow['value'] ?? '';

                        $isWithoutValue = in_array($queryType, SearchResources::PROPERTY_QUERY['value_none'], true);

                        // A value can be an array with types "list" and "nlist".
                        if (!is_array($value)
                            && !strlen((string) $value)
                            && !$isWithoutValue
                        ) {
                            continue;
                        }

                        if ($isWithoutValue) {
                            $value = '';
                        }

                        // The field is currently always single: use multi-fields else.
                        // TODO Support multi-fields.
                        $queryField = $queryRow['field'] ?? '';
                        /*
                        $fieldLabel = $queryField
                            ? $fieldFiltersLabels[$queryField] ?? $translate('Unknown field') // @ translate
                            : $translate('[Any field]'); // @ translate
                        */
                        // Support default solr index names for compatibility
                        // of custom themes.
                        if ($queryField) {
                            if (isset($fieldFiltersLabels[$queryField])) {
                                $fieldLabel = $fieldFiltersLabels[$queryField];
                            } elseif (strpos($queryField, '_')) {
                                $fieldLabel = $fieldFiltersLabels[strtok($queryField, '_') . ':' . strtok('_')] ?? $translate('Unknown field'); // @translate
                            } else {
                                $fieldLabel = $translate('Unknown field'); // @translate
                            }
                        } else {
                            $fieldLabel = $translate('[Any field]'); // @translate
                        }

                        $filterLabel = $fieldLabel . ' ' . $queryTypes[$queryType];
                        if ($index > 0) {
                            if ($joiner === 'or') {
                                $filterLabel = $translate('OR') . ' ' . $filterLabel;
                            } elseif ($joiner === 'not') {
                                $filterLabel = $translate('EXCEPT') . ' ' . $filterLabel; // @translate
                            } else {
                                $filterLabel = $translate('AND') . ' ' . $filterLabel;
                            }
                        }

                        $vals = in_array($queryType, SearchResources::PROPERTY_QUERY['value_subject'])
                            ? $flatArrayValueResourceIds($value, $vrTitles)
                            : $flatArray($value);
                        $filters[$filterLabel][$this->urlQuery($key, $subKey)] = implode(', ', $vals);

                        ++$index;
                    }
                    break;

                default:
                    // Append only fields that are not yet processed somewhere
                    // else, included searchFilters helper.
                    if (isset($fieldLabels[$key]) && !isset($filters[$fieldLabels[$key]])) {
                        if (is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))) {
                            $filterLabel = $fieldLabels[$key];
                            if (array_key_exists('from', $value) && array_key_exists('to', $value)) {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('from %s to %s'), $value['from'], $value['to']); // @translate
                            } elseif (array_key_exists('from', $value)) {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('since %s'), $value['from']); // @translate
                            } elseif (array_key_exists('to', $value)) {
                                $filters[$filterLabel][$this->urlQuery($key)] = sprintf($translate('until %s'), $value['to']); // @translate
                            }
                            break;
                        }

                        $filterLabel = $fieldLabels[$key];
                        foreach (array_filter(array_map('trim', array_map('strval', $flatArray($value))), 'strlen') as $subKey => $subValue) {
                            $filters[$filterLabel][$this->urlQuery($key, $subKey)] = $subValue;
                        }
                    }
                    break;
            }
        }

        return $filters;
    }

    /**
     * Get the url of the query without the specified key and subkey.
     *
     * @param string|int $key
     * @param string|int|null $subKey
     * @return string
     */
    protected function urlQuery($key, $subKey = null): string
    {
        $newQuery = $this->query;
        if (is_null($subKey) || !is_array($newQuery[$key]) || count($newQuery[$key]) <= 1) {
            unset($newQuery[$key]);
        } else {
            unset($newQuery[$key][$subKey]);
        }
        return $newQuery
            ? $this->baseUrl . '?' . http_build_query($newQuery, '', '&', PHP_QUERY_RFC3986)
            : $this->baseUrl;
    }

    /**
     * Get url of the query without specified key and subkey for special fields.
     *
     * @todo Remove this special case.
     *
     * @param string|int $key
     * @param string|int|null $subKey
     * @return string
     */
    protected function urlQueryId($key, $subKey): string
    {
        $newQuery = $this->query;
        if (!is_array($newQuery[$key]) || !is_array($newQuery[$key]['id']) || count($newQuery[$key]['id']) <= 1) {
            unset($newQuery[$key]);
        } else {
            unset($newQuery[$key]['id'][$subKey]);
        }
        return $newQuery
            ? $this->baseUrl . '?' . http_build_query($newQuery, '', '&', PHP_QUERY_RFC3986)
            : $this->baseUrl;
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
    public function preBatchUpdateSearchEngine(Event $event): void
    {
        // This is a background job if there is no route match.
        $routeMatch = $this->getServiceLocator()->get('application')->getMvcEvent()->getRouteMatch();
        $this->isBatchUpdate = !empty($routeMatch);
    }

    public function postBatchUpdateSearchEngine(Event $event): void
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

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            if (in_array($requestResource, $searchEngine->setting('resources', []))) {
                $indexer = $searchEngine->indexer();
                try {
                    $indexer->indexResources($resources);
                } catch (\Exception $e) {
                    $services = $this->getServiceLocator();
                    $logger = $services->get('Omeka\Logger');
                    $logger->err(new Message(
                        'Unable to batch index metadata for search engine "%s": %s', // @translate
                        $searchEngine->name(), $e->getMessage()
                    ));
                    $messenger = $services->get('ControllerPluginManager')->get('messenger');
                    $messenger->addWarning(new Message(
                        'Unable to batch update the search engine "%s": see log.', // @translate
                        $searchEngine->name()
                    ));
                }
            }
        }

        $this->isBatchUpdate = false;
    }

    public function preUpdateSearchEngineMedia(Event $event): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $request = $event->getParam('request');
        $media = $api->read('media', $request->getId())->getContent();
        $data = $request->getContent();
        $data['itemId'] = $media->item()->id();
        $request->setContent($data);
    }

    public function updateSearchEngine(Event $event): void
    {
        if ($this->isBatchUpdate) {
            return;
        }
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $requestResource = $request->getResource();

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            if (in_array($requestResource, $searchEngine->setting('resources', []))) {
                $indexer = $searchEngine->indexer();
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

    public function updateSearchEngineMedia(Event $event): void
    {
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $itemId = $request->getValue('itemId');
        $item = $itemId
            ? $api->read('items', $itemId, [], ['responseContent' => 'resource'])->getContent()
            : $response->getContent()->getItem();

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            if (in_array('items', $searchEngine->setting('resources', []))) {
                $indexer = $searchEngine->indexer();
                $this->updateIndexResource($indexer, $item);
            }
        }
    }

    /**
     * Delete the index for the resource in search engine.
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
     * Update the index in search engine for a resource.
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
            if ($params['controller'] === \AdvancedSearch\Controller\IndexController::class) {
                $searchConfig = @$params['id'];
            } else {
                $searchConfig = $view->siteSetting('advancedsearch_main_config');
            }
        } elseif ($status->isAdminRequest()) {
            $searchConfig = $view->setting('advancedsearch_main_config');
        } else {
            return;
        }

        if (!$searchConfig) {
            return;
        }

        // A try/catch is required to bypass issues during upgrade.
        try {
            /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
            $searchConfig = $plugins->get('api')->read('search_configs', ['id' => $searchConfig])->getContent();
        } catch (\Exception $e) {
            return;
        }
        if (!$searchConfig) {
            return;
        }

        $formAdapter = $searchConfig->formAdapter();
        $partialHeaders = $formAdapter ? $formAdapter->getFormPartialHeaders() : null;

        if ($status->isAdminRequest()) {
            $basePath = $plugins->get('basePath');
            $assetUrl = $plugins->get('assetUrl');
            $searchUrl = $basePath('admin/' . $searchConfig->path());
            $autoSuggestUrl = $searchConfig->subSetting('autosuggest', 'url');
            if (!$autoSuggestUrl) {
                $suggester = $searchConfig->subSetting('autosuggest', 'suggester');
                if ($suggester) {
                    $autoSuggestUrl = $searchUrl . '/suggest';
                }
            }
            $plugins->get('headLink')
                ->appendStylesheet($assetUrl('css/advanced-search-admin.css', 'AdvancedSearch'));
            $plugins->get('headScript')
                ->appendScript(sprintf('var searchUrl = %s;', json_encode($searchUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                   . ($autoSuggestUrl ? sprintf("\nvar searchAutosuggestUrl=%s;", json_encode($autoSuggestUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : '')
                )
                ->appendFile($assetUrl('js/advanced-search-admin.js', 'AdvancedSearch'), 'text/javascript', ['defer' => 'defer']);
        }

        if (!$partialHeaders) {
            return;
        }

        // No echo: it should just be a preload.
        // "searchPage" is kept to simplify migration.
        $view->vars()->offsetSet('searchPage', $searchConfig);
        $view->partial($partialHeaders);
    }

    public function addSearchConfigToSite(Event $event): void
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Omeka\Mvc\Controller\Plugin\Api $api
         *
         * @var \Omeka\Api\Representation\SiteRepresentation $site
         * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        $site = null;
        $searchConfig = null;

        // Take the search config of the default site or the first site, else the
        // default search config.
        $defaultSite = (int) $settings->get('default_site');
        if ($defaultSite) {
            try {
                $site = $api->read('sites', ['id' => $defaultSite])->getContent();
            } catch (\Exception $e) {
            }
        }
        if ($site) {
            $siteSettings->setTargetId($site->id());
            $searchConfigId = (int) $siteSettings->get('advancedsearch_main_config');
        } else {
            $searchConfigId = (int) $settings->get('advancedsearch_main_config');
        }
        $searchConfig = null;
        if ($searchConfigId) {
            try {
                $searchConfig = $api->read('search_configs', ['id' => $searchConfigId])->getContent();
            } catch (\Exception $e) {
            }
        }
        if (!$searchConfig) {
            try {
                $searchConfig = $api->search('search_configs', ['limit' => 1])->getContent();
                $searchConfig = reset($searchConfig);
            } catch (\Exception $e) {
            }
        }
        if (!$searchConfig) {
            $searchConfigId = $this->createDefaultSearchConfig();
            $searchConfig = $api->read('search_configs', ['id' => $searchConfigId])->getContent();
        }

        /** @var \Omeka\Entity\Site $site */
        $site = $event->getParam('response')->getContent();

        $siteSettings->setTargetId($site->getId());
        $siteSettings->set('advancedsearch_main_config', $searchConfig->id());
        $siteSettings->set('advancedsearch_configs', [$searchConfig->id()]);
        $siteSettings->set('advancedsearch_redirect_itemset', true);
    }

    protected function installResources(): void
    {
        $this->createDefaultSearchConfig();
    }

    /**
     * @todo Replace this method by the standard InstallResources() when the upgrade from Search will be removed.
     */
    protected function createDefaultSearchConfig(): int
    {
        // Note: during installation or upgrade, the api may not be available
        // for the search api adapters, so use direct sql queries.

        $services = $this->getServiceLocator();

        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $messenger = new Messenger;

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        // Check if the internal index exists.
        $sqlSearchEngineId = <<<'SQL'
SELECT `id`
FROM `search_engine`
WHERE `adapter` = "internal"
ORDER BY `id`;
SQL;
        $searchEngineId = (int) $connection->fetchColumn($sqlSearchEngineId);

        if (!$searchEngineId) {
            // Create the internal adapter.
            $sql = <<<'SQL'
INSERT INTO `search_engine`
(`name`, `adapter`, `settings`, `created`)
VALUES
(?, ?, ?, NOW());
SQL;
            $searchEngineConfig = require __DIR__ . '/data/search_engines/internal.php';
            $connection->executeStatement($sql, [
                $searchEngineConfig['o:name'],
                $searchEngineConfig['o:adapter'],
                json_encode($searchEngineConfig['o:settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $searchEngineId = $connection->fetchColumn($sqlSearchEngineId);
            $message = new Message(
                'The internal search engine (sql) is available. Configure it in the %ssearch manager%s.', // @translate
                // Don't use the url helper, the route is not available during install.
                sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager'),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        // Check if the internal suggester exists.
        $sqlSuggesterId = <<<SQL
SELECT `id`
FROM `search_suggester`
WHERE `engine_id` = $searchEngineId
ORDER BY `id`
LIMIT 1;
SQL;
        $suggesterId = (int) $connection->fetchColumn($sqlSuggesterId);

        if (!$suggesterId) {
            // Create the internal suggester.
            $sql = <<<SQL
INSERT INTO `search_suggester`
(`engine_id`, `name`, `settings`, `created`)
VALUES
($searchEngineId, 'Internal suggester (sql)', ?, NOW());
SQL;
            $suggesterSettings = [
                'direct' => false,
                'mode_index' => 'start',
                'mode_search' => 'start',
                'limit' => 25,
                'length' => 50,
                'fields' => [],
                'excluded_fields' => [],
            ];
            $connection->executeStatement($sql, [
                json_encode($suggesterSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            // $suggesterId = $connection->fetchColumn($sqlSuggesterId);
            $message = new Message(
                'The internal suggester (sql) will be available after indexation. Configure it in the %ssearch manager%s.', // @translate
                // Don't use the url helper, the route is not available during install.
                sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager'),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        // Check if the default search config exists.
        $sqlSearchConfigId = <<<SQL
SELECT `id`
FROM `search_config`
WHERE `engine_id` = $searchEngineId
ORDER BY `id`;
SQL;
        $searchConfigId = (int) $connection->fetchColumn($sqlSearchConfigId);

        if (!$searchConfigId) {
            $sql = <<<SQL
INSERT INTO `search_config`
(`engine_id`, `name`, `path`, `form_adapter`, `settings`, `created`)
VALUES
($searchEngineId, ?, ?, ?, ?, NOW());
SQL;
            $searchConfigConfig = require __DIR__ . '/data/search_configs/default.php';
            $connection->executeStatement($sql, [
                $searchConfigConfig['o:name'],
                $searchConfigConfig['o:path'],
                $searchConfigConfig['o:form'],
                json_encode($searchConfigConfig['o:settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $message = new Message(
                'The default search page is available. Configure it in the %ssearch manager%s, in the main settings (for admin) and in site settings (for public).', // @translate
                // Don't use the url helper, the route is not available during install.
                sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager/suggester/1/edit'),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        return $searchConfigId;
    }
}
