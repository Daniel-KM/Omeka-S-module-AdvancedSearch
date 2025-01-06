<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2024
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

namespace AdvancedSearch\Controller;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Query;
use AdvancedSearch\Response;
use Laminas\Feed\Writer\Feed;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class SearchController extends AbstractActionController
{
    /**
     * @throws \Omeka\Api\Exception\NotFoundException for item set.
     */
    public function searchAction()
    {
        $searchConfigId = (int) $this->params('id');

        $isSiteRequest = $this->status()->isSiteRequest();
        if ($isSiteRequest) {
            $site = $this->currentSite();
            $siteSettings = $this->siteSettings();
            $siteSearchConfigs = $siteSettings->get('advancedsearch_configs', []);
            if (!in_array($searchConfigId, $siteSearchConfigs)) {
                $this->logger()->err(
                    'The search engine {search_slug} is not available in site {site_slug}. Check site settings or search config.', // @translate
                    ['search_slug' => $this->params('search-slug'), 'site_slug' => $site->slug()]
                );
                return $this->notFoundAction();
            }
            // Check if it is an item set redirection.
            $itemSetId = (int) $this->params('item-set-id');
            // This is just a check: if set, mvc listeners add item_set['id'][].
            // @see \AdvancedSearch\Mvc\MvcListeners::redirectItemSetToSearch()
            if ($itemSetId) {
                // May throw a not found exception.
                $this->api()->read('item_sets', $itemSetId);
            }
        } else {
            $site = null;
        }

        // The config is required, else there is no form.
        // TODO Make the config and  the form independant (or noop form).
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', $searchConfigId)->getContent();

        // TODO Factorize with rss output below.
        /** @see \AdvancedSearch\FormAdapter\AbstractFormAdapter::renderForm() */
        $view = new ViewModel([
            // The form is set via searchConfig.
            'searchConfig' => $searchConfig,
            'site' => $site,
            // Set a default empty query and response to simplify view.
            // They will be filled in searchRequestToResponse.
            'query' => new Query,
            'response' => new Response,
        ]);

        $template = $isSiteRequest ? $searchConfig->subSetting('results', 'template') : null;
        if ($template && $template !== 'search/search') {
            $view->setTemplate($template);
        }

        $request = $this->params()->fromQuery();

        // Here, only the csrf is needed, if any.
        $validateForm = (bool) $searchConfig->subSetting('request', 'validate_form');
        if ($validateForm) {
            // Check csrf issue.
            $request = $this->validateSearchRequest($searchConfig, $request);
            if ($request === false) {
                return $view;
            }
        }

        // The form may be empty for a direct query.
        $formAdapter = $searchConfig->formAdapter();
        $hasForm = $formAdapter ? (bool) $formAdapter->getFormClass() : false;
        $isJsonQuery = !$hasForm;

        // Check if the query is empty and use the default query in that case.
        // So the default query is used only on the search config.
        [$request, $isEmptyRequest] = $this->cleanRequest($request);
        if ($isEmptyRequest) {
            $defaultResults = $searchConfig->subSetting('request', 'default_results') ?: 'default';
            switch ($defaultResults) {
                case 'none':
                    $defaultQuery = '';
                    $defaultQueryPost = '';
                    break;
                case 'query':
                    $defaultQuery = $searchConfig->subSetting('request', 'default_query') ?: '';
                    $defaultQueryPost = $searchConfig->subSetting('request', 'default_query_post') ?: '';
                    break;
                case 'default':
                default:
                    // "*" means the default query managed by the search engine.
                    $defaultQuery = '*';
                    $defaultQueryPost = $searchConfig->subSetting('request', 'default_query_post') ?: '';
                    break;
            }
            if ($defaultQuery === '' && $defaultQueryPost === '') {
                if ($isJsonQuery) {
                    return new JsonModel([
                        'status' => 'error',
                        'message' => 'No query.', // @translate
                    ]);
                }
                return $view;
            }
            $parsedQuery = [];
            if ($defaultQuery) {
                parse_str($defaultQuery, $parsedQuery);
            }
            $parsedQueryPost = [];
            if ($defaultQueryPost) {
                parse_str($defaultQueryPost, $parsedQueryPost);
            }
            // Keep the other arguments of the request (mainly pagination, sort,
            // and facets), but append default args if not set in request.
            // It allows user to sort the default query.
            $request = $parsedQuery + $request + $parsedQueryPost;
        }

        /** @see \AdvancedSearch\Mvc\Controller\Plugin\SearchRequestToResponse */
        $result = $this->searchRequestToResponse($request, $searchConfig, $site);

        if ($result['status'] === 'fail') {
            // Currently only "no query".
            if ($isJsonQuery) {
                return new JsonModel([
                    'status' => 'error',
                    'message' => 'No query.', // @translate
                ]);
            }
            return $view;
        }

        if ($result['status'] === 'error') {
            if ($isJsonQuery) {
                return new JsonModel($result);
            }
            $this->messenger()->addError($result['message']);
            return $view;
        }

        if ($isJsonQuery) {
            /** @var \AdvancedSearch\Response $response */
            $response = $result['data']['response'];
            if (!$response) {
                $this->getResponse()->setStatusCode(\Laminas\Http\Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'error',
                    'message' => 'An error occurred.', // @translate
                ]);
            }

            if (!$response->isSuccess()) {
                $this->getResponse()->setStatusCode(\Laminas\Http\Response::STATUS_CODE_500);
                return new JsonModel([
                    'status' => 'error',
                    'message' => $response->getMessage(),
                ]);
            }

            $engineSettings = $searchConfig->engine()->settings();
            $result = [];
            foreach ($engineSettings['resource_types'] as $resourceType) {
                $result[$resourceType] = $response->getResults($resourceType);
            }
            return new JsonModel($result);
        }

        $vars = [
            'searchConfig' => $searchConfig,
            'site' => $site,
            'query' => $result['data']['query'],
            'response' => $result['data']['response'],
        ];

        return $view
            ->setVariables($vars, true);
    }

    public function suggestAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('This action requires an ajax request.'), // @translate
            ]);
        }

        $params = $this->params();

        // Some search engines may use trailing spaces, so keep them.
        $q = (string) $params->fromQuery('q');
        if (!strlen($q)) {
            return new JsonModel([
                'status' => 'success',
                'data' => [
                    'query' => '',
                    'suggestions' => [],
                ],
            ]);
        }

        $searchConfigId = (int) $params->fromRoute('id');

        $isSiteRequest = $this->status()->isSiteRequest();
        if ($isSiteRequest) {
            $site = $this->currentSite();
            $siteSettings = $this->siteSettings();
            $siteSearchConfigs = $siteSettings->get('advancedsearch_configs', []);
            if (!in_array($searchConfigId, $siteSearchConfigs)) {
                return new JsonModel([
                    'status' => 'error',
                    'message' => $this->translate('Not a search page for this site.'), // @translate
                ]);
            }
            // TODO Manage item set redirection.
        } else {
            $site = null;
        }

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        try {
            $searchConfig = $this->api()->read('search_configs', $searchConfigId)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('The seach engine is not available.'), // @translate
            ]);
        }

        $field = $params->fromQuery('field');

        $response = $searchConfig->suggest($q, $field, $site);

        if (!$response) {
            $this->getResponse()->setStatusCode(\Laminas\Http\Response::STATUS_CODE_500);
            return new JsonModel([
                'status' => 'error',
                'message' => $this->translate('An error occurred.'), // @translate
            ]);
        }

        if (!$response->isSuccess()) {
            $this->getResponse()->setStatusCode(\Laminas\Http\Response::STATUS_CODE_500);
            return new JsonModel([
                'status' => 'error',
                'message' => $response->getMessage(),
            ]);
        }

        /** @see \AdvancedSearch\Response $response */
        return new JsonModel([
            'status' => 'success',
            'data' => [
                'query' => $q,
                'suggestions' => $response->getSuggestions(),
            ],
        ]);
    }

    /**
     * Get rss from advanced search results.
     *
     * Adaptation of module Feed.
     * @see \Feed\Controller\FeedController::rss()
     */
    public function rssAction()
    {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        $searchConfigId = (int) $this->params('id');

        $isSiteRequest = $this->status()->isSiteRequest();
        if ($isSiteRequest) {
            $site = $this->currentSite();
            $siteSettings = $this->siteSettings();
            $siteSearchConfigs = $siteSettings->get('advancedsearch_configs', []);
            if (!in_array($searchConfigId, $siteSearchConfigs)) {
                return $this->notFoundAction();
            }
            // Check if it is an item set redirection.
            $itemSetId = (int) $this->params('item-set-id');
            // This is just a check: if set, mvc listeners add item_set['id'][].
            // @see \AdvancedSearch\Mvc\MvcListeners::redirectItemSetToSearch()
            if ($itemSetId) {
                // May throw a not found exception.
                $this->api()->read('item_sets', $itemSetId);
            }
        } else {
            throw new \Omeka\Mvc\Exception\RuntimeException('Rss are available only via public interface.'); // @translate
        }

        // The config is required, else there is no form.
        // TODO Make the config and  the form independant (or noop form) and useless for the rss.
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', $searchConfigId)->getContent();

        // Copy from module Feed.

        $type = $this->params()->fromRoute('feed', 'rss');

        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $this->currentSite();
        $siteSettings = $this->siteSettings();
        $urlHelper = $this->viewHelpers()->get('url');

        $feed = new Feed;
        $feed
            ->setType($type)
            ->setTitle($site->title())
            ->setLink($site->siteUrl($site->slug(), true))
            // Use rdf because Omeka is Semantic, but "atom" is required when
            // the type is "atom".
            ->setFeedLink($urlHelper('site/feed', ['site-slug' => $site->slug()], ['force_canonical' => true]), $type === 'atom' ? 'atom' : 'rdf')
            ->setGenerator('Omeka S module Advanced Search', $searchConfig->getServiceLocator()->get('Omeka\ModuleManager')->getModule('AdvancedSearch')->getIni('version'), 'https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch')
            ->setDateModified(time())
        ;

        $description = $site->summary();
        if ($description) {
            $feed
                ->setDescription($description);
        }
        // The type "rss" requires a description.
        elseif ($type === 'rss') {
            $feed
                ->setDescription($site->title());
        }

        $locale = $siteSettings->get('locale');
        if ($locale) {
            $feed
                ->setLanguage($locale);
        }

        /** @var \Omeka\Api\Representation\AssetRepresentation $asset */
        $asset = $siteSettings->get('feed_logo');
        if (is_numeric($asset)) {
            $asset = $this->api()->searchOne('assets', ['id' => $asset])->getContent();
        }
        if (!$asset) {
            $asset = $site->thumbnail();
        }
        if ($asset) {
            $image = [
                'uri' => $asset->assetUrl(),
                'link' => $site->siteUrl(null, true),
                'title' => $this->translate('Logo'),
                // Optional for "rss".
                // 'description' => '',
                // 'height' => '',
                // 'width' => '',
            ];
            $feed->setImage($image);
        }

        $this->appendEntriesDynamic($feed, $searchConfig);

        $content = $feed->export($type);

        $response = $this->getResponse();
        $response->setContent($content);

        /** @var \Laminas\Http\Headers $headers */
        $headers = $response->getHeaders();
        $headers
            ->addHeaderLine('Content-length: ' . strlen($content))
            ->addHeaderLine('Pragma: public');
        // TODO Manage content type requests (atom/rss).
        // Note: normally, application/rss+xml is the standard one, but text/xml
        // may be more compatible.
        if ($siteSettings->get('feed_media_type', 'standard') === 'xml') {
            $headers
                ->addHeaderLine('Content-type: ' . 'text/xml; charset=UTF-8');
        } else {
            $headers
                ->addHeaderLine('Content-type: ' . 'application/' . $type . '+xml; charset=UTF-8');
        }

        $contentDisposition = $siteSettings->get('feed_disposition', 'attachment');
        switch ($contentDisposition) {
            case 'undefined':
                break;
            case 'inline':
                $headers
                    ->addHeaderLine('Content-Disposition', 'inline');
                break;
            case 'attachment':
            default:
                $filename = 'feed-' . (new \DateTime('now'))->format('Y-m-d') . '.' . $type . '.xml';
                $headers
                    ->addHeaderLine('Content-Disposition', $contentDisposition . '; filename="' . $filename . '"');
                break;
        }

        return $response;
    }

    /**
     * Get the request from the query and check it according to the search page.
     *
     * In fact, only check the csrf, but the csrf is removed from the form in
     * most of the cases, so it is useless.
     *
     * @todo Factorize with \AdvancedSearch\Site\BlockLayout\SearchingForm::getSearchRequest()
     * @todo Clarify process of force validation if it is really useful.
     *
     * @return array|bool
     */
    protected function validateSearchRequest(
        SearchConfigRepresentation $searchConfig,
        array $request
    ) {
        // Only validate the csrf.
        // Note: The search engine is used to display item sets too via the mvc
        // redirection. In that case, there is no csrf element, so no check to
        // do.
        if (array_key_exists('csrf', $request)) {
            $form = $searchConfig->form([
                'variant' => 'csrf',
            ]);
            $form->setData($request);
            if (!$form->isValid()) {
                $messages = $form->getMessages();
                if (isset($messages['csrf'])) {
                    $this->messenger()->addError('Invalid or missing CSRF token'); // @translate
                    return false;
                }
            }
        }
        return $request;
    }

    /**
     * Remove all empty values (zero length strings) and check empty request.
     *
     * @todo Factorize with \AdvancedSearch\Mvc\Controller\Plugin\SearchRequestToResponse::cleanRequest()
     * @see \AdvancedSearch\Mvc\Controller\Plugin\SearchRequestToResponse::cleanRequest()
     *
     * @return array First key is the cleaned request, the second a bool to
     * indicate if it is empty.
     */
    protected function cleanRequest(array $request): array
    {
        // They should be already removed.
        unset($request['csrf'], $request['submit']);

        $this->arrayFilterRecursive($request);

        $checkRequest = array_diff_key(
            $request,
            [
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
                'page' => null,
                'per_page' => null,
                'limit' => null,
                'offset' => null,
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::search().
                'sort_by' => null,
                'sort_order' => null,
                // Used by Search.
                'resource_type' => null,
                'sort' => null,
            ]
        );

        return [
            $request,
            !count($checkRequest),
        ];
    }

    /**
     * Remove zero-length values or an array, recursively.
     *
     * @todo Factorize with \AdvancedSearch\Mvc\Controller\Plugin\SearchRequestToResponse::arrayFilterRecursive()
     */
    protected function arrayFilterRecursive(array &$array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->arrayFilterRecursive($value);
                if (!count($array[$key])) {
                    unset($array[$key]);
                }
            } elseif (!strlen(trim((string) $array[$key]))) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * Fill each entry according to the search query.
     *
     * Adaptation of module Feed.
     * @see \Feed\Controller\FeedController::appendEntriesDynamic()
     */
    protected function appendEntriesDynamic(Feed $feed, SearchConfigRepresentation $searchConfig): void
    {
        $controllersToApi = [
            'item' => 'items',
            'resource' => 'resources',
            'item-set' => 'item_sets',
            'media' => 'media',
            'annotation' => 'annotations',
        ];

        // Resource name to controller name.
        $controllerNames = [
            'site_pages' => 'page',
            'items' => 'item',
            'item_sets' => 'item-set',
            'media' => 'media',
            'annotations' => 'annotation',
        ];

        $allowedTags = '<p><a><i><b><em><strong><br>';

        $maxLength = (int) $this->siteSettings()->get('feed_entry_length', 0);

        /** @var \Omeka\Api\Representation\SiteRepresentation $currentSite */
        $currentSite = $this->currentSite();
        $currentSiteSlug = $currentSite->slug();

        $controller = $this->params()->fromRoute('resource-type', 'item');
        $mainResourceType = $controllersToApi[$controller] ?? 'items';

        // TODO Factorize to get results directly.

        $site = $currentSite;

        $request = $this->params()->fromQuery();

        // Check if the query is empty and use the default query in that case.
        // So the default query is used only on the search config.
        [$request, $isEmptyRequest] = $this->cleanRequest($request);
        if ($isEmptyRequest) {
            $defaultResults = $searchConfig->subSetting('request', 'default_results') ?: 'default';
            switch ($defaultResults) {
                case 'none':
                    $defaultQuery = '';
                    $defaultQueryPost = '';
                    break;
                case 'query':
                    $defaultQuery = $searchConfig->subSetting('request', 'default_query') ?: '';
                    $defaultQueryPost = $searchConfig->subSetting('request', 'default_query_post') ?: '';
                    break;
                case 'default':
                default:
                    // "*" means the default query managed by the search engine.
                    $defaultQuery = '*';
                    $defaultQueryPost = $searchConfig->subSetting('request', 'default_query_post') ?: '';
                    break;
            }
            if ($defaultQuery === '' && $defaultQueryPost === '') {
                return;
            }
            $parsedQuery = [];
            if ($defaultQuery) {
                parse_str($defaultQuery, $parsedQuery);
            }
            $parsedQueryPost = [];
            if ($defaultQueryPost) {
                parse_str($defaultQueryPost, $parsedQueryPost);
            }
            // Keep the other arguments of the request (mainly pagination, sort,
            // and facets), but append default args if not set in request.
            // It allows user to sort the default query.
            $request = $parsedQuery + $request + $parsedQueryPost;
        }

        $result = $this->searchRequestToResponse($request, $searchConfig, $site);
        if ($result['status'] === 'fail'
            || $result['status'] === 'error'
        ) {
            return;
        }

        /** @var \AdvancedSearch\Response $response */
        $response = $result['data']['response'];
        if (!$response) {
            return;
        }

        $resources = $response->getResources($mainResourceType);
        foreach ($resources as $resource) {
            // Manage the case where the main resource is "resource".
            $resourceName = $resource->resourceName();

            $entry = $feed->createEntry();
            $id = $controllerNames[$resourceName] . '-' . $resource->id();

            $entry
                ->setId($id)
                ->setLink($resource->siteUrl($currentSiteSlug, true))
                ->setDateCreated($resource->created())
                ->setDateModified($resource->modified())
                ->setTitle((string) $resource->displayTitle($id));

            $content = strip_tags($resource->displayDescription(), $allowedTags);
            if ($content) {
                if ($maxLength) {
                    $clean = trim(str_replace('  ', ' ', strip_tags($content)));
                    $content = mb_substr($clean, 0, $maxLength) . '…';
                } else {
                    $content = trim(strip_tags($content, $allowedTags));
                }
                $entry->setContent($content);
            }
            $shortDescription = $resource->value('bibo:shortDescription');
            if ($shortDescription) {
                $entry->setDescription(strip_tags($shortDescription, $allowedTags));
            }

            $feed->addEntry($entry);
        }
    }
}
