<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2025
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

namespace AdvancedSearch\Api\Representation;

use AdvancedSearch\Querier\Exception\QuerierException;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Common\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

class SearchConfigRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:SearchConfig';
    }

    public function getJsonLd()
    {
        $getDateTimeJsonLd = function (?\DateTime $dateTime): ?array {
            return $dateTime
                ? [
                    '@value' => $dateTime->format('c'),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ]
                : null;
        };

        return [
            'o:name' => $this->resource->getName(),
            'o:slug' => $this->resource->getSlug(),
            'o:search_engine' => $this->searchEngine()->getReference()->jsonSerialize(),
            'o:form_adapter' => $this->resource->getFormAdapter(),
            'o:settings' => $this->resource->getSettings(),
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
        ];
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        $params = [
            'action' => $action,
            'id' => $this->id(),
        ];
        $options = [
            'force_canonical' => $canonical,
        ];
        return $url('admin/search-manager/config-id', $params, $options);
    }

    /**
     * Url of the real search page.
     */
    public function adminSearchUrl($canonical = false, array $query = []): string
    {
        $url = $this->getViewHelper('Url');
        $options = [
            'force_canonical' => $canonical,
        ];
        if ($query) {
            $options['query'] = $query;
        }
        return $url('search-admin-page-' . $this->slug(), [], $options);
    }

    public function siteUrl($siteSlug = null, $canonical = false, array $query = [])
    {
        if (!$siteSlug) {
            $siteSlug = $this->getServiceLocator()->get('Application')
                ->getMvcEvent()->getRouteMatch()->getParam('site-slug');
        }
        $params = [
            'site-slug' => $siteSlug,
        ];
        $options = [
            'force_canonical' => $canonical,
        ];
        if ($query) {
            $options['query'] = $query;
        }
        $url = $this->getViewHelper('Url');
        return $url('search-page-' . $this->slug(), $params, $options);
    }

    public function name(): string
    {
        return $this->resource->getName();
    }

    public function slug(): ?string
    {
        return $this->resource->getSlug();
    }

    public function searchEngine(): ?\AdvancedSearch\Api\Representation\SearchEngineRepresentation
    {
        $searchEngine = $this->resource->getEngine();
        return $searchEngine
            ? $this->getAdapter('search_engines')->getRepresentation($searchEngine)
            : null;
    }

    public function engineAdapter(): ?\AdvancedSearch\EngineAdapter\EngineAdapterInterface
    {
        $searchEngine = $this->searchEngine();
        if ($searchEngine) {
            $engineAdapter = $searchEngine->engineAdapter();
            if ($engineAdapter) {
                return $engineAdapter->setSearchConfig($this);
            }
        }
        return null;
    }

    /**
     * @return \AdvancedSearch\Indexer\IndexerInterface|null Return null when
     * there is no search engine, NoopIndexer when there is no indexer, or the
     * real indexer.
     */
    public function indexer(): ?\AdvancedSearch\Indexer\IndexerInterface
    {
        $searchEngine = $this->searchEngine();
        return $searchEngine
            ? $searchEngine->indexer()
            : null;
    }

    /**
     * @return \AdvancedSearch\Querier\QuerierInterface|null Return null when
     * there is no search engine, NoopQuerier when there is no querier, or the
     * real querier.
     */
    public function querier(): ?\AdvancedSearch\Querier\QuerierInterface
    {
        $searchEngine = $this->searchEngine();
        return $searchEngine
            ? $searchEngine->querier()
            : null;
    }

    public function formAdapterName(): ?string
    {
        return $this->resource->getFormAdapter();
    }

    public function formAdapter(): ?\AdvancedSearch\FormAdapter\FormAdapterInterface
    {
        $formAdapterName = $this->formAdapterName();
        if (!$formAdapterName) {
            return null;
        }

        $formAdapterManager = $this->getServiceLocator()->get('AdvancedSearch\FormAdapterManager');
        if (!$formAdapterManager->has($formAdapterName)) {
            return null;
        }

        /** @var \AdvancedSearch\FormAdapter\FormAdapterInterface $formAdapter */
        $formAdapter = $formAdapterManager->get($formAdapterName);
        return $formAdapter
            ->setSearchConfig($this);
    }

    public function settings(): array
    {
        return $this->resource->getSettings();
    }

    public function setting(string $name, $default = null)
    {
        [$name] = $this->settingCheckName($name);
        return $this->resource->getSettings()[$name] ?? $default;
    }

    public function subSetting(string $mainName, string $name, $default = null)
    {
        [$mainName, $name] = $this->settingCheckName($mainName, $name);
        return $this->resource->getSettings()[$mainName][$name] ?? $default;
    }

    public function subSubSetting(string $mainName, string $name, string $subName, $default = null)
    {
        [$mainName, $name, $subName] = $this->settingCheckName($mainName, $name, $subName);
        return $this->resource->getSettings()[$mainName][$name][$subName] ?? $default;
    }

    /**
     * Log issues for deprecated themes.
     */
    protected function settingCheckName(string $mainName, ?string $name = null, ?string $subName = null): array
    {
        if ($mainName === 'search') {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $message = new PsrMessage(
                'The search config setting "{old}" was renamed "{new}". You should update your theme.', // @translate
                ['old' => 'search', 'new' => 'request']
            );
            $logger->err($message->getMessage(), $message->getContext());
            return ['request', $name, $subName];
        } elseif ($mainName === 'autosuggest') {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $message = new PsrMessage(
                'The search config setting "{old}" was renamed "{new}". You should update your theme.', // @translate
                ['old' => 'autosuggest', 'new' => 'q']
            );
            $news = [
                'url' => 'suggest_url',
                'url_param_name' => 'suggest_url_param_name',
                'limit' => 'suggest_limit',
                'fill_input' => 'suggest_fill_input',
            ];
            $name = $news[$name] ?? $name;
            $logger->err($message->getMessage(), $message->getContext());
            return ['q', $name, $subName];
        } elseif ($mainName === 'display') {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $message = new PsrMessage(
                'The search config setting "{old}" was renamed "{new}". You should update your theme.', // @translate
                ['old' => 'display', 'new' => 'results']
            );
            $logger->err($message->getMessage(), $message->getContext());
            return ['results', $name, $subName];
        } elseif ($mainName === 'sort') {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $message = new PsrMessage(
                'The search config setting "{old}" was renamed "{new}". You should update your theme.', // @translate
                ['old' => 'sort', 'new' => 'results']
            );
            if ($name === 'label') {
                $name = 'label_sort';
            } elseif ($name === 'fields') {
                $name = 'sort_list';
            }
            $logger->err($message->getMessage(), $message->getContext());
            return ['results', $name, $subName];
        } elseif ($mainName === 'q' && $name === 'fulltext_search') {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $message = new PsrMessage(
                'The search config setting "{old}" was renamed "{new}". You should update your theme.', // @translate
                ['old' => 'q[fulltext_search]', 'new' => 'form[rft]']
            );
            $logger->err($message->getMessage(), $message->getContext());
            return ['form', 'rft', null];
        }

        return [$mainName, $name, $subName];
    }

    public function created(): \DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?\DateTime
    {
        return $this->resource->getModified();
    }

    public function getEntity(): \AdvancedSearch\Entity\SearchConfig
    {
        return $this->resource;
    }

    /**
     * Get the form from the adapter.
     *
     * See options in renderForm().
     * @see \AdvancedSearch\Api\Representation\SearchConfigRepresentation::renderForm()
     *
     * @uses \AdvancedSearch\FormAdapter\FormAdapterInterface::getForm()
     */
    public function form(array $options = []): ?\Laminas\Form\Form
    {
        $formAdapter = $this->formAdapter();
        return $formAdapter
            ? $formAdapter->getForm($options)
            : null;
    }

    /**
     * Render the form.
     *
     * @param array $options Options are same than renderForm() in interface.
     *   Default keys:
     *   - itemSet (ItemSetRepresentation|null): for item set redirection.
     *   - template (string): Use a specific template instead of the default one.
     *     This is the template of the form, not the main template of the search
     *     config.
     *   - skip_form_action (bool): Don't set form action, so use current page.
     *   - form_action (string|null): Custom form action url. If set, this url
     *     is used instead of the default.
     *   - skip_partial_headers (bool): Skip partial headers.
     *   - skip_values: Does not init form element values (quicker results).
     *   - variant: Name of a variant of the form;
     *     - "quick": only "q", "rft" and hidden elements
     *     - "simple": only "q" and hidden elements
     *     - "csrf": for internal use
     *     To use a variant allows a quicker process than a template alone.
     *   Other options are passed to the partial.
     *
     * @uses \AdvancedSearch\FormAdapter\FormAdapterInterface::renderForm()
     */
    public function renderForm(array $options = []): string
    {
        $formAdapter = $this->formAdapter();
        return $formAdapter
            ? $formAdapter->renderForm($options)
            : '';
    }

    /**
     * Render the search filters of the query.
     *
     * The search filters are the list of the query arguments used in the
     * request when the advanced search form is used.
     */
    public function renderSearchFilters(Query $query, array $options = []): string
    {
        $template = $options['template'] ?? null;

        // TODO Use the managed query to get a clean query.

        $params = $this->getViewHelper('params');
        $request = $params->fromQuery();

        // Quick clean query.
        $arrayFilterRecursiveEmpty = null;
        $arrayFilterRecursiveEmpty = function (array &$array) use (&$arrayFilterRecursiveEmpty): array {
            foreach ($array as $key => $value) {
                if (is_array($value) && $value) {
                    $array[$key] = $arrayFilterRecursiveEmpty($value);
                }
                if (in_array($array[$key], ['', null, []], true)) {
                    unset($array[$key]);
                }
            }
            return $array;
        };
        $arrayFilterRecursiveEmpty($request);

        // Manage exceptions.

        // Don't display the resource type if the search engine support only one
        // resource type and if it is the one set in the query.
        // It is used especially for the search engine for item-set/browse.
        $resourceTypes = $query->getResourceTypes();
        if (count($resourceTypes) === 1) {
            $searchEngine = $this->searchEngine();
            $searchEngineResourceTypes = $searchEngine ? $searchEngine->setting('resource_types', []) : [];
            if (count($searchEngineResourceTypes) === 1
                && reset($resourceTypes) === reset($searchEngineResourceTypes)
            ) {
                unset($request['resource_type']);
            }
        }

        // Don't display the current item set argument on item set page.
        $currentItemSet = (int) $params->fromRoute('item-set-id');
        if ($currentItemSet) {
            foreach (['item_set_id', 'item_set'] as $key) {
                if (!empty($request[$key])) {
                    $value = $request[$key];
                    if (is_array($value)) {
                        // Check if this is not a sub array (item_set[id][]).
                        $first = reset($value);
                        if (is_array($first)) {
                            $value = $first;
                        }
                        $pos = array_search($currentItemSet, $value);
                        if ($pos !== false) {
                            if (count($request[$key]) <= 1) {
                                unset($request[$key]);
                            } else {
                                unset($request[$key][$pos]);
                            }
                        }
                    } elseif ((int) $value === $currentItemSet) {
                        unset($request[$key]);
                    }
                }
            }
        }

        $request['__searchConfig'] = $this;
        $request['__searchQuery'] = $query;

        // The search filters trigger event "'view.search.filters", that calls
        // the method filterSearchingFilter(). This process allows to use the
        // standard filters.
        return $this->getViewHelper('searchFilters')->__invoke($template, $request);
    }

    /**
     * @todo Remove site (but manage direct query).
     * @todo Manage direct query here? Remove it?
     *
     * Adapted:
     * @see \AdvancedSearch\Api\Representation\SearchConfigRepresentation::suggest()
     * @see \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation::suggest()
     * @see \AdvancedSearch\Form\MainSearchForm::listValuesForField()
     * @see \Reference\Mvc\Controller\Plugin\References
     */
    public function suggest(string $q, ?string $field, ?SiteRepresentation $site): Response
    {
        /**
         * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
         * @var \Laminas\Log\Logger $logger
         * @var \Laminas\I18n\Translator\Translator $translator
         * @var \Omeka\Mvc\Controller\Plugin\UserIsAllowed $userIsAllowed
         */
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $translator = $services->get('MvcTranslator');
        $easyMeta = $services->get('Common\EasyMeta');
        $userIsAllowed = $plugins->get('userIsAllowed');

        $response = new Response();
        $response->setApi($api);

        if ($field === null) {
            // Check if the main index exists when no field is set.
            // The suggester may be the url, but in that case it's pure js and the
            // query doesn't come here (for now).
            $suggesterId = $this->subSetting('q', 'suggester');
            if (!$suggesterId) {
                $message = new PsrMessage(
                    'The search page "{search_page}" has no suggester.', // @translate
                    ['search_page' => $this->slug()]
                );
                $logger->err($message->getMessage(), $message->getContext());
                return $response
                    ->setMessage($message->setTranslator($translator));
            }

            try {
                /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
                $suggester = $api->read('search_suggesters', $suggesterId)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $message = new PsrMessage(
                    'The search page "{search_page}" has no more suggester.', // @translate
                    ['search_page' => $this->slug()]
                );
                $logger->err($message->getMessage(), $message->getContext());
                return $response
                    ->setMessage($message->setTranslator($translator));
            }

            return $suggester->suggest($q, $site);
        }

        // When a field is set, there is no suggester for now, so use a direct
        // query.

        $searchEngine = $this->searchEngine();
        if (!$searchEngine) {
            $message = new PsrMessage(
                'The search page "{search_page}" has no search engine.', // @translate
                ['search_page' => $this->slug()]
            );
            $logger->err($message->getMessage(), $message->getContext());
            return $response
                ->setMessage($message->setTranslator($translator));
        }

        // Prepare dynamic query.
        $query = new Query();

        $query->setQuery($q);

        // TODO Manage roles from modules and visibility from modules (access resources).
        // FIXME Researcher and author may not access all private resources. So index resource owners and roles?
        // Default is public only.
        $accessToAdmin = $userIsAllowed('Omeka\Controller\Admin\Index', 'browse');
        if ($accessToAdmin) {
            $query->setIsPublic(false);
        }

        if ($site) {
            $query->setSiteId($site->id());
        }

        $aliases = $this->subSetting('index', 'aliases', []);
        $fieldQueryArgs = $this->subSetting('index', 'query_args', []);
        $query
            ->setAliases($aliases)
            ->setFieldsQueryArgs($fieldQueryArgs)
            ->setOption('remove_diacritics', (bool) $this->subSetting('q', 'remove_diacritics', false))
            ->setOption('default_search_partial_word', (bool) $this->subSetting('q', 'default_search_partial_word', false));

        $fields = [];
        if ($field) {
            $metadataFieldsToNames = [
                'resource_name' => 'resource_type',
                'resource_type' => 'resource_type',
                'is_public' => 'is_public',
                'owner_id' => 'o:owner',
                'site_id' => 'o:site',
                'resource_class_id' => 'o:resource_class',
                'resource_template_id' => 'o:resource_template',
                'item_set_id' => 'o:item_set',
                'access' => 'access',
                'item_sets_tree' => 'o:item_set',
            ];
            // Convert aliases into a list of property terms.
            // Normalize search query keys as omeka keys for items and item sets.
            $cleanField = $metadataFieldsToNames[$field]
                ?? $easyMeta->propertyTerm($field)
                ?? $aliases[$field]['fields']
                ?? $field;
            $fields = (array) $cleanField;
        }

        $searchEngineSettings = $searchEngine->settings();

        $query
            ->setResourceTypes($searchEngineSettings['resource_types'])
            ->setLimitPage(1, \Omeka\Stdlib\Paginator::PER_PAGE)
            ->setSuggestOptions([
                'suggester' => null,
                'direct' => true,
                'mode_index' => 'start',
                'mode_search' => 'start',
                'length' => 50,
            ])
            ->setSuggestFields($fields)
            // ->setExcludedFields([])
        ;

        /** @var \AdvancedSearch\Querier\QuerierInterface $querier */
        $querier = $searchEngine
            ->querier()
            ->setQuery($query);
        try {
            return $querier->querySuggestions();
        } catch (QuerierException $e) {
            $message = new PsrMessage(
                "Query error: {message}\nQuery:{query}", // @translate
                ['message' => $e->getMessage(), 'query' => $query->jsonSerialize()]
            );
            $this->logger()->err($message->getMessage(), $message->getContext());
            return $response
                ->setMessage($message);
        }
    }
}
