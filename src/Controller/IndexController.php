<?php

/*
 * Copyright BibLibre, 2016-2017
 * Copyright Daniel Berthereau, 2017-2020
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

use Search\Api\Representation\SearchPageRepresentation;
use Search\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

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
            'query' => null,
            // Set a default empty response and values to simplify view.
            'response' => new Response,
            'sortOptions' => [],
        ]);

        $request = $this->params()->fromQuery();

        $form = $this->searchForm($searchPage);

        // The form may be empty for a direct query.
        $isJsonQuery = !$form;

        if ($form) {
            $request = $this->validateSearchRequest($searchPage, $form, $request);
            if ($request === false) {
                return $view;
            }
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
            $response = $result['data']['response'];
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

    /**
     * Get the request from the query and check it according to the search page.
     *
     * @param SearchPageRepresentation $searchPage
     * @param \Zend\Form\Form $searchForm
     * @param array $request
     * @return array|bool
     */
    protected function validateSearchRequest(
        SearchPageRepresentation $searchPage,
        \Zend\Form\Form $form,
        array $request
    ) {
        $searchPageSettings = $searchPage->settings();
        $restrictRequestToForm = !empty($searchPageSettings['restrict_query_to_form']);

        // TODO Validate api query too and add a minimal check of unrestricted queries, even if it's only a search in items, and public/private is always managed.
        // Note: The default query is not checked.
        if ($restrictRequestToForm) {
            $form->setData($request);
            if (!$form->isValid()) {
                $messages = $form->getMessages();
                if (isset($messages['csrf'])) {
                    // The search engine is used to display item sets too via
                    // the mvc redirection. In that case, there is no csrf
                    // element, so no check to do.
                    // TODO Add a csrf check in the mvc redirection of item sets to search page.
                    if (array_key_exists('csrf', $request)) {
                        $this->messenger()->addError('Invalid or missing CSRF token'); // @translate
                        return false;
                    }
                } else {
                    $this->messenger()->addError('There was an error during validation'); // @translate
                    return false;
                }
            }

            // Get the filtered request, but keep the pagination and sort params,
            // that are not managed by the form.
            // FIXME Text filters are not filled with the results, so they are temporary took from the original request.
            $textFilters = isset($request['text']['filters']) ? $request['text']['filters'] : [];
            $request = ['text' => ['filters' => $textFilters]]
                + $form->getData() + $this->filterExtraParams($request);
        }

        return $request;
    }

    /**
     * Filter the pagination and sort params from the request.
     *
     * @todo Warning: "limit" is used as limit (int) of results and as filter for facets (array).
     *
     * @param array $request
     * @return array
     */
    protected function filterExtraParams(array $request)
    {
        $limitFacetRequest = [];
        if (!empty($request['limit']) && is_array($request['limit'])) {
            $limitFacetRequest['limit'] = $request['limit'];
        }

        $paginationRequest = array_map('intval', array_filter(array_intersect_key(
            $request,
            // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
            ['page' => null, 'per_page' => null, 'limit' => null, 'offset' => null]
        )));

        // No filter neither cast here, but checked after.
        $sortRequest = array_intersect_key(
            $request,
            [
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::search().
                'sort_by' => null, 'sort_order' => null,
                // Used by Search.
                'resource-type' => null, 'sort' => null,
            ]
        );

        return $limitFacetRequest + $paginationRequest + $sortRequest;
    }
}
