<?php declare(strict_types=1);

/**
 * Advanced Search
 *
 * Improve search with new fields, auto-suggest, filters, facets, specific pages, etc.
 *
 * @copyright BibLibre, 2016-2017
 * @copyright Daniel Berthereau, 2017-2021
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
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
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

    /**
     * @var Listener\SearchResourcesListener
     */
    protected $searchResourcesListener;

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

        $version = $module->getIni('version');
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
                    'AdvancedSearchPlus',
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
                    'Search',
                );
                $messenger->addError($message);
            }
        } else {
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
            $connection->executeUpdate($sql);
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

        /*
         * The listener is stored because it is uses for each adapter and in the
         * method "filterSearchFilters()".
         */
        $this->searchResourcesListener = new Listener\SearchResourcesListener();

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
            // Because this event does not apply when initialize = false, the
            // api manager has a delegator that does the same.
            $sharedEventManager->attach(
                $adapter,
                'api.search.pre',
                [$this, 'onApiSearchPre'],
                // Let any other module, except core, to search properties.
                -100
            );
            // Add the search query filters for resources.
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this->searchResourcesListener, 'onDispatch'],
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
            // Filter the search filters for the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
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

        // Listeners for the indexing for items, item sets and media.

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngine']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngine']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngineMedia']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.batch_update.post',
            [$this, 'postBatchUpdateSearchEngine']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.delete.pre',
            [$this, 'preUpdateSearchEngineMedia']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngineMedia']
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
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            // Suggesters are available only for admins.
            // TODO This first rule duplicates the second, but is needed for a site.
            ->allow(
                null,
                [
                    \AdvancedSearch\Controller\IndexController::class,
                    \AdvancedSearch\Api\Adapter\SearchConfigAdapter::class,
                    \AdvancedSearch\Api\Adapter\SearchEngineAdapter::class,
                ],
                ['read', 'search']
            )
            ->allow(
                null,
                [
                    \AdvancedSearch\Controller\IndexController::class,
                    \AdvancedSearch\Api\Adapter\SearchConfigAdapter::class,
                    \AdvancedSearch\Api\Adapter\SearchEngineAdapter::class,
                ]
            )
            ->allow(
                null,
                [
                    \AdvancedSearch\Entity\SearchConfig::class,
                    \AdvancedSearch\Entity\SearchEngine::class,
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

        $isAdminRequest = $status->isAdminRequest();
        if ($isAdminRequest) {
            $adminSearchConfigs = $settings->get('advancedsearch_configs', []);
            $adminSearchConfigs = array_intersect_key($searchConfigs, array_flip($adminSearchConfigs));
            foreach ($adminSearchConfigs as $searchConfigId => $searchConfigSlug) {
                $router->addRoute(
                    'search-admin-page-' . $searchConfigId,
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
                    ],
                );
            }
            return;
        }

        $siteSlug = $status->getRouteParam('site-slug');
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
                ],
            );
        }
    }

    /**
     * Save key "property" of the original query to process it one time only.
     *
     * @param Event $event
     */
    public function onApiSearchPre(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $query = $request->getContent();
        if (empty($query['property'])) {
            return;
        }
        $request->setOption('override', ['property' => $query['property']]);
        unset($query['property']);
        $request->setContent($query);
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

        if ($this->getServiceLocator()->get('Omeka\Status')->isAdminRequest()) {
            $view = $event->getTarget();
            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('vendor/chosen-js/chosen.css', 'Omeka'))
                ->appendStylesheet($assetUrl('css/advanced-search-admin.css', 'AdvancedSearch'));
            $view->headScript()
                ->appendFile($assetUrl('vendor/chosen-js/chosen.jquery.js', 'Omeka'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('js/advanced-search-admin.js', 'AdvancedSearch'), 'text/javascript', ['defer' => 'defer']);
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
     * @param Event $event
     */
    public function filterSearchFilters(Event $event): void
    {
        $query = $event->getParam('query', []);
        if (empty($query)) {
            return;
        }

        $view = $event->getTarget();
        $translate = $view->plugin('translate');
        $filters = $event->getParam('filters');

        $query = $this->searchResourcesListener->normalizeQueryDateTime($query);
        if (!empty($query['datetime'])) {
            $queryTypes = [
                'gt' => $translate('after'),
                'gte' => $translate('after or on'),
                'eq' => $translate('on'),
                'neq' => $translate('not on'),
                'lte' => $translate('before or on'),
                'lt' => $translate('before'),
                'ex' => $translate('has any date / time'),
                'nex' => $translate('has no date / time'),
            ];

            $value = $query['datetime'];
            $engine = 0;
            foreach ($value as $queryRow) {
                $joiner = $queryRow['joiner'];
                $field = $queryRow['field'];
                $type = $queryRow['type'];
                $datetimeValue = $queryRow['value'];

                $fieldLabel = $field === 'modified' ? $translate('Modified') : $translate('Created');
                $filterLabel = $fieldLabel . ' ' . $queryTypes[$type];
                if ($engine > 0) {
                    if ($joiner === 'or') {
                        $filterLabel = $translate('OR') . ' ' . $filterLabel;
                    } else {
                        $filterLabel = $translate('AND') . ' ' . $filterLabel;
                    }
                }
                $filters[$filterLabel][] = $datetimeValue;
                ++$engine;
            }
        }

        if (isset($query['is_public']) && $query['is_public'] !== '') {
            $value = $query['is_public'] === '0' ? $translate('Private') : $translate('Public');
            $filters[$translate('Visibility')][] = $value;
        }

        if (isset($query['resource_class_term'])) {
            $value = $query['resource_class_term'];
            if ($value) {
                $filterLabel = $translate('Class'); // @translate
                $filters[$filterLabel][] = $value;
            }
        }

        if (isset($query['has_media'])) {
            $value = $query['has_media'];
            if ($value) {
                $filterLabel = $translate('Has media'); // @translate
                $filters[$filterLabel][] = $translate('yes'); // @translate
            } elseif ($value !== '') {
                $filterLabel = $translate('Has media'); // @translate
                $filters[$filterLabel][] = $translate('no'); // @translate
            }
        }

        if (isset($query['has_original'])) {
            $value = $query['has_original'];
            if ($value) {
                $filterLabel = $translate('Has original'); // @translate
                $filters[$filterLabel][] = $translate('yes'); // @translate
            } elseif ($value !== '') {
                $filterLabel = $translate('Has original'); // @translate
                $filters[$filterLabel][] = $translate('no'); // @translate
            }
        }

        if (isset($query['has_thumbnails'])) {
            $value = $query['has_thumbnails'];
            if ($value) {
                $filterLabel = $translate('Has thumbnails'); // @translate
                $filters[$filterLabel][] = $translate('yes'); // @translate
            } elseif ($value !== '') {
                $filterLabel = $translate('Has thumbnails'); // @translate
                $filters[$filterLabel][] = $translate('no'); // @translate
            }
        }

        if (!empty($query['media_types'])) {
            $value = is_array($query['media_types'])
                ? $query['media_types']
                : [$query['media_types']];
            foreach ($value as $subValue) {
                $filterLabel = $translate('Media types'); // @translate
                $filters[$filterLabel][] = $subValue;
            }
        }

        // The query "item_set_id" is already managed by the main search filter.

        $event->setParam('filters', $filters);
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
     * Delete the search engine for a resource.
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
                'Unable to delete the search engine for resource #%d: %s', // @translate
                $id, $e->getMessage()
            ));
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(new Message(
                'Unable to delete the search engine for the deleted resource #%d: see log.', // @translate
                $id
            ));
        }
    }

    /**
     * Update the search engine for a resource.
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
                'Unable to update the search engine for resource #%d: see log.', // @translate
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

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $plugins->get('api')->searchOne('search_configs', ['id' => $searchConfig])->getContent();
        if (!$searchConfig) {
            return;
        }

        $formAdapter = $searchConfig->formAdapter();
        $partialHeaders = $formAdapter ? $formAdapter->getFormPartialHeaders() : null;

        if ($status->isAdminRequest()) {
            $basePath = $plugins->get('basePath');
            $assetUrl = $plugins->get('assetUrl');
            $searchUrl = $basePath('admin/' . $searchConfig->path());
            $autosuggestUrl = $searchConfig->subSetting('autosuggest', 'url');
            if (!$autosuggestUrl) {
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
        $api = $services->get('ControllerPluginManager')->get('api');
        $site = null;
        $searchConfig = null;

        // Take the search config of the default site or the first site, else the
        // default search config.
        $defaultSite = (int) $settings->get('default_site');
        if ($defaultSite) {
            $site = $api->searchOne('sites', ['id' => $defaultSite])->getContent();
        }
        if ($site) {
            $siteSettings->setTargetId($site->id());
            $searchConfigId = (int) $siteSettings->get('advancedsearch_main_config');
        } else {
            $searchConfigId = (int) $settings->get('advancedsearch_main_config');
        }
        if ($searchConfigId) {
            $searchConfig = $api->searchOne('search_configs', ['id' => $searchConfigId])->getContent();
        }
        if (!$searchConfig) {
            $searchConfig = $api->searchOne('search_configs')->getContent();
        }
        if (!$searchConfig) {
            $searchConfigId = $this->createDefaultSearchConfig();
            $searchConfig = $api->searchOne('search_configs', ['id' => $searchConfigId])->getContent();
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
('Internal (sql)', 'internal', ?, NOW());
SQL;
            $searchEngineSettings = [
                'resources' => ['items', 'item_sets'],
            ];
            $connection->executeQuery($sql, [
                json_encode($searchEngineSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
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
                'direct' => true,
                'mode_index' => 'start',
                'mode_search' => 'start',
                'limit' => 25,
                'length' => 50,
                'fields' => [],
                'excluded_fields' => [],
            ];
            $connection->executeQuery($sql, [
                json_encode($suggesterSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            // $suggesterId = $connection->fetchColumn($sqlSuggesterId);
            $message = new Message(
                'The internal suggester (sql) is available. Configure it in the %ssearch manager%s.', // @translate
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
($searchEngineId, 'Default', 'find', 'main', ?, NOW());
SQL;
            $searchConfigSettings = require __DIR__ . '/data/search_configs/default.php';
            $connection->executeQuery($sql, [
                json_encode($searchConfigSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $message = new Message(
                'The default search page is available. Configure it in the %ssearch manager%s, in the main settings (for admin) and in site settings (for public).', // @translate
                // Don't use the url helper, the route is not available during install.
                sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager'),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        return $searchConfigId;
    }
}
