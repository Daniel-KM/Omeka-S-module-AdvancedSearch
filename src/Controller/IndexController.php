<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2022
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

class IndexController extends AbstractActionController
{
    public function searchAction()
    {
        $searchConfigId = (int) $this->params('id');

        $isPublic = $this->status()->isSiteRequest();
        if ($isPublic) {
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
            $site = null;
        }

        // The config is required, else there is no form.
        // TODO Make the config and  the form independant (or noop form).
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', $searchConfigId)->getContent();

        $view = new ViewModel([
            // The form is not set in the view, but via helper searchingForm()
            // or via searchConfig.
            'searchConfig' => $searchConfig,
            // "searchPage" is kept to simplify migration.
            'searchPage' => $searchConfig,
            'site' => $site,
            // Set a default empty query and response to simplify view.
            'query' => new Query,
            'response' => new Response,
        ]);

        $request = $this->params()->fromQuery();
        $form = $this->searchForm($searchConfig);
        // The form may be empty for a direct query.
        $isJsonQuery = !$form;

        if ($form) {
            // Check csrf issue.
            $request = $this->validateSearchRequest($searchConfig, $form, $request);
            if ($request === false) {
                return $view;
            }
        }

        // Check if the query is empty and use the default query in that case.
        // So the default query is used only on the search config.
        list($request, $isEmptyRequest) = $this->cleanRequest($request);
        if ($isEmptyRequest) {
            $defaultResults = $searchConfig->subSetting('search', 'default_results') ?: 'default';
            switch ($defaultResults) {
                case 'none':
                    $defaultQuery = '';
                    break;
                case 'query':
                    $defaultQuery = $searchConfig->subSetting('search', 'default_query') ?: '';
                    break;
                case 'default':
                default:
                    // "*" means the default query managed by the search engine.
                    $defaultQuery = '*';
                    break;
            }
            if ($defaultQuery === '') {
                if ($isJsonQuery) {
                    return new JsonModel([
                        'status' => 'error',
                        'message' => 'No query.', // @translate
                    ]);
                }
                return $view;
            }
            $parsedQuery = [];
            parse_str($defaultQuery, $parsedQuery);
            // Keep the other arguments of the request (mainly pagination, sort,
            // and facets).
            $request = $parsedQuery + $request;
        }

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
            foreach ($engineSettings['resources'] as $resource) {
                $result[$resource] = $response->getResults($resource);
            }
            return new JsonModel($result);
        }

        return $view
            ->setVariables($result['data'], true)
            ->setVariable('searchConfig', $searchConfig)
            // "searchPage" is kept to simplify migration.
            ->setVariable('searchPage', $searchConfig);
    }

    public function suggestAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'This action requires an ajax request.', // @translate
            ]);
        }

        $q = (string) $this->params()->fromQuery('q');
        if (!strlen($q)) {
            return new JsonModel([
                'status' => 'success',
                'data' => [
                    'query' => '',
                    'suggestions' => [],
                ],
            ]);
        }

        $searchConfigId = (int) $this->params('id');

        $isPublic = $this->status()->isSiteRequest();
        if ($isPublic) {
            $site = $this->currentSite();
            $siteSettings = $this->siteSettings();
            $siteSearchConfigs = $siteSettings->get('advancedsearch_configs', []);
            if (!in_array($searchConfigId, $siteSearchConfigs)) {
                return new JsonModel([
                    'status' => 'error',
                    'message' => 'Not a search page for this site.', // @translate
                ]);
            }
            // TODO Manage item set redirection.
        } else {
            $site = null;
        }

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $this->api()->read('search_configs', $searchConfigId)->getContent();

        // The suggester may be the url, but in that case it's pure js and the
        // query doesn't come here (for now).
        $suggesterId = $searchConfig->subSetting('autosuggest', 'suggester');
        if (!$suggesterId) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'The search page has no suggester.', // @translate
            ]);
        }

        try {
            /** @var \AdvancedSearch\Api\Representation\SearchSuggesterRepresentation $suggester */
            $suggester = $this->api()->read('search_suggesters', $suggesterId)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'The search page has no more suggester.', // @translate
            ]);
        }

        $response = $suggester->suggest($q, $site);
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
     * Get the request from the query and check it according to the search page.
     *
     * @todo Factorize with \AdvancedSearch\Site\BlockLayout\SearchingForm::getSearchRequest()
     *
     * @return array|bool
     */
    protected function validateSearchRequest(
        SearchConfigRepresentation $searchConfig,
        \Laminas\Form\Form $form,
        array $request
    ) {
        // Only validate the csrf.
        // Note: The search engine is used to display item sets too via the mvc
        // redirection. In that case, there is no csrf element, so no check to
        // do.
        if (array_key_exists('csrf', $request)) {
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
}
