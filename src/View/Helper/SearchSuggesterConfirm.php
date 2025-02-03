<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Form\ConfirmForm;

/**
 * View helper for rendering a confirm partial.
 *
 * @note Similar to DeleteConfirm, but with a different link and partial.
 * @todo Modify the core view helper to manage any action and partial.
 * @see \Omeka\View\Helper\DeleteConfirm
 */
class SearchSuggesterConfirm extends AbstractHelper
{
    protected $formElementManager;

    /**
     * Construct the helper.
     *
     * @param ServiceLocatorInterface $formElementManager
     */
    public function __construct(ServiceLocatorInterface $formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Render the index confirm partial.
     *
     * @param \Omeka\Api\Representation\RepresentationInterface $resource
     * @param string $resourceLabel
     * @param bool $wrapSidebar
     * @param array $listJobsStatusesByIds
     * @return string
     */
    public function __invoke($resource, ?string $resourceLabel = null, ?bool $wrapSidebar = true, array $listJobsStatusesByIds = [])
    {
        $form = $this->formElementManager->get(ConfirmForm::class);
        $form->setAttribute('action', $resource->url('index'));

        return $this->getView()->partial(
            'advanced-search/admin/search-suggester/index-confirm',
            [
                'wrapSidebar' => $wrapSidebar,
                'resource' => $resource,
                'resourceLabel' => $resourceLabel,
                'form' => $form,
                'listJobsStatusesByIds' => $listJobsStatusesByIds,
            ]
        );
    }
}
