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

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Tests\Unit\ATestCase;

class ExternalValueTest extends ATestCase
{
    /**
     * @return array<string, string|int|float|bool|array|object|null>
     */
    protected function getSource()
    {
        return [
            'string'  => ' Moscow ',
            'int'     => 7,
            'float'   => 12.5,
            'numeric' => '12.5',
            'zero'    => '0',
            'true'    => true,
            'null'    => null,
            'array'   => ['a' => 1],
            'object'  => new \stdClass(),
            'words'   => 'abc',
        ];
    }

    public function testTextTakesScalarsOnly()
    {
        $data = $this->getSource();

        $this->assertSame(' Moscow ', ExternalValue::text($data, 'string'));
        $this->assertSame('7', ExternalValue::text($data, 'int'));
        $this->assertSame('1', ExternalValue::text($data, 'true'));
        $this->assertSame('', ExternalValue::text($data, 'array'));
        $this->assertSame('', ExternalValue::text($data, 'object'));
        $this->assertSame('', ExternalValue::text($data, 'null'));
        $this->assertSame('', ExternalValue::text($data, 'missing'));
    }

    public function testIntTakesNumbersOnly()
    {
        $data = $this->getSource();

        $this->assertSame(7, ExternalValue::int($data, 'int'));
        $this->assertSame(12, ExternalValue::int($data, 'numeric'));
        $this->assertSame(0, ExternalValue::int($data, 'zero'));
        $this->assertSame(0, ExternalValue::int($data, 'words'));
        $this->assertSame(0, ExternalValue::int($data, 'true'));
        $this->assertSame(0, ExternalValue::int($data, 'array'));
        $this->assertSame(0, ExternalValue::int($data, 'missing'));
    }

    public function testFloatTakesNumbersOnly()
    {
        $data = $this->getSource();

        $this->assertSame(12.5, ExternalValue::float($data, 'float'));
        $this->assertSame(12.5, ExternalValue::float($data, 'numeric'));
        $this->assertSame(0.0, ExternalValue::float($data, 'words'));
        $this->assertSame(0.0, ExternalValue::float($data, 'array'));
        $this->assertSame(0.0, ExternalValue::float($data, 'missing'));
    }

    public function testArrTakesArraysOnly()
    {
        $data = $this->getSource();

        $this->assertSame(['a' => 1], ExternalValue::arr($data, 'array'));
        $this->assertSame([], ExternalValue::arr($data, 'string'));
        $this->assertSame([], ExternalValue::arr($data, 'object'));
        $this->assertSame([], ExternalValue::arr($data, 'missing'));
    }

    public function testNestedResponseIsReadWithoutChecksAtEveryLevel()
    {
        // Yandex may answer with an error instead of the expected structure, and reading it must not produce junk.
        $response = ['request' => 'failed'];

        $this->assertSame(0, ExternalValue::int(ExternalValue::arr($response, 'request'), 'id'));
    }
}
