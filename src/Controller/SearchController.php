<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2026
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
        /**
         * The config is required, else there is no form.
         * @todo Make the config and  the form independant (or noop form).
         *
         * @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig
         * @see \AdvancedSearch\View\Helper\GetSearchConfig
         */

        $params = $this->params();
        $isSiteRequest = $this->status()->isSiteRequest();
        $site = $isSiteRequest ? $this->currentSite() : null;
        $searchConfigId = (int) $params->fromRoute('id');
        $searchConfig = $this->viewHelpers()->get('getSearchConfig')($searchConfigId);
        if ($searchConfig === null) {
            if ($isSiteRequest) {
                $this->logger()->err(
                    'The search engine {search_slug} is not available in site {site_slug}. Check site settings or search config.', // @translate
                    ['search_slug' => $params->fromRoute('search-slug'), 'site_slug' => $site->slug()]
                );
            } else {
                $this->logger()->err(
                    'The search engine {search_slug} is not available for admin. Check main settings or search config.', // @translate
                    ['search_slug' => $params->fromRoute('search-slug')]
                );
            }
            return $this->notFoundAction();
        }

        if ($isSiteRequest) {
            // Check if it is an item set redirection.
            $itemSetId = (int) $params->fromRoute('item-set-id');
            // This is just a check: if set, mvc listeners add item_set['id'][].
            // @see \AdvancedSearch\Mvc\MvcListeners::redirectItemSetToSearch()
            // May throw a not found exception.
            // TODO Use site item set ?
            $itemSet = $itemSetId
                ? $this->api()->read('item_sets', ['id' => $itemSetId])->getContent()
                : null;
        } else {
            $itemSet = null;
            $itemSetId = null;
        }

        // TODO Factorize with rss output below.
        /** @see \AdvancedSearch\FormAdapter\AbstractFormAdapter::renderForm() */
        $view = new ViewModel([
            'site' => $site,
            // The form is set via searchConfig.
            'searchConfig' => $searchConfig,
            'itemSet' => $itemSet,
            // Set a default empty query and response to simplify view.
            // They will be filled in formAdapter.
            'query' => new Query,
            'response' => new Response,
        ]);

        $template = $isSiteRequest ? $searchConfig->subSetting('results', 'template') : null;
        if ($template) {
            $view->setTemplate($template);
        }

        $request = $params->fromQuery();

        // On an item set page, only one item set can be used and the page
        // should limit results to it.
        // With the module Advanced Search, the name of the arg was "item_set".
        if ($itemSet
            // Avoid to duplicate arg for item set, that has a default alias.
            && (empty($request['item_set_id'])
                || (is_numeric($request['item_set_id']) && (int) $request['item_set_id'] !== $itemSetId)
            )
        ) {
            $request['item_set'] = $itemSetId;
        }

        // The form may be empty for a direct query.
        $formAdapter = $searchConfig->formAdapter();
        $hasForm = $formAdapter ? (bool) $formAdapter->getFormClass() : false;
        $isJsonQuery = !$hasForm;

        // If wanted, only the csrf is needed and checked, if any.
        if (!$formAdapter->validateRequest($request)) {
            return $view;
        }

        // Check if the query is empty and use the default query in that case.
        // So the default query is used only on the search config.
        $request = $formAdapter->cleanRequest($request);
        $isEmptyRequest = $formAdapter->isEmptyRequest($request);
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
                        'status' => 'fail',
                        'data' => [
                            'query' => $this->translate('No query.'), // @translate
                        ],
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

        // Get the response.
        $response = $formAdapter->toResponse($request, $site);

        if (!$response->isSuccess()) {
            $this->getResponse()->setStatusCode(\Laminas\Http\Response::STATUS_CODE_500);
            $msg = $response->getMessage();
            if ($isJsonQuery) {
                return new JsonModel([
                    'status' => 'error',
                    'message' => $this->translate($msg ?: 'An error occurred.'), // @translate
                ]);
            }
            if ($msg) {
                $this->messenger()->addError($msg);
            }
            return $view;
        }

        // Warning: The service Paginator is not a shared service: each instance
        // is a new one. Furthermore, the delegator SitePaginatorFactory is not
        // declared in the main config and only used in Omeka MvcListeners().

        /** @see \Omeka\Mvc\Controller\Plugin\Paginator */
        $this->paginator(
            $response->getTotalResults(),
            $response->getCurrentPage(),
            $response->getPerPage()
        );

        if ($isJsonQuery) {
            $searchEngineSettings = $searchConfig->searchEngine()->settings();
            $result = [];
            foreach ($searchEngineSettings['resource_types'] as $resourceType) {
                $result[$resourceType] = $response->getResults($resourceType);
            }
            return new JsonModel($result);
        }

        $vars = [
            'searchConfig' => $searchConfig,
            'itemSet' => $itemSet,
            'site' => $site,
            'query' => $response->getQuery(),
            'response' => $response,
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

}
