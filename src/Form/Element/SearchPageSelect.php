<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Element;

use Laminas\Form\Element\Select;

class SearchConfigSelect extends Select
{
    protected $apiManager;

    public function getValueOptions()
    {
        $response = $this->getApiManager()->search('search_configs');
        $searchConfigs = $response->getContent();

        $options = [];
        foreach ($searchConfigs as $searchConfig) {
            $options[$searchConfig->id()] = $searchConfig->name();
        }

        return $options;
    }

    public function setApiManager($apiManager)
    {
        $this->apiManager = $apiManager;
        return $this;
    }

    public function getApiManager()
    {
        return $this->apiManager;
    }
}
