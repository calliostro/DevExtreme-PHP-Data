<?php

require_once('TestBase.php');

class DbSetAPITest extends TestBase
{
    public function providerFilterAnd()
    {
        $filterExpression1 = [
            ['Category', '=', 'Dairy Products'],
            ['BDate', '=', '6/19/2013'],
            ['Name', '=', "Sir Rodney's Scones"],
            ['CustomerName', '=', 'Fuller Andrew'],
            ['ID', '=', 21],
        ];
        $filterExpression2 = [
            ['Category', '=', 'Dairy Products'],
            'and',
            ['BDate', '=', '2013-06-19'],
            'and',
            ['Name', '=', "Sir Rodney's Scones"],
            'and',
            ['CustomerName', '=', 'Fuller Andrew'],
            'and',
            ['!', ['ID', '<>', 21]],
        ];
        $filterExpression3 = [
            ['Category', '=', 'Dairy Products'],
            ['BDate.year', '=', '2013'],
            ['Name', '=', "Sir Rodney's Scones"],
            ['CustomerName', '=', 'Fuller Andrew'],
            ['ID', '=', 21],
        ];
        $filterExpression4 = [
            ['Category', '=', 'Dairy Products'],
            ['BDate.month', '=', '6'],
            ['Name', '=', "Sir Rodney's Scones"],
            ['CustomerName', '=', 'Fuller Andrew'],
            ['ID', '=', 21],
        ];
        $filterExpression5 = [
            ['Category', '=', 'Dairy Products'],
            ['BDate.day', '=', '19'],
            ['Name', '=', "Sir Rodney's Scones"],
            ['CustomerName', '=', 'Fuller Andrew'],
            ['ID', '=', 21],
        ];
        $filterExpression6 = [
            ['Category', '=', 'Dairy Products'],
            ['BDate.dayOfWeek', '=', '3'],
            ['Name', '=', "Sir Rodney's Scones"],
            ['CustomerName', '=', 'Fuller Andrew'],
            ['ID', '=', 21],
        ];
        $filterExpression7 = [
            ['Category', '=', 'Dairy Products'],
            ['CustomerName', '=', null],
        ];
        $values1 = [21, "Sir Rodney's Scones", 'Dairy Products', 'Fuller Andrew', '2013-06-19'];
        $values2 = [31, 'Camembert Pierrot', 'Dairy Products', '', '2013-11-17'];

        return [
            [$filterExpression1, $values1],
            [$filterExpression2, $values1],
            [$filterExpression3, $values1],
            [$filterExpression4, $values1],
            [$filterExpression5, $values1],
            [$filterExpression6, $values1],
            [$filterExpression7, $values2],
        ];
    }

    public function providerSort()
    {
        $field = 'Name';
        $sortExpression1 = [$field];
        $sortExpression2 = [
            (object)[
                'selector' => $field,
                'desc' => false,
            ],
        ];
        $sortExpression3 = [
            (object)[
                'selector' => $field,
                'desc' => true,
            ],
        ];

        return [
            [$sortExpression1, '', false, $field],
            [$sortExpression2, '', false, $field],
            [$sortExpression3, 'Z', true, $field],
        ];
    }

    public function providerGroup()
    {
        $field = 'Category';
        $groupCount = 4;
        $groupField = 'key';
        $groupExpression1 = [$field];
        $groupExpression2 = [
            (object)[
                'selector' => $field,
                'desc' => false,
            ],
        ];
        $groupExpression3 = [
            (object)[
                'selector' => $field,
                'desc' => true,
            ],
        ];

        return [
            [$groupExpression1, '', false, $groupField, $groupCount],
            [$groupExpression2, '', false, $groupField, $groupCount],
            [$groupExpression3, 'Seafood', true, $groupField, $groupCount],
        ];
    }

