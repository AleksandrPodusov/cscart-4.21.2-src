<?php

namespace Tygh\Tests\Unit\Functions\Users;

use Tygh\Enum\UserTypes;
use Tygh\Registry;
use Tygh\Tests\Unit\ATestCase;

class CheckUserTypeTest extends ATestCase
{
    protected function setUp(): void
    {
        if (!defined('AREA')) {
            define('AREA', 'A');
        }

        $this->requireMockFunction('fn_set_hook');
        $this->requireCore('functions/fn.users.php');

        \Tygh::$app['session'] = [
            'auth' => [
                'user_type' => UserTypes::CUSTOMER,
            ],
        ];

        Registry::del('runtime.users.allow_vendor_admin_creation');
    }

    protected function tearDown(): void
    {
        Registry::del('runtime.users.allow_vendor_admin_creation');
    }

    public function testCustomerCannotCreateVendorAdminWithUserDataFlag()
    {
        $this->assertSame(
            UserTypes::CUSTOMER,
            fn_check_user_type(
                [
                    'company_id'          => 1,
                    'create_vendor_admin' => 'Y',
                ],
                [
                    'user_type' => UserTypes::CUSTOMER,
                ]
            )
        );
    }

    public function testTrustedCallerCanCreateVendorAdmin()
    {
        Registry::set('runtime.users.allow_vendor_admin_creation', true);

        $this->assertSame(
            UserTypes::VENDOR,
            fn_check_user_type(
                [
                    'user_type'  => UserTypes::VENDOR,
                    'company_id' => 1,
                ],
                [
                    'user_type' => UserTypes::CUSTOMER,
                ]
            )
        );
    }

    public function testTrustedCallerDoesNotUpgradeUserDataWithoutVendorType()
    {
        Registry::set('runtime.users.allow_vendor_admin_creation', true);

        $this->assertSame(
            UserTypes::CUSTOMER,
            fn_check_user_type(
                [
                    'company_id'          => 1,
                    'create_vendor_admin' => 'Y',
                ],
                [
                    'user_type' => UserTypes::CUSTOMER,
                ]
            )
        );
    }
}
