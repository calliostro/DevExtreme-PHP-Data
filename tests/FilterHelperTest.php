<?php

declare(strict_types=1);

namespace DevExtreme\Tests;

use DevExtreme\FilterHelper;

final class FilterHelperTest extends TestBase
{
    public function providerFilterExpression(): array
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

    public function providerKey(): array
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
     * @throws \Exception
     */
    public function testGetSqlExprByArray(array $expression, string $expectedResult): void
    {
        $result = FilterHelper::getSqlExprByArray($expression);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider providerKey
     */
    public function testGetSqlExprByKey(array $key, string $expectedResult): void
    {
        $result = FilterHelper::getSqlExprByKey($key);

        $this->assertEquals($expectedResult, $result);
    }
}
