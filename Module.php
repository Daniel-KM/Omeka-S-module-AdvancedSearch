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
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\ItemAdapter;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Entity\Resource;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    /**
     * List of property ids by term.
     *
     * @var array
     */
    protected $properties;

    /**
     * List of used property ids by term.
     *
     * @var array
     */
    protected $usedProperties;

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
            'AdvancedSearch\AdapterManager',
            'search_adapters',
            Feature\AdapterProviderInterface::class,
            'getSearchAdapterConfig'
        );
        $serviceListener->addServiceManager(
            'AdvancedSearch\FormAdapterManager',
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

        $this->initSettings();
        $this->installResources();
    }

    protected function initSettings(): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $settings->set('advancedsearch_restrict_used_terms', true);

        $siteSettings = $services->get('Omeka\Settings\Site');
        $defaultSearchFields = include __DIR__ . '/config/module.config.php';
        $defaultSearchFields = $defaultSearchFields['advancedsearch']['site_settings']['advancedsearch_search_fields'];
        /** @var int[] $siteIds */
        $siteIds = $services->get('Omeka\ApiManager')->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
        foreach ($siteIds as $siteId) {
            $siteSettings->setTargetId($siteId);
            $siteSettings->set('advancedsearch_restrict_used_terms', true);
            $siteSettings->set('advancedsearch_search_fields', $defaultSearchFields);
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
            // Because this event does not apply when initialize = false, the
            // api manager has a delegator that does the same.
            $sharedEventManager->attach(
                $adapter,
                'api.search.pre',
                [$this, 'handlePropertiesPreBefore'],
                // Let any other module, except core, to search properties.
                -100
            );
            // Add the search query filters for resources.
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'handleApiSearchQuery'],
                // Process before any other module in order to reset query.
                +100
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\Element\PropertySelect::class,
            'form.vocab_member_select.query',
            [$this, 'formVocabMemberSelectQuery']
        );
        $sharedEventManager->attach(
            \Omeka\Form\Element\ResourceClassSelect::class,
            'form.vocab_member_select.query',
            [$this, 'formVocabMemberSelectQuery']
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

        $api = $services->get('Omeka\ApiManager');
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation[] $searchConfigs */
        $searchConfigs = $api->search('search_configs')->getContent();

        $isAdminRequest = $status->isAdminRequest();
        if ($isAdminRequest) {
            $settings = $services->get('Omeka\Settings');
            $adminSearchConfigs = $settings->get('advancedsearch_configs', []);
            foreach ($searchConfigs as $searchConfig) {
                $searchConfigId = $searchConfig->id();
                if (in_array($searchConfigId, $adminSearchConfigs)) {
                    $router->addRoute(
                        'search-admin-page-' . $searchConfigId,
                        [
                            'type' => \Laminas\Router\Http\Segment::class,
                            'options' => [
                                'route' => '/admin/' . $searchConfig->path(),
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
            }
        }

        // Public search pages are required to manage them at site level.
        // The urls use "search-page-" to simplify migration.
        foreach ($searchConfigs as $searchConfig) {
            $searchConfigId = $searchConfig->id();
            $searchConfigSlug = $searchConfig->path();
            $router->addRoute(
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
    public function handlePropertiesPreBefore(Event $event): void
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

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function handleApiSearchQuery(Event $event): void
    {
        /**
         * @var \Doctrine\ORM\QueryBuilder $qb
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter
         * @var \Omeka\Api\Request $request
         */
        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();
        $request = $event->getParam('request');
        $query = $request->getContent();

        // Reset the query for properties.
        $override = $request->getOption('override', []);
        if (!empty($override['property'])) {
            $query['property'] = $override['property'];
            $request->setContent($query);
            $request->setOption('override', null);
        }

        // Process advanced search plus keys.
        $this->searchDateTime($qb, $adapter, $query);
        $this->buildPropertyQuery($qb, $query, $adapter);
        if ($adapter instanceof ItemAdapter) {
            $this->searchHasMedia($qb, $adapter, $query);
            $this->searchHasMediaOriginal($qb, $adapter, $query);
            $this->searchHasMediaThumbnails($qb, $adapter, $query);
            $this->searchItemByMediaType($qb, $adapter, $query);
        } elseif ($adapter instanceof MediaAdapter) {
            $this->searchMediaByItemSet($qb, $adapter, $query);
            $this->searchHasOriginal($qb, $adapter, $query);
            $this->searchHasThumbnails($qb, $adapter, $query);
        }
    }

    public function formVocabMemberSelectQuery(Event $event): void
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
        $translate = $event->getTarget()->plugin('translate');
        $query = $event->getParam('query', []);
        $filters = $event->getParam('filters');

        $query = $this->normalizeDateTime($query);
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
     * Normalize the query for the datetime.
     *
     * @param array $query
     * @return array
     */
    protected function normalizeDateTime(array $query)
    {
        if (empty($query['datetime'])) {
            return $query;
        }

        // Manage a single date time.
        if (!is_array($query['datetime'])) {
            $query['datetime'] = [[
                'joiner' => 'and',
                'field' => 'created',
                'type' => 'eq',
                'value' => $query['datetime'],
            ]];
            return $query;
        }

        foreach ($query['datetime'] as $key => &$queryRow) {
            if (empty($queryRow)) {
                unset($query['datetime'][$key]);
                continue;
            }

            // Clean query and manage default values.
            if (is_array($queryRow)) {
                $queryRow = array_map('mb_strtolower', array_map('trim', $queryRow));
                if (empty($queryRow['joiner'])) {
                    $queryRow['joiner'] = 'and';
                } else {
                    if (!in_array($queryRow['joiner'], ['and', 'or'])) {
                        unset($query['datetime'][$key]);
                        continue;
                    }
                }

                if (empty($queryRow['field'])) {
                    $queryRow['field'] = 'created';
                } else {
                    if (!in_array($queryRow['field'], ['created', 'modified'])) {
                        unset($query['datetime'][$key]);
                        continue;
                    }
                }

                if (empty($queryRow['type'])) {
                    $queryRow['type'] = 'eq';
                } else {
                    // "ex" and "nex" are useful only for the modified time.
                    if (!in_array($queryRow['type'], ['lt', 'lte', 'eq', 'gte', 'gt', 'neq', 'ex', 'nex'])) {
                        unset($query['datetime'][$key]);
                        continue;
                    }
                }

                if (in_array($queryRow['type'], ['ex', 'nex'])) {
                    $query['datetime'][$key]['value'] = '';
                } elseif (empty($queryRow['value'])) {
                    unset($query['datetime'][$key]);
                    continue;
                } else {
                    // Date time cannot be longer than 19 numbers.
                    // But user can choose a year only, etc.
                }
            } else {
                $queryRow = [
                    'joiner' => 'and',
                    'field' => 'created',
                    'type' => 'eq',
                    'value' => $queryRow,
                ];
            }
        }

        return $query;
    }

    /**
     * Build query on date time (created/modified), partial date/time allowed.
     *
     * The query format is inspired by Doctrine and properties.
     *
     * Query format:
     *
     * - datetime[{index}][joiner]: "and" OR "or" joiner with previous query
     * - datetime[{index}][field]: the field "created" or "modified"
     * - datetime[{index}][type]: search type
     *   - gt: greater than (after)
     *   - gte: greater than or equal
     *   - eq: is exactly
     *   - neq: is not exactly
     *   - lte: lower than or equal
     *   - lt: lower than (before)
     *   - ex: has any value
     *   - nex: has no value
     * - datetime[{index}][value]: search date time (sql format: "2017-11-07 17:21:17",
     *   partial date/time allowed ("2018-05", etc.).
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     * @param array $query
     */
    protected function searchDateTime(
        QueryBuilder $qb,
        AbstractResourceEntityAdapter $adapter,
        array $query
    ): void {
        $query = $this->normalizeDateTime($query);
        if (empty($query['datetime'])) {
            return;
        }

        $where = '';
        $expr = $qb->expr();

        foreach ($query['datetime'] as $queryRow) {
            $joiner = $queryRow['joiner'];
            $field = $queryRow['field'];
            $type = $queryRow['type'];
            $value = $queryRow['value'];
            $incorrectValue = false;

            // By default, sql replace missing time by 00:00:00, but this is not
            // clear for the user. And it doesn't allow partial date/time.
            switch ($type) {
                case 'gt':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->gt('omeka_root.' . $field, $param);
                    }
                    break;
                case 'gte':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->gte('omeka_root.' . $field, $param);
                    }
                    break;
                case 'eq':
                    $valueFromNorm = $this->getDateTimeFromValue($value, true);
                    $valueToNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueFromNorm) || is_null($valueToNorm)) {
                        $incorrectValue = true;
                    } else {
                        if ($valueFromNorm === $valueToNorm) {
                            $param = $adapter->createNamedParameter($qb, $valueFromNorm);
                            $predicateExpr = $expr->eq('omeka_root.' . $field, $param);
                        } else {
                            $paramFrom = $adapter->createNamedParameter($qb, $valueFromNorm);
                            $paramTo = $adapter->createNamedParameter($qb, $valueToNorm);
                            $predicateExpr = $expr->between('omeka_root.' . $field, $paramFrom, $paramTo);
                        }
                    }
                    break;
                case 'neq':
                    $valueFromNorm = $this->getDateTimeFromValue($value, true);
                    $valueToNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueFromNorm) || is_null($valueToNorm)) {
                        $incorrectValue = true;
                    } else {
                        if ($valueFromNorm === $valueToNorm) {
                            $param = $adapter->createNamedParameter($qb, $valueFromNorm);
                            $predicateExpr = $expr->neq('omeka_root.' . $field, $param);
                        } else {
                            $paramFrom = $adapter->createNamedParameter($qb, $valueFromNorm);
                            $paramTo = $adapter->createNamedParameter($qb, $valueToNorm);
                            $predicateExpr = $expr->not(
                                $expr->between('omeka_root.' . $field, $paramFrom, $paramTo)
                            );
                        }
                    }
                    break;
                case 'lte':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->lte('omeka_root.' . $field, $param);
                    }
                    break;
                case 'lt':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $param = $adapter->createNamedParameter($qb, $valueNorm);
                        $predicateExpr = $expr->lt('omeka_root.' . $field, $param);
                    }
                    break;
                case 'ex':
                    $predicateExpr = $expr->isNotNull('omeka_root.' . $field);
                    break;
                case 'nex':
                    $predicateExpr = $expr->isNull('omeka_root.' . $field);
                    break;
                default:
                    continue 2;
            }

            // Avoid to get results with some incorrect query.
            if ($incorrectValue) {
                $param = $adapter->createNamedParameter($qb, 'incorrect value: ' . $queryRow['value']);
                $predicateExpr = $expr->eq('omeka_root.' . $field, $param);
                $joiner = 'and';
            }

            // First expression has no joiner.
            if ($where === '') {
                $where = '(' . $predicateExpr . ')';
            } elseif ($joiner === 'or') {
                $where .= ' OR (' . $predicateExpr . ')';
            } else {
                $where .= ' AND (' . $predicateExpr . ')';
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Build query to check if an item has media or not.
     *
     * The argument uses "has_media", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param ItemAdapter $adapter
     * @param array $query
     */
    protected function searchHasMedia(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query
    ): void {
        if (!isset($query['has_media'])) {
            return;
        }

        $value = (string) $query['has_media'];
        if ($value === '') {
            return;
        }

        $expr = $qb->expr();

        // With media.
        $mediaAlias = $adapter->createAlias();
        if ($value) {
            $qb->innerJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                Join::WITH,
                $expr->eq($mediaAlias . '.item', 'omeka_root.id')
            );
        }
        // Without media.
        else {
            $qb
                ->leftJoin(
                    \Omeka\Entity\Media::class,
                    $mediaAlias,
                    Join::WITH,
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id')
                )
                ->andWhere($expr->isNull($mediaAlias . '.id'));
        }
    }

    /**
     * Build query to check if an item has an original file or not.
     *
     * The argument uses "has_original", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param ItemAdapter $adapter
     * @param array $query
     */
    protected function searchHasMediaOriginal(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query
    ): void {
        $this->searchHasMediaSpecific($qb, $adapter, $query, 'has_original');
    }

    /**
     * Build query to check if an item has thumbnails or not.
     *
     * The argument uses "has_thumbnails", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param ItemAdapter $adapter
     * @param array $query
     */
    protected function searchHasMediaThumbnails(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query
    ): void {
        $this->searchHasMediaSpecific($qb, $adapter, $query, 'has_thumbnails');
    }

    /**
     * Build query to check if an item has an original file or thumbnails or not.
     *
     * @param QueryBuilder $qb
     * @param ItemAdapter $adapter
     * @param array $query
     * @param string $field "has_original" or "has_thumbnails".
     */
    protected function searchHasMediaSpecific(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query,
        $field
    ): void {
        if (!isset($query[$field])) {
            return;
        }

        $value = (string) $query[$field];
        if ($value === '') {
            return;
        }

        $expr = $qb->expr();
        $fields = [
            'has_original' => 'hasOriginal',
            'has_thumbnails' => 'hasThumbnails',
        ];

        // With original media.
        $mediaAlias = $adapter->createAlias();
        if ($value) {
            $qb->innerJoin(
                \Omeka\Entity\Media::class,
                $mediaAlias,
                Join::WITH,
                $expr->andX(
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id'),
                    $expr->eq($mediaAlias . '.' . $fields[$field], 1)
                )
            );
        }
        // Without original media.
        else {
            $qb
                ->leftJoin(
                    \Omeka\Entity\Media::class,
                    $mediaAlias,
                    Join::WITH,
                    $expr->eq($mediaAlias . '.item', 'omeka_root.id')
                )
                ->andWhere($expr->orX(
                    $expr->isNull($mediaAlias . '.id'),
                    $expr->eq($mediaAlias . '.' . $fields[$field], 0)
            ));
        }
    }

    /**
     * Build query to check if media types.
     *
     * @param QueryBuilder $qb
     * @param ItemAdapter $adapter
     * @param array $query
     */
    protected function searchItemByMediaType(
        QueryBuilder $qb,
        ItemAdapter $adapter,
        array $query
    ): void {
        if (!isset($query['media_types'])) {
            return;
        }

        $values = is_array($query['media_types'])
            ? $query['media_types']
            : [$query['media_types']];
        $values = array_filter(array_map('trim', $values));
        if (empty($values)) {
            return;
        }

        $mediaAlias = $adapter->createAlias();
        $expr = $qb->expr();

        $qb->innerJoin(
            \Omeka\Entity\Media::class,
            $mediaAlias,
            Join::WITH,
            $expr->andX(
                $expr->eq($mediaAlias . '.item', 'omeka_root.id'),
                $expr->in(
                    $mediaAlias . '.mediaType',
                    $adapter->createNamedParameter($qb, $values)
                )
            )
        );
    }

    /**
     * Build query to check if a media has an original file or not.
     *
     * The argument uses "has_original", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param MediaAdapter $adapter
     * @param array $query
     */
    protected function searchHasOriginal(
        QueryBuilder $qb,
        MediaAdapter $adapter,
        array $query
    ): void {
        $this->searchMediaSpecific($qb, $adapter, $query, 'has_original');
    }

    /**
     * Build query to check if a media has thumbnails or not.
     *
     * The argument uses "has_thumbnails", with value "1" or "0".
     *
     * @param QueryBuilder $qb
     * @param MediaAdapter $adapter
     * @param array $query
     */
    protected function searchHasThumbnails(
        QueryBuilder $qb,
        MediaAdapter $adapter,
        array $query
    ): void {
        $this->searchMediaSpecific($qb, $adapter, $query, 'has_thumbnails');
    }

    /**
     * Build query to check if a media has an original file or thumbnails or not.
     *
     * @param QueryBuilder $qb
     * @param MediaAdapter $adapter
     * @param array $query
     * @param string $field "has_original" or "has_thumbnails".
     */
    protected function searchMediaSpecific(
        QueryBuilder $qb,
        MediaAdapter $adapter,
        array $query,
        $field
    ): void {
        if (!isset($query[$field])) {
            return;
        }

        $value = (string) $query[$field];
        if ($value === '') {
            return;
        }

        $fields = [
            'has_original' => 'hasOriginal',
            'has_thumbnails' => 'hasThumbnails',
        ];
        $qb
            ->andWhere($qb->expr()->eq('omeka_root.' . $fields[$field], (int) (bool) $value));
    }

    /**
     * Build query to search media by item set.
     *
     * @param QueryBuilder $qb
     * @param MediaAdapter $adapter
     * @param array $query
     */
    protected function searchMediaByItemSet(
        QueryBuilder $qb,
        MediaAdapter $adapter,
        array $query
    ): void {
        if (!isset($query['item_set_id'])) {
            return;
        }

        $itemSets = $query['item_set_id'];
        if (!is_array($itemSets)) {
            $itemSets = [$itemSets];
        }
        $itemSets = array_filter($itemSets, 'is_numeric');

        if ($itemSets) {
            $expr = $qb->expr();
            $itemAlias = $adapter->createAlias();
            $itemSetAlias = $adapter->createAlias();
            $qb
                ->leftJoin(
                    'omeka_root.item',
                    $itemAlias, 'WITH',
                    $expr->eq("$itemAlias.id", 'omeka_root.item')
                )
                ->innerJoin(
                    $itemAlias . '.itemSets',
                    $itemSetAlias, 'WITH',
                    $expr->in("$itemSetAlias.id", $adapter->createNamedParameter($qb, $itemSets))
                );
        }
    }

    /**
     * Build query on value.
     *
     * Pseudo-override \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     * via the api manager delegator.
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" joiner with previous query
     * - property[{index}][property]: property ID
     * - property[{index}][text]: search text
     * - property[{index}][type]: search type
     *   - eq: is exactly (core)
     *   - neq: is not exactly (core)
     *   - in: contains (core)
     *   - nin: does not contain (core)
     *   - ex: has any value (core)
     *   - nex: has no value (core)
     *   - list: is in list
     *   - nlist: is not in list
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - res: has resource
     *   - nres: has no resource
     *
     * @param QueryBuilder $qb
     * @param array $query
     * @param AbstractResourceEntityAdapter $adapter
     */
    protected function buildPropertyQuery(QueryBuilder $qb, array $query, AbstractResourceEntityAdapter $adapter): void
    {
        if (!isset($query['property']) || !is_array($query['property'])) {
            return;
        }

        $valuesJoin = 'omeka_root.values';
        $where = '';
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);
        };

        $entityManager = $adapter->getEntityManager();

        foreach ($query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }
            $propertyId = $queryRow['property'];
            $excludePropertyIds = $propertyId || empty($queryRow['except']) ? false : $queryRow['except'];
            $queryType = $queryRow['type'];
            $joiner = $queryRow['joiner'] ?? '';
            $value = $queryRow['text'] ?? '';

            // A value can be an array with types "list" and "nlist".
            if (!is_array($value)
                && !strlen((string) $value)
                && $queryType !== 'nex'
                && $queryType !== 'ex'
            ) {
                continue;
            }

            $valuesAlias = $adapter->createAlias();
            $positive = true;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                        $predicateExpr = $expr->orX(
                            $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                            $expr->eq("$valuesAlias.value", $param),
                            $expr->eq("$valuesAlias.uri", $param)
                        );
                    break;

                case 'nin':
                    $positive = false;
                    // no break.
                case 'in':
                    $param = $adapter->createNamedParameter($qb, '%' . $escape($value) . '%');
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                        $predicateExpr = $expr->orX(
                            $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                            $expr->like("$valuesAlias.value", $param),
                            $expr->like("$valuesAlias.uri", $param)
                        );
                    break;

                case 'nlist':
                    $positive = false;
                    // no break.
                case 'list':
                    $list = is_array($value) ? $value : explode("\n", $value);
                    $list = array_filter(array_map('trim', array_map('strval', $list)), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $adapter->createNamedParameter($qb, $list);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->in("$subqueryAlias.title", $param));
                        $predicateExpr = $expr->orX(
                            $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                            $expr->in("$valuesAlias.value", $param),
                            $expr->in("$valuesAlias.uri", $param)
                        );
                    break;

                case 'nsw':
                    $positive = false;
                    // no break.
                case 'sw':
                    $param = $adapter->createNamedParameter($qb, $escape($value) . '%');
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                        $predicateExpr = $expr->orX(
                            $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                            $expr->like("$valuesAlias.value", $param),
                            $expr->like("$valuesAlias.uri", $param)
                        );
                    break;

                case 'new':
                    $positive = false;
                    // no break.
                case 'ew':
                    $param = $adapter->createNamedParameter($qb, '%' . $escape($value));
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                        $predicateExpr = $expr->orX(
                            $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                            $expr->like("$valuesAlias.value", $param),
                            $expr->like("$valuesAlias.uri", $param)
                        );
                    break;

                case 'nres':
                    $positive = false;
                    // no break.
                case 'res':
                    $predicateExpr = $expr->eq(
                    "$valuesAlias.valueResource",
                    $adapter->createNamedParameter($qb, $value)
                    );
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                default:
                    continue 2;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected
            if ($propertyId) {
                $propertyId = $this->getPropertyId($propertyId);
                $joinConditions[] = $expr->eq("$valuesAlias.property", $propertyId);
            } elseif ($excludePropertyIds) {
                $excludePropertyIds = is_array($excludePropertyIds)
                ? array_map([$this, 'getPropertyId'], $excludePropertyIds)
                : [$this->getPropertyId($excludePropertyIds)];
                $excludePropertyIds = array_filter($excludePropertyIds);
                // Use standard query if nothing to exclude, else limit search.
                if (count($excludePropertyIds)) {
                    // The aim is to search anywhere except ocr content.
                    // Use not positive + in() or notIn()? A full list is simpler.
                    $otherIds = array_diff($this->usedProperties, $excludePropertyIds);
                    $joinConditions[] = $expr->in("$valuesAlias.property", $otherIds);
                }
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, 'WITH', $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }

            if ($where == '') {
                $where = $whereClause;
            } elseif ($joiner == 'or') {
                $where .= " OR $whereClause";
            } else {
                $where .= " AND $whereClause";
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Convert into a standard DateTime. Manage some badly formatted values.
     *
     * Adapted from module NumericDataType.
     * The main difference is the max/min date: from 1000 to 9999. Since fields
     * are "created" and "modified", other dates are removed.
     * The regex pattern allows partial month and day too.
     * @link https://mariadb.com/kb/en/datetime/
     * @see \NumericDataTypes\DataType\AbstractDateTimeDataType::getDateTimeFromValue()
     *
     * Allow mysql datetime too, not only iso 8601 (so with a space, not only a
     * "T" to separate date and time).
     *
     * @param string $value
     * @param bool $defaultFirst
     * @return array|null
     */
    protected function getDateTimeFromValue($value, $defaultFirst = true)
    {
        // $yearMin = -292277022656;
        // $yearMax = 292277026595;
        $yearMin = 1000;
        $yearMax = 9999;
        $patternIso8601 = '^(?<date>(?<year>-?\d{4,})(-(?<month>\d{1,2}))?(-(?<day>\d{1,2}))?)(?<time>((?:T| )(?<hour>\d{1,2}))?(:(?<minute>\d{1,2}))?(:(?<second>\d{1,2}))?)(?<offset>((?<offset_hour>[+-]\d{1,2})?(:(?<offset_minute>\d{1,2}))?)|Z?)$';
        static $dateTimes = [];

        $firstOrLast = $defaultFirst ? 'first' : 'last';
        if (isset($dateTimes[$value][$firstOrLast])) {
            return $dateTimes[$value][$firstOrLast];
        }

        $dateTimes[$value][$firstOrLast] = null;

        // Match against ISO 8601, allowing for reduced accuracy.
        $matches = [];
        if (!preg_match(sprintf('/%s/', $patternIso8601), $value, $matches)) {
            return null;
        }

        // Remove empty values.
        $matches = array_filter($matches);

        // An hour requires a day.
        if (isset($matches['hour']) && !isset($matches['day'])) {
            return null;
        }

        // An offset requires a time.
        if (isset($matches['offset']) && !isset($matches['time'])) {
            return null;
        }

        // Set the datetime components included in the passed value.
        $dateTime = [
            'value' => $value,
            'date_value' => $matches['date'],
            'time_value' => $matches['time'] ?? null,
            'offset_value' => $matches['offset'] ?? null,
            'year' => (int) $matches['year'],
            'month' => isset($matches['month']) ? (int) $matches['month'] : null,
            'day' => isset($matches['day']) ? (int) $matches['day'] : null,
            'hour' => isset($matches['hour']) ? (int) $matches['hour'] : null,
            'minute' => isset($matches['minute']) ? (int) $matches['minute'] : null,
            'second' => isset($matches['second']) ? (int) $matches['second'] : null,
            'offset_hour' => isset($matches['offset_hour']) ? (int) $matches['offset_hour'] : null,
            'offset_minute' => isset($matches['offset_minute']) ? (int) $matches['offset_minute'] : null,
        ];

        // Set the normalized datetime components. Each component not included
        // in the passed value is given a default value.
        $dateTime['month_normalized'] = $dateTime['month'] ?? ($defaultFirst ? 1 : 12);
        // The last day takes special handling, as it depends on year/month.
        $dateTime['day_normalized'] = $dateTime['day']
        ?? ($defaultFirst ? 1 : self::getLastDay($dateTime['year'], $dateTime['month_normalized']));
        $dateTime['hour_normalized'] = $dateTime['hour'] ?? ($defaultFirst ? 0 : 23);
        $dateTime['minute_normalized'] = $dateTime['minute'] ?? ($defaultFirst ? 0 : 59);
        $dateTime['second_normalized'] = $dateTime['second'] ?? ($defaultFirst ? 0 : 59);
        $dateTime['offset_hour_normalized'] = $dateTime['offset_hour'] ?? 0;
        $dateTime['offset_minute_normalized'] = $dateTime['offset_minute'] ?? 0;
        // Set the UTC offset (+00:00) if no offset is provided.
        $dateTime['offset_normalized'] = isset($dateTime['offset_value'])
        ? ('Z' === $dateTime['offset_value'] ? '+00:00' : $dateTime['offset_value'])
        : '+00:00';

        // Validate ranges of the datetime component.
        if (($yearMin > $dateTime['year']) || ($yearMax < $dateTime['year'])) {
            return null;
        }
        if ((1 > $dateTime['month_normalized']) || (12 < $dateTime['month_normalized'])) {
            return null;
        }
        if ((1 > $dateTime['day_normalized']) || (31 < $dateTime['day_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['hour_normalized']) || (23 < $dateTime['hour_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['minute_normalized']) || (59 < $dateTime['minute_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['second_normalized']) || (59 < $dateTime['second_normalized'])) {
            return null;
        }
        if ((-23 > $dateTime['offset_hour_normalized']) || (23 < $dateTime['offset_hour_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['offset_minute_normalized']) || (59 < $dateTime['offset_minute_normalized'])) {
            return null;
        }

        // Adding the DateTime object here to reduce code duplication. To ensure
        // consistency, use Coordinated Universal Time (UTC) if no offset is
        // provided. This avoids automatic adjustments based on the server's
        // default timezone.
        // With strict type, "now" is required.
        $dateTime['date'] = new \DateTime('now', new \DateTimeZone($dateTime['offset_normalized']));
        $dateTime['date']
            ->setDate(
                $dateTime['year'],
                $dateTime['month_normalized'],
                $dateTime['day_normalized']
            )
            ->setTime(
                $dateTime['hour_normalized'],
                $dateTime['minute_normalized'],
                $dateTime['second_normalized']
            );

        // Cache the date/time as a sql date time.
        $dateTimes[$value][$firstOrLast] = $dateTime['date']->format('Y-m-d H:i:s');
        return $dateTimes[$value][$firstOrLast];
    }

    /**
     * Get the last day of a given year/month.
     *
     * @param int $year
     * @param int $month
     * @return int
     */
    protected function getLastDay($year, $month)
    {
        switch ($month) {
            case 2:
                // February (accounting for leap year)
                $leapYear = date('L', mktime(0, 0, 0, 1, 1, $year));
                return $leapYear ? 29 : 28;
            case 4:
            case 6:
            case 9:
            case 11:
                // April, June, September, November
                return 30;
            default:
                // January, March, May, July, August, October, December
                return 31;
        }
    }

    /**
     * Get a property id by JSON-LD term or by numeric id.
     *
     * Prepare the list of properties and used properties too.
     */
    public function getPropertyId($termOrId): int
    {
        if (is_null($this->properties)) {
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            $qb = $connection->createQueryBuilder();
            $qb
                ->select([
                    'DISTINCT property.id AS id',
                    'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'property.id',
                ])
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
                ->addGroupBy('property.id')
            ;
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $this->properties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->properties = array_map('intval', array_column($this->properties, 'id', 'term'));

            $qb->innerJoin('property', 'value', 'value', 'property.id = value.property_id');
            $stmt = $connection->executeQuery($qb);
            // Fetch by key pair is not supported by doctrine 2.0.
            $this->usedProperties = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->usedProperties = array_map('intval', array_column($this->usedProperties, 'id', 'term'));
        }

        if (is_numeric($termOrId)) {
            return in_array($termOrId, $this->properties) ? (int) $termOrId : 0;
        }
        return $this->properties[$termOrId] ?? 0;
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

    public function handleSiteSettings(Event $event): void
    {
        // This is an exception, because there is already a fieldset named
        // "search" in the core, so it should be named "advancedsearch_module".

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

        $space = 'advancedsearch_module';

        $fieldset = $services->get('FormElementManager')->get(\AdvancedSearch\Form\SiteSettingsFieldset::class);
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
            if ($searchConfig->subSetting('autosuggest', 'enable')) {
                $autoSuggestUrl = $searchConfig->subSetting('autosuggest', 'url') ?: $searchUrl . '/suggest';
            }
            $plugins->get('headLink')
                ->appendStylesheet($assetUrl('css/advanced-search-admin.css', 'AdvancedSearch'));
            $plugins->get('headScript')
                ->appendScript(sprintf('var searchUrl = %s;', json_encode($searchUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                   . (isset($autoSuggestUrl) ? sprintf("\nvar searchAutosuggestUrl=%s;", json_encode($autoSuggestUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : '')
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
            $searchEngineSettings = ['resources' => ['items', 'item_sets']];
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
