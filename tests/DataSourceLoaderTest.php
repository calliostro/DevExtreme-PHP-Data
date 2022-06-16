<?php

use DevExtreme\DataSourceLoader;

require_once('TestBase.php');

class DataSourceLoaderTest extends TestBase
{
    public function providerSort()
    {
        return [
            [['Name'], '', false, 'Name'],
            [
                [
                    (object)[
                        'selector' => 'Name',
                        'desc' => false,
                    ],
                ],
                '',
                false,
                'Name',
            ],
            [
                [
                    (object)[
                        'selector' => 'Name',
                        'desc' => true,
                    ],
                ],
                'Z',
                true,
                'Name',
            ],
        ];
    }

    public function providerFilter()
    {
        return [
            [
                ['ID', '=', 10],
                [10],
            ],
            [
                [
                    ['ID', '>', 1],
                    'and',
                    ['ID', '<=', 3],
                ],
                [2, 3],
            ],
            [
                ['ID', '>=', 29],
                [29, 30, 31],
            ],
            [
                ['ID', '<', 2],
                [1],
            ],
            [
                [
                    ['!', ['ID', '=', 2]],
                    'and',
                    ['ID', '<=', 3],
                ],
                [1, 3],
            ],
            [
                ['Name', 'startswith', 'Cha'],
                [1, 2],
            ],
            [
                ['Name', 'endswith', 'ku'],
                [9],
            ],
            [
                ['Name', 'contains', 'onb'],
                [13],
            ],
            [
                [
                    ['Name', 'notcontains', 'A'],
                    'and',
                    ['Name', 'notcontains', 'a'],
                ],
                [9, 13, 14, 15, 21, 23, 26],
            ],
            [
                [
                    ['CustomerName', '<>', null],
                    'and',
                    ['ID', '>', 27],
                ],
                [28, 29, 30],
            ],
        ];
    }

    public function providerGroup()
    {
        return [
            [['Category'], '', false, 'key', 4, [10, 9, 4, 8]],
            [
                [
                    (object)[
                        'selector' => 'Category',
                        'desc' => false,
                    ],
                ],
                '',
                false,
                'key',
                4,
                [10, 9, 4, 8],
            ],
            [
                [
                    (object)[
                        'selector' => 'Category',
                        'desc' => true,
                        'isExpanded' => false,
                    ],
                ],
                'Z',
                true,
                'key',
                4,
                [8, 4, 9, 10],
            ],
            [
                [
                    (object)[
                        'selector' => 'BDate',
                        'groupInterval' => 'year',
                        'desc' => true,
                        'isExpanded' => false,
                    ],
                ],
                '9999',
                true,
                'key',
                1,
                [31],
            ],
        ];
    }

    public function providerGroupPaging()
    {
        $groupExpression1 = [
            (object)[
                'selector' => 'Category',
                'desc' => false,
                'isExpanded' => false,
            ],
        ];
        $groupExpression2 = [
            (object)[
                'selector' => 'Category',
                'desc' => false,
                'isExpanded' => true,
            ],
        ];
        $params1 = [
            'requireGroupCount' => true,
            'group' => $groupExpression1,
            'skip' => 1,
            'take' => 2,
        ];
        $params2 = [
            'requireGroupCount' => true,
            'group' => $groupExpression2,
            'skip' => 1,
            'take' => 2,
        ];
        $resultGroupItems = ['Condiments', 'Dairy Products'];

        return [
            [$params1, $resultGroupItems],
            [$params2, $resultGroupItems],
        ];
    }

    public function providerTotalSummary()
    {
        $summaryExpression1 = [
            (object)[
                'summaryType' => 'count',
            ],
        ];
        $summaryExpression2 = [
            (object)[
                'selector' => 'ID',
                'summaryType' => 'min',
            ],
        ];
        $summaryExpression3 = [
            (object)[
                'selector' => 'ID',
                'summaryType' => 'max',
            ],
        ];
        $summaryExpression4 = [
            (object)[
                'selector' => 'ID',
                'summaryType' => 'sum',
            ],
        ];
        $summaryExpression5 = [
            (object)[
                'selector' => 'ID',
                'summaryType' => 'avg',
            ],
        ];

        return [
            [$summaryExpression1, 31],
            [$summaryExpression2, 1],
            [$summaryExpression3, 31],
            [$summaryExpression4, 496],
            [$summaryExpression5, 16],
        ];
    }

    public function testLoaderSelect()
    {
        $columns = ['BDate', 'Category', 'CustomerName'];
        $params = [
            'select' => $columns,
        ];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && is_array($data) && isset($data['data']) && count($data['data']) > 0 ?
            array_keys($data['data'][0]) :
            [];
        $this->assertEquals($columns, $result);
    }

