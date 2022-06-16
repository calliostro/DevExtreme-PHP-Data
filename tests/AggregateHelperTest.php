<?php

use DevExtreme\AggregateHelper;

require_once('TestBase.php');

class AggregateHelperTest extends TestBase
{
    public function providerGroupSelectors()
    {
        return [
            [[], true],
            [
                [
                    'field1',
                    'field2',
                ],
                true,
            ],
            [
                [
                    (object)[
                        'selector' => 'field1',
                        'isExpanded' => false,
                    ],
                    (object)[
                        'selector' => 'field2',
                    ],
                ],
                true,
            ],
            [
                [
                    (object)[
                        'selector' => 'field1',
                    ],
                    (object)[
                        'selector' => 'field2',
                        'isExpanded' => false,
                    ],
                ],
                false,
            ],
            [
                [
                    'field1',
                    (object)[
                        'selector' => 'field2',
                        'isExpanded' => false,
                    ],
                ],
                false,
            ],
        ];
    }

    public function testGetFieldSetBySelectors()
    {
        $params = [
            'field1',
            (object)[
                'selector' => 'field2',
            ],
            (object)[
                'selector' => 'field3',
                'desc' => true,
            ],
            (object)[
                'selector' => 'field4',
                'groupInterval' => 10,
            ],
            (object)[
                'selector' => 'field5',
                'groupInterval' => 10,
                'desc' => true,
            ],
            (object)[
                'selector' => 'field6',
                'groupInterval' => 'year',
            ],
            (object)[
                'selector' => 'field6',
                'groupInterval' => 'month',
                'desc' => true,
            ],
        ];
        $groupFields = '`field1`, `field2`, `field3`, `dx_field4_10`, `dx_field5_10`, `dx_field6_year`, `dx_field6_month`';
        $sortFields = '`field1`, `field2`, `field3` DESC, `dx_field4_10`, `dx_field5_10` DESC, `dx_field6_year`, `dx_field6_month` DESC';
        $selectFields = '`field1`, `field2`, `field3`, (`field4` - (`field4` % 10)) AS `dx_field4_10`, (`field5` - (`field5` % 10)) AS `dx_field5_10`, YEAR(`field6`) AS `dx_field6_year`, MONTH(`field6`) AS `dx_field6_month`';

        $fieldSet = AggregateHelper::getFieldSetBySelectors($params);

        $this->assertEquals($groupFields, $fieldSet['group']);
        $this->assertEquals($sortFields, $fieldSet['sort']);
        $this->assertEquals($selectFields, $fieldSet['select']);
    }

    public function testGetGroupedDataFromQuery_lastGroupExpanded()
    {
        $groupSettings = [
            'groupCount' => 2,
            'lastGroupExpanded' => true,
        ];

        $expectedResult = [
            [
                'key' => '2013',
                'items' => [
                    [
                        'key' => 'Beverages',
                        'count' => 10,
                    ],
                    [
                        'key' => 'Condiments',
                        'count' => 9,
                    ],
                    [
                        'key' => 'Dairy Products',
                        'count' => 4,
                    ],
                    [
                        'key' => 'Seafood',
                        'count' => 8,
                    ],
                ],
            ],
        ];

        $query = 'SELECT YEAR(`BDate`) AS `dx_BDate_year`, `Category`, test_products_1.* FROM (SELECT * FROM test_products) AS test_products_1 ORDER BY `dx_BDate_year`, `Category`';
        $queryResult = AggregateHelperTest::$mySQL->query($query);
        $result = AggregateHelper::getGroupedDataFromQuery($queryResult, $groupSettings);
        $queryResult->close();

        $this->assertEquals(count($expectedResult), count($result));

        $checked = true;
        for ($i = 0; $i < count($expectedResult); $i++) {
            if ($expectedResult[$i]['key'] != $result[$i]['key']) {
                $checked = false;
                break;
            }

            for ($j = 0; $j < count($expectedResult[$i]['items']); $j++) {
                $expectedGroup = $expectedResult[$i]['items'][$j];
                $group = $result[$i]['items'][$j];

                if ($expectedGroup['key'] != $group['key']) {
                    $checked = false;
                    break;
                }

                if ($expectedGroup['count'] != count($group['items'])) {
                    $checked = false;
                    break;
                }
            }

            if (!$checked) {
                break;
            }
        }

        $this->assertTrue($checked);
    }

