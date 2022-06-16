<?php

namespace DevExtreme;

class AggregateHelper
{
    const MIN_OP = 'MIN';
    const MAX_OP = 'MAX';
    const AVG_OP = 'AVG';
    const COUNT_OP = 'COUNT';
    const SUM_OP = 'SUM';
    const AS_OP = 'AS';
    const GENERATED_FIELD_PREFIX = 'dx_';

    private static function _recalculateGroupCountAndSummary(&$dataItem, $groupInfo)
    {
        if ($groupInfo['groupIndex'] <= $groupInfo['groupCount'] - 3) {
            $items = $dataItem['items'];

            foreach ($items as $item) {
                $grInfo = $groupInfo;
                $grInfo['groupIndex']++;
                self::_recalculateGroupCountAndSummary($item, $grInfo);
            }
        }

        if (isset($groupInfo['summaryTypes']) && $groupInfo['groupIndex'] < $groupInfo['groupCount'] - 2) {
            $result = [];
            $items = $dataItem['items'];
            $itemsCount = count($items);

            foreach ($items as $index => $item) {
                $currentSummaries = $item['summary'];

                if ($index == 0) {
                    foreach ($currentSummaries as $summaryItem) {
                        $result[] = $summaryItem;
                    }

                    continue;
                }

                foreach ($groupInfo['summaryTypes'] as $si => $stItem) {
                    if ($stItem == self::MIN_OP) {
                        if ($result[$si] > $currentSummaries[$si]) {
                            $result[$si] = $currentSummaries[$si];
                        }

                        continue;
                    }

                    if ($stItem == self::MAX_OP) {
                        if ($result[$si] < $currentSummaries[$si]) {
                            $result[$si] = $currentSummaries[$si];
                        }

                        continue;
                    }

                    $result[$si] += $currentSummaries[$si];
                }
            }

            foreach ($groupInfo['summaryTypes'] as $si => $stItem) {
                if ($stItem == self::AVG_OP) {
                    $result[$si] /= $itemsCount;
                }
            }

            $dataItem['summary'] = $result;
        }
    }

    private static function _getNewDataItem($row, $groupInfo)
    {
        $dataItem = [];
        $dataFieldCount = count($groupInfo['dataFieldNames']);

        for ($index = 0; $index < $dataFieldCount; $index++) {
            $dataItem[$groupInfo['dataFieldNames'][$index]] = $row[$groupInfo['groupCount'] + $index];
        }

        return $dataItem;
    }

    private static function _getNewGroupItem($row, $groupInfo)
    {
        $groupIndexOffset = $groupInfo['lastGroupExpanded'] ? 1 : 2;
        $groupItem = [];
        $groupItem['key'] = $row[$groupInfo['groupIndex']];
        $groupItem['items'] = $groupInfo['groupIndex'] < $groupInfo['groupCount'] - $groupIndexOffset ? [] :
            ($groupInfo['lastGroupExpanded'] ? [] : null);

        if ($groupInfo['groupIndex'] == $groupInfo['groupCount'] - $groupIndexOffset) {
            if (isset($groupInfo['summaryTypes'])) {
                $summaries = [];
                $endIndex = $groupInfo['groupIndex'] + count($groupInfo['summaryTypes']) + 1;

                for ($index = $groupInfo['groupCount']; $index <= $endIndex; $index++) {
                    $summaries[] = $row[$index];
                }

                $groupItem['summary'] = $summaries;
            }

            if (!$groupInfo['lastGroupExpanded']) {
                $groupItem['count'] = $row[$groupInfo['groupIndex'] + 1];
            } else {
                $groupItem['items'][] = self::_getNewDataItem($row, $groupInfo);
            }
        }

        return $groupItem;
    }

    private static function _groupData($row, &$resultItems, $groupInfo)
    {
        $itemsCount = count($resultItems);

        if (!isset($row) && !$itemsCount) {
            return;
        }

        $currentItem = null;
        $groupIndexOffset = $groupInfo['lastGroupExpanded'] ? 1 : 2;

        if ($itemsCount) {
            $currentItem = &$resultItems[$itemsCount - 1];

            if (!$groupInfo['lastGroupExpanded']) {
                if (!isset($row) || $currentItem['key'] != $row[$groupInfo['groupIndex']]) {
                    if ($groupInfo['groupIndex'] == 0 && $groupInfo['groupCount'] > 2) {
                        self::_recalculateGroupCountAndSummary($currentItem, $groupInfo);
                    }

                    unset($currentItem);

                    if (!isset($row)) {
                        return;
                    }
                }
            } else {
                if ($currentItem['key'] != $row[$groupInfo['groupIndex']]) {
                    unset($currentItem);
                } else {
                    if ($groupInfo['groupIndex'] == $groupInfo['groupCount'] - $groupIndexOffset) {
                        $currentItem['items'][] = self::_getNewDataItem($row, $groupInfo);
                    }
                }
            }
        }

        if (!isset($currentItem)) {
            $currentItem = self::_getNewGroupItem($row, $groupInfo);
            $resultItems[] = &$currentItem;
        }

        if ($groupInfo['groupIndex'] < $groupInfo['groupCount'] - $groupIndexOffset) {
            $groupInfo['groupIndex']++;
            self::_groupData($row, $currentItem['items'], $groupInfo);
        }
    }

