<?php declare(strict_types=1);

namespace AdvancedSearch\Site\BlockLayout;

use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Response;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

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

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData() + ['query' => '', 'query_filter' => ''];
        $data['query'] = ltrim($data['query'], "? \t\n\r\0\x0B");
        $data['query_filter'] = ltrim($data['query_filter'], "? \t\n\r\0\x0B");
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
        $blockFieldset = \AdvancedSearch\Form\AdvancedSearchingFormFieldset::class;

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
        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $block->dataValue('advancedsearch_config');
        if ($searchConfig) {
            try {
                $searchConfig = $view->api()->read('search_configs', ['id' => $searchConfig])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $view->logger()->err($e->getMessage());
                return '';
            }
            $available = $view->siteSetting('search_configs');
            if (!in_array($searchConfig->id(), $available)) {
                $message = new \Omeka\Stdlib\Message(
                    'The search page #%d is not available for the site %s.', // @translate
                    $searchConfig->id(), $block->page()->site()->slug()
                );
                $view->logger()->err($message);
                return '';
            }
        }

        /** @var \Laminas\Form\Form $form */
        $form = $view->searchForm($searchConfig)->getForm();
        if (!$form) {
            return '';
        }

        $site = $block->page()->site();
        $displayResults = $block->dataValue('display_results', false);

        $vars = [
            'heading' => $block->dataValue('heading', ''),
            'displayResults' => $displayResults,
            'searchConfig' => $searchConfig,
            'site' => $site,
            'query' => null,
            'response' => new Response,
        ];

        if ($displayResults) {
            $query = [];
            parse_str((string) $block->dataValue('query'), $query);
            $query = array_filter($query);

            $filterQuery = [];
            parse_str((string) $block->dataValue('query_filter'), $filterQuery);
            $filterQuery = array_filter($filterQuery);

            $query += $filterQuery;

            $request = $view->params()->fromQuery();
            $request = array_filter($request);
            if ($request) {
                $request += $filterQuery;
                $request = $this->validateSearchRequest($searchConfig, $form, $request) ?: $query;
            } else {
                $request = $query;
            }

            $result = $view->searchRequestToResponse($request, $searchConfig, $site);
            if ($result['status'] === 'success') {
                $vars = array_replace($vars, $result['data']);
            } elseif ($result['status'] === 'error') {
                $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                $messenger->addError($result['message']);
            }
        }

        $template = $block->dataValue('template', self::PARTIAL_NAME);
        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $vars)
            : $view->partial(self::PARTIAL_NAME, $vars);
    }

    /**
     * Get the request from the query and check it according to the search page.
     *
     * @todo Factorize with \AdvancedSearch\Controller\IndexController::getSearchRequest()
     *
     * @param SearchConfigRepresentation $searchConfig
     * @param \Laminas\Form\Form $searchForm
     * @param array $request
     * @return array|bool
     */
    protected function validateSearchRequest(
        SearchConfigRepresentation $searchConfig,
        \Laminas\Form\Form $form,
        array $request
    ) {
        // Only validate the csrf.
        // There may be no csrf element for initial query.
        if (array_key_exists('csrf', $request)) {
            $form->setData($request);
            if (!$form->isValid()) {
                $messages = $form->getMessages();
                if (isset($messages['csrf'])) {
                    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger;
                    $messenger->addError('Invalid or missing CSRF token'); // @translate
                    return false;
                }
            }
        }
        return $request;
    }
}