    public function testGetGroupedDataFromQuery_summaryType()
    {
        $groupSettings = [
            'groupCount' => 3,
            'summaryTypes' => ['SUM'],
            'lastGroupExpanded' => false,
        ];

        $expectedResult = [
            [
                'key' => '2013',
                'items' => [
                    [
                        'key' => 'Beverages',
                        'count' => 10,
                        'summary' => [141],
                    ],
                    [
                        'key' => 'Condiments',
                        'count' => 9,
                        'summary' => [138],
                    ],
                    [
                        'key' => 'Dairy Products',
                        'count' => 4,
                        'summary' => [65],
                    ],
                    [
                        'key' => 'Seafood',
                        'count' => 8,
                        'summary' => [152],
                    ],
                ],
            ],
        ];

        $query = 'SELECT YEAR(`BDate`) AS `dx_BDate_year`, `Category`, COUNT(1), SUM(`ID`) AS dx_f0 FROM (SELECT * FROM test_products) AS test_products_1 GROUP BY `dx_BDate_year`, `Category` ORDER BY `dx_BDate_year`, `Category`';
        $queryResult = AggregateHelperTest::$mySQL->query($query);
        $result = AggregateHelper::getGroupedDataFromQuery($queryResult, $groupSettings);
        $queryResult->close();

        $this->assertEquals(count($expectedResult), count($result));

        $checked = true;

        for ($i = 0; $i < count($expectedResult); $i++) {
            if ($expectedResult[$i]['key'] != $result[$i]['key']) {
                $checked = false;
                break;
            }

            for ($j = 0; $j < count($expectedResult[$i]['items']); $j++) {
                $expectedGroup = $expectedResult[$i]['items'][$j];
                $group = $result[$i]['items'][$j];

                if ($expectedGroup['key'] != $group['key']) {
                    $checked = false;
                    break;
                }

                if ($expectedGroup['count'] != $group['count']) {
                    $checked = false;
                    break;
                }

                if (count($expectedGroup['summary']) != count($group['summary'])) {
                    $checked = false;
                    break;
                }

                if ($expectedGroup['summary'][0] != $group['summary'][0]) {
                    $checked = false;
                    break;
                }
            }

            if (!$checked) {
                break;
            }
        }

        $this->assertTrue($checked);
    }

    /**
     * @dataProvider providerGroupSelectors
     */
    public function testIsLastGroupExpanded($selectors, $expectedResult)
    {
        $result = AggregateHelper::isLastGroupExpanded($selectors);

        $this->assertTrue($expectedResult === $result);
    }

    public function testGetSummaryInfo()
    {
        $selectors = [
            (object)[
                'selector' => 'field1',
                'summaryType' => 'max',
            ],
            (object)[
                'selector' => 'field2',
                'summaryType' => 'sum',
            ],
        ];

        $expectedResult = [
            'fields' => 'MAX(`field1`) AS dx_f0, SUM(`field2`) AS dx_f1',
            'summaryTypes' => ['MAX', 'SUM'],
        ];

        $result = AggregateHelper::getSummaryInfo($selectors);

        $this->assertEquals($expectedResult['fields'], $result['fields']);
        $this->assertEquals(count($expectedResult['summaryTypes']), count($result['summaryTypes']));
        $this->assertEquals($expectedResult['summaryTypes'][0], $result['summaryTypes'][0]);
        $this->assertEquals($expectedResult['summaryTypes'][1], $result['summaryTypes'][1]);
    }
}
