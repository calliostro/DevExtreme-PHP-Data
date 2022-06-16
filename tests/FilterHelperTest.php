<?php

use DevExtreme\FilterHelper;

class FilterHelperTest extends PHPUnit_Framework_TestCase
{
    public function providerFilterExpression()
    {
        return [
            [
                [
                    ['field1', '=', 'Test'],
                    ['field2', '<', 3],
                ],
                "((`field1` = 'Test') AND (`field2` < '3'))",
            ],
            [
                [
                    ['field1', '=', 'Test'],
                    'and',
                    ['field2', '<', 3],
                ],
                "((`field1` = 'Test') AND (`field2` < '3'))",
            ],
            [
                [
                    ['field1', '=', 'Test'],
                    'or',
                    ['field2', '<', 3],
                ],
                "((`field1` = 'Test') OR (`field2` < '3'))",
            ],
            [
                [
                    ['field1', '=', 'Test'],
                    'or',
                    ['field2', '<', 3],
                ],
                "((`field1` = 'Test') OR (`field2` < '3'))",
            ],
            [
                [
                    ['field1', '=', 'Test'],
                    'and',
                    [
                        '!',
                        ['field2', '<', 3],
                    ],
                ],
                "((`field1` = 'Test') AND (NOT (`field2` < '3')))",
            ],
            [
                [
                    ['field1', 'startswith', 'test'],
                    'and',
                    ['field2', 'endswith', 'test'],
                ],
                "((`field1` LIKE 'test%') AND (`field2` LIKE '%test'))",
            ],
            [
                [
                    ['field1', 'contains', 'test'],
                    'and',
                    ['field2', 'notcontains', 'test'],
                ],
                "((`field1` LIKE '%test%') AND (`field2` NOT LIKE '%test%'))",
            ],
        ];
    }

    public function providerKey()
    {
        return [
            [
                ['field1' => 1],
                "`field1` = '1'",
            ],
            [
                [
                    'field1' => 1,
                    'field2' => 2,
                ],
                "`field1` = '1' AND `field2` = '2'",
            ],
        ];
    }

    /**
     * @dataProvider providerFilterExpression
     */
    public function testGetSqlExprByArray($expression, $expectedResult)
    {
        $result = FilterHelper::getSqlExprByArray($expression);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider providerKey
     */
    public function testGetSqlExprByKey($key, $expectedResult)
    {
        $result = FilterHelper::getSqlExprByKey($key);

        $this->assertEquals($expectedResult, $result);
    }
}
