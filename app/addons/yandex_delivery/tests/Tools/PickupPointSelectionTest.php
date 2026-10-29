<?php
/***************************************************************************
*                                                                          *
*   © 2012 ООО "Эком Системы"                                              *
*                                                                          *
* Это коммерческое программное обеспечение. Только пользователи, которые   *
* приобрели действующую лицензию и согласились с условиями лицензионного   *
* соглашения, могут устанавливать и использовать эту программу.            *
*                                                                          *
****************************************************************************
* ПОЖАЛУЙСТА, ВНИМАТЕЛЬНО ПРОЧТИТЕ ПОЛНЫЙ ТЕКСТ ЛИЦЕНЗИОННОГО СОГЛАШЕНИЯ   *
* В ФАЙЛЕ "copyright.txt", ПРЕДОСТАВЛЕННОМ ВМЕСТЕ С ЭТИМ ДИСТРИБУТИВОМ.    *
***************************************************************************/

namespace Tygh\Addons\YandexDelivery\Tests\Unit\Tools;

use Tygh\Addons\YandexDelivery\Tools\PickupPointSelection;
use Tygh\Tests\Unit\ATestCase;

class PickupPointSelectionTest extends ATestCase
{
    public function testPickerChoiceIsKept()
    {
        $selection = [
            'group_0' => [12 => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6'],
            'group_1' => [34 => '11111111-2222-3333-4444-555555555555'],
        ];

        $this->assertSame([
            'group_0' => [12 => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6'],
            'group_1' => [34 => '11111111-2222-3333-4444-555555555555'],
        ], PickupPointSelection::sanitize($selection));
    }

    /**
     * @param string|int|float|bool|array|object|null $selection Raw value from the request
     *
     * @dataProvider dpRejected
     */
    public function testJunkNeverReachesTheSession($selection)
    {
        $this->assertSame([], PickupPointSelection::sanitize($selection));
    }

    /**
     * @return array<string, array{0: string|int|float|bool|array|object|null}>
     */
    public function dpRejected()
    {
        return [
            'not an array'          => ['station-1'],
            'nothing selected'      => [null],
            'group is not an array' => [['group_0' => 'station-1']],
            'shipping is not a id'  => [['group_0' => ['courier' => 'station-1']]],
            'point is an array'     => [['group_0' => [12 => ['station-1']]]],
            'point is blank'        => [['group_0' => [12 => '   ']]],
            'point is too long'     => [['group_0' => [12 => str_repeat('x', PickupPointSelection::MAX_ID_LENGTH + 1)]]],
        ];
    }

    public function testIdentifierIsTrimmedAndKeysAreNormalized()
    {
        $selection = ['group_0' => ['12' => ' station-1 ']];

        $this->assertSame(['group_0' => [12 => 'station-1']], PickupPointSelection::sanitize($selection));
    }

    public function testValidEntriesSurviveNextToJunk()
    {
        $selection = [
            'group_0' => [12 => 'station-1', 'courier' => 'station-2', 13 => ''],
            'group_1' => 'not an array',
            'group_2' => [14 => 'station-3'],
        ];

        $this->assertSame([
            'group_0' => [12 => 'station-1'],
            'group_2' => [14 => 'station-3'],
        ], PickupPointSelection::sanitize($selection));
    }

    public function testOversizedSelectionIsCutOff()
    {
        $points = [];
        for ($shipping_id = 1; $shipping_id <= PickupPointSelection::MAX_ENTRIES + 10; $shipping_id++) {
            $points[$shipping_id] = 'station-' . $shipping_id;
        }

        $clean = PickupPointSelection::sanitize(['group_0' => $points]);

        $this->assertCount(PickupPointSelection::MAX_ENTRIES, $clean['group_0']);
    }
}
