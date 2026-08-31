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

    public function testInternalEngineHasNoIndexingCheckbox(): void
    {
        // The internal engine queries the database directly, so there is no
        // indexation to enable or to disable.
        $form = $this->form(true);
        $this->assertFalse($form->has('is_indexing_enabled'));
    }

    private function form(bool $isInternal): SearchEngineConfigureForm
    {
        $form = new SearchEngineConfigureForm();
        $form->setOption('is_adapter_internal', $isInternal);
        $form->init();
        return $form;
    }
}
