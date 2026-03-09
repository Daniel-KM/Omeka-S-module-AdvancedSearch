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

        if ($queryTypesLabels === null) {
            $this->translator ??= $this->getView()
                ->getHelperPluginManager()
                ->get('translate')->getTranslator();
            $queryTypesLabels = array_map(
                [$this->translator, 'translate'],
                \AdvancedSearch\Form\TraitCommonSettings::filterTypeLabels()
            );
            // Extra aliases for core property types (dt/ndt) and
            // deprecated types (list/nlist).
            $queryTypesLabels += [
                'dt' => $queryTypesLabels['dtp'],
                'ndt' => $queryTypesLabels['ndtp'],
                'list' => $this->translator->translate('is in list'), // @translate
                'nlist' => $this->translator->translate('is not in list'), // @translate
            ];
        }

        return $queryTypesLabels;
    }
}
