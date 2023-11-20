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
            'facetActive' => View\Helper\FacetActive::class,
            'facetActives' => View\Helper\FacetActives::class,
            'facetCheckbox' => View\Helper\FacetCheckbox::class,
            'facetCheckboxes' => View\Helper\FacetCheckboxes::class,
            'facetCheckboxesTree' => View\Helper\FacetCheckboxesTree::class,
            'facetLabel' => View\Helper\FacetLabel::class,
            'facetLink' => View\Helper\FacetLink::class,
            'facetLinks' => View\Helper\FacetLinks::class,
            'facetLinksTree' => View\Helper\FacetLinksTree::class,
            'facetSelect' => View\Helper\FacetSelect::class,
            'facetSelectRange' => View\Helper\FacetSelectRange::class,
            'formMultiText' => Form\View\Helper\FormMultiText::class,
            'formNote' => Form\View\Helper\FormNote::class,
            'getSearchConfig' => View\Helper\GetSearchConfig::class,
            'hiddenInputsFromFilteredQuery' => View\Helper\HiddenInputsFromFilteredQuery::class,
            'searchFilters' => View\Helper\SearchFilters::class,
            'searchForm' => View\Helper\SearchForm::class,
            'searchingFilters' => View\Helper\SearchingFilters::class,
            'searchingForm' => View\Helper\SearchingForm::class,
            'searchingUrl' => View\Helper\SearchingUrl::class,
            'searchPaginationPerPageSelector' => View\Helper\SearchPaginationPerPageSelector::class,
            'searchSortSelector' => View\Helper\SearchSortSelector::class,
        ],
        'factories' => [
            'apiSearch' => Service\ViewHelper\ApiSearchFactory::class,
            'apiSearchOne' => Service\ViewHelper\ApiSearchOneFactory::class,
            // Used in AdvancedResourceTemplate, AdvancedSearch and BlockPlus.
            'assetUrl' => Service\ViewHelper\AssetUrlFactory::class,
            'cleanQuery' => Service\ViewHelper\CleanQueryFactory::class,
            // Used in AdvancedSearch and Annotate.
            'easyMeta' => Service\ViewHelper\EasyMetaFactory::class,
            'matchedRouteName' => Service\ViewHelper\MatchedRouteNameFactory::class,
            'mediaTypeSelect' => Service\ViewHelper\MediaTypeSelectFactory::class,
            'searchEngineConfirm' => Service\ViewHelper\SearchEngineConfirmFactory::class,
            'searchSuggesterConfirm' => Service\ViewHelper\SearchSuggesterConfirmFactory::class,
            // Allow to call EasyMeta, used in AdvancedSearch and Annotate.
            View\Helper\EasyMeta::class => Service\ViewHelper\EasyMetaFactory::class,
        ],
        'delegators' => [
            'Laminas\Form\View\Helper\FormElement' => [
                Service\Delegator\FormElementDelegatorFactory::class,
            ],
            \Omeka\View\Helper\UserBar::class => [
                Service\ViewHelper\UserBarDelegatorFactory::class,
            ],
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'searchingForm' => Site\BlockLayout\SearchingForm::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\Admin\InternalConfigFieldset::class => Form\Admin\InternalConfigFieldset::class,
            Form\Element\ArrayText::class => Form\Element\ArrayText::class,
            Form\Element\DataTextarea::class => Form\Element\DataTextarea::class,
            Form\Element\MultiText::class => Form\Element\MultiText::class,
            Form\Element\Note::class => Form\Element\Note::class,
            Form\Element\OptionalMultiCheckbox::class => Form\Element\OptionalMultiCheckbox::class,
            Form\Element\OptionalRadio::class => Form\Element\OptionalRadio::class,
            Form\Element\OptionalSelect::class => Form\Element\OptionalSelect::class,
            Form\Element\OptionalUrl::class => Form\Element\OptionalUrl::class,
            Form\Element\TextExact::class => Form\Element\TextExact::class,
            Form\Element\UrlQuery::class => Form\Element\UrlQuery::class,
        ],
        'factories' => [
            Form\Admin\ApiFormConfigFieldset::class => Service\Form\ApiFormConfigFieldsetFactory::class,
            Form\Admin\SearchConfigConfigureForm::class => Service\Form\SearchConfigConfigureFormFactory::class,
            Form\Admin\SearchConfigForm::class => Service\Form\SearchConfigFormFactory::class,
            Form\Admin\SearchEngineConfigureForm::class => Service\Form\SearchEngineConfigureFormFactory::class,
            Form\Admin\SearchEngineForm::class => Service\Form\SearchEngineFormFactory::class,
            Form\Admin\SearchSuggesterForm::class => Service\Form\SearchSuggesterFormFactory::class,
            Form\Element\MediaTypeSelect::class => Service\Form\Element\MediaTypeSelectFactory::class,
            Form\Element\SearchConfigSelect::class => Service\Form\Element\SearchConfigSelectFactory::class,
            // These three elements are overridden from core in order to be able to fix prepend value "0".
            Form\Element\ItemSetSelect::class => Service\Form\Element\ItemSetSelectFactory::class,
            Form\Element\ResourceTemplateSelect::class => Service\Form\Element\ResourceTemplateSelectFactory::class,
            Form\Element\SiteSelect::class => Service\Form\Element\SiteSelectFactory::class,
            Form\SearchFilter\Advanced::class => Service\Form\StandardFactory::class,
            Form\MainSearchForm::class => Service\Form\MainSearchFormFactory::class,
            Form\SearchingFormFieldset::class => Service\Form\SearchingFormFieldsetFactory::class,
            Form\SettingsFieldset::class => Service\Form\SettingsFieldsetFactory::class,
            Form\SiteSettingsFieldset::class => Service\Form\SiteSettingsFieldsetFactory::class,
        ],
        'aliases' => [
            \Omeka\Form\Element\ItemSetSelect::class => Form\Element\ItemSetSelect::class,
            \Omeka\Form\Element\ResourceTemplateSelect::class => Form\Element\ResourceTemplateSelect::class,
            \Omeka\Form\Element\SiteSelect::class => Form\Element\SiteSelect::class,
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
        'invokables' => [
            'searchRequestToResponse' => Mvc\Controller\Plugin\SearchRequestToResponse::class,
        ],
        'factories' => [
            'apiSearch' => Service\ControllerPlugin\ApiSearchFactory::class,
            'apiSearchOne' => Service\ControllerPlugin\ApiSearchOneFactory::class,
            'searchResources' => Service\ControllerPlugin\SearchResourcesFactory::class,
            'totalJobs' => Service\ControllerPlugin\TotalJobsFactory::class,
        ],
    ],
    'listeners' => [
        Mvc\MvcListeners::class,
    ],
    'service_manager' => [
        'invokables' => [
            Mvc\MvcListeners::class => Mvc\MvcListeners::class,
        ],
        'delegators' => [
            'Omeka\ApiManager' => [Service\ApiManagerDelegatorFactory::class],
        ],
        'factories' => [
            'Search\AdapterManager' => Service\AdapterManagerFactory::class,
            'Search\FormAdapterManager' => Service\FormAdapterManagerFactory::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'search-page' => Site\Navigation\Link\SearchPage::class,
        ],
    ],
    'router' => [
        'routes' => [
            // TODO Include site routes here, not during bootstrap.
            'admin' => [
                'child_routes' => [
                    // To simplify migration, the route is "search".
                    'search' => [
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
                'route' => 'admin/search',
                'resource' => Controller\Admin\IndexController::class,
                'privilege' => 'browse',
                'class' => 'o-icon-search',
                'pages' => [
                    [
                        'route' => 'admin/search/engine',
                        'visible' => false,
                        'pages' => [
                            [
                                'route' => 'admin/search/engine-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'route' => 'admin/search/config',
                        'visible' => false,
                        'pages' => [
                            [
                                'route' => 'admin/search/config-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'route' => 'admin/search/suggester',
                        'visible' => false,
                        'pages' => [
                            [
                                'route' => 'admin/search/suggester-id',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'AdvancedSearch\Config' => [
            [
                'label' => 'Manage', // @translate
                'route' => 'admin/search/config-id',
                'resource' => Controller\Admin\SearchConfigController::class,
                'action' => 'edit',
                'privilege' => 'edit',
                'useRouteMatch' => true,
            ],
            [
                'label' => 'Configure', // @translate
                'route' => 'admin/search/config-id',
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
    ],
    'search_adapters' => [
        'factories' => [
            'internal' => Service\Adapter\InternalAdapterFactory::class,
        ],
    ],
    'search_form_adapters' => [
        'invokables' => [
            'main' => FormAdapter\MainFormAdapter::class,
        ],
        'factories' => [
            'api' => Service\FormAdapter\ApiFormAdapterFactory::class,
        ],
    ],
    'advancedsearch' => [
        'settings' => [
            'advancedsearch_restrict_used_terms' => true,
            'advancedsearch_main_config' => 1,
            'advancedsearch_configs' => [1],
            'advancedsearch_api_config' => '',
            // TODO Remove this option if there is no issue with sync or async (except multiple search engines).
            'advancedsearch_index_batch_edit' => 'sync',
            'advancedsearch_batch_size' => 100,
            // Hidden value.
            'advancedsearch_all_configs' => [1 => 'find'],
        ],
        'site_settings' => [
            'advancedsearch_search_fields' => [
                'common/advanced-search/fulltext',
                'common/advanced-search/properties',
                'common/advanced-search/resource-class',
                'common/advanced-search/item-sets',
                'common/advanced-search/date-time',
                'common/advanced-search/has-media',
                'common/advanced-search/media-type',
                'common/advanced-search/data-type-geography',
                'common/numeric-data-types-advanced-search',
            ],
            'advancedsearch_main_config' => 1,
            'advancedsearch_configs' => [1],
            'advancedsearch_redirect_itemset' => true,
        ],
        'block_settings' => [
            'searchingForm' => [
                'heading' => '',
                'html' => '',
                'link' => '',
                // Name "search_page" is kept to simplify migration.
                'search_page' => null,
                'display_results' => false,
                'query' => '',
                'query_filter' => '',
                'template' => '',
            ],
        ],
        // This is the default list of all possible fields, allowing other modules
        // to complete it. The partials that are not set in merged Config (included
        // config/local.config.php) are not managed by this module.
        'search_fields' => [
            // From view/common/advanced-search'.
            'common/advanced-search/fulltext' => ['label' => 'Full text'], // @translate
            'common/advanced-search/properties' => ['label' => 'Properties'], // @translate
            'common/advanced-search/resource-class' => ['label' => 'Classes'], // @translate
            'common/advanced-search/resource-template' => ['label' => 'Templates', 'default' => false], // @translate
            'common/advanced-search/resource-template-restrict' => ['label' => 'Templates (restricted)', 'default' => false], // @translate
            'common/advanced-search/item-sets' => ['label' => 'Item sets'], // @translate
            'common/advanced-search/owner' => ['label' => 'Owner', 'default' => false], // @translate
            'common/advanced-search/site' => ['label' => 'Site', 'default' => false], // @translate
            'common/advanced-search/sort' => ['label' => 'Sort', 'default' => false], // @translate
            // This partial is managed separately by a core option.
            // 'common/advanced-search/resource-template-restrict' => ['label' => 'Resource template restrict'],
            // From module advanced search plus.
            'common/advanced-search/date-time' => ['label' => 'Date time'], // @translate
            'common/advanced-search/has-media' => ['label' => 'Has media'], // @translate
            'common/advanced-search/has-original' => ['label' => 'Has original file', 'default' => false], // @translate
            'common/advanced-search/has-thumbnails' => ['label' => 'Has thumbnails', 'default' => false], // @translate
            'common/advanced-search/media-type' => ['label' => 'Media types'], // @translate
            'common/advanced-search/visibility' => ['label' => 'Visibility', 'default' => false], // @translate
            // From module data type geometry.
            'common/advanced-search/data-type-geography' => ['module' => 'DataTypeGeometry', 'label' => 'Geography', 'default' => false], // @translate
            // From module numeric data type.
            'common/numeric-data-types-advanced-search' => ['module' => 'NumericDataTypes', 'label' => 'Numeric', 'default' => false], // @translate
        ],
    ],
];