    public function testLoaderTotalCount()
    {
        $params = [
            'requireTotalCount' => true,
        ];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && is_array($data) &&
            isset($data['data']) && isset($data['totalCount']) &&
            count($data['data']) == $data['totalCount'] && $data['totalCount'] == 31;
        $this->assertTrue($result);
    }

    /**
     * @dataProvider providerSort
     */
    public function testLoaderSort($sortExpression, $currentValue, $desc, $field)
    {
        $sorted = true;
        $params = [
            'sort' => $sortExpression,
        ];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && isset($data['data']) && is_array($data['data']) ? $data['data'] : null;
        $dataItemsCount = isset($result) ? count($result) : 0;

        for ($i = 0; $i < $dataItemsCount; $i++) {
            $compareResult = strcmp($currentValue, $result[$i][$field]);

            if ((!$desc && $compareResult > 0) || ($desc && $compareResult < 0)) {
                $sorted = false;
                break;
            }

            $currentValue = $result[$i][$field];
        }

        $this->assertTrue($sorted && $dataItemsCount == 31);
    }

    public function testLoaderSkipTake()
    {
        $params = [
            'skip' => 5,
            'take' => 10,
        ];
        $ids = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && isset($data['data']) && is_array($data['data']) ? $data['data'] : null;
        $itemsCount = isset($result) ? count($result) : 0;
        $paginated = true;

        if ($itemsCount != count($ids)) {
            $paginated = false;
        } else {
            for ($i = 0; $i < $itemsCount; $i++) {
                if ($result[$i]['ID'] != $ids[$i]) {
                    $paginated = false;
                    break;
                }
            }
        }

        $this->assertTrue($paginated);
    }

    /**
     * @dataProvider providerFilter
     */
    public function testLoaderFilter($expression, $ids)
    {
        $params = [
            'filter' => $expression,
        ];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && isset($data['data']) && is_array($data['data']) ? $data['data'] : null;
        $itemsCount = isset($result) ? count($result) : 0;
        $filtered = true;

        if ($itemsCount != count($ids)) {
            $filtered = false;
        } else {
            for ($i = 0; $i < $itemsCount; $i++) {
                if ($result[$i]['ID'] != $ids[$i]) {
                    $filtered = false;
                    break;
                }
            }
        }

        $this->assertTrue($filtered);
    }

    /**
     * @dataProvider providerGroup
     */
    public function testLoaderGroup($groupExpression, $currentValue, $desc, $field, $groupCount, $itemsInGroups)
    {
        $grouped = true;
        $params = [
            'group' => $groupExpression,
        ];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && isset($data['data']) && is_array($data['data']) ? $data['data'] : null;
        $dataItemsCount = isset($result) ? count($result) : 0;

        for ($i = 0; $i < $dataItemsCount; $i++) {
            $compareResult = strcmp($currentValue, strval($result[$i][$field]));
            $count = isset($groupExpression[0]->isExpanded) && $groupExpression[0]->isExpanded === false ? $result[$i]['count'] : count(
                $result[$i]['items']
            );

            if ((!$desc && $compareResult > 0) || ($desc && $compareResult < 0) || ($count != $itemsInGroups[$i])) {
                $grouped = false;
                break;
            }

            $currentValue = strval($result[$i][$field]);
        }

        $this->assertTrue($grouped && $dataItemsCount == $groupCount);
    }

    /**
     * @dataProvider providerGroupPaging
     */
    public function testLoaderGroupPaging($params, $resultGroupItems)
    {
        $data = DataSourceLoader::load($this->dbSet, $params);
        $isPaginated = false;
        $groupCount = 0;

        if (isset($data) && isset($data['data']) && isset($data['groupCount']) && count($resultGroupItems) === count(
                $data['data']
            )) {
            $groupItems = $data['data'];
            $isPaginated = true;

            foreach ($groupItems as $index => $groupItem) {
                if (strcmp($groupItem['key'], $resultGroupItems[$index]) !== 0) {
                    $isPaginated = false;
                    break;
                }
            }

            $groupCount = $data['groupCount'];
        }

        $this->assertTrue($isPaginated && $groupCount === 4);
    }

    /**
     * @dataProvider providerTotalSummary
     */
    public function testLoaderTotalSummary($summaryExpression, $value)
    {
        $params = [
            'totalSummary' => $summaryExpression,
        ];
        $data = DataSourceLoader::load($this->dbSet, $params);
        $result = isset($data) && is_array($data) && isset($data['summary']) ? $data['summary'][0] : 0;
        $this->assertEquals($value, $result);
    }
}
