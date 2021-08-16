<?php declare(strict_types=1);
namespace AdvancedSearch\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use AdvancedSearch\Api\Representation\SearchConfigRepresentation;
use AdvancedSearch\View\Helper\AdvancedSearchForm as SearchFormHelper;

class SearchForm extends AbstractPlugin
{
    /**
     * @var SearchFormHelper
     */
    protected $searchFormHelper;

    /**
     * @param SearchFormHelper $searchFormHelper
     */
    public function __construct(SearchFormHelper $searchFormHelper)
    {
        $this->searchFormHelper = $searchFormHelper;
    }

    /**
     * @param SearchConfigRepresentation|null $searchConfig
     * @return \Laminas\Form\Form;
     */
    public function __invoke(SearchConfigRepresentation $searchConfig = null)
    {
        $searchForm = $this->searchFormHelper;
        return $searchForm($searchConfig)->getForm();
    }
}
