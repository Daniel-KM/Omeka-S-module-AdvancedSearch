<?php declare(strict_types=1);

namespace AdvancedSearch;

return [
    'api_adapters' => [
        'invokables' => [
            'search_configs' => Api\Adapter\SearchConfigAdapter::class,
            'search_engines' => Api\Adapter\SearchEngineAdapter::class,
            'search_suggesters' => Api\Adapter\SearchSuggesterAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'service_manager' => [
        'invokables' => [
            Mvc\MvcListeners::class => Mvc\MvcListeners::class,
        ],
        'factories' => [
            'AdvancedSearch\EngineAdapterManager' => Service\EngineAdapter\ManagerFactory::class,
            'AdvancedSearch\FormAdapterManager' => Service\FormAdapter\ManagerFactory::class,
            'AdvancedSearch\SearchResources' => Service\Stdlib\SearchResourcesFactory::class,
        ],
        'delegators' => [
            'Omeka\ApiManager' => [
                __NAMESPACE__ => Service\Delegator\ApiManagerDelegatorFactory::class,
            ],
            'Omeka\FulltextSearch' => [
                __NAMESPACE__ => Service\Delegator\FulltextSearchDelegatorFactory::class,
            ],
        ],
    ],
    'listeners' => [
        Mvc\MvcListeners::class,
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        // View "search" is kept to simplify migration.
        'controller_map' => [
            Controller\SearchController::class => 'search',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'facetActives' => View\Helper\FacetActives::class,
            'facetCheckboxes' => View\Helper\FacetCheckboxes::class,
            'facetCheckboxesTree' => View\Helper\FacetCheckboxesTree::class,
            'facetElements' => View\Helper\FacetElements::class,
            'facetLinks' => View\Helper\FacetLinks::class,
            'facetLinksTree' => View\Helper\FacetLinksTree::class,
            'facetRangeDouble' => View\Helper\FacetRangeDouble::class,
            'facetSelect' => View\Helper\FacetSelect::class,
            'facetSelectRange' => View\Helper\FacetSelectRange::class,
            'formMultiText' => Form\View\Helper\FormMultiText::class,
            'formRangeDouble' => Form\View\Helper\FormRangeDouble::class,
            'getSearchConfig' => View\Helper\GetSearchConfig::class,
            'hiddenInputsFromFilteredQuery' => View\Helper\HiddenInputsFromFilteredQuery::class,
            'searchFilters' => View\Helper\SearchFilters::class,
            'searchingFilters' => View\Helper\SearchingFilters::class,
            'searchingForm' => View\Helper\SearchingForm::class,
            'searchingUrl' => View\Helper\SearchingUrl::class,
            'searchingValue' => View\Helper\SearchingValue::class,
            'searchPaginationPerPageSelector' => View\Helper\SearchPaginationPerPageSelector::class,
            'searchSortSelector' => View\Helper\SearchSortSelector::class,
        ],
        'factories' => [
            'apiSearch' => Service\ViewHelper\ApiSearchFactory::class,
            'apiSearchOne' => Service\ViewHelper\ApiSearchOneFactory::class,
            'escapeValueOrGetHtml' => Service\ViewHelper\EscapeValueOrGetHtmlFactory::class,
            'fieldSelect' => Service\ViewHelper\FieldSelectFactory::class,
            'paginationSearch' => Service\ViewHelper\PaginationSearchFactory::class,
            'queryInput' => Service\ViewHelper\QueryInputFactory::class,
            'searchEngineConfirm' => Service\ViewHelper\SearchEngineConfirmFactory::class,
            'searchResources' => Service\ViewHelper\SearchResourcesFactory::class,
            'searchSuggesterConfirm' => Service\ViewHelper\SearchSuggesterConfirmFactory::class,
        ],
        'delegators' => [
            \Omeka\Form\View\Helper\FormQuery::class => [
                Service\ViewHelper\FormQueryDelegatorFactory::class,
            ],
            'Laminas\Form\View\Helper\FormElement' => [
                Service\Delegator\FormElementDelegatorFactory::class,
            ],
            \Omeka\View\Helper\UserBar::class => [
                Service\ViewHelper\UserBarDelegatorFactory::class,
            ],
        ],
        'aliases' => [
            // Deprecated.
            'searchConfigCurrent' => View\Helper\GetSearchConfig::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'searchingForm' => Site\BlockLayout\SearchingForm::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'searchingForm' => Site\ResourcePageBlockLayout\SearchingForm::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Element\MultiText::class => Form\Element\MultiText::class,
            Form\Element\TextExact::class => Form\Element\TextExact::class,
        ],
        'factories' => [
            Form\Admin\ApiFormConfigFieldset::class => Service\Form\ApiFormConfigFieldsetFactory::class,
            Form\Admin\SearchConfigConfigureForm::class => Service\Form\SearchConfigConfigureFormFactory::class,
            Form\Admin\SearchConfigFacetFieldset::class => \Common\Service\Form\GenericFormFactory::class,
            Form\Admin\SearchConfigFilterFieldset::class => \Common\Service\Form\GenericFormFactory::class,
            Form\Admin\SearchConfigSortFieldset::class => \Common\Service\Form\GenericFormFactory::class,
            Form\Admin\SearchConfigForm::class => Service\Form\SearchConfigFormFactory::class,
            Form\Admin\SearchEngineConfigureForm::class => \Common\Service\Form\GenericFormFactory::class,
            Form\Admin\SearchEngineForm::class => Service\Form\SearchEngineFormFactory::class,
            Form\Admin\SearchSuggesterForm::class => Service\Form\SearchSuggesterFormFactory::class,
            Form\Element\FieldSelect::class => Service\Form\Element\FieldSelectFactory::class,
            Form\Element\SearchConfigSelect::class => Service\Form\Element\SearchConfigSelectFactory::class,
            Form\SearchFilter\Advanced::class => Service\Form\SearchFilterAdvancedFactory::class,
            Form\MainSearchForm::class => Service\Form\MainSearchFormFactory::class,
            Form\SearchingFormFieldset::class => Service\Form\SearchingFormFieldsetFactory::class,
            Form\SettingsFieldset::class => Service\Form\SettingsFieldsetFactory::class,
            Form\SiteSettingsFieldset::class => Service\Form\SiteSettingsFieldsetFactory::class,
            // These three elements are overridden from core in order to be able to fix prepend value "0".
            Form\Element\ItemSetSelect::class => Service\Form\Element\ItemSetSelectFactory::class,
            Form\Element\ResourceTemplateSelect::class => Service\Form\Element\ResourceTemplateSelectFactory::class,
            Form\Element\SiteSelect::class => Service\Form\Element\SiteSelectFactory::class,
        ],
        'aliases' => [
            \Omeka\Form\Element\ItemSetSelect::class => Form\Element\ItemSetSelect::class,
            \Omeka\Form\Element\ResourceTemplateSelect::class => Form\Element\ResourceTemplateSelect::class,
            \Omeka\Form\Element\SiteSelect::class => Form\Element\SiteSelect::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'searchingPage' => Site\Navigation\Link\SearchingPage::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\IndexController::class => Controller\Admin\IndexController::class,
            Controller\SearchController::class => Controller\SearchController::class,
        ],
        'factories' => [
            Controller\Admin\SearchConfigController::class => Service\Controller\Admin\SearchConfigControllerFactory::class,
            Controller\Admin\SearchEngineController::class => Service\Controller\Admin\SearchEngineControllerFactory::class,
            Controller\Admin\SearchSuggesterController::class => Service\Controller\Admin\SearchSuggesterControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'apiSearch' => Service\ControllerPlugin\ApiSearchFactory::class,
            'apiSearchOne' => Service\ControllerPlugin\ApiSearchOneFactory::class,
            'listJobStatusesByIds' => Service\ControllerPlugin\ListJobStatusesByIdsFactory::class,
            'searchResources' => Service\ControllerPlugin\SearchResourcesFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            // TODO Include site routes here, not during bootstrap.
            'admin' => [
                'child_routes' => [
                    'search-manager' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/search-manager',
                            'defaults' => [
                                '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                'controller' => Controller\Admin\IndexController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'engine' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/engine/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                        'controller' => Controller\Admin\SearchEngineController::class,
                                    ],
                                ],
                            ],
                            'engine-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/engine/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                        'controller' => Controller\Admin\SearchEngineController::class,
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                            'config' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/config/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                        'controller' => Controller\Admin\SearchConfigController::class,
                                    ],
                                ],
                            ],
                            'config-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/config/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                        'controller' => Controller\Admin\SearchConfigController::class,
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                            'suggester' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/suggester/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                        'controller' => Controller\Admin\SearchSuggesterController::class,
                                    ],
                                ],
                            ],
                            'suggester-id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/suggester/:id[/:action]',
                                    'constraints' => [
                                        'id' => '\d+',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'AdvancedSearch\Controller\Admin',
                                        'controller' => Controller\Admin\SearchSuggesterController::class,
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'advanced-search' => [
                'label' => 'Search manager', // @translate
                'route' => 'admin/search-manager',
                'resource' => Controller\Admin\IndexController::class,
                'privilege' => 'browse',
                'class' => 'o-icon-search',
                'pages' => [
                    [
                        'route' => 'admin/search-manager/engine',
                        'visible' => false,
                        'pages' => [
                            [
                                'route' => 'admin/search-manager/engine-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'route' => 'admin/search-manager/config',
                        'visible' => false,
                        'pages' => [
                            [
                                'route' => 'admin/search-manager/config-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'route' => 'admin/search-manager/suggester',
                        'visible' => false,
                        'pages' => [
                            [
                                'route' => 'admin/search-manager/suggester-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'AdvancedSearch\Config' => [
            [
                'label' => 'Settings', // @translate
                'route' => 'admin/search-manager/config-id',
                'resource' => Controller\Admin\SearchConfigController::class,
                'action' => 'edit',
                'privilege' => 'edit',
                'useRouteMatch' => true,
            ],
            [
                'label' => 'Configure', // @translate
                'route' => 'admin/search-manager/config-id',
                'resource' => Controller\Admin\SearchConfigController::class,
                'action' => 'configure',
                'privilege' => 'edit',
                'useRouteMatch' => true,
            ],
        ],
    ],
    'assets' => [
        // Override internals assets. Only for Omeka assets: modules can use another filename.
        'internals' => [
            'js/global.js' => 'AdvancedSearch',
            'js/query-form.js' => 'AdvancedSearch',
        ],
    ],
    'js_translate_strings' => [
        'Automatic mapping of empty values', // @translate
        'Available', // @translate
        'Enabled', // @translate
        'Find', // @translate
        'Find resources…', // @translate
        'Processing…', // @translate
        'Try to map automatically the metadata and the properties that are not mapped yet with the fields of the index', // @translate
        '[Edit below]', // @translate
    ],
    'advanced_search_engine_adapters' => [
        'factories' => [
            'internal' => Service\EngineAdapter\InternalFactory::class,
        ],
    ],
    'advanced_search_form_adapters' => [
        'invokables' => [
            'main' => FormAdapter\MainFormAdapter::class,
        ],
        'factories' => [
            'api' => Service\FormAdapter\ApiFormAdapterFactory::class,
        ],
    ],
    'advancedsearch' => [
        'settings' => [
            'advancedsearch_search_fields' => [
                // Any resource type.
                'common/advanced-search/sort',
                'common/advanced-search/fulltext',
                'common/advanced-search/properties',
                'common/advanced-search/filters',
                'common/advanced-search/resource-class',
                'common/advanced-search/resource-template',
                // Items.
                'common/advanced-search/item-sets',
                'common/advanced-search/site',
                'common/advanced-search/has-media',
                'common/advanced-search/media-types',
                // Media
                'common/advanced-search/media-type',
                // Item sets.
                // Other common.
                'common/advanced-search/owner',
                'common/advanced-search/visibility-radio',
                'common/advanced-search/ids',
                // Modules.
                'common/advanced-search/item-set-is-dynamic',
                'common/advanced-search/data-type-geography',
                'common/numeric-data-types-advanced-search',
            ],
            'advancedsearch_fulltextsearch_alto' => false,
            'advancedsearch_main_config' => 1,
            'advancedsearch_api_config' => '',
            // Hidden value.
            'advancedsearch_all_configs' => [1 => 'find'],
        ],
        'site_settings' => [
            // See the full list below.
            'advancedsearch_search_fields' => [
                // Any resource type.
                'common/advanced-search/sort',
                'common/advanced-search/fulltext',
                // 'common/advanced-search/properties',
                'common/advanced-search/filters',
                'common/advanced-search/resource-class',
                // Items.
                'common/advanced-search/item-sets',
                'common/advanced-search/has-media',
                'common/advanced-search/media-types',
                // Media
                'common/advanced-search/media-type',
                // Item sets.
                // Other common.
                'common/advanced-search/resource-template-restrict',
                'common/advanced-search/ids',
                // Modules.
                'common/advanced-search/data-type-geography',
                'common/numeric-data-types-advanced-search',
            ],
            'advancedsearch_configs' => [1],
            'advancedsearch_main_config' => 1,
            'advancedsearch_items_config' => 1,
            'advancedsearch_items_template_form' => null,
            'advancedsearch_media_config' => 1,
            'advancedsearch_media_template_form' => null,
            'advancedsearch_item_sets_config' => 1,
            'advancedsearch_item_sets_template_form' => null,
            'advancedsearch_item_sets_scope' => 0,
            'advancedsearch_item_sets_redirect_browse' => ['all'],
            'advancedsearch_item_sets_redirect_search' => [],
            'advancedsearch_item_sets_redirect_search_first' => [],
            'advancedsearch_item_sets_redirect_page_url' => [],
            'advancedsearch_item_sets_browse_config' => 0,
            'advancedsearch_item_sets_browse_page' => '',
            // Hidden options.
            // This option is a merge of the previous ones for simplicity.
            'advancedsearch_item_sets_redirects' => [],
            // The old options are not removed for now for compatibility with old themes (search, links).
            'advancedsearch_redirect_itemsets' => [],
            'advancedsearch_redirect_itemset' => 'browse',
        ],
        'block_settings' => [
            'searchingForm' => [
                'search_config' => null,
                'display_results' => true,
                'query' => [],
                'query_filter' => [],
                'link' => '',
            ],
        ],
        // This list of templates for advanced search form comes from view/common/advanced-search.
        // The partials that are not set in merged Config (included config/local.config.php)
        // are not managed by this module. Other modules can complete it.
        // The keys "admin_site" and "default_site" mean available on admin or
        // site by default. Any partial can be displayed.
        'search_fields' => [
            // Any resource type.
            'common/advanced-search/sort' => [
                'label' => 'Sort', // @translate
            ],
            'common/advanced-search/fulltext' => [
                'label' => 'Full text', // @translate
            ],
            'common/advanced-search/properties' => [
                'label' => 'Properties', // @translate
            ],
            'common/advanced-search/properties-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Properties *', // @translate
                'default_admin' => false,
                'default_site' => false,
                'improve' => 'common/advanced-search/properties',
            ],
            'common/advanced-search/filters' => [
                'module' => 'AdvancedSearch',
                'label' => 'Filters', // @translate
            ],
            'common/advanced-search/resource-class' => [
                'label' => 'Classes', // @translate
            ],
            'common/advanced-search/resource-class-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Classes *', // @translate
                'default_admin' => false,
                'default_site' => false,
                'improve' => 'common/advanced-search/resource-class',
            ],
            'common/advanced-search/resource-template' => [
                'label' => 'Templates', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/resource-template-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Templates *', // @translate
                'default_admin' => false,
                'default_site' => false,
                'improve' => 'common/advanced-search/resource-template',
            ],
            // Warning: this partial is managed separately by a core option.
            'common/advanced-search/resource-template-restrict' => [
                'label' => 'Templates (specific option "restricted")', // @translate
                'default_admin' => false,
                'default_site' => false,
            ],

            // Items.
            'common/advanced-search/item-sets' => [
                'label' => 'Item sets', // @translate
                'resource_type' => ['items'],
            ],
            'common/advanced-search/item-sets-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Item sets *', // @translate
                'resource_type' => ['items'],
                'default_site' => false,
                'improve' => 'common/advanced-search/item-sets',
            ],
            'common/advanced-search/site' => [
                'label' => 'default_site', // @translate
                'resource_type' => ['items', 'item_sets'],
                'default_site' => false,
            ],
            'common/advanced-search/site-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Site *', // @translate
                'resource_type' => ['items', 'item_sets'],
                'default_admin' => false,
                'default_site' => false,
                'improve' => 'common/advanced-search/site',
            ],
            'common/advanced-search/has-media' => [
                'label' => 'Has media (select)', // @translate
                'resource_type' => ['items'],
                'default_admin' => false,
                'default_site' => false,
            ],
            'common/advanced-search/has-media-radio' => [
                'module' => 'AdvancedSearch',
                'label' => 'Has media (radio)', // @translate
                'resource_type' => ['items'],
            ],
            'common/advanced-search/media-types' => [
                'label' => 'Media types', // @translate
                'resource_type' => ['items'],
            ],

            // Medias.
            'common/advanced-search/media-type' => [
                'label' => 'Media type (single)', // @translate
                'resource_type' => ['media'],
            ],
            'common/advanced-search/media-type-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Media types *', // @translate
                'resource_type' => ['media'],
                'default_admin' => false,
                'default_site' => false,
                'improve' => 'common/advanced-search/media-type',
            ],

            // Item sets.

            // Other common.
            'common/advanced-search/owner' => [
                'label' => 'Owner', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/owner-improved' => [
                'module' => 'AdvancedSearch',
                'label' => 'Owner *', // @translate
                'default_admin' => false,
                'default_site' => false,
                'improve' => 'common/advanced-search/owner',
            ],
            // Visibility filter was included in Omeka S v4.0.
            'common/advanced-search/visibility' => [
                'label' => 'Visibility (select)', // @translate
                'default_admin' => false,
                'default_site' => false,
            ],
            'common/advanced-search/visibility-radio' => [
                'module' => 'AdvancedSearch',
                'label' => 'Visibility (radio)', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/ids' => [
                'label' => 'Id', // @translate
            ],

            // Common for this module.
            'common/advanced-search/date-time' => [
                'module' => 'AdvancedSearch',
                'label' => 'Date time', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/has-original' => [
                'module' => 'AdvancedSearch',
                'label' => 'Has original file', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/has-thumbnails' => [
                'module' => 'AdvancedSearch',
                'label' => 'Has thumbnails', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/has-asset' => [
                'module' => 'AdvancedSearch',
                'label' => 'Has asset as thumbnail', // @translate
                'default_site' => false,
            ],
            'common/advanced-search/asset' => [
                'module' => 'AdvancedSearch',
                'label' => 'Has a specific asset', // @translate
                'default_site' => false,
            ],

            // From module data type geometry.
            'common/advanced-search/data-type-geography' => [
                'module' => 'DataTypeGeometry',
                'label' => 'Geography', // @translate
            ],

            // From module dynamic item sets.
            'common/advanced-search/item-set-is-dynamic' => [
                'module' => 'Dynamic Item Sets',
                'label' => 'Is dynamic item set', // @translate
                'default_site' => false,
                'resource_type' => ['item_sets'],
            ],

            // From module numeric data type.
            // The partial is used only in search items, but data are available
            // anywhere.
            'common/numeric-data-types-advanced-search' => [
                'module' => 'NumericDataTypes',
                'label' => 'Numeric', // @translate
                'resource_type' => ['items'],
            ],

            // From module oai-pmh harvester.
            'common/advanced-search/harvests' => [
                'module' => 'OaiPmhHarvester',
                'label' => 'OAI-PMH harvests', // @translate
                'resource_type' => ['items'],
                'default_admin' => true,
                'default_site' => false,
            ],

            // From module selection.
            'common/advanced-search/selections' => [
                'module' => 'Selection',
                'label' => 'Selections', // @translate
                'resource_type' => ['item_sets', 'items', 'media'],
                'default_admin' => true,
                'default_site' => false,
            ],
        ],
    ],
];
