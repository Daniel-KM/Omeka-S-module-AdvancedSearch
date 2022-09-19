<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

trait SearchFiltersTrait
{
    protected function getQueryTypesLabels(): array
    {
        static $queryTypesLabels;

        if (is_null($queryTypesLabels)) {
            $translate = $this->getView()->plugin('translate');
            $queryTypesLabels = [
                'eq' => $translate('is exactly'), // @translate
                'neq' => $translate('is not exactly'), // @translate
                'in' => $translate('contains'), // @translate
                'nin' => $translate('does not contain'), // @translate
                'ex' => $translate('has any value'), // @translate
                'nex' => $translate('has no values'), // @translate
                'exs' => $translate('has a single value'), // @translate
                'nexs' => $translate('has not a single value'), // @translate
                'exm' => $translate('has multiple values'), // @translate
                'nexm' => $translate('has not multiple values'), // @translate
                'list' => $translate('is in list'), // @translate
                'nlist' => $translate('is not in list'), // @translate
                'sw' => $translate('starts with'), // @translate
                'nsw' => $translate('does not start with'), // @translate
                'ew' => $translate('ends with'), // @translate
                'new' => $translate('does not end with'), // @translate
                'res' => $translate('is resource with ID'), // @translate
                'nres' => $translate('is not resource with ID'), // @translate
                'res' => $translate('is'), // @translate
                'nres' => $translate('is not'), // @translate
                'lex' => $translate('is a linked resource'), // @translate
                'nlex' => $translate('is not a linked resource'), // @translate
                'lres' => $translate('is linked with resource with ID'), // @translate
                'nlres' => $translate('is not linked with resource with ID'), // @translate
                'lres' => $translate('is linked with'), // @translate
                'nlres' => $translate('is not linked with'), // @translate
                'tp' => $translate('has main type'), // @translate
                'ntp' => $translate('has not main type'), // @translate
                'tpl' => $translate('has type literal-like'), // @translate
                'ntpl' => $translate('has not type literal-like'), // @translate
                'tpr' => $translate('has type resource-like'), // @translate
                'ntpr' => $translate('has not type resource-like'), // @translate
                'tpu' => $translate('has type uri-like'), // @translate
                'ntpu' => $translate('has not type uri-like'), // @translate
                'dtp' => $translate('has data type'), // @translate
                'ndtp' => $translate('has not data type'), // @translate
                'gt' => $translate('greater than'), // @translate
                'gte' => $translate('greater than or equal'), // @translate
                'lte' => $translate('lower than or equal'), // @translate
                'lt' => $translate('lower than'), // @translate
            ];
        }
        return $queryTypesLabels;
    }
}
