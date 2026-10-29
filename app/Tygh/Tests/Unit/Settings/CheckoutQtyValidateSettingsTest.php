<?php

namespace Tygh {

if (!function_exists(__NAMESPACE__ . '\\fn_allowed_for')) {
    function fn_allowed_for($editions)
    {
        return false;
    }
}

if (!function_exists(__NAMESPACE__ . '\\fn_get_runtime_company_id')) {
    function fn_get_runtime_company_id()
    {
        return 0;
    }
}

}

namespace {

if (!function_exists('fn_allowed_for')) {
    function fn_allowed_for($editions)
    {
        return false;
    }
}

if (!function_exists('fn_get_runtime_company_id')) {
    function fn_get_runtime_company_id()
    {
        return 0;
    }
}

}

namespace Tygh\Tests\Unit\Settings {

use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;
use Tygh\Database\Connection;
use Tygh\Settings;
use Tygh\Tests\Unit\ATestCase;

/**
 * Covers checkout qty string cleanup when quantity step is non-global (SQL NULL) and related cases.
 */
class CheckoutQtyValidateSettingsTest extends ATestCase
{
    public $runTestInSeparateProcess = true;

    public $backupGlobals = false;

    public $preserveGlobalState = false;

    public function setUp(): void
    {
        if (!defined('BOOTSTRAP')) {
            define('BOOTSTRAP', true);
        }

        if (!defined('AREA')) {
            define('AREA', 'A');
        }

        if (!defined('CART_LANGUAGE')) {
            define('CART_LANGUAGE', 'en');
        }

        if (empty(\Tygh::$app['storefront.repository'])) {
            \Tygh::$app['storefront.repository'] = new class {
                public function getCount(array $params = [])
                {
                    return 0;
                }
            };
        }

        if (empty(\Tygh::$app['db'])) {
            \Tygh::$app['db'] = $this->createMock(Connection::class);
        }

        \Tygh::$app['session'] = new class {
            public function isStarted()
            {
                return false;
            }
        };

        $this->requireCore('functions/fn.common.php');
        require_once __DIR__ . '/../../../../schemas/settings/actions.functions.php';
    }

    protected function tearDown(): void
    {
        $this->clearSettingsInstanceCache();
        parent::tearDown();
    }

    private function clearSettingsInstanceCache(): void
    {
        $ref = new ReflectionClass(Settings::class);
        $cache = $ref->getProperty('instance_cache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    /**
     * Makes Settings::instance() return the given mock (same cache key as a parameterless instance() call).
     *
     * @param Settings|MockObject $mock
     */
    private function bindSingletonSettingsMock($mock): void
    {
        $this->clearSettingsInstanceCache();

        $ref = new ReflectionClass(Settings::class);
        $normalize = new ReflectionMethod(Settings::class, 'normalizeInstanceParameters');
        $normalize->setAccessible(true);

        try {
            $params = $normalize->invoke(null, []);
        } catch (\Throwable $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $key = "{$params['area']}_{$params['storefront_id']}_{$params['company_id']}_{$params['has_multiple_storefronts']}";
        $cache = $ref->getProperty('instance_cache');
        $cache->setAccessible(true);
        $cache->setValue(null, [$key => $mock]);
    }

    /**
     * @return array{0: Settings|MockObject, 1: object{state: array<string, mixed>, updates: list<array{0: string, 1: mixed}>}}
     */
    private function buildSettingsMock(array $initial)
    {
        $data = new class {
            /** @var array<string, mixed> */
            public $state = [];
            /** @var list<array{0: string, 1: mixed}> */
            public $updates = [];
        };
        $data->state = $initial;

        /** @var Settings|MockObject $settings */
        $settings = $this->getMockBuilder(Settings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getValue', 'updateValue'])
            ->getMock();

        $settings->method('getValue')->willReturnCallback(
            function ($name, $section) use ($data) {
                $this->assertSame('Checkout', $section);

                return array_key_exists($name, $data->state) ? $data->state[$name] : null;
            }
        );

        $settings->method('updateValue')->willReturnCallback(
            function ($name, $value, $section, $force_cleanup = false, $company_id = null, $execute_functions = true, $storefront_id = null) use ($data) {
                $this->assertSame('Checkout', $section);
                $data->updates[] = [$name, $value];
                $data->state[$name] = $value;

                return true;
            }
        );

        return [$settings, $data];
    }

    public function testInvalidGlobalMinResetToZeroWhenGlobalQtyStepIsNull()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'global_list_qty_count' => null,
            'global_qty_step'       => null,
            'global_min_qty'        => 'not_a_number',
            'global_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('global_');

        $this->assertEquals(0, $data->state['global_min_qty'], 'Invalid min must be persisted as 0 even without global qty step');
        $this->assertUpdatesContainPair(['global_min_qty', 0], $data->updates);
    }

    public function testValidGlobalMinUnchangedWhenGlobalQtyStepIsNull()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'global_list_qty_count' => null,
            'global_qty_step'       => null,
            'global_min_qty'        => '5',
            'global_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('global_');

        $this->assertSame('5', $data->state['global_min_qty']);
        $this->assertSame([], $data->updates);
    }

    public function testInvalidDefaultMinAlignedWhenDefaultQtyStepIsNumeric()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'default_list_qty_count' => '0',
            'default_qty_step'       => '3',
            'default_min_qty'        => 'bad',
            'default_max_qty'        => '9',
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('default_');

        $this->assertEquals(0, $data->state['default_min_qty']);
        $this->assertUpdatesContainPair(['default_min_qty', 0], $data->updates);
        $this->assertUpdatesContainPair(['default_qty_step', 3], $data->updates);
    }

    public function testInvalidGlobalQtyStepResetBeforeMinCleanup()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'global_list_qty_count' => null,
            'global_qty_step'       => 'x',
            'global_min_qty'        => 'y',
            'global_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('global_');

        $this->assertEquals(0, $data->state['global_qty_step']);
        $this->assertEquals(0, $data->state['global_min_qty']);
    }