    private function groupSummariesEqual($data, $standard)
    {
        $dataCount = count($data);
        $standardCount = count($standard);
        $result = $dataCount === $standardCount;

        if ($result) {
            for ($i = 0; $i < $dataCount; $i++) {
                $dataSummary = $data[$i]['summary'];
                $standardSummary = $standard[$i]['summary'];

                if (is_array($dataSummary) &&
                    (count($dataSummary) == count($standard[$i]['summary'])) &&
                    (count(array_diff($dataSummary, $standardSummary)) === 0)) {
                    if (isset($standard[$i]['items'])) {
                        if (isset($data[$i]['items'])) {
                            $result = $this->groupSummariesEqual($data[$i]['items'], $standard[$i]['items']);
                        }
                    }

                    if ($result) {
                        continue;
                    }
                }

                $result = false;
                break;
            }
        }

        return $result;
    }

    public function providerGroupSummary()
    {
        $group = [
            (object)[
                'selector' => 'Category',
                'desc' => false,
                'isExpanded' => false,
            ],
            (object)[
                'selector' => 'CustomerName',
                'desc' => true,
                'isExpanded' => false,
            ],
        ];
        $groupSummary = [
            (object)[
                'selector' => 'ID',
                'summaryType' => 'min',
            ],
            (object)[
                'selector' => 'ID',
                'summaryType' => 'max',
            ],
            (object)[
                'selector' => 'ID',
                'summaryType' => 'sum',
            ],
            (object)[
                'summaryType' => 'count',
            ],
        ];
        $result = [
            [
                'summary' => [3, 29, 141, 10],
                'items' => [
                    ['summary' => [5, 29, 56, 3]],
                    ['summary' => [18, 18, 18, 1]],
                    ['summary' => [3, 16, 23, 3]],
                    ['summary' => [6, 23, 44, 3]],
                ],
            ],
            [
                'summary' => [1, 28, 138, 9],
                'items' => [
                    ['summary' => [1, 28, 41, 3]],
                    ['summary' => [26, 26, 26, 1]],
                    ['summary' => [8, 17, 34, 3]],
                    ['summary' => [13, 24, 37, 2]],
                ],
            ],
            [
                'summary' => [2, 31, 65, 4],
                'items' => [
                    ['summary' => [2, 2, 2, 1]],
                    ['summary' => [21, 21, 21, 1]],
                    ['summary' => [11, 11, 11, 1]],
                    ['summary' => [31, 31, 31, 1]],
                ],
            ],
            [
                'summary' => [7, 30, 152, 8],
                'items' => [
                    ['summary' => [10, 20, 30, 2]],
                    ['summary' => [27, 30, 57, 2]],
                    ['summary' => [7, 25, 46, 3]],
                    ['summary' => [19, 19, 19, 1]],
                ],
            ],
        ];

        return [
            [$group, $groupSummary, $result],
        ];
    }

    public function providerGetTotalSummary()
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

    public function providerEscapeExpressionValues()
    {
        $filterExpression1 = ['Name', '=', "N'o\"r\d-Ost Mat%123_jes)hering#"];
        $filterExpression2 = ['Name', 'contains', '%123_jes)'];

        return [
            [$filterExpression1, 30],
            [$filterExpression2, 30],
        ];
    }

    public function testGetCount()
    {
        $this->assertEquals(31, $this->dbSet->getCount());
    }

    public function testSelect()
    {
        $columns = ['BDate', 'Category', 'CustomerName'];
        $this->dbSet->select($columns);
        $data = $this->dbSet->asArray();
        $result = count($data) > 0 ? array_keys($data[0]) : [];
        $this->assertEquals($columns, $result);
    }

    /**
     * @dataProvider providerFilterAnd
     */
    public function testFilterAnd($filterExpression, $values)
    {
        $this->dbSet->filter($filterExpression);
        $data = $this->dbSet->asArray();
        $result = count($data) > 0 ? array_values($data[0]) : [];
        $this->assertEquals($values, $result);
    }

