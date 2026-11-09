<?php declare(strict_types=1);

namespace AdvancedSearchTest\Stdlib;

use AdvancedSearch\Stdlib\SearchResources;
use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * A form field "Text" on a property searches a part of the value ("in"), not
 * the exact value, unlike "TextExact" and the lists of values.
 *
 * @group stdlib
 */
class FormFilterQueryTypeTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    protected array $filters = [
        ['field' => 'dcterms:title', 'type' => 'Text'],
        ['field' => 'dcterms:identifier', 'type' => 'TextExact'],
        ['field' => 'dcterms:subject', 'type' => 'MultiText'],
        ['field' => 'dcterms:type', 'type' => 'Select'],
        ['field' => 'dcterms:creator', 'type' => 'Text', 'options' => ['query_type' => 'sw']],
        ['field' => 'dcterms:publisher', 'type' => 'Text', 'options' => ['query_type' => 'unknown']],
    ];

    public function testQueryTypesOfTheFormFilters(): void
    {
        $this->assertSame([
            'dcterms:title' => 'in',
            'dcterms:creator' => 'sw',
            'dcterms:publisher' => 'in',
        ], SearchResources::formFiltersQueryTypes($this->filters));
    }

    public function testFieldsOfTheFormAreExpandedWithTheirQueryType(): void
    {
        $services = $this->getServiceLocator();
        $searchResources = new SearchResources(
            $services->get('Omeka\Connection'),
            $services->get('Common\EasyMeta'),
            $services->get('Omeka\Logger'),
            [
                'aliases' => [],
                'query_args' => [],
                'form_filters_fields' => array_column($this->filters, 'field', 'field'),
                'form_filters_types' => SearchResources::formFiltersQueryTypes($this->filters),
            ]
        );
        $query = $searchResources->expandFieldQueryArgs([
            'dcterms:title' => 'paris',
            'dcterms:identifier' => 'A 12',
            'dcterms:type' => 'Image',
            'dcterms:creator' => 'Dup',
        ]);
        $types = array_column($query['filter'], 'type', 'field');
        $this->assertSame([
            'dcterms:title' => 'in',
            'dcterms:identifier' => 'eq',
            'dcterms:type' => 'eq',
            'dcterms:creator' => 'sw',
        ], $types);
    }
}
