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
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;

class SearchingForm extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/searching-form';

    public function getLabel()
    {
        return 'Search form (module Advanced Search)'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = ($block->getData() ?? []) + ['query' => '', 'query_filter' => ''];

        if (empty($data['query'])) {
            $data['query'] = [];
        } elseif (!is_array($data['query'])) {
            $query = [];
            parse_str(ltrim($data['query'], "? \t\n\r\0\x0B"), $query);
            $data['query'] = array_filter($query, fn ($v) => $v !== '' && $v !== [] && $v !== null);
        }

        if (empty($data['query_filter'])) {
            $data['query_filter'] = [];
        } elseif (!is_array($data['query_filter'])) {
            $query = [];
            parse_str(ltrim($data['query_filter'], "? \t\n\r\0\x0B"), $query);
            $data['query_filter'] = array_filter($query, fn ($v) => $v !== '' && $v !== [] && $v !== null);
        }

        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $searchConfig = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['advancedsearch']['block_settings']['searchingForm'];
        $blockFieldset = \AdvancedSearch\Form\SearchingFormFieldset::class;

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        $data['query'] = http_build_query($data['query'] ?? [], '', '&', PHP_QUERY_RFC3986);
        $data['query_filter'] = http_build_query($data['query_filter'] ?? [], '', '&', PHP_QUERY_RFC3986);

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        return $view->formCollection($fieldset);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $data = $block->data();

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $searchConfig = $data['search_config'] ?? null;
        $searchConfigId = empty($searchConfig) || $searchConfig === 'default' ? null : (int) $searchConfig;
        $searchConfig = $view->getSearchConfig($searchConfigId);
        if (!$searchConfig) {
            $view->logger()->err(
                'No search config specified for this block or not available for this site.' // @translate
            );
            return '';
        }

        $form = $searchConfig->form();
        if (!$form) {
            $view->logger()->warn(
                'The search config "{search_slug}" has no form associated.', // @translate
                ['search_slug' => $searchConfig->slug()]
            );
            return '';
        }

        $site = $block->page()->site();

        $displayResults = !empty($data['display_results']);

        if (!$displayResults) {
            $form->setAttribute('action', $searchConfig->siteUrl($site->slug()));
        }

        if (empty($data['link'])) {
            $link = [];
        } else {
            $link = explode(' ', $data['link'], 2);
            $link = ['url' => trim($link[0]), 'label' => trim($link[1] ?? '')];
        }

        $vars = [
            'block' => $block,
            'site' => $site,
            'searchConfig' => $searchConfig,
            'request' => null,
            'link' => $link,
            // Returns results on the same page.
            'skipFormAction' => $displayResults,
            'displayResults' => $displayResults,
        ];

        if ($displayResults) {
            $query = $data['query'] ?? [];
            $filterQuery = $data['query_filter'] ?? [];
            $query += $filterQuery;

            $request = $view->params()->fromQuery();
            $request = array_filter($request, fn ($v) => $v !== '' && $v !== [] && $v !== null);
            if ($request) {
                $request += $filterQuery;
                $request = $this->validateSearchRequest($searchConfig, $form, $request) ?: $query;
            } else {
                $request = $query;
            }
            $vars['request'] = $request;

            $plugins = $block->getServiceLocator()->get('ControllerPluginManager');
            $searchRequestToResponse = $plugins->get('searchRequestToResponse');
            $result = $searchRequestToResponse($request, $searchConfig, $site);
            if ($result['status'] === 'success') {
                $vars = array_replace($vars, $result['data']);
            } elseif ($result['status'] === 'error') {
                $messenger = $plugins->get('messenger');
                $messenger->addError($result['message']);
            }
        }

        return $view->partial($templateViewScript, $vars);
    }

    /**
     * Get the request from the query and check it according to the search config.
     *
     * @todo Factorize with \AdvancedSearch\Controller\SearchController::getSearchRequest()
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
                    $messenger = $searchConfig->getServiceLocator()->get('ControllerPluginManager')->get('messenger');
                    $messenger->addError('Invalid or missing CSRF token'); // @translate
                    return false;
                }
            }
        }
        return $request;
    }
}
