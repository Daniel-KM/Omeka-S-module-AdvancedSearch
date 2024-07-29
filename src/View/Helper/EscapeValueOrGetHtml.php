<?php declare(strict_types=1);

namespace AdvancedSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Helper\EscapeHtml;
use Omeka\Api\Representation\ValueRepresentation;

class EscapeValueOrGetHtml extends AbstractHelper
{
    /**
     * @var \Laminas\View\Helper\EscapeHtml
     */
    protected $escapeHtml;

    public function __construct(EscapeHtml $escapeHtml)
    {
        $this->escapeHtml = $escapeHtml;
    }

    /**
     * Trim and escape string, decoding entities and stripping tags when needed.
     *
     * In lists, the html should be removed, but the heading or body may return
     * a value or a string. Events "rep.value.string" and "rep.value.html" cannot
     * be used in that case because the context is unknown.
     *
     * The check to determine if a string is html is a quick one because php
     * does not have a function for that before 8.4.
     *
     * @param \Omeka\Api\Representation\ValueRepresentation|\Stringable|string $valueOrString
     *
     * @uses \Laminas\View\Helper\EscapeHtml
     */
    public function __invoke($valueOrString, bool $allowHtml = false): string
    {
        if (!$valueOrString) {
            return (string) $valueOrString;
        }

        // Manage quick process when the input is a value.
        if ($valueOrString instanceof ValueRepresentation) {
            if ($allowHtml) {
                return $valueOrString->asHtml();
            }
            return $this->escapeHtml->__invoke(
                html_entity_decode(
                    strip_tags((string) $valueOrString),
                    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
                )
            );
        }

        // Skip non scalar, except stringable object.
        if (!is_scalar($valueOrString)
            && !(is_object($valueOrString) && method_exists($valueOrString, '__toString'))
        ) {
            return '';
        }

        $string = trim((string) $valueOrString);

        // Quick check if the string is not html.
        if (!$string
            || mb_substr($string, 0, 1) !== '<'
            || mb_substr($string, -1) !== '>'
        ) {
            return $this->escapeHtml->__invoke($string);
        }

        $strippedString = strip_tags($string);

        // Another check when the string is not html.
        if ($string === $strippedString) {
            return $this->escapeHtml->__invoke($string);
        }

        // The string is html.
        if ($allowHtml) {
            return $string;
        }

        return $this->escapeHtml->__invoke(
            html_entity_decode($strippedString, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)
        );
    }
}
