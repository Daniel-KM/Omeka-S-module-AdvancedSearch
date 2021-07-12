<?php declare(strict_types=1);

namespace Search\View\Helper;

use Laminas\View\Helper\AbstractHtmlElement;

/**
 * View helper for building a hidden form input for every argument of a url query.
 */
class HiddenInputsFromFilteredQuery extends AbstractHtmlElement
{
    /**
     * Build a hidden form input for every query in the URL query string.
     *
     * Used to preserve the current query string when submitting a GET form.
     *
     * Similar to Omeka QueryToHiddenInputs, but for any query and any argument.
     * @see \Omeka\View\Helper\QueryToHiddenInputs
     *
     * @param array|null $query Use the current query when null.
     * @param array $skipRootKeys Root keys to remove from a nested query.
     * @param array $skipNames Full key names to remove from a query.
     * @return string
     */
    public function __invoke(?array $query, array $skipRootKeys = [], array $skipNames = []): string
    {
        $html = '';

        if (is_null($query)) {
            $query = $this->getView()->params()->fromQuery();
        }

        if ($skipRootKeys) {
            $query = array_diff_key($query, array_flip($skipRootKeys));
        }

        foreach (explode("\n", http_build_query($query, '', "\n")) as $nameValue) {
            if (!$nameValue) {
                continue;
            }
            [$name, $value] = explode('=', $nameValue, 2);
            $name = urldecode($name);
            if (is_null($value) || in_array($name, $skipNames)) {
                continue;
            }
            $name = htmlspecialchars($name, ENT_COMPAT | ENT_HTML5);
            $value = htmlspecialchars(urldecode($value), ENT_COMPAT | ENT_HTML5);
            $html .= '<input type="hidden" name="' . $name . '" value="' . $value . '"' . "/>\n";
        }
        return $html;
    }
}
