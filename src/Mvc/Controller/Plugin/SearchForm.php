<?php declare(strict_types=1);
namespace AdvancedSearch\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use AdvancedSearch\Api\Representation\SearchPageRepresentation;
use AdvancedSearch\View\Helper\SearchForm as SearchFormHelper;

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
     * @param SearchPageRepresentation|null $searchPage
     * @return \Laminas\Form\Form;
     */
    public function __invoke(SearchPageRepresentation $searchPage = null)
    {
        $searchForm = $this->searchFormHelper;
        return $searchForm($searchPage)->getForm();
    }
}
