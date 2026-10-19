<?php declare(strict_types=1);

namespace AdvancedSearchTest\View\Helper;

use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The label of a value is the same in the facets and in the list of the active
 * filters, for example the title of an item set queried by a Solr index.
 *
 * @group view
 */
class FacetValueLabelTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    protected $itemSetId;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $itemSet = $this->api()->create('item_sets', [
            'dcterms:title' => [
                ['type' => 'literal', 'property_id' => (int) $easyMeta->propertyId('dcterms:title'), '@value' => 'Collection for labels'],
            ],
        ])->getContent();
        $this->itemSetId = $itemSet->id();
        $this->createdResources[] = ['type' => 'item_sets', 'id' => $this->itemSetId];
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    protected function valueLabel(string $field, $value): ?string
    {
        $plugins = $this->getServiceLocator()->get('ViewHelperManager');
        $view = new \Laminas\View\Renderer\PhpRenderer();
        $view->setHelperPluginManager($plugins);
        $helper = $plugins->get('facetLinks');
        $helper->setView($view);
        return $helper->valueLabel($field, $value);
    }

    public function testItemSetIdIndexGivesTitle(): void
    {
        $this->assertSame('Collection for labels', $this->valueLabel('item_set_id_is', (string) $this->itemSetId));
        $this->assertSame('Collection for labels', $this->valueLabel('item_set_id', (string) $this->itemSetId));
    }

    public function testLinkIndexGivesTitle(): void
    {
        $this->assertSame('Collection for labels', $this->valueLabel('dcterms_isPartOf_link_is', (string) $this->itemSetId));
    }

    public function testUnknownResourceGivesNoLabel(): void
    {
        $this->assertNull($this->valueLabel('item_set_id_is', '999999999'));
    }

    public function testPlainValueIsUnchanged(): void
    {
        $this->assertSame('Agriculture', $this->valueLabel('dcterms_subject_ss', 'Agriculture'));
    }

    public function testBooleanIndexGivesYesOrNo(): void
    {
        $translate = $this->getServiceLocator()->get('ViewHelperManager')->get('translate');
        $this->assertSame($translate('Yes'), $this->valueLabel('has_media_b', '1'));
        $this->assertSame($translate('No'), $this->valueLabel('has_media_b', '0'));
    }
}
