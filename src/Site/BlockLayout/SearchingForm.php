<?php
namespace Search\Site\BlockLayout;

use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Search\Api\Representation\SearchPageRepresentation;
use Search\Response;
use Zend\View\Renderer\PhpRenderer;

class SearchingForm extends AbstractBlockLayout
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/searching-form';

    public function getLabel()
    {
        return 'Search form (module Search)'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $data['query'] = ltrim($data['query'], "? \t\n\r\0\x0B");
        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['search']['block_settings']['searchingForm'];
        $blockFieldset = \Search\Form\SearchingFormFieldset::class;

        $data = $block ? $block->data() + $defaultSettings : $defaultSettings;

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        return $view->formCollection($fieldset);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $searchPage = $block->dataValue('search_page');
        if ($searchPage) {
            /** @var \Search\Api\Representation\SearchPageRepresentation $searchPage */
            try {
                $searchPage = $view->api()->read('search_pages', ['id' => $searchPage])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $view->logger()->err($e->getMessage());
                return '';
            }
            $available = $view->siteSetting('search_pages');
            if (!in_array($searchPage->id(), $available)) {
                $message = new \Omeka\Stdlib\Message(
                    'The search page #%d is not available for the site %s.', // @translate
                    $searchPage->id(), $block->page()->site()->slug()
                );
                $view->logger()->err($message);
                return '';
            }
        }

        /** @var \Zend\Form\Form $form */
        $form = $view->searchForm($searchPage)->getForm();
        if (!$form) {
            return '';
        }

        $site = $block->page()->site();
        $displayResults = $block->dataValue('display_results', false);

        $vars = [
            'heading' => $block->dataValue('heading', ''),
            'displayResults' => $displayResults,
            'searchPage' => $searchPage,
            'site' => $site,
            'query' => null,
            'response' => new Response,
            'sortOptions' => [],
        ];

        if ($displayResults) {
            $query = [];
            parse_str($block->dataValue('query'), $query);

            $request = $view->params()->fromQuery();
            if ($request) {
                $request = $this->validateSearchRequest($searchPage, $form, $request) ?: $query;
            } else {
                $request = $query;
            }

            $result = $view->searchRequestToResponse($request, $searchPage, $site);
            if ($result['status'] === 'success') {
                $vars = array_replace($vars, $result['data']);
            } elseif ($result['status'] === 'error') {
                $this->messenger()->addError($result['message']);
            }
        }

        $template = $block->dataValue('template', self::PARTIAL_NAME);
        return $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }

    /**
     * Get the request from the query and check it according to the search page.
     *
     * @todo Factorize with \Search\Controller\IndexController::getSearchRequest()
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
     * @todo Factorize with \Search\Controller\IndexController::filterExtraParams()
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
