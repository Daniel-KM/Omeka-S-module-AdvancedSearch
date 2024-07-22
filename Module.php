<?php declare(strict_types=1);

/**
 * Advanced Search
 *
 * Improve search with new fields, auto-suggest, filters, facets, specific pages, etc.
 *
 * @copyright BibLibre, 2016-2017
 * @copyright Daniel Berthereau, 2017-2024
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

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Entity\Resource;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Common',
    ];

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
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.61')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.61'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        if ($this->isModuleActive('Search')) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                $translate('This module is not compatible with mocule "Search". Disable or uninstall it first. To upgrade from it, update it to version 3.5.23.3 or greater and downgrade this module to version 3.4.14.') // @translate
            );
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        $optionalModule = 'Reference';
        if (!$this->isModuleActive($optionalModule)) {
            $messenger->addWarning('The module Reference is required to use the facets with the default internal adapter, but not for the Solr adapter.'); // @translate
        }

        // The module is automatically disabled when Search is uninstalled.
        $module = $moduleManager->getModule('SearchSolr');
        if ($module && in_array($module->getState(), [
            \Omeka\Module\Manager::STATE_ACTIVE,
            \Omeka\Module\Manager::STATE_NOT_ACTIVE,
            \Omeka\Module\Manager::STATE_NEEDS_UPGRADE,
        ])) {
            $version = $module->getIni('version');
            if (version_compare($version, '3.5.45', '<')) {
                $message = new PsrMessage(
                    'The module {module} should be upgraded to version {version} or later.', // @translate
                    ['module' => 'SearchSolr', 'version' => '3.5.45']
                );
                $messenger->addWarning($message);
            } elseif ($module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                $message = new PsrMessage(
                    'The module {module} can be reenabled.', // @translate
                    ['module' => 'SearchSolr']
                );
                $messenger->addNotice($message);
            }
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

        /** @see \AdvancedSearch\Api\ManagerDelegator::search() */
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            // Annotation is not supported any more for now, but all features
            // are included directly inside the module.
            // \Annotate\Api\Adapter\AnnotationAdapter::class,
            // \Generateur\Api\Adapter\GenerationAdapter::class,
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
            // TODO Use a single event but with another priority?
            $sharedEventManager->attach(
                $adapter,
                'api.search.pre',
                [$this, 'startOverrideQuery'],
                // Let any other module, except core, to search properties.
                -200
            );
            // Add the search query filters for resources.
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'endOverrideQuery'],
                // Process before any other module in order to reset query.
                +200
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
            'Omeka\Controller\Admin\Query',
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
                [$this, 'handleViewAdvancedSearch']
            );
        }
        $controllers = [
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
        ];
        foreach ($controllers as $controller) {
            // Specify fields to filter from the advanced search form.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'handleViewAdvancedSearchPost'],
                -100
            );
        }

        // The search pages use the core process to display used filters.
        $sharedEventManager->attach(
            \AdvancedSearch\Controller\SearchController::class,
            'view.search.filters',
            [$this, 'filterSearchFilters']
        );

        // Listeners for the indexing of items, item sets and media.
        // Let other modules to update data before indexing.

        // See the fix for issue before 3.4.7 for Omeka < 4.1.
        // Nevertheless, batch process with "remove" or "append" is not indexed.

        // Items.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine'],
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

        // Item sets.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine'],
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

        // Medias.
        // There is no api.create.post for medias.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngineMedia'],
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

        // Listeners for sites.

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'addSearchConfigToSite']
        );

        // Listeners for configs.

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
            // All can search and suggest, only admins can admin.
            ->allow(
                null,
                [
                    \AdvancedSearch\Controller\SearchController::class,
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
        $isApiRequest = $status->isApiRequest();
        if ($isApiRequest) {
            return;
        }

        $router = $services->get('Router');
        if (!$router instanceof \Laminas\Router\Http\TreeRouteStack) {
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $searchConfigs = $settings->get('advancedsearch_all_configs', []);

        // A specific check to manage site admin or public site.
        // The site slug is required to build public routes in background job.
        $siteSlug = $status->getRouteParam('site-slug');
        if (!$siteSlug) {
            $helpers = $services->get('ViewHelperManager');
            if ($helpers->has('defaultSite')) {
                $defaultSite = $helpers->get('defaultSite');
                $siteSlug = $defaultSite('slug');
            } else {
                $defaultSite = (int) $settings->get('default_site');
                if ($defaultSite) {
                    try {
                        $site = $api->read('sites', ['id' => $defaultSite])->getContent();
                        $siteSlug = $site->slug();
                    } catch (\Exception $e) {
                    }
                }
            }
        }

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
                                'controller' => \AdvancedSearch\Controller\SearchController::class,
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
                                        '__ADMIN__' => true,
                                        'controller' => \AdvancedSearch\Controller\SearchController::class,
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
                            'controller' => \AdvancedSearch\Controller\SearchController::class,
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
                                    'controller' => \AdvancedSearch\Controller\SearchController::class,
                                    'action' => 'suggest',
                                    'id' => $searchConfigId,
                                    // Store the page slug to simplify checks.
                                    'page-slug' => $searchConfigSlug,
                                ],
                            ],
                        ],
                        'atom' => [
                            'type' => \Laminas\Router\Http\Literal::class,
                            'options' => [
                                'route' => '/atom',
                                'defaults' => [
                                    '__NAMESPACE__' => 'AdvancedSearch\Controller',
                                    '__SITE__' => true,
                                    'controller' => \AdvancedSearch\Controller\SearchController::class,
                                    'action' => 'rss',
                                    'feed' => 'atom',
                                    'id' => $searchConfigId,
                                    // Store the page slug to simplify checks.
                                    'page-slug' => $searchConfigSlug,
                                ],
                            ],
                        ],
                        'rss' => [
                            'type' => \Laminas\Router\Http\Literal::class,
                            'options' => [
                                'route' => '/rss',
                                'defaults' => [
                                    '__NAMESPACE__' => 'AdvancedSearch\Controller',
                                    '__SITE__' => true,
                                    'controller' => \AdvancedSearch\Controller\SearchController::class,
                                    'action' => 'rss',
                                    'feed' => 'rss',
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

        // Don't override for api index search.
        if ($request->getOption('is_index_search')) {
            return;
        }

        /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::startOverrideRequest() */
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

        // Don't override for api index search.
        if ($request->getOption('is_index_search')) {
            return;
        }

        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();

        /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::startOverrideRequest() */
        /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchResources::buildInitialQuery() */
        $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('searchResources')
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
     * Display the advanced search form via partial for sites.
     *
     * @param Event $event
     */
    public function handleViewAdvancedSearch(Event $event): void
    {
        $view = $event->getTarget();

        $plugins = $view->getHelperPluginManager();
        $status = $plugins->get('status');
        $assetUrl = $plugins->get('assetUrl');
        $headLink = $plugins->get('headLink');
        $headScript = $plugins->get('headScript');

        // Include chosen-select in sites.
        $isSite = $status->isSiteRequest();
        if ($isSite) {
            $headLink
                ->prependStylesheet($assetUrl('vendor/chosen-js/chosen.min.css', 'Omeka'));
            $headScript
                ->appendFile($assetUrl('vendor/chosen-js/chosen.jquery.js', 'Omeka'), 'text/javascript', ['defer' => 'defer']);
        }

        $headLink
            ->appendStylesheet($assetUrl('css/advanced-search-form.css', 'AdvancedSearch'));
        $headScript
            ->appendFile($assetUrl('js/advanced-search-form.js', 'AdvancedSearch'), 'text/javascript', ['defer' => 'defer']);

        $this->handlePartialsAdvancedSearch($event);
    }

    public function handlePartialsAdvancedSearch(Event $event): void
    {
        // Adapted from application/view/common/advanced-search.phtml.

        $query = $event->getParam('query', []);

        $partials = $event->getParam('partials', []);
        $resourceType = $event->getParam('resourceType');

        if ($resourceType === 'media') {
            $query['item_set_id'] = isset($query['item_set_id']) ? (array) $query['item_set_id'] : [];
            $partials[] = 'common/advanced-search/media-item-sets';
        }

        $query['datetime'] ??= '';
        $partials[] = 'common/advanced-search/date-time';

        // Visibility filter was included in Omeka S v4.0.

        if ($resourceType === 'item') {
            $query['has_media'] ??= '';
            $partials[] = 'common/advanced-search/has-media';
        }

        if ($resourceType === 'item' || $resourceType === 'media') {
            $query['has_original'] ??= '';
            $partials[] = 'common/advanced-search/has-original';
            $query['has_thumbnails'] ??= '';
            $partials[] = 'common/advanced-search/has-thumbnails';
        }

        if ($resourceType === 'item') {
            $query['media_types'] = isset($query['media_types']) ? (array) $query['media_types'] : [];
            $partials[] = 'common/advanced-search/media-type';
        }

        $partials = array_unique($partials);

        $event->setParam('query', $query);
        $event->setParam('partials', $partials);
    }

    /**
     * Update partials (search fields) to the advanced search form.
     *
     * @param Event $event
     */
    public function handleViewAdvancedSearchPost(Event $event): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $defaultSearchFields = $config['advancedsearch']['search_fields'];

        $view = $event->getTarget();
        $partials = $event->getParam('partials', []);

        $partials = array_unique($partials);

        // Don't add new partials, only remove existing ones: by default, they
        // are forbidden partials for sites.
        $searchFields = $view->siteSetting('advancedsearch_search_fields', $defaultSearchFields) ?: [];
        foreach ($partials as $key => $partial) {
            if (isset($defaultSearchFields[$partial]) && !in_array($partial, $searchFields)) {
                unset($partials[$key]);
            }
        }

        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * The search filter helper is overridden, so manage only the searching
     * filters here.
     *
     * @see \Omeka\View\Helper\SearchFilters
     * @see \AdvancedSearch\View\Helper\SearchFilters
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event): void
    {
        $query = $event->getParam('query', []);
        if (empty($query)) {
            return;
        }

        $filters = $event->getParam('filters');

        // $this->baseUrl = (string) $event->getParam('baseUrl');

        /** @var \AdvancedSearch\Mvc\Controller\Plugin\SearchResources $searchResources */
        $searchResources = $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('searchResources');

        $cleanedQuery = $searchResources->cleanQuery($query);
        $searchConfig = $cleanedQuery['__searchConfig'] ?? null;
        // TODO Use the search Query directly or the query in the params? Currently, Query is not used.
        // $searchQuery = $cleanedQuery['__searchQuery'] ?? null;
        unset(
            $cleanedQuery['page'],
            $cleanedQuery['offset'],
            $cleanedQuery['submit'],
            $cleanedQuery['__searchConfig'],
            $cleanedQuery['__searchQuery']
        );

        // TODO Clarify main search filters and searching filters.
        if ($searchConfig) {
            $view = $event->getTarget();
            $filters = $view->searchingFilters()->filterSearchingFilters($searchConfig, $cleanedQuery, $filters);
        }

        $event->setParam('filters', $filters);
    }

    public function preBatchUpdateSearchEngine(Event $event): void
    {
        $this->isBatchUpdate = true;
    }

    /**
     * Update multiple resources after a batch process.
     *
     * @fixme Indexation when there is process "remove" or "append".
     */
    public function postBatchUpdateSearchEngine(Event $event): void
    {
        if (!$this->isBatchUpdate) {
            return;
        }

        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $indexBatchEdit = $settings->get('advancedsearch_index_batch_edit', 'sync');
        if ($indexBatchEdit === 'none') {
            return;
        }

        /**
         * @var \Omeka\Api\Request $request
         * @var \Omeka\Api\Response $response
         */
        $request = $event->getParam('request');

        // Unlike module Bulk Edit, "append" was used, because a
        // hidden element was added to manage indexation at the end.
        // Nevertheless, it makes "remove" and "append" not indexed.
        // This process avoids doctrine issue on properties, reloaded to check
        // resource templates in the core.
        $collectionAction = $request->getOption('collectionAction', 'replace');
        if ($collectionAction !== 'replace') {
            return;
        }

        $response = $event->getParam('response');
        $resources = $response->getContent();
        $resourceType = $request->getResource();

        // TODO Use async indexation when short batch edit and sync when background batch edit?
        if ($indexBatchEdit === 'sync' || $indexBatchEdit === 'async') {
            $this->runJobIndexSearch($resourceType, $request->getIds(), $indexBatchEdit === 'sync');
            return;
        }

        // Integrated indexation.
        // TODO A doctrine issue "new entity was found" may occur when there are multiple linked resources.

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            $indexer = $searchEngine->indexer();
            if ($indexer->canIndex($resourceType)
                && in_array($resourceType, $searchEngine->setting('resources', []))
            ) {
                try {
                    $indexer->indexResources($resources);
                } catch (\Exception $e) {
                    $logger->err(
                        'Unable to batch index metadata for search engine "{name}": {message}', // @translate
                        ['name' => $searchEngine->name(), 'message' => $e->getMessage()]
                    );
                    $messenger = $services->get('ControllerPluginManager')->get('messenger');
                    $messenger->addWarning(new PsrMessage(
                        'Unable to batch update the search engine "{name}": see log.', // @translate
                        ['name' => $searchEngine->name()]
                    ));
                }
            }
        }

        $this->isBatchUpdate = false;
    }

    /**
     * Adapted:
     * @see \AdvancedSearch\Controller\Admin\SearchEngineController::indexAction()
     */
    protected function runJobIndexSearch(string $resourceType, array $ids, bool $sync): void
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) {
            return;
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
        $strategy = $sync ? $services->get('Omeka\Job\DispatchStrategy\Synchronous') : null;

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        $first = true;
        foreach ($searchEngines as $searchEngine) {
            $indexer = $searchEngine->indexer();
            if ($indexer->canIndex($resourceType)
                && in_array($resourceType, $searchEngine->setting('resources', []))
            ) {
                $jobArgs = [];
                $jobArgs['search_engine_id'] = $searchEngine->id();
                $jobArgs['resource_ids'] = $ids;
                $jobArgs['resource_names'] = [$resourceType];
                // Most of the time, there is only one solr index.
                // TODO Improve indexing of multiple search engines after batch process.
                $jobArgs['force'] = !$first;
                try {
                    $jobDispatcher->dispatch(\AdvancedSearch\Job\IndexSearch::class, $jobArgs, $strategy);
                    $first = false;
                } catch (\Exception $e) {
                    $logger->err(
                        'Unable to launch index metadata for search engine "{name}": {message}', // @translate
                        ['name' => $searchEngine->name(), 'message' => $e->getMessage()]
                    );
                    $messenger->addWarning(new PsrMessage(
                        'Unable to launch indexing for the search engine "{name}": see log.', // @translate
                        ['name' => $searchEngine->name()]
                    ));
                }
            }
        }
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

    /**
     * Index a single resource in search engines.
     */
    public function updateSearchEngine(Event $event): void
    {
        if ($this->isBatchUpdate) {
            return;
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $requestResource = $request->getResource();

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            if ($searchEngine->indexer()->canIndex($requestResource)
                && in_array($requestResource, $searchEngine->setting('resources', []))
            ) {
                if ($request->getOperation() === 'delete') {
                    $id = $request->getId();
                    $this->deleteIndexResource($searchEngine, $requestResource, $id);
                } else {
                    $resource = $response->getContent();
                    $this->updateIndexResource($searchEngine, $resource);
                }
            }
        }
    }

    /**
     * Index a single media in search engines.
     */
    public function updateSearchEngineMedia(Event $event): void
    {
        if ($this->isBatchUpdate) {
            return;
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $itemId = $request->getValue('itemId');
        $item = $itemId
            ? $api->read('items', $itemId, [], ['responseContent' => 'resource'])->getContent()
            : $response->getContent()->getItem();

        /** @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation[] $searchEngines */
        $searchEngines = $api->search('search_engines')->getContent();
        foreach ($searchEngines as $searchEngine) {
            if ($searchEngine->indexer()->canIndex('items')
                && in_array('items', $searchEngine->setting('resources', []))
            ) {
                $this->updateIndexResource($searchEngine, $item);
            }
        }
    }

    /**
     * Delete the index for the resource in search engine.
     *
     * @param SearchEngineRepresentation $searchEngine
     * @param string $resourceName
     * @param int $id
     */
    protected function deleteIndexResource(SearchEngineRepresentation $searchEngine, $resourceName, $id): void
    {
        $indexer = $searchEngine->indexer();
        try {
            $indexer->deleteResource($resourceName, $id);
        } catch (\Exception $e) {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $logger->err(
                'Unable to delete the search index for resource #{resource_id} in search engine "{name}": {message}', // @translate
                ['resource_id' => $id, 'name' => $searchEngine->name(), 'message' => $e->getMessage()]
            );
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(new PsrMessage(
                'Unable to delete the search index for the deleted resource #{resource_id} in search engine "{name}": see log.', // @translate
                ['resource_id' => $id, 'name' => $searchEngine->name()]
            ));
        }
    }

    /**
     * Update the index in search engine for a resource.
     *
     * @param SearchEngineRepresentation $searchEngine
     * @param Resource $resource
     */
    protected function updateIndexResource(SearchEngineRepresentation $searchEngine, Resource $resource): void
    {
        $indexer = $searchEngine->indexer();
        try {
            $indexer->indexResource($resource);
        } catch (\Exception $e) {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $logger->err(
                'Unable to index metadata of resource #{resource_id} for search in search engine "{name}": {message}', // @translate
                ['resource_id' => $resource->getId(), 'name' => $searchEngine->name(), 'message' => $e->getMessage()]
            );
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(new PsrMessage(
                'Unable to update the search index for resource #{resource_id} in search engine "{name}": see log.', // @translate
                ['resource_id' => $resource->getId(), 'name' => $searchEngine->name()]
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
            if ($params['controller'] === \AdvancedSearch\Controller\SearchController::class) {
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
        $view->vars()->offsetSet('searchConfig', $searchConfig);
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
        $helpers = $services->get('ViewHelperManager');
        if ($helpers->has('defaultSite')) {
            $defaultSite = $helpers->get('defaultSite');
            $site = $defaultSite();
        } else {
            $defaultSite = (int) $settings->get('default_site');
            if ($defaultSite) {
                try {
                    $site = $api->read('sites', ['id' => $defaultSite])->getContent();
                } catch (\Exception $e) {
                }
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
        $siteSettings->set('advancedsearch_redirect_itemset', 'first');
    }

    protected function installResources(): void
    {
        $this->createDefaultSearchConfig();
    }

    protected function createDefaultSearchConfig(): int
    {
        // Note: during installation or upgrade, the api may not be available
        // for the search api adapters, so use direct sql queries.

        $services = $this->getServiceLocator();

        /**
         * @var \Laminas\I18n\View\Helper\Translate $translate
         *
         * Translate is used in search_config.default.php.
         */
        $translate = $services->get('ViewHelperManager')->get('translate');
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        // Check if the internal index exists.
        $sqlSearchEngineId = <<<'SQL'
SELECT `id`
FROM `search_engine`
WHERE `adapter` = "internal"
ORDER BY `id` ASC;
SQL;
        $searchEngineId = (int) $connection->fetchOne($sqlSearchEngineId);

        if (!$searchEngineId) {
            // Create the internal adapter.
            $sql = <<<'SQL'
INSERT INTO `search_engine`
(`name`, `adapter`, `settings`, `created`)
VALUES
(?, ?, ?, NOW());
SQL;
            $searchEngineConfig = require __DIR__ . '/data/configs/search_engine.internal.php';
            $connection->executeStatement($sql, [
                $searchEngineConfig['o:name'],
                $searchEngineConfig['o:adapter'],
                json_encode($searchEngineConfig['o:settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $searchEngineId = $connection->fetchOne($sqlSearchEngineId);
            $message = new PsrMessage(
                'The internal search engine (sql) can be edited in the {link_url}search manager{link_end}.', // @translate
                [
                    // Don't use the url helper, the route is not available during install.
                    'link_url' => sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager/engine/' . $searchEngineId . '/edit'),
                    'link_end' => '</a>',
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        // Check if the internal suggester exists.
        $sqlSuggesterId = <<<SQL
SELECT `id`
FROM `search_suggester`
WHERE `engine_id` = $searchEngineId
ORDER BY `id` ASC
LIMIT 1;
SQL;
        $suggesterId = (int) $connection->fetchOne($sqlSuggesterId);

        if (!$suggesterId) {
            // Create the internal suggester.
            $sql = <<<SQL
INSERT INTO `search_suggester`
(`engine_id`, `name`, `settings`, `created`)
VALUES
($searchEngineId, 'Internal suggester (sql)', ?, NOW());
SQL;
            $suggesterSettings = require __DIR__ . '/data/configs/search_suggester.internal.php';
            $connection->executeStatement($sql, [
                json_encode($suggesterSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $suggesterId = (int) $connection->fetchOne($sqlSuggesterId);
            $message = new PsrMessage(
                'The {link_url}internal suggester{link_end} (sql) will be available after indexation.', // @translate
                [
                    // Don't use the url helper, the route is not available during install.
                    'link_url' => sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager/suggester/' . $suggesterId . '/edit'),
                    'link_end' => '</a>',
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        // Check if the default search config exists.
        $sqlSearchConfigId = <<<SQL
SELECT `id`
FROM `search_config`
WHERE `engine_id` = $searchEngineId
ORDER BY `id` ASC;
SQL;
        $searchConfigId = (int) $connection->fetchOne($sqlSearchConfigId);

        if (!$searchConfigId) {
            $sql = <<<SQL
INSERT INTO `search_config`
(`engine_id`, `name`, `path`, `form_adapter`, `settings`, `created`)
VALUES
($searchEngineId, ?, ?, ?, ?, NOW());
SQL;
            $searchConfigConfig = require __DIR__ . '/data/configs/search_config.default.php';
            $connection->executeStatement($sql, [
                $searchConfigConfig['o:name'],
                $searchConfigConfig['o:path'],
                $searchConfigConfig['o:form'],
                json_encode($searchConfigConfig['o:settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $searchConfigId = $connection->fetchOne($sqlSearchConfigId);
            $message = new PsrMessage(
                'The default search config can be {link_url}edited{link_end} and configured in the {link_url_2}main settings{link_end} for admin search and in each site settings for public search.', // @translate
                [
                    // Don't use the url helper, the route is not available during install.
                    'link_url' => sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager/config/' . $searchConfigId . '/edit'),
                    'link_end' => '</a>',
                    'link_url_2' => sprintf('<a href="%s">', $urlHelper('admin') . '/setting#advancedsearch_main_config'),
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        return (int) $searchConfigId;
    }
}
