<?php declare(strict_types=1);

namespace AdvancedSearchTest\Form\Admin;

use AdvancedSearch\Form\Admin\SearchEngineConfigureForm;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the "Indexing enabled" option (#7) of the configure form.
 *
 * @group unit
 * @group form
 */
class SearchEngineConfigureFormTest extends TestCase
{
    public function testRegularEngineHasEnabledCheckbox(): void
    {
        $form = $this->form(false);
        $element = $form->get('is_indexing_enabled');
        $this->assertInstanceOf(\Laminas\Form\Element\Checkbox::class, $element);
        $this->assertNull($element->getAttribute('disabled'));
    }

    public function testRegularEngineKeepsSubmittedValue(): void
    {
        $form = $this->form(false);
        $form->setData(['is_indexing_enabled' => 'false']);
        $this->assertSame('false', $form->get('is_indexing_enabled')->getValue());
    }

    public function testInternalEngineCheckboxIsCheckedAndDisabled(): void
    {
        $form = $this->form(true);
        $element = $form->get('is_indexing_enabled');
        $this->assertTrue((bool) $element->getAttribute('disabled'));
        $this->assertTrue((bool) $element->getAttribute('checked'));
    }

    public function testInternalEngineForcesEnabledEvenWhenNotPosted(): void
    {
        // A disabled checkbox is not posted: the value must be forced to
        // "true".
        $form = $this->form(true);
        $form->setData([]);
        $this->assertSame('true', $form->get('is_indexing_enabled')->getValue());
    }

    private function form(bool $isInternal): SearchEngineConfigureForm
    {
        $form = new SearchEngineConfigureForm();
        $form->setOption('is_adapter_internal', $isInternal);
        $form->init();
        return $form;
    }
}
