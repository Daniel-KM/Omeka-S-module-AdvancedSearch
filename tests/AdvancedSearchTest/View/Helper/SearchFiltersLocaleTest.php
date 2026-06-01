<?php declare(strict_types=1);

namespace AdvancedSearchTest\View\Helper;

use AdvancedSearch\View\Helper\SearchFilters;
use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Issue #14 (filters side): linked resource titles in filter chips are resolved
 * in the site locale, respecting each resource template title property (not
 * only dcterms:title).
 *
 * @group view
 * @group i18n
 */
class SearchFiltersLocaleTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    protected $titlePropertyId;
    protected $alternativePropertyId;
    protected $templateId;
    protected $itemId;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $this->titlePropertyId = (int) $easyMeta->propertyId('dcterms:title');
        $this->alternativePropertyId = (int) $easyMeta->propertyId('dcterms:alternative');

        // Template whose title property is NOT dcterms:title, to prove the
        // resolution respects the template title property.
        $template = $this->api()->create('resource_templates', [
            'o:label' => 'AltTitleTemplate_' . substr(md5(self::class), 0, 8),
            'o:title_property' => ['o:id' => $this->alternativePropertyId],
        ])->getContent();
        $this->templateId = $template->id();

        $item = $this->api()->create('items', [
            'o:resource_template' => ['o:id' => $this->templateId],
            'dcterms:title' => [
                ['type' => 'literal', 'property_id' => $this->titlePropertyId, '@value' => 'Should not be used'],
            ],
            'dcterms:alternative' => [
                ['type' => 'literal', 'property_id' => $this->alternativePropertyId, '@value' => 'Alt EN', '@language' => 'en'],
                ['type' => 'literal', 'property_id' => $this->alternativePropertyId, '@value' => 'Alt ES', '@language' => 'es'],
            ],
        ])->getContent();
        $this->itemId = $item->id();
        $this->createdResources[] = ['type' => 'items', 'id' => $this->itemId];
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        if ($this->templateId) {
            try {
                $this->api()->delete('resource_templates', $this->templateId);
            } catch (\Throwable $e) {
                // Ignore.
            }
        }
        $this->logout();
        parent::tearDown();
    }

    public function testUsesTemplateTitlePropertyInLocale(): void
    {
        // Template title property is dcterms:alternative, so the "es" value of
        // dcterms:alternative is used, not dcterms:title.
        $out = $this->localize('es');
        $this->assertSame('Alt ES', $out[$this->itemId]);
    }

    public function testFallsBackToDefaultWhenLanguageMissing(): void
    {
        $out = $this->localize('fr');
        $this->assertSame('PLACEHOLDER', $out[$this->itemId]);
    }

    public function testNoLocaleLeavesTitleUnchanged(): void
    {
        $out = $this->localize('');
        $this->assertSame('PLACEHOLDER', $out[$this->itemId]);
    }

    private function localize(string $locale): array
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $helper = new SearchFilters();
        $method = new \ReflectionMethod($helper, 'localizeResourceTitles');
        $method->setAccessible(true);
        return $method->invokeArgs($helper, [
            $connection,
            [$this->itemId => 'PLACEHOLDER'],
            $locale,
            $this->titlePropertyId,
        ]);
    }
}
