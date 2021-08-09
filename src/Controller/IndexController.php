<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2021
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

namespace Search\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\Message;
use Search\Api\Representation\SearchPageRepresentation;
use Search\Querier\Exception\QuerierException;
use Search\Query;
use Search\Response;

class IndexController extends AbstractActionController
{
    public function searchAction()
    {
        $pageId = (int) $this->params('id');

        $isPublic = $this->status()->isSiteRequest();
        if ($isPublic) {
            $site = $this->currentSite();
            $siteSettings = $this->siteSettings();
            $siteSearchPages = $siteSettings->get('search_pages', []);
            if (!in_array($pageId, $siteSearchPages)) {
                return $this->notFoundAction();
            }
            // Check if it is an item set redirection.
            $itemSetId = (int) $this->params('item-set-id');
            // This is just a check: if set, mvc listeners add itemSet['ids'][].
            // @see \Search\Mvc\MvcListeners::redirectItemSetToSearch()
            if ($itemSetId) {
                // May throw a not found exception.
                $this->api()->read('item_sets', $itemSetId);
            }
        } else {
            $site = null;
        }

        // The page is required, else there is no form.
        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->api()->read('search_pages', $pageId)->getContent();

        $view = new ViewModel([
            // The form is not set in the view, but via helper searchingForm()
            // or via searchPage.
            'searchPage' => $searchPage,
            'site' => $site,
            // Set a default empty query and response to simplify view.
            'query' => new Query,
            'response' => new Response,
        ]);

        $request = $this->params()->fromQuery();
        $form = $this->searchForm($searchPage);
        // The form may be empty for a direct query.
        $isJsonQuery = !$form;

        if ($form) {
            // Check csrf issue.
            $request = $this->validateSearchRequest($searchPage, $form, $request);
            if ($request === false) {
                return $view;
            }
        }

        // Check if the query is empty and use the default query in that case.
        // So the default query is used only on the search page.
        list($request, $isEmptyRequest) = $this->cleanRequest($request);
        if ($isEmptyRequest) {
            $defaultResults = $searchPage->subSetting('search', 'default_results') ?: 'default';
            switch ($defaultResults) {
                case 'none':
                    $defaultQuery = '';
                    break;
                case 'query':
                    $defaultQuery = $searchPage->subSetting('search', 'default_query') ?: '';
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

        $result = $this->searchRequestToResponse($request, $searchPage, $site);
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
            /** @var \Search\Response $response */
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

            $indexSettings = $searchPage->index()->settings();
            $result = [];
            foreach ($indexSettings['resources'] as $resource) {
                $result[$resource] = $response->getResults($resource);
            }
            return new JsonModel($result);
        }

        return $view
            ->setVariables($result['data'], true)
            ->setVariable('searchPage', $searchPage);
    }

    public function suggestAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'This action requires an ajax request.', // @translate
            ]);
        }

        $pageId = (int) $this->params('id');

        $isPublic = $this->status()->isSiteRequest();
        if ($isPublic) {
            $site = $this->currentSite();
            $siteSettings = $this->siteSettings();
            $siteSearchPages = $siteSettings->get('search_pages', []);
            if (!in_array($pageId, $siteSearchPages)) {
                return new JsonModel([
                    'status' => 'error',
                    'message' => 'Not a search page for this site.', // @translate
                ]);
            }
            // TODO Manage item set redirection.
        } else {
            $site = null;
        }

        /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
        $searchPage = $this->api()->read('search_pages', $pageId)->getContent();

        /** @var \Search\FormAdapter\FormAdapterInterface $formAdapter */
        $formAdapter = $searchPage->formAdapter();
        if (!$formAdapter) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'The search page has no querier.', // @translate
            ]);
        }

        if (!$searchPage->subSetting('autosuggest', 'enable')) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Auto-suggestion is not enabled on this server.', // @translate
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

        $response = $this->processQuerySuggestions($searchPage, $q, $site);
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

        /** @var \Search\Response $response */
        return new JsonModel([
            'status' => 'success',
            'data' => [
                'query' => $q,
                'suggestions' => $response->getSuggestions(),
            ],
        ]);
    }

    protected function processQuerySuggestions(
        SearchPageRepresentation $searchPage,
        string $q,
        ?SiteRepresentation $site
    ): Response {
        /** @var \Search\FormAdapter\FormAdapterInterface $formAdapter */
        $formAdapter = $searchPage->formAdapter();
        $searchPageSettings = $searchPage->settings();
        $searchFormSettings = $searchPageSettings['form'] ?? [];

        $autosuggestSettings = $searchPage->setting('autosuggest', []);

        // TODO Add a default query to manage any suggestion on any field and suggestions on item set page.

        /** @var \Search\Query $query */
        $query = $formAdapter->toQuery(['q' => $q], $searchFormSettings);

        $searchIndex = $searchPage->index();
        $indexSettings = $searchIndex->settings();

        $user = $this->identity();
        // TODO Manage roles from modules and visibility from modules (access resources).
        $omekaRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];
        if ($user && in_array($user->getRole(), $omekaRoles)) {
            $query->setIsPublic(false);
        }

        if ($site) {
            $query->setSiteId($site->id());
        }

        $query
            ->setResources($indexSettings['resources'])
            ->setLimitPage(1, empty($autosuggestSettings['limit']) ? \Omeka\Stdlib\Paginator::PER_PAGE : (int) $autosuggestSettings['limit'])
            ->setSuggestMode($autosuggestSettings['mode'] ?? 'start')
            ->setSuggestFields($autosuggestSettings['fields'] ?? []);

        /** @var \Search\Querier\QuerierInterface $querier */
        $querier = $searchIndex
            ->querier()
            ->setQuery($query);
        try {
            return $querier->querySuggestions();
        } catch (QuerierException $e) {
            $message = new Message("Query error: %s\nQuery:%s", $e->getMessage(), json_encode($query->jsonSerialize(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); // @translate
            $this->logger()->err($message);
            return (new Response)
                ->setMessage($message);
        }
    }

    /**
     * Get the request from the query and check it according to the search page.
     *
     * @todo Factorize with \Search\Site\BlockLayout\SearchingForm::getSearchRequest()
     *
     * @return array|bool
     */
    protected function validateSearchRequest(
        SearchPageRepresentation $searchPage,
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
     * @todo Factorize with \Search\Mvc\Controller\Plugin\SearchRequestToResponse::cleanRequest()
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
                'resource-type' => null,
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
     * @todo Factorize with \Search\Mvc\Controller\Plugin\SearchRequestToResponse::arrayFilterRecursive()
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
