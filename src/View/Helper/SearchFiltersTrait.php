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
                'near' => $translate('is similar to'), // @translate
                'nnear' => $translate('is not similar to'), // @translate
                'res' => $translate('is resource with ID'), // @translate
                'nres' => $translate('is not resource with ID'), // @translate
                // 'res' => $translate('is'), // @translate
                // 'nres' => $translate('is not'), // @translate
                'resq' => $translate('is resource matching query'), // @translate
                'nresq' => $translate('is not resource matching query'), // @translate
                'lex' => $translate('is a linked resource'), // @translate
                'nlex' => $translate('is not a linked resource'), // @translate
                'lres' => $translate('is linked with resource with ID'), // @translate
                'nlres' => $translate('is not linked with resource with ID'), // @translate
                'lres' => $translate('is linked with'), // @translate
                'nlres' => $translate('is not linked with'), // @translate
                'lkq' => $translate('is linked with query'), // @translate
                'nlkq' => $translate('is not linked with query'), // @translate
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
                'dup' => $translate('has duplicate values'), // @translate
                'ndup' => $translate('has not duplicate values'), // @translate
                'dupt' => $translate('has duplicate values and type'), // @translate
                'ndupt' => $translate('has not duplicate values and type'), // @translate
                'dupl' => $translate('has duplicate values and language'), // @translate
                'ndupl' => $translate('has not duplicate values and language'), // @translate
                'duptl' => $translate('has duplicate values, type and language'), // @translate
                'nduptl' => $translate('has not duplicate values, type and language'), // @translate
                'dupv' => $translate('has duplicate simple values'), // @translate
                'ndupv' => $translate('has not duplicate simple values'), // @translate
                'dupvt' => $translate('has duplicate simple values and type'), // @translate
                'ndupvt' => $translate('has not duplicate simple values and type'), // @translate
                'dupvl' => $translate('has duplicate simple values and language'), // @translate
                'ndupvl' => $translate('has not duplicate simple values and language'), // @translate
                'dupvtl' => $translate('has duplicate simple values, type and language'), // @translate
                'ndupvtl' => $translate('has not duplicate simple values, type and language'), // @translate
                'dupr' => $translate('has duplicate linked resources'), // @translate
                'ndupr' => $translate('has not duplicate linked resources'), // @translate
                'duprt' => $translate('has duplicate linked resources and type'), // @translate
                'nduprt' => $translate('has not duplicate linked resources and type'), // @translate
                'duprl' => $translate('has duplicate linked resources and language'), // @translate
                'nduprl' => $translate('has not duplicate linked resources and language'), // @translate
                'duprtl' => $translate('has duplicate linked resources, type and language'), // @translate
                'nduprtl' => $translate('has not duplicate linked resources, type and language'), // @translate
                'dupu' => $translate('has duplicate uris'), // @translate
                'ndupu' => $translate('has not duplicate uris'), // @translate
                'duput' => $translate('has duplicate uris and type'), // @translate
                'nduput' => $translate('has not duplicate uris and type'), // @translate
                'dupul' => $translate('has duplicate uris and language'), // @translate
                'ndupul' => $translate('has not duplicate uris and language'), // @translate
                'duputl' => $translate('has duplicate uris, type and language'), // @translate
                'nduputl' => $translate('has not duplicate uris, type and language'), // @translate
                'gt' => $translate('greater than'), // @translate
                'gte' => $translate('greater than or equal'), // @translate
                'lte' => $translate('lower than or equal'), // @translate
                'lt' => $translate('lower than'), // @translate
            ];
        }

        return $queryTypesLabels;
    }
}
