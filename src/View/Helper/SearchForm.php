<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use AdvancedSearch\Adapter\AdapterInterface;
use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\Api\Representation\SearchEngineRepresentation;
use Laminas\Form\Form;
use Laminas\View\Helper\AbstractHelper;

/**
 * @todo Remove this view helper and use SearchConfigRepresentation::form() only.
 */
class SearchForm extends AbstractHelper
{
    /**
     * The default partial view script.
     *
     * With the default form, this is search/search-form-main.
     */
    const PARTIAL_NAME = 'search/search-form';

    /**
     * @var SearchConfigRepresentation
     */
    protected $searchConfig;

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var string
     */
    protected $template;

    /**
     * Options for the template.
     *
     * @var array
     */
    protected $options = [];

    /**
     * @param SearchConfigRepresentation $searchConfig
     * @param array $options
     * - template (string): Specific template for the form, else the config one.
     * - skip_form_action (bool): Don't set form action, so use the current page.
     * Deprecated: non array options:
     * @param string $partial Specific partial for the search form of the page.
     * @param bool $skipFormAction Don't set form action, so use the current page.
     */
    public function __invoke(?SearchConfigRepresentation $searchConfig = null, $options = [], $skipFormAction = false): self
    {
        if (is_array($options)) {
            $options += [
                'template' => null,
                'skip_form_action' => false,
            ];
        } else {
            $options = [
                'template' => $options,
                'skip_form_action' => $skipFormAction,
            ];
        }

        $this->initSearchForm($searchConfig, $options);
        return $this;
    }

    /**
     * Prepare default search page, form and partial.
     *
     * @param SearchConfigRepresentation $searchConfig
     * @param array $options
     * - template (string): Specific partial for the search form.
     * - skip_form_action (bool): Don't set form action, so use the current page.
     */
    protected function initSearchForm(?SearchConfigRepresentation $searchConfig = null, array $options): void
    {
        $this->options = $options;

        $plugins = $this->getView()->getHelperPluginManager();
        $isAdmin = $plugins->get('status')->isAdminRequest();

        if (empty($searchConfig)) {
            $getSearchConfig = $plugins->get('getSearchConfig');
            // If it is on a search page route, use the id.
            // TODO It may be possible to use the search config path.
            $params = $plugins->get('params')->fromRoute();
            $searchConfigId = $params['controller'] === 'AdvancedSearch\Controller\IndexController'
                ? (int) $params['id']
                : null;
            $this->searchConfig = $getSearchConfig($searchConfigId);
        } else {
            $this->searchConfig = $searchConfig;
        }

        $this->form = null;
        $this->template = null;

        if (!$searchConfig) {
            return;
        }

        $formAdapter = $this->searchConfig->formAdapter();
        if ($formAdapter) {
            $this->form = $formAdapter->form();
            if ($this->form) {
                if (empty($options['skip_form_action'])) {
                    $url = $isAdmin
                        ? $this->searchConfig->adminSearchUrl()
                        : $this->searchConfig->siteUrl();
                    $this->form->setAttribute('action', $url);
                }
            }
        }

        if ($this->form) {
            $this->template = $options['template'];
            if (empty($this->template) && $this->searchConfig) {
                $formAdapter = $this->searchConfig->formAdapter();
                $this->template = $formAdapter
                    ? ($formAdapter->getFormPartial() ?: self::PARTIAL_NAME)
                    : self::PARTIAL_NAME;
            }
        }
    }

    /**
     * Get the specified search config.
     */
    public function getSearchConfig(): ?SearchConfigRepresentation
    {
        return $this->searchConfig;
    }

    /**
     * Get the specified search engine.
     */
    public function getSearchEngine(): ?SearchEngineRepresentation
    {
        return $this->searchConfig
            ? $this->searchConfig->engine()
            : null;
    }

    /**
     * Get the specified search adapter.
     */
    public function getSearchAdapter(): ?AdapterInterface
    {
        $searchEngine = $this->getSearchEngine();
        return $searchEngine ? $searchEngine->adapter() : null;
    }

    /**
     * Get the form of the search config.
     *
     * @return \Laminas\Form\Form|null
     */
    public function getForm(): ?Form
    {
        return $this->form;
    }

    /**
     * Get the template form used for this form of this page.
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    public function __toString(): string
    {
        if (!$this->template) {
            return '';
        }

        return $this->getView()->partial($this->template, [
            'searchConfig' => $this->searchConfig,
            'form' => $this->form,
        ] + $this->options);
    }
}
