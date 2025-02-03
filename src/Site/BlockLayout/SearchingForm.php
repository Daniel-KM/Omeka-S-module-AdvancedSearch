<?php declare(strict_types=1);

namespace AdvancedSearch\Site\BlockLayout;

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

        $query = $data['query'] ?? '';
        $data['query'] = is_array($query) ? http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $query;
        $queryFilter = $data['query_filter'] ?? '';
        $data['query_filter'] = is_array($queryFilter) ? http_build_query($queryFilter, '', '&', PHP_QUERY_RFC3986) : $queryFilter;

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

        // Check if it is an item set redirection.
        $itemSetId = (int) $view->params()->fromRoute('item-set-id');
        // This is just a check: if set, mvc listeners add item_set['id'][].
        // @see \AdvancedSearch\Mvc\MvcListeners::redirectItemSetToSearch()
        // May throw a not found exception.
        // TODO Use site item set ?
        $itemSet = $itemSetId
            ? $view->api()->read('item_sets', ['id' => $itemSetId])->getContent()
            : null;

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
            'itemSet' => $itemSet,
            'request' => null,
            'link' => $link,
            // Returns results on the same page.
            'skipFormAction' => $displayResults,
            'displayResults' => $displayResults,
        ];

        $formAdapter = $searchConfig->formAdapter();

        if ($displayResults) {
            $query = $data['query'] ?? [];
            $filterQuery = $data['query_filter'] ?? [];
            $query += $filterQuery;

            $request = $view->params()->fromQuery();
            $request = $formAdapter->cleanRequest($request);
            $isEmptyRequest = $formAdapter->isEmptyRequest($request);
            if ($isEmptyRequest) {
                $request = $query + ['page' => 1];
            } else {
                $request += $filterQuery;
                if (!$formAdapter->validateRequest($request)) {
                    $request = $query;
                }
            }
            $vars['request'] = $request;

            $response = $formAdapter->toResponse($request, $site);
            if ($response->isSuccess()) {
                $vars['query'] = $response->getQuery();
                $vars['response'] = $response;
            } else {
                $msg = $response->getMessage();
                if ($msg) {
                    $plugins = $block->getServiceLocator()->get('ControllerPluginManager');
                    $messenger = $plugins->get('messenger');
                    $messenger->addError($msg);
                }
            }
        }

        return $view->partial($templateViewScript, $vars);
    }
}
