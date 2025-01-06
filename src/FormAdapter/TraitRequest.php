<?php declare(strict_types=1);

namespace AdvancedSearch\FormAdapter;

/**
 * Need $this->searchConfig.
 */
trait TraitRequest
{
    public function cleanRequest(array $request): array
    {
        // They should be already removed.
        unset(
            $request['csrf'],
            $request['submit']
        );

        /**
         * Remove null, empty array and zero-length values of an array, recursively.
         */
        $arrayFilterRecursive = null;
        $arrayFilterRecursive = function (array &$array) use (&$arrayFilterRecursive): array {
            foreach ($array as $key => $value) {
                if ($value === null || $value === '' || $value === []) {
                    unset($array[$key]);
                } elseif (is_array($value)) {
                    $array[$key] = $arrayFilterRecursive($value);
                    if (!count($array[$key])) {
                        unset($array[$key]);
                    }
                }
            }
            return $array;
        };

        return $arrayFilterRecursive($request);
    }

    public function isEmptyRequest(array $request): bool
    {
        $checkRequest = array_diff_key(
            $request,
            [
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
                'page' => null,
                'per_page' => null,
                'limit' => null,
                'offset' => null,
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::search().
                'sort_by' => null,
                'sort_order' => null,
                // Used by Advanced Search.
                'resource_type' => null,
                'sort' => null,
            ]
        );

        return !count($checkRequest);
    }

    /**
     * Check if a request is valid for a form. Only the csrf is checked.
     *
     * The request is validated only when it is enabled in the search config.
     * Generally, a search form has no csrf.
     */
    public function validateRequest(array $request): bool
    {
        if (!$this->searchConfig
            || !array_key_exists('csrf', $request)
        ) {
            return true;
        }

        if (!$this->searchConfig->subSetting('request', 'validate_form')) {
            return true;
        }

        $form = $this->searchConfig->form(['variant' => 'csrf']);
        if (!$form) {
            return true;
        }

        $form->setData($request);
        if ($form->isValid()) {
            return true;
        }

        $messages = $form->getMessages();
        if (isset($messages['csrf'])) {
            $messenger = $this->searchConfig->getServiceLocator()->get('ControllerPluginManager')->get('messenger');
            $messenger->addError('Invalid or missing CSRF token'); // @translate
            return false;
        }

        // The error is somewhere else, but not managed (useless for a search).
        return true;
    }
}
