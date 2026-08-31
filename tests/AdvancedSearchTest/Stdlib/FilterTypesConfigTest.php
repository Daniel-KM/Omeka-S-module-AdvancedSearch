<?php declare(strict_types=1);

namespace AdvancedSearchTest\Stdlib;

use AdvancedSearch\Stdlib\SearchResources;
use PHPUnit\Framework\TestCase;

/**
 * Check the lists of query types stored in the config of the module.
 *
 * The config is loaded before the autoloader of the module, so the lists are
 * literal there and they cannot be built from SearchResources. This test keeps
 * them in sync.
 *
 * @see \AdvancedSearch\Stdlib\SearchResources::filterTypesDisplayed()
 *
 * @group unit
 * @group config
 */
class FilterTypesConfigTest extends TestCase
{
    protected array $config;

    public function setUp(): void
    {
        $this->config = include dirname(__DIR__, 3) . '/config/module.config.php';
    }

    public function testMainSettingsListEveryDisplayedType(): void
    {
        $displayed = SearchResources::filterTypesDisplayed();
        $configured = $this->config['advancedsearch']['settings']['advancedsearch_filter_types'];

        // The admin board keeps all the types, so the literal list of the
        // config must be the one built by the class.
        $this->assertSame($displayed, $configured);
    }

    public function testSiteSettingsAreASubsetOfTheDisplayedTypes(): void
    {
        $displayed = SearchResources::filterTypesDisplayed();
        $configured = $this->config['advancedsearch']['site_settings']['advancedsearch_filter_types'];

        $this->assertNotEmpty($configured);
        $this->assertSame([], array_diff($configured, $displayed));
    }

    public function testSiteSettingsAreReduced(): void
    {
        $configured = $this->config['advancedsearch']['site_settings']['advancedsearch_filter_types'];

        // A stored list contains no negative type and no variant of duplicate:
        // they are derived when the query is built.
        $this->assertSame($configured, SearchResources::collapseFilterTypes($configured));
    }

    public function testDisplayedTypesExpandToEveryRealType(): void
    {
        $displayed = SearchResources::filterTypesDisplayed();
        $all = array_keys(SearchResources::FIELD_QUERY['labels']);

        // No type is lost by the round trip: the 39 checkboxes give back the 84
        // types of the queries.
        $this->assertSame([], array_diff($all, SearchResources::expandFilterTypes($displayed)));
    }

    public function testConfigIsLoadableWithoutTheAutoloaderOfTheModule(): void
    {
        // The class is not usable in the config: getConfig() and the script of
        // upgrade are called before the autoloader of the module is added.
        $contents = (string) file_get_contents(dirname(__DIR__, 3) . '/config/module.config.php');
        $contents = preg_replace('~/\*\*.*?\*/~s', '', $contents) ?? $contents;

        $this->assertSame(0, preg_match('~\\\\?AdvancedSearch\\\\[A-Za-z\\\\]+::~', $contents));
    }
}