    public static function getGroupedDataFromQuery($queryResult, $groupSettings)
    {
        $result = [];
        $groupSummaryTypes = null;
        $dataFieldNames = null;
        $startSummaryFieldIndex = null;
        $endSummaryFieldIndex = null;

        if ($groupSettings['lastGroupExpanded']) {
            $queryFields = $queryResult->fetch_fields();
            $dataFieldNames = [];
            for ($i = $groupSettings['groupCount']; $i < count($queryFields); $i++) {
                $dataFieldNames[] = $queryFields[$i]->name;
            }
        }

        if (isset($groupSettings['summaryTypes'])) {
            $groupSummaryTypes = $groupSettings['summaryTypes'];
            $startSummaryFieldIndex = $groupSettings['groupCount'] - 1;
            $endSummaryFieldIndex = $startSummaryFieldIndex + count($groupSummaryTypes);
        }

        $groupInfo = [
            'groupCount' => $groupSettings['groupCount'],
            'groupIndex' => 0,
            'summaryTypes' => $groupSummaryTypes,
            'lastGroupExpanded' => $groupSettings['lastGroupExpanded'],
            'dataFieldNames' => $dataFieldNames,
        ];

        while ($row = $queryResult->fetch_array(MYSQLI_NUM)) {
            if (isset($startSummaryFieldIndex)) {
                for ($i = $startSummaryFieldIndex; $i <= $endSummaryFieldIndex; $i++) {
                    $row[$i] = Utils::stringToNumber($row[$i]);
                }
            }

            self::_groupData($row, $result, $groupInfo);
        }

        if (!$groupSettings['lastGroupExpanded']) {
            self::_groupData($row, $result, $groupInfo);
        } else {
            if (isset($groupSettings['skip']) && $groupSettings['skip'] >= 0 &&
                isset($groupSettings['take']) && $groupSettings['take'] >= 0) {
                $result = array_slice($result, $groupSettings['skip'], $groupSettings['take']);
            }
        }

        return $result;
    }

    public static function isLastGroupExpanded($items)
    {
        $result = true;
        $itemsCount = count($items);

        if ($itemsCount > 0) {
            $lastItem = $items[$itemsCount - 1];

            if (gettype($lastItem) === 'object') {
                $result = isset($lastItem->isExpanded) ? $lastItem->isExpanded === true : true;
            } else {
                $result = true;
            }
        }

        return $result;
    }

    public static function getFieldSetBySelectors($items)
    {
        $group = '';
        $sort = '';
        $select = '';

        foreach ($items as $item) {
            $groupField = null;
            $sortField = null;
            $selectField = null;
            $desc = false;

            if (is_string($item) && strlen($item = trim($item))) {
                $selectField = $groupField = $sortField = Utils::quoteStringValue($item);
            } else {
                if (gettype($item) === 'object' && isset($item->selector)) {
                    $quoteSelector = Utils::quoteStringValue($item->selector);
                    $desc = isset($item->desc) ? $item->desc : false;

                    if (isset($item->groupInterval)) {
                        if (is_int($item->groupInterval)) {
                            $groupField = Utils::quoteStringValue(
                                sprintf('%s%s_%d', self::GENERATED_FIELD_PREFIX, $item->selector, $item->groupInterval)
                            );
                            $selectField = sprintf(
                                '(%s - (%s %% %d)) %s %s',
                                $quoteSelector,
                                $quoteSelector,
                                $item->groupInterval,
                                self::AS_OP,
                                $groupField
                            );
                        } else {
                            $groupField = Utils::quoteStringValue(
                                sprintf('%s%s_%s', self::GENERATED_FIELD_PREFIX, $item->selector, $item->groupInterval)
                            );
                            $selectField = sprintf(
                                '%s(%s) %s %s',
                                strtoupper($item->groupInterval),
                                $quoteSelector,
                                self::AS_OP,
                                $groupField
                            );
                        }
                        $sortField = $groupField;
                    } else {
                        $selectField = $groupField = $sortField = $quoteSelector;
                    }
                }
            }

            if (isset($selectField)) {
                $select .= (strlen($select) > 0 ? ', ' . $selectField : $selectField);
            }

            if (isset($groupField)) {
                $group .= (strlen($group) > 0 ? ', ' . $groupField : $groupField);
            }

            if (isset($sortField)) {
                $sort .= (strlen($sort) > 0 ? ', ' . $sortField : $sortField) .
                    ($desc ? ' DESC' : '');
            }
        }

        return [
            'group' => $group,
            'sort' => $sort,
            'select' => $select,
        ];
    }

    private static function _isSummaryTypeValid($summaryType)
    {
        return in_array($summaryType, [self::MIN_OP, self::MAX_OP, self::AVG_OP, self::COUNT_OP, self::SUM_OP]);
    }

    public static function getSummaryInfo($expression)
    {
        $result = [];
        $fields = '';
        $summaryTypes = [];

        foreach ($expression as $index => $item) {
            if (gettype($item) === 'object' && isset($item->summaryType)) {
                $summaryType = strtoupper(trim($item->summaryType));

                if (!self::_isSummaryTypeValid($summaryType)) {
                    continue;
                }
                $summaryTypes[] = $summaryType;
                $fields .= sprintf(
                    '%s(%s) %s %sf%d',
                    strlen($fields) > 0 ? ', ' . $summaryTypes[$index] : $summaryTypes[$index],
                    (isset($item->selector) && is_string($item->selector)) ? Utils::quoteStringValue(
                        $item->selector
                    ) : '1',
                    self::AS_OP,
                    self::GENERATED_FIELD_PREFIX,
                    $index
                );
            }
        }

        $result['fields'] = $fields;
        $result['summaryTypes'] = $summaryTypes;

        return $result;
    }
}
