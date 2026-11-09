<?php declare(strict_types=1);

namespace AdvancedSearchTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The upgrade to 3.4.68 restores the form of the search configs left under the
 * key "default" by previous upgrades, and adds the submit button.
 *
 * @group upgrade
 */
class UpgradeSearchConfigFormTest extends AbstractHttpControllerTestCase
{
    use AdvancedSearchTestTrait;

    /**
     * @var int[]
     */
    protected $configIds = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        if ($this->configIds) {
            $this->getServiceLocator()->get('Omeka\Connection')->executeStatement(
                'DELETE FROM `search_config` WHERE `id` IN (' . implode(',', array_map('intval', $this->configIds)) . ')'
            );
        }
        $this->logout();
        parent::tearDown();
    }

    public function testFormUnderDefaultIsRestored(): void
    {
        $id = $this->insertConfig('upgrade-default', [
            'form' => [
                'default' => [
                    'name' => 'Old',
                    'button_submit' => true,
                    'label_submit' => 'Go',
                    'filters' => [
                        'advanced' => ['type' => 'Advanced', 'label' => 'Filters'],
                        'title' => ['field' => 'dcterms:title', 'label' => 'Title', 'type' => 'Text'],
                    ],
                    'advanced' => [
                        'default_number' => 2,
                        'max_number' => 5,
                        'field_joiner' => true,
                        'field_operator' => true,
                        'fields' => ['dcterms:title' => 'Title'],
                    ],
                ],
                'filters' => [
                    // Rebuilt from an alias: no type.
                    'creator' => ['field' => 'alias_creator'],
                    'subject' => ['field' => 'dcterms:subject', 'type' => 'Text', 'label' => 'Subject'],
                ],
            ],
            'index' => ['aliases' => ['alias_creator' => ['fields' => ['dcterms:creator']]]],
        ]);

        $form = $this->upgradeAndGetForm($id);

        $this->assertArrayNotHasKey('default', $form);
        $this->assertArrayNotHasKey('name', $form);
        $this->assertSame('Go', $form['label_submit']);
        $this->assertSame(['advanced', 'title', 'subject'], array_keys($form['filters']));
        $this->assertSame([
            'default_number' => 2,
            'max_number' => 5,
            'field_elements' => ['joiner', 'operator'],
            'fields' => ['dcterms:title' => 'Title'],
        ], $form['filters']['advanced']['options']);
    }

    public function testSubmitButtonIsAdded(): void
    {
        $id = $this->insertConfig('upgrade-button', [
            'form' => ['filters' => ['q' => ['type' => 'Text', 'field' => 'q']]],
        ]);

        $form = $this->upgradeAndGetForm($id);

        $this->assertTrue($form['button_submit']);
        $this->assertNotSame('', (string) $form['label_submit']);
        $this->assertSame(['q' => ['type' => 'Text', 'field' => 'q']], $form['filters']);
    }

    public function testUpgradeIsReplayable(): void
    {
        $id = $this->insertConfig('upgrade-replay', [
            'form' => [
                'default' => ['filters' => ['title' => ['field' => 'dcterms:title', 'type' => 'Text']]],
                'button_submit' => false,
            ],
        ]);

        $first = $this->upgradeAndGetForm($id);
        $second = $this->upgradeAndGetForm($id);

        $this->assertSame($first, $second);
        // An explicit choice is kept.
        $this->assertFalse($second['button_submit']);
    }

    protected function insertConfig(string $slug, array $settings): int
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $connection->executeStatement(
            'INSERT INTO `search_config` (`engine_id`, `name`, `slug`, `form_adapter`, `settings`, `created`) VALUES (?, ?, ?, ?, ?, NOW())',
            [$this->internalSearchEngine()->id(), $slug, $slug, 'main', json_encode($settings)]
        );
        $id = (int) $connection->lastInsertId();
        $this->configIds[] = $id;
        return $id;
    }

    protected function upgradeAndGetForm(int $id): array
    {
        $services = $this->getServiceLocator();
        $module = new \AdvancedSearch\Module();
        $module->setServiceLocator($services);
        $module->upgrade('3.4.67', '3.4.68', $services);
        $settings = $services->get('Omeka\Connection')
            ->fetchOne('SELECT `settings` FROM `search_config` WHERE `id` = ?', [$id]);
        return json_decode((string) $settings, true)['form'] ?? [];
    }
}
