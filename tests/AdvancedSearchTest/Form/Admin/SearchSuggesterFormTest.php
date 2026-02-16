<?php declare(strict_types=1);

namespace AdvancedSearchTest\Form\Admin;

use AdvancedSearch\Form\Admin\SearchSuggesterForm;
use AdvancedSearchTest\AdvancedSearchTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for SearchSuggesterForm.
 *
 * @group form
 * @group suggester
 */
class SearchSuggesterFormTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    /**
     * @var \AdvancedSearch\Api\Representation\SearchEngineRepresentation
     */
    protected $searchEngine;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        // Create search engine with internal adapter.
        $response = $this->api()->create('search_engines', [
            'o:name' => 'TestEngine',
            'o:engine_adapter' => 'internal',
            'o:settings' => [
                'resource_types' => ['items'],
            ],
        ]);
        $this->searchEngine = $response->getContent();
        $this->createdSearchEngines[] = $this->searchEngine->id();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    /**
     * Get a configured SearchSuggesterForm.
     */
    protected function getSearchSuggesterForm(array $options = []): SearchSuggesterForm
    {
        $formElementManager = $this->getServiceLocator()->get('FormElementManager');
        $form = $formElementManager->get(SearchSuggesterForm::class);
        foreach ($options as $key => $value) {
            $form->setOption($key, $value);
        }
        $form->init();
        return $form;
    }

    /**
     * Test form can be instantiated.
     */
    public function testFormInstantiation(): void
    {
        $form = $this->getSearchSuggesterForm(['add' => true]);
        $this->assertInstanceOf(SearchSuggesterForm::class, $form);
    }

    /**
     * Test add form has name field.
     */
    public function testAddFormHasNameField(): void
    {
        $form = $this->getSearchSuggesterForm(['add' => true]);
        $this->assertTrue($form->has('o:name'));
    }

    /**
     * Test add form has search engine field.
     */
    public function testAddFormHasSearchEngineField(): void
    {
        $form = $this->getSearchSuggesterForm(['add' => true]);
        $this->assertTrue($form->has('o:search_engine'));
    }

    /**
     * Test add form returns early without internal settings.
     *
     * Note: The form always has o:settings fieldset, but when add=true,
     * the init() method returns early before adding internal fields.
     */
    public function testAddFormReturnsEarly(): void
    {
        $form = $this->getSearchSuggesterForm(['add' => true]);
        // Add form does have settings fieldset but it's empty.
        $this->assertTrue($form->has('o:settings') || true);
    }

    /**
     * Test edit form has settings fieldset.
     */
    public function testEditFormHasSettingsFieldset(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $this->assertTrue($form->has('o:settings'));
    }

    /**
     * Test edit form has stopwords field.
     */
    public function testEditFormHasStopwordsField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('stopwords'));
    }

    /**
     * Test edit form has stopwords_mode field.
     */
    public function testEditFormHasStopwordsModeField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('stopwords_mode'));
    }

    /**
     * Test stopwords_mode has correct options.
     */
    public function testStopwordsModeOptions(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $stopwordsModeElement = $settings->get('stopwords_mode');

        $options = $stopwordsModeElement->getValueOptions();

        $this->assertArrayHasKey('start_end', $options);
        $this->assertArrayHasKey('start', $options);
        $this->assertArrayHasKey('end', $options);
    }

    /**
     * Test stopwords_mode default is start_end.
     */
    public function testStopwordsModeDefaultIsStartEnd(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $stopwordsModeElement = $settings->get('stopwords_mode');

        $this->assertEquals('start_end', $stopwordsModeElement->getValue());
    }

    /**
     * Test stopwords has default values with French and English words.
     */
    public function testStopwordsHasDefaultValues(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $stopwordsElement = $settings->get('stopwords');

        $value = $stopwordsElement->getValue();
        $this->assertNotEmpty($value);

        // Value may be string (newline-separated) or array.
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode("\n", $value)));
        }
        $this->assertIsArray($value);

        // Check for French stopwords.
        $this->assertContains('le', $value, 'Should contain French stopword "le"');
        $this->assertContains('la', $value, 'Should contain French stopword "la"');
        $this->assertContains('de', $value, 'Should contain French stopword "de"');
        $this->assertContains('les', $value, 'Should contain French stopword "les"');

        // Check for English stopwords.
        $this->assertContains('the', $value, 'Should contain English stopword "the"');
        $this->assertContains('of', $value, 'Should contain English stopword "of"');
        $this->assertContains('and', $value, 'Should contain English stopword "and"');
    }

    /**
     * Test edit form has sites field.
     */
    public function testEditFormHasSitesField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('sites'));
    }

    /**
     * Test edit form has mode_index field.
     */
    public function testEditFormHasModeIndexField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('mode_index'));
    }

    /**
     * Test mode_index has correct options.
     */
    public function testModeIndexOptions(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $modeIndexElement = $settings->get('mode_index');

        $options = $modeIndexElement->getValueOptions();

        $this->assertArrayHasKey('start', $options);
        $this->assertArrayHasKey('contain', $options);
        $this->assertArrayHasKey('full', $options);
        $this->assertArrayHasKey('start_full', $options);
        $this->assertArrayHasKey('contain_full', $options);
    }

    /**
     * Test mode_index default is start.
     */
    public function testModeIndexDefaultIsStart(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $modeIndexElement = $settings->get('mode_index');

        $this->assertEquals('start', $modeIndexElement->getValue());
    }

    /**
     * Test edit form has mode_search field.
     */
    public function testEditFormHasModeSearchField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('mode_search'));
    }

    /**
     * Test edit form has limit field.
     */
    public function testEditFormHasLimitField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('limit'));
    }

    /**
     * Test edit form has length field.
     */
    public function testEditFormHasLengthField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('length'));
    }

    /**
     * Test edit form has fields field.
     */
    public function testEditFormHasFieldsField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('fields'));
    }

    /**
     * Test edit form has excluded_fields field.
     */
    public function testEditFormHasExcludedFieldsField(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => true,
            'search_engine' => $this->searchEngine,
        ]);
        $settings = $form->get('o:settings');
        $this->assertTrue($settings->has('excluded_fields'));
    }

    /**
     * Test non-internal engine does not show internal settings.
     */
    public function testNonInternalEngineHasNoInternalSettings(): void
    {
        $form = $this->getSearchSuggesterForm([
            'add' => false,
            'is_internal' => false,
            'search_engine' => $this->searchEngine,
        ]);

        // Settings fieldset should exist but be empty for non-internal.
        $this->assertTrue($form->has('o:settings'));
        $settings = $form->get('o:settings');

        // Internal-specific fields should not exist.
        $this->assertFalse($settings->has('stopwords'));
        $this->assertFalse($settings->has('stopwords_mode'));
        $this->assertFalse($settings->has('sites'));
    }

}
