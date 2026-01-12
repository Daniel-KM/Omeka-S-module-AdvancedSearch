<?php declare(strict_types=1);

namespace AdvancedSearchTest\Controller\Admin;

require_once dirname(__DIR__) . '/SearchControllerTestCase.php';

/**
 * @group controller
 */
class IndexControllerTest extends \AdvancedSearchTest\Controller\SearchControllerTestCase
{
    public function testIndexAction(): void
    {
        $this->dispatch('/admin/search-manager');
        $this->assertResponseStatusCode(200);

        // Check that test engine is listed.
        $this->assertXpathQueryContentRegex('//table[1]//td[1]', '/TestIndex/');
        $this->assertXpathQueryContentRegex('//table[1]//td[2]', '/TestEngineAdapter/');

        // Check that test config is listed.
        $this->assertXpathQueryContentRegex('//table[2]//td[1]', '/TestPage/');
        $this->assertXpathQueryContentRegex('//table[2]//td[2]', '/testsearch/');
    }
}