    public function testFractionalGlobalMinTruncatedWhenGlobalQtyStepIsNull()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'global_list_qty_count' => null,
            'global_qty_step'       => null,
            'global_min_qty'        => '5.7',
            'global_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('global_');

        $this->assertEquals(5, $data->state['global_min_qty']);
        $this->assertUpdatesContainPair(['global_min_qty', 5], $data->updates);
    }

    public function testListQtyCountFractionalUsesCeilLikeBefore()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'default_list_qty_count' => '2.3',
            'default_qty_step'       => null,
            'default_min_qty'        => null,
            'default_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('default_');

        $this->assertEquals(3, $data->state['default_list_qty_count']);
        $this->assertUpdatesContainPair(['default_list_qty_count', 3], $data->updates);
    }

    public function testNonNumericListQtyCountResetToZero()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'default_list_qty_count' => 'not_a_number',
            'default_qty_step'       => null,
            'default_min_qty'        => null,
            'default_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('default_');

        $this->assertEquals(0, $data->state['default_list_qty_count']);
        $this->assertUpdatesContainPair(['default_list_qty_count', 0], $data->updates);
    }

    public function testNonNumericGlobalListQtyCountResetToZero()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'global_list_qty_count' => 'oops',
            'global_qty_step'       => null,
            'global_min_qty'        => null,
            'global_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('global_');

        $this->assertEquals(0, $data->state['global_list_qty_count']);
        $this->assertUpdatesContainPair(['global_list_qty_count', 0], $data->updates);
    }

    public function testGlobalMinWithTrailingDecimalZerosUnchangedWhenGlobalQtyStepIsNull()
    {
        [$settings, $data] = $this->buildSettingsMock([
            'global_list_qty_count' => null,
            'global_qty_step'       => null,
            'global_min_qty'        => '5.0',
            'global_max_qty'        => null,
        ]);

        $this->bindSingletonSettingsMock($settings);
        fn_checkout_qty_validate_settings_for_prefix('global_');

        $this->assertSame('5.0', $data->state['global_min_qty']);
        $this->assertSame([], $data->updates);
    }

    /**
     * @param array{0: string, 1: mixed} $needle
     * @param list<array{0: string, 1: mixed}> $haystack
     */
    private function assertUpdatesContainPair(array $needle, array $haystack): void
    {
        foreach ($haystack as $row) {
            if ($row[0] === $needle[0] && (string) $row[1] === (string) $needle[1]) {
                $this->assertTrue(true);

                return;
            }
        }
        $this->fail('Expected update not found: ' . json_encode($needle));
    }
}

}
