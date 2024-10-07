<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

/**
 * Requires translator to be prepared first.
 */
trait SearchFiltersTrait
{
    /**
     * @var \Laminas\I18n\Translator\TranslatorInterface
     */
    protected $translator;

    protected function getQueryTypesLabels(): array
    {
        static $queryTypesLabels;

        if (is_null($queryTypesLabels)) {
            $this->translator ??= $this->getView()->getHelperPluginManager()->get('translate')->getTranslator();
            $queryTypesLabels = [
                // Value.
                'eq' => $this->translator->translate('is exactly'), // @translate
                'neq' => $this->translator->translate('is not exactly'), // @translate
                'in' => $this->translator->translate('contains'), // @translate
                'nin' => $this->translator->translate('does not contain'), // @translate
                'sw' => $this->translator->translate('starts with'), // @translate
                'nsw' => $this->translator->translate('does not start with'), // @translate
                'ew' => $this->translator->translate('ends with'), // @translate
                'new' => $this->translator->translate('does not end with'), // @translate
                'near' => $this->translator->translate('is similar to'), // @translate
                'nnear' => $this->translator->translate('is not similar to'), // @translate
                'ma' => $this->translator->translate('matches'), // @translate
                'nma' => $this->translator->translate('does not match'), // @translate
                // Comparison (alphabetical).
                'lt' => $this->translator->translate('lower than'), // @translate
                'lte' => $this->translator->translate('lower than or equal'), // @translate
                'gte' => $this->translator->translate('greater than or equal'), // @translate
                'gt' => $this->translator->translate('greater than'), // @translate
                // Comparison (numerical).
                '<' => '<',
                '≤' => '≤',
                '≥' => '≥',
                '>' => '>',
                // Internal.
                'list' => $this->translator->translate('is in list'), // @translate
                'nlist' => $this->translator->translate('is not in list'), // @translate
                // Resource (duplcated for translation).
                'res' => $this->translator->translate('is'), // @translate
                'nres' => $this->translator->translate('is not'), // @translate
                'res' => $this->translator->translate('is resource with ID'), // @translate
                'nres' => $this->translator->translate('is not resource with ID'), // @translate
                'resq' => $this->translator->translate('is resource matching query'), // @translate
                'nresq' => $this->translator->translate('is not resource matching query'), // @translate
                // Linked resource (duplcated for translation).
                'lex' => $this->translator->translate('is a linked resource'), // @translate
                'nlex' => $this->translator->translate('is not a linked resource'), // @translate
                'lres' => $this->translator->translate('is linked with resource with ID'), // @translate
                'nlres' => $this->translator->translate('is not linked with resource with ID'), // @translate
                'lres' => $this->translator->translate('is linked with'), // @translate
                'nlres' => $this->translator->translate('is not linked with'), // @translate
                'lkq' => $this->translator->translate('is linked with query'), // @translate
                'nlkq' => $this->translator->translate('is not linked with query'), // @translate
                // Count.
                'ex' => $this->translator->translate('has any value'), // @translate
                'nex' => $this->translator->translate('has no values'), // @translate
                'exs' => $this->translator->translate('has a single value'), // @translate
                'nexs' => $this->translator->translate('has not a single value'), // @translate
                'exm' => $this->translator->translate('has multiple values'), // @translate
                'nexm' => $this->translator->translate('has not multiple values'), // @translate
                // Data type.
                'tp' => $this->translator->translate('has main type'), // @translate
                'ntp' => $this->translator->translate('has not main type'), // @translate
                'tpl' => $this->translator->translate('has type literal-like'), // @translate
                'ntpl' => $this->translator->translate('has not type literal-like'), // @translate
                'tpr' => $this->translator->translate('has type resource-like'), // @translate
                'ntpr' => $this->translator->translate('has not type resource-like'), // @translate
                'tpu' => $this->translator->translate('has type uri-like'), // @translate
                'ntpu' => $this->translator->translate('has not type uri-like'), // @translate
                'dtp' => $this->translator->translate('has data type'), // @translate
                'ndtp' => $this->translator->translate('has not data type'), // @translate
                // Curation (duplicates).
                'dup' => $this->translator->translate('has duplicate values'), // @translate
                'ndup' => $this->translator->translate('has not duplicate values'), // @translate
                'dupt' => $this->translator->translate('has duplicate values and type'), // @translate
                'ndupt' => $this->translator->translate('has not duplicate values and type'), // @translate
                'dupl' => $this->translator->translate('has duplicate values and language'), // @translate
                'ndupl' => $this->translator->translate('has not duplicate values and language'), // @translate
                'duptl' => $this->translator->translate('has duplicate values, type and language'), // @translate
                'nduptl' => $this->translator->translate('has not duplicate values, type and language'), // @translate
                'dupv' => $this->translator->translate('has duplicate simple values'), // @translate
                'ndupv' => $this->translator->translate('has not duplicate simple values'), // @translate
                'dupvt' => $this->translator->translate('has duplicate simple values and type'), // @translate
                'ndupvt' => $this->translator->translate('has not duplicate simple values and type'), // @translate
                'dupvl' => $this->translator->translate('has duplicate simple values and language'), // @translate
                'ndupvl' => $this->translator->translate('has not duplicate simple values and language'), // @translate
                'dupvtl' => $this->translator->translate('has duplicate simple values, type and language'), // @translate
                'ndupvtl' => $this->translator->translate('has not duplicate simple values, type and language'), // @translate
                'dupr' => $this->translator->translate('has duplicate linked resources'), // @translate
                'ndupr' => $this->translator->translate('has not duplicate linked resources'), // @translate
                'duprt' => $this->translator->translate('has duplicate linked resources and type'), // @translate
                'nduprt' => $this->translator->translate('has not duplicate linked resources and type'), // @translate
                'duprl' => $this->translator->translate('has duplicate linked resources and language'), // @translate
                'nduprl' => $this->translator->translate('has not duplicate linked resources and language'), // @translate
                'duprtl' => $this->translator->translate('has duplicate linked resources, type and language'), // @translate
                'nduprtl' => $this->translator->translate('has not duplicate linked resources, type and language'), // @translate
                'dupu' => $this->translator->translate('has duplicate uris'), // @translate
                'ndupu' => $this->translator->translate('has not duplicate uris'), // @translate
                'duput' => $this->translator->translate('has duplicate uris and type'), // @translate
                'nduput' => $this->translator->translate('has not duplicate uris and type'), // @translate
                'dupul' => $this->translator->translate('has duplicate uris and language'), // @translate
                'ndupul' => $this->translator->translate('has not duplicate uris and language'), // @translate
                'duputl' => $this->translator->translate('has duplicate uris, type and language'), // @translate
                'nduputl' => $this->translator->translate('has not duplicate uris, type and language'), // @translate
            ];
        }

        return $queryTypesLabels;
    }
}