    public function testFilterOr()
    {
        $filterExpression = [
            ['ID', '=', 10],
            'or',
            ['ID', '=', 20],
        ];
        $values = [10, 20];
        $this->dbSet->filter($filterExpression);
        $data = $this->dbSet->asArray();
        $result = [];
        $dataItemsCount = count($data);

        for ($i = 0; $i < $dataItemsCount; $i++) {
            $result[$i] = $data[$i]['ID'];
        }

        $this->assertEquals($values, $result);
    }

    public function testFilterNotNull()
    {
        $filterExpression = [
            ['CustomerName', '<>', null],
            ['ID', '>', 29],
        ];
        $this->dbSet->filter($filterExpression);
        $data = $this->dbSet->asArray();
        $this->assertTrue($data !== null && count($data) == 1 && $data[0]['ID'] == 30);
    }

    /**
     * @dataProvider providerSort
     */
    public function testSort($sortExpression, $currentValue, $desc, $field)
    {
        $sorted = true;
        $this->dbSet->sort($sortExpression);
        $data = $this->dbSet->asArray();
        $dataItemsCount = count($data);

        for ($i = 0; $i < $dataItemsCount; $i++) {
            $compareResult = strcmp($currentValue, $data[$i][$field]);

            if ((!$desc && $compareResult > 0) || ($desc && $compareResult < 0)) {
                $sorted = false;
                break;
            }

            $currentValue = $data[$i][$field];
        }

        $this->assertTrue($sorted && $dataItemsCount > 0);
    }

    public function testSkipTake()
    {
        $this->dbSet->skipTake(10, 5);
        $data = $this->dbSet->asArray();
        $itemsCount = count($data);
        $firstIndex = $itemsCount > 0 ? $data[0]['ID'] : 0;
        $lastIndex = $itemsCount == 5 ? $data[4]['ID'] : 0;
        $this->assertTrue($itemsCount == 5 && $firstIndex == 11 && $lastIndex == 15);
    }

    /**
     * @dataProvider providerGroup
     */
    public function testGroup($groupExpression, $currentValue, $desc, $field, $groupCount)
    {
        $grouped = true;
        $this->dbSet->group($groupExpression);
        $data = $this->dbSet->asArray();
        $dataItemsCount = count($data);

        for ($i = 0; $i < $dataItemsCount; $i++) {
            $compareResult = strcmp($currentValue, $data[$i][$field]);

            if ((!$desc && $compareResult > 0) || ($desc && $compareResult < 0)) {
                $grouped = false;
                break;
            }

            $currentValue = $data[$i][$field];
        }

        $this->assertTrue($grouped && $dataItemsCount == $groupCount);
    }

    /**
     * @dataProvider providerGroupSummary
     */
    public function testGroupSummary($group, $groupSummary, $standard)
    {
        $this->dbSet->group($group, $groupSummary);
        $data = $this->dbSet->asArray();
        $result = $this->groupSummariesEqual($data, $standard);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider providerGetTotalSummary
     */
    public function testGetTotalSummary($summaryExpression, $value)
    {
        $data = $this->dbSet->getTotalSummary($summaryExpression);
        $result = count($data) > 0 ? $data[0] : 0;
        $this->assertEquals($value, $result);
    }

    public function testGetGroupCount()
    {
        $groupExpression = [
            (object)[
                'selector' => 'Category',
                'desc' => false,
                'isExpanded' => false,
            ],
        ];
        $this->dbSet->group($groupExpression);
        $groupCount = $this->dbSet->getGroupCount();
        $this->assertEquals($groupCount, 4);
    }

    /**
     * @dataProvider providerEscapeExpressionValues
     */
    public function testEscapeExpressionValues($filterExpression, $value)
    {
        $data = $this->dbSet->select('ID')->filter($filterExpression)->asArray();
        $result = false;

        if (count($data) == 1) {
            $itemData = $data[0];
            $result = isset($itemData['ID']) && $itemData['ID'] == $value;
        }

        $this->assertTrue($result);
    }
}
