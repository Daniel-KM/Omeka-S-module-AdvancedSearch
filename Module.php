<?php declare(strict_types=1);

/**
 * Advanced Search
 *
 * Improve search with new fields, auto-suggest, filters, facets, specific pages, etc.
 *
 * @copyright BibLibre, 2016-2017
 * @copyright Daniel Berthereau, 2017-2025
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
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.66')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.66'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        if (!$this->isModuleActive('Reference')) {
            $messenger->addWarning('The module Reference is required to use the facets with the default internal adapter, but not for the Solr adapter.'); // @translate
        } elseif (!$this->isModuleVersionAtLeast('Reference', '3.4.52')) {
            $messenger->addWarning(new PsrMessage(
                'The module {module} should be upgraded to version {version} or later.', // @translate
                ['module' => 'Reference', 'version' => '3.4.52']
            ));
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        if (!$this->isModuleActive('Reference')) {
            $messenger->addWarning('The module Reference is required to use the facets with the default internal adapter, but not for the Solr adapter.'); // @translate
        } elseif (!$this->isModuleVersionAtLeast('Reference', '3.4.52')) {
            $messenger->addWarning(new PsrMessage(
                'The module {module} should be upgraded to version {version} or later.', // @translate
                ['module' => 'Reference', 'version' => '3.4.52']
            ));
        }

        // The module is automatically disabled when Search is uninstalled.
        $module = $moduleManager->getModule('SearchSolr');
        if ($module && in_array($module->getState(), [
            \Omeka\Module\Manager::STATE_ACTIVE,
            \Omeka\Module\Manager::STATE_NOT_ACTIVE,
            \Omeka\Module\Manager::STATE_NEEDS_UPGRADE,
        ])) {
            $version = $module->getIni('version');
            if (version_compare($version, '3.5.53', '<')) {
                $messenger->addWarning(new PsrMessage(
                    'The module {module} should be upgraded to version {version} or later.', // @translate
                    ['module' => 'SearchSolr', 'version' => '3.5.53']
                ));
            } elseif ($module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                $messenger->addNotice(new PsrMessage(
                    'The module {module} can be reenabled.', // @translate
                    ['module' => 'SearchSolr']
                ));
            }
        }

        $this->installResources();
    }

    protected function postUpgrade(?string $oldVersion, ?string $newVersion): void
    {
        $this->warnOverriddenSearch();
        $this->postUpgradeAuto($oldVersion, $newVersion);
    }

    /**
     * Adapted:
     * @see \AdvancedSearch\Module::warnOverriddenSearch()
     * @see \AdvancedSearch\Controller\Admin\IndexController::warnOverriddenSearch()
     *
     * @todo Identify modules, blocks and queries that use old features.
     */
    protected function warnOverriddenSearch(): bool
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        /*
        $improvedTemplates = [
            'common/advanced-search/properties-improved'
            'common/advanced-search/resource-class-improved',
            'common/advanced-search/resource-template-improved',
            'common/advanced-search/item-sets-improved',
            'common/advanced-search/site-improved',
            'common/advanced-search/media-type-improved',
            'common/advanced-search/owner-improved',
        ];
        */

        $results = [];
        $searchFields = $settings->get('advancedsearch_search_fields') ?: [];
        // foreach ($searchFields as $searchField) {
        //     if (substr($searchField, -9) === '-improved') {
        //         $results[0] = 'admin';
        //         break;
        //     }
        // }
        if (in_array('common/advanced-search/properties-improved', $searchFields)) {
            $results[0] = 'admin';
        }

        $siteSlugs = $api->search('sites', [], ['returnScalar' => 'slug'])->getContent();
        foreach ($siteSlugs as $siteId => $siteSlug) {
            $siteSettings->setTargetId($siteId);
            $searchFields = $siteSettings->get('advancedsearch_search_fields') ?: [];
            // foreach ($searchFields as $searchField) {
            //     if (substr($searchField, -9) === '-improved') {
            //         $results[$siteId] = $siteSlug;
            //         break;
            //     }
            // }
            if (in_array('common/advanced-search/properties-improved', $searchFields)) {
                $results[$siteId] = $siteSlug;
            }
        }

        if (!count($results)) {
            return false;
        }

        $message = new PsrMessage(
            'The setting to override search element "property" is enabled. This feature will be removed in a future version and should be {link}replaced by the search element "filter"{link_end}. Check your pages and settings. Matching sites: {json}', // @translate
            ['link' => '<a href="https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch#deprecated-improvements-of-the-advanced-search-elements" target="_blank" rel="noopener">', 'link_end' => '</a>', 'json' => json_encode($results, 448)]
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);

        return true;
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
            \Omeka\Api\Adapter\ResourceAdapter::class,
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

            // Omeka S v4.1 does not allow to search fulltext and return scalar.
            // And event "api.search.query.finalize" isn't available for scalar.
            // @see https://github.com/omeka/omeka-s/pull/2224
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'fixSearchFullTextScalar'],
                // Process after Omeka\Module and last.
                -200
            );
        }

        // Manage exception for full text search with resource adapter.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ResourceAdapter::class,
            'api.search.query',
            [$this, 'overrideQueryResourceFullText'],
            // Process after Omeka\Module.
            -10
        );

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
            // Specify fields to filter from the advanced search form.
            // Let other modules append partial first.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'handleViewAdvancedSearch'],
                -100
            );
        }

        // The search pages use the core process to display used filters.
        $sharedEventManager->attach(
            \AdvancedSearch\Controller\SearchController::class,
            'view.search.filters',
            [$this, 'filterSearchFilters']
        );

        // Append json-ld when enabled.
        $sharedEventManager->attach(
            \AdvancedSearch\Controller\SearchController::class,
            'view.browse.after',
            [$this, 'appendBrowseAfter']
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

        // Annotations.
        $sharedEventManager->attach(
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            'api.create.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            'api.update.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            'api.batch_update.pre',
            [$this, 'preBatchUpdateSearchEngine'],
            -100
        );
        $sharedEventManager->attach(
            \Annotate\Api\Adapter\AnnotationAdapter::class,
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
        $siteSlug = $status->getRouteParam('site-slug')
            ?: $services->get('ViewHelperManager')->get('defaultSite')('slug');

        // The search routes are all literal an contains all data.
        // They are all built early. Check is done in controller.

        // To avoid collision with module Search, the routes use the slug.
        // The search slug is stored in options to simplify checks.
        // TODO Where is it used? So keep it for now.

        $isAdminRequest = $status->isAdminRequest();
        if ($isAdminRequest) {
            $baseRoutes = ['search-admin-page-'];
            // Quick check if this is a site admin page. The list is required to
            // create the navigation.
            if ($siteSlug) {
                $baseRoutes[] = 'search-page-';
            }
            foreach ($baseRoutes as $baseRoute) foreach ($searchConfigs as $searchConfigId => $searchConfigSlug) {
                $router->addRoute(
                    $baseRoute . $searchConfigSlug,
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
                                'page-slug' => $searchConfigSlug,
                                'search-slug' => $searchConfigSlug,
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
                                        'page-slug' => $searchConfigSlug,
                                        'search-slug' => $searchConfigSlug,
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
                'search-page-' . $searchConfigSlug,
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
                            'page-slug' => $searchConfigSlug,
                            'search-slug' => $searchConfigSlug,
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
                                    'page-slug' => $searchConfigSlug,
                                    'search-slug' => $searchConfigSlug,
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
                                    'page-slug' => $searchConfigSlug,
                                    'search-slug' => $searchConfigSlug,
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
                                    'page-slug' => $searchConfigSlug,
                                    'search-slug' => $searchConfigSlug,
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }
    }

    public function handleSiteSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'site_settings');
        $this->finalizeSiteSettings();
    }

    protected function finalizeSiteSettings()
    {
        // Prepare a single setting with all values to simplify next checks.
        // Most of the time, the array contains only the default value and
        // sometime a few item sets.

        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        // Don't set default early.
        $redirectBrowse = $siteSettings->get('advancedsearch_redirect_itemset_browse') ?: [];
        $redirectSearch = $siteSettings->get('advancedsearch_redirect_itemset_search') ?: [];
        $redirectSearchFirst = $siteSettings->get('advancedsearch_redirect_itemset_search_first') ?: [];
        $redirectPageUrl = $siteSettings->get('advancedsearch_redirect_itemset_page_url') ?: [];
        $redirectBrowse = array_fill_keys($redirectBrowse, 'browse');
        $redirectSearch = array_fill_keys($redirectSearch, 'search');
        $redirectSearchFirst = array_fill_keys($redirectSearchFirst, 'first');
        // Keep redirect page urls as it: this is already an array with data.

        // Don't use "else" in order to manage bad config. Default is browse.
        $merged = ['default' => 'browse'];
        if (isset($redirectSearchFirst['all'])) {
            $merged = ['default' => 'first'];
            unset($redirectSearchFirst['all']);
        }
        if (isset($redirectSearch['all'])) {
            $merged = ['default' => 'search'];
            unset($redirectSearch['all']);
        }
        if (isset($redirectBrowse['all'])) {
            $merged = ['default' => 'browse'];
            unset($redirectBrowse['all']);
        }

        $merged += $redirectBrowse
            + $redirectSearch
            + $redirectSearchFirst
            + $redirectPageUrl;

        $siteSettings->set('advancedsearch_redirect_itemsets', $merged);
        // Kept for compatibility with old themes.
        $siteSettings->set('advancedsearch_redirect_itemset', $merged['default']);
    }

    /**
     * Clean useless fields and store some keys to process them one time only.
     *
     * @see \AdvancedSearch\Api\ManagerDelegator::search()
     * @see \AdvancedSearch\Stdlib\SearchResources::startOverrideQuery()
     */
    public function startOverrideQuery(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');

        // Don't override for api index search.
        if ($request->getOption('is_index_search')) {
            return;
        }

        /** @see \AdvancedSearch\Stdlib\SearchResources::startOverrideRequest() */
        $this->getServiceLocator()->get('AdvancedSearch\SearchResources')
            ->startOverrideRequest($request);
    }

    /**
     * Reset original fields and process search after core.
     *
     * @see \AdvancedSearch\Api\ManagerDelegator::search()
     * @see \AdvancedSearch\Stdlib\SearchResources::endOverrideQuery()
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

        /** @see \AdvancedSearch\Stdlib\SearchResources::startOverrideRequest() */
        /** @see \AdvancedSearch\Stdlib\SearchResources::buildInitialQuery() */
        $this->getServiceLocator()->get('AdvancedSearch\SearchResources')
            ->endOverrideRequest($request)
            ->setAdapter($adapter)
            // Process the query for overridden keys.
            ->buildInitialQuery($qb, $request->getContent());
    }

    /**
     * Override fulltext for type "resources". The adapter must be set first.
     *
     * This method is set separately because it should be passed after
     * \Omeka\Module.
     *
     * @see \Omeka\Module::searchFullText()
     */
    public function overrideQueryResourceFullText(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');

        // Don't override for api index search.
        if ($request->getOption('is_index_search')) {
            return;
        }

        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();

        /** @see \AdvancedSearch\Stdlib\SearchResources::searchResourcesFullText() */
        $this->getServiceLocator()->get('AdvancedSearch\SearchResources')
            ->setAdapter($adapter)
            ->searchResourcesFullText($qb, $request->getContent());
    }

    /**
     * Process fix when searching fulltext and returning scalar ids.
     *
     * The option "require_fix_2224" is set when a scalar search with full text
     * sort is prepared in query builder.
     *
     * The fix is set only for the internal querier.
     *
     * @see https://github.com/omeka/omeka-s/pull/2224
     */
    public function fixSearchFullTextScalar(Event $event): void
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        if (!$request->getOption('require_fix_2224')) {
            return;
        }

        /** @var \Doctrine\ORM\QueryBuilder $qb */
        $qb = $event->getParam('queryBuilder');
        $query = $request->getContent();

        // A full text search cannot be "*" alone. Anyway it has no meaning.
        $qValue = $qb->getParameter('omeka_fulltext_search');
        if (in_array($qValue, [null, '', '*'], true)) {
            return;
        }

        $scalarField = $request->getOption('returnScalar') ?? $query['return_scalar'] ?? 'id';
        $matchOrder = 'MATCH(omeka_fulltext_search.title, omeka_fulltext_search.text) AGAINST (:omeka_fulltext_search)';

        $fixQb = clone $qb;
        $fixQb
            ->select(['omeka_root.id' => 'omeka_root.' . $scalarField])
            ->addSelect($matchOrder . ' AS HIDDEN orderMatch')
            ->addGroupBy('orderMatch');
        $content = array_column($fixQb->getQuery()->getScalarResult(), $scalarField, 'id');

        // The response is not yet available, so store results as options of
        // the request.
        $request
            ->setOption('results', $content)
            ->setOption('total_results', count($content));

        // Remove the order from main query and limit results and return a fake
        // result that is detected early by mariadb/mysql.
        $qb
            ->resetDQLPart('orderBy')
            ->setMaxResults(0)
            ->andWhere('1 = 0');
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

        $services = $this->getServiceLocator();
        $plugins = $view->getHelperPluginManager();
        $status = $services->get('Omeka\Status');
        $config = $services->get('Config');
        $assetUrl = $plugins->get('assetUrl');
        $headLink = $plugins->get('headLink');
        $headScript = $plugins->get('headScript');
        $searchFields = $config['advancedsearch']['search_fields'];

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

        // Adapted from application/view/common/advanced-search.phtml.
        /** @see application/view/common/advanced-search.phtml */

        $partials = $event->getParam('partials', []);
        $resourceType = $event->getParam('resourceType');

        $resourceNames = [
            'item' => 'items',
            'media' => 'media',
            'item-set' => 'item_sets',
            'value-annotation' => 'value_annotations',
            'annotation' => 'annotations',
            'resource' => 'resources',
        ];
        $resourceName = $resourceNames[$resourceType] ?? $resourceType;

        // List partials for the current resource type with AdvancedSearch.
        // The list is reordered below.
        $improveds = [];
        foreach ($searchFields as $partial => $searchFieldData) {
            if (isset($searchFieldData['module'])
                && $searchFieldData['module'] === 'AdvancedSearch'
                && (empty($searchFieldData['resource_type']) || in_array($resourceName, $searchFieldData['resource_type']))
            ) {
                $partials[] = $partial;
            }
            if (!empty($searchFieldData['improve'])) {
                $improveds[$partial] = $searchFieldData['improve'];
            }
        }

        $partials = array_unique($partials);

        // Check improved/deprecated features.
        $isSite = $status->isSiteRequest();
        if ($isSite) {
            $siteSettings = $services->get('Omeka\Settings\Site');
            $selectedSearchFields = $siteSettings->get('advancedsearch_search_fields', array_filter($searchFields, fn ($v) => !isset($v['site']) || $v['site']));
        } else {
            $settings = $services->get('Omeka\Settings');
            $selectedSearchFields = $settings->get('advancedsearch_search_fields', $searchFields);
        }

        // Manage core option to restrict list of resource templates.
        if ($isSite) {
            if ($siteSettings->get('search_restrict_templates')) {
                if (($key = array_search('common/advanced-search/resource-template', $partials)) !== false) {
                    $partials[$key] = 'common/advanced-search/resource-template-restrict';
                }
                if (($key = array_search('common/advanced-search/resource-template-improved', $partials)) !== false) {
                    $partials[$key] = 'common/advanced-search/resource-template-restrict';
                }
            } elseif (($key = array_search('common/advanced-search/resource-template-restrict', $partials)) !== false) {
                unset($partials[$key]);
            }
        } elseif (($key = array_search('common/advanced-search/resource-template-restrict', $partials)) !== false) {
            unset($partials[$key]);
        }

        // Reorder, deduplicate the list and filter with selected list.
        $partials = array_values(array_intersect(array_keys($searchFields), array_unique($partials), $selectedSearchFields));

        // Avoid duplicates on improved partials: keep improved partials only.
        foreach ($improveds as $improved => $base) {
            if (($key = array_search($base, $partials)) !== false
                && array_search($improved, $partials) !== false
            ) {
                unset($partials[$key]);
            }
        }

        // Clean query early to avoid issues and deprecation notices.
        // In particular, some arguments support array, but only one in form.
        $query = $event->getParam('query', []);
        if ($query) {
            if (!empty($query['property'])) {
                // TODO Clean joiner and type for property.
                if (in_array('common/advanced-search/properties-improved', $partials)) {
                    foreach ($query['property'] as $propertyField) {
                        if (array_key_exists('property', $propertyField) && !is_array($propertyField['property'])) {
                            $propertyField['property'] = (array) $propertyField['property'];
                        }
                        if (array_key_exists('text', $propertyField) && is_array($propertyField['text'])) {
                            $propertyField['text'] = count($propertyField['text']) === 1 ? reset($propertyField['text']) : '';
                        }
                    }
                }
                if (in_array('common/advanced-search/properties', $partials)) {
                    foreach ($query['property'] as $propertyField) {
                        // TODO Clean joiner and type for property.
                        if (isset($propertyField['property']) && is_array($propertyField['property'])) {
                            $propertyField['property'] = count($propertyField['property']) === 1 ? reset($propertyField['property']) : '';
                        }
                        if (isset($propertyField['text']) && is_array($propertyField['text'])) {
                            $propertyField['text'] = count($propertyField['text']) === 1 ? reset($propertyField['text']) : '';
                        }
                    }
                }
            }
            if (in_array('common/advanced-search/media-type-improved', $partials) && array_key_exists('media_type', $query) && !is_array($query['media_type'])) {
                $query['media_type'] = (array) $query['media_type'];
            } elseif (in_array('common/advanced-search/media-type', $partials) && array_key_exists('media_type', $query) && is_array($query['media_type'])) {
                $query['media_type'] = $query['media_type'] ? reset($query['media_type']) : '';
            }
            if (array_key_exists('owner_id', $query) && is_array($query['owner_id'])) {
                $query['owner_id'] = $query['owner_id'] ? (int) reset($query['owner_id']) : '';
            }
            if (array_key_exists('site_id', $query) && is_array($query['site_id'])) {
                $query['site_id'] = $query['site_id'] ? (int) reset($query['site_id']) : '';
            }
            if (array_key_exists('asset_id', $query) && is_array($query['asset_id'])) {
                $query['asset_id'] = $query['asset_id'] ? (int) reset($query['asset_id']) : '';
            }
        }
        $event->setParam('query', $query);

        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * The search filter helper is overridden in order to manage improved
     * filters (url) and new filters. Furthermore, the event manage the specific
     * arguments of the advanced search.
     * This process allows to manage filtering for standard browse, filtering by
     * other modules and filtering for advanced search.
     *
     * @see \Omeka\View\Helper\SearchFilters
     * @see \AdvancedSearch\View\Helper\SearchFilters
     * @see \AdvancedSearch\View\Helper\SearchingFilters
     */
    public function filterSearchFilters(Event $event): void
    {
        $query = $event->getParam('query', []);
        if (empty($query)) {
            return;
        }

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $query['__searchConfig'] ?? null;
        if (!$searchConfig) {
            return;
        }

        $view = $event->getTarget();
        $filters = $event->getParam('filters');

        $filters = $view->searchingFilters()
            ->filterSearchingFilters($searchConfig, $query, $filters);

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
                && in_array($resourceType, $searchEngine->setting('resource_types', []))
            ) {
                $resourcesToIndex = $this->filterVisibility($resources);
                try {
                    $indexer->indexResources($resourcesToIndex);
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
     * @see \Omeka\Module::attachListeners().
     */
    public function appendBrowseAfter(Event $event): void
    {
        /**
         * @var \Omeka\Mvc\Status $status
         */
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');
        if (($status->isAdminRequest() && !$services->get('Omeka\Settings')->get('disable_jsonld_embed'))
            || ($status->isSiteRequest() && !$services->get('Omeka\Settings\Site')->get('disable_jsonld_embed'))
        ) {
            /**
             * @var \AdvancedSearch\Response $respoonse
             * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
             */
            $view = $event->getTarget();
            $response = $view->response;
            foreach ($response->getResources() as $resource) {
                echo $resource->embeddedJsonLd();
            }
        }
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
                && in_array($resourceType, $searchEngine->setting('resource_types', []))
            ) {
                $jobArgs = [];
                $jobArgs['search_engine_id'] = $searchEngine->id();
                $jobArgs['resource_ids'] = $ids;
                $jobArgs['resource_types'] = [$resourceType];
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
                && in_array($requestResource, $searchEngine->setting('resource_types', []))
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
                && in_array('items', $searchEngine->setting('resource_types', []))
            ) {
                $this->updateIndexResource($searchEngine, $item);
            }
        }
    }

    /**
     * Delete the index for the resource in search engine.
     *
     * @param SearchEngineRepresentation $searchEngine
     * @param string $resourceType
     * @param int $id
     */
    protected function deleteIndexResource(SearchEngineRepresentation $searchEngine, $resourceType, $id): void
    {
        $indexer = $searchEngine->indexer();
        try {
            $indexer->deleteResource($resourceType, $id);
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
        $resourceToIndex = $this->filterVisibility($searchEngine, [$resource]);
        if (!count($resourceToIndex)) {
            return;
        }

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

    protected function filterVisibility(SearchEngineRepresentation $searchEngine, array $resources): array
    {
        $visibility = $searchEngine->setting('visibility');
        if (!in_array($visibility, ['public', 'private'])) {
            return $resources;
        }
        /** @var \Omeka\Entity\Resource $resource */
        if ($visibility === 'private') {
            foreach ($resources as $key => $resource) {
                if ($resource->isPublic()) {
                    unset($resources[$key]);
                }
            }
        } else {
            foreach ($resources as $key => $resource) {
                if (!$resource->isPublic()) {
                    unset($resources[$key]);
                }
            }
        }
        return array_values($resources);
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
            $searchConfig = $plugins->get('api')->read('search_configs', [is_numeric($searchConfig) ? 'id' : 'slug' => $searchConfig])->getContent();
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
            $searchUrl = $basePath('admin/' . $searchConfig->slug());
            $script = sprintf('var searchUrl = %s;', json_encode($searchUrl, 320));

            $autoSuggestUrl = $searchConfig->subSetting('q', 'suggest_url');
            if (!$autoSuggestUrl) {
                $suggester = $searchConfig->subSetting('q', 'suggester');
                if ($suggester) {
                    $autoSuggestUrl = $searchUrl . '/suggest';
                }
            }
            if ($autoSuggestUrl) {
                $script .= sprintf("\nvar searchAutosuggestUrl = %s;", json_encode($autoSuggestUrl, 320));
                /*
                // Always autosubmit in admin.
                // TODO Add a setting for autosubmit in admin quick form?
                $autoSuggestFillInput = $searchConfig->subSetting('q', 'suggest_fill_input');
                if ($autoSuggestFillInput) {
                    $script .= "\nvar searchAutosuggestFillInput = true;";
                }
                */
            }

            $plugins->get('headLink')
                ->appendStylesheet($assetUrl('css/advanced-search-admin.css', 'AdvancedSearch'));
            $plugins->get('headScript')
                ->appendScript($script)
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
                $searchConfig = $api->read('search_configs', [is_numeric($searchConfigId) ? 'id' : 'slug' => $searchConfigId])->getContent();
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
        $siteSettings->set('advancedsearch_redirect_itemset_browse', ['all']);
        $siteSettings->set('advancedsearch_redirect_itemset_search', []);
        $siteSettings->set('advancedsearch_redirect_itemset_search_first', []);
        $siteSettings->set('advancedsearch_redirect_itemset_page_url', []);
        $siteSettings->set('advancedsearch_redirect_itemsets', ['default' => 'browse']);
        $siteSettings->set('advancedsearch_redirect_itemset', 'browse');
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
                $searchEngineConfig['o:engine_adapter'],
                json_encode($searchEngineConfig['o:settings']),
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
            $mainIndex = $translate('Main index'); // @translate
            // Create the internal suggester.
            $sql = <<<SQL
                INSERT INTO `search_suggester`
                (`engine_id`, `name`, `settings`, `created`)
                VALUES
                ($searchEngineId, "$mainIndex", ?, NOW());
                SQL;
            $suggesterSettings = require __DIR__ . '/data/configs/search_suggester.internal.php';
            $connection->executeStatement($sql, [
                json_encode($suggesterSettings),
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
                (`engine_id`, `name`, `slug`, `form_adapter`, `settings`, `created`)
                VALUES
                ($searchEngineId, ?, ?, ?, ?, NOW());
                SQL;
            $searchConfigConfig = require __DIR__ . '/data/configs/search_config.default.php';
            $connection->executeStatement($sql, [
                $searchConfigConfig['o:name'],
                $searchConfigConfig['o:slug'],
                $searchConfigConfig['o:form_adapter'],
                json_encode($searchConfigConfig['o:settings']),
            ]);

            $searchConfigId = $connection->fetchOne($sqlSearchConfigId);
            $message = new PsrMessage(
                'The default search config can be {link_1}edited{link_end}, {link_2}configured{link_end} and defined in the {link_3}main settings{link_end} for admin search and in each site settings for public search.', // @translate
                [
                    // Don't use the module routes: they are not available during install.
                    'link_end' => '</a>',
                    'link_1' => sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager/config/' . $searchConfigId . '/edit'),
                    'link_2' => sprintf('<a href="%s">', $urlHelper('admin') . '/search-manager/config/' . $searchConfigId . '/configure'),
                    'link_3' => sprintf('<a href="%s">', $urlHelper('admin') . '/setting#advancedsearch_main_config'),
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addSuccess($message);
        }

        return (int) $searchConfigId;
    }
}
