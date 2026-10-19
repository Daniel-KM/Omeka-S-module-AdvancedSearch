<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller\Admin;

use AdvancedSearch\Controller\Admin\SearchConfigController;
use PHPUnit\Framework\TestCase;

/**
 * Tests the cleaning of the settings of a facet before saving.
 *
 * @group controller
 */
class FacetSettingsTest extends TestCase
{
    protected function normalize(array $facet): array
    {
        $controller = (new \ReflectionClass(SearchConfigController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'normalizeFacetToSave');
        $method->setAccessible(true);
        $warnLanguage = false;
        return $method->invokeArgs($controller, [$facet, &$warnLanguage]);
    }

    protected function facet(string $type): array
    {
        return [
            'field' => 'dcterms:subject',
            'label' => 'Subject',
            'type' => $type,
            'order' => 'alphabetic asc',
            'limit' => '25',
            'display_count' => '1',
            'as_link' => '1',
            'intro' => 'Choose a subject',
        ];
    }

    /**
     * An unset input type is the default one, so its settings are kept (#32).
     */
    public function testEmptyTypeKeepsSettingsOfDefaultType(): void
    {
        $facet = $this->normalize($this->facet(''));
        $this->assertSame('alphabetic asc', $facet['order'] ?? null);
        $this->assertSame(25, $facet['limit'] ?? null);
        $this->assertTrue($facet['display_count'] ?? null);
        $this->assertTrue($facet['as_link'] ?? null);
    }

    public function testEmptyTypeKeepsSameSettingsAsCheckbox(): void
    {
        $empty = $this->normalize($this->facet(''));
        $checkbox = $this->normalize($this->facet('Checkbox'));
        unset($empty['type'], $checkbox['type']);
        $this->assertEquals($checkbox, $empty);
    }

    public function testSettingsOfOtherTypesAreRemoved(): void
    {
        // The links are not managed by a select.
        $facet = $this->normalize($this->facet('Select'));
        $this->assertArrayNotHasKey('as_link', $facet);
        $this->assertSame(25, $facet['limit'] ?? null);
    }

    public function testIntroIsKeptForAnyType(): void
    {
        foreach (['', 'Checkbox', 'Select', 'RangeDouble'] as $type) {
            $facet = $this->normalize($this->facet($type));
            $this->assertSame('Choose a subject', $facet['intro'] ?? null, "Type \"$type\"");
        }
    }
}
