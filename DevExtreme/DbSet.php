<?php

namespace DevExtreme;

use Exception;

class DbSet
{
    private static $SELECT_OP = 'SELECT';
    private static $FROM_OP = 'FROM';
    private static $WHERE_OP = 'WHERE';
    private static $ORDER_OP = 'ORDER BY';
    private static $GROUP_OP = 'GROUP BY';
    private static $ALL_FIELDS = '*';
    private static $LIMIT_OP = 'LIMIT';
    private static $INSERT_OP = 'INSERT INTO';
    private static $VALUES_OP = 'VALUES';
    private static $UPDATE_OP = 'UPDATE';
    private static $SET_OP = 'SET';
    private static $DELETE_OP = 'DELETE';
    private static $MAX_ROW_INDEX = 2147483647;
    private $dbTableName;
    private $tableNameIndex = 0;
    private $lastWrappedTableName;
    private $resultQuery;
    private $mySQL;
    private $lastError;
    private $groupSettings;

    public function __construct($mySQL, $table)
    {
        if (!is_a($mySQL, '\mysqli') || !isset($table)) {
            throw new Exception('Invalid params');
        }

        $this->mySQL = $mySQL;
        $this->dbTableName = $table;

        $this->resultQuery = sprintf(
            '%s %s %s %s',
            self::$SELECT_OP,
            self::$ALL_FIELDS,
            self::$FROM_OP,
            $this->dbTableName
        );
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    private function _wrapQuery()
    {
        $this->tableNameIndex++;
        $this->lastWrappedTableName = "{$this->dbTableName}_{$this->tableNameIndex}";

        $this->resultQuery = sprintf(
            '%s %s %s (%s) %s %s',
            self::$SELECT_OP,
            self::$ALL_FIELDS,
            self::$FROM_OP,
            $this->resultQuery,
            AggregateHelper::AS_OP,
            $this->lastWrappedTableName
        );
    }

    private function _prepareQueryForLastOperator($operator)
    {
        $operator = trim($operator);
        $lastOperatorPos = strrpos($this->resultQuery, ' ' . $operator . ' ');

        if ($lastOperatorPos !== false) {
            $lastBracketPos = strrpos($this->resultQuery, ')');

            if (($lastBracketPos !== false && $lastOperatorPos > $lastBracketPos) || ($lastBracketPos === false)) {
                $this->_wrapQuery();
            }
        }
    }

    public function select($expression)
    {
        Utils::escapeExpressionValues($this->mySQL, $expression);

        $this->_selectImpl($expression);

        return $this;
    }

    private function _selectImpl($expression, $needQuotes = true)
    {
        if (isset($expression)) {
            $fields = '';

            if (is_string($expression)) {
                $expression = explode(',', $expression);
            }

            if (is_array($expression)) {
                foreach ($expression as $field) {
                    $fields .= (strlen($fields) ? ', ' : '') . ($needQuotes ? Utils::quoteStringValue(
                            trim($field)
                        ) : trim($field));
                }
            }

            if (strlen($fields)) {
                $allFieldOperatorPos = strpos($this->resultQuery, self::$ALL_FIELDS);

                if ($allFieldOperatorPos == 7) {
                    $this->resultQuery = substr_replace($this->resultQuery, $fields, 7, strlen(self::$ALL_FIELDS));
                } else {
                    $this->_wrapQuery();
                    $this->_selectImpl($expression);
                }
            }
        }
    }

    public function filter($expression)
    {
        Utils::escapeExpressionValues($this->mySQL, $expression);

        if (isset($expression) && is_array($expression)) {
            $result = FilterHelper::getSqlExprByArray($expression);

            if (strlen($result)) {
                $this->_prepareQueryForLastOperator(self::$WHERE_OP);

                $this->resultQuery .= sprintf(
                    ' %s %s',
                    self::$WHERE_OP,
                    $result
                );
            }
        }

        return $this;
    }

    public function sort($expression)
    {
        Utils::escapeExpressionValues($this->mySQL, $expression);

        if (isset($expression)) {
            $result = '';

            if (is_string($expression)) {
                $result = trim($expression);
            }

            if (is_array($expression)) {
                $fieldSet = AggregateHelper::getFieldSetBySelectors($expression);
                $result = $fieldSet['sort'];
            }

            if (strlen($result)) {
                $this->_prepareQueryForLastOperator(self::$ORDER_OP);

                $this->resultQuery .= sprintf(
                    ' %s %s',
                    self::$ORDER_OP,
                    $result
                );
            }
        }

        return $this;
    }

    public function skipTake($skip, $take)
    {
        $skip = (!isset($skip) || !is_int($skip) ? 0 : $skip);
        $take = (!isset($take) || !is_int($take) ? self::$MAX_ROW_INDEX : $take);

        if ($skip != 0 || $take != 0) {
            $this->_prepareQueryForLastOperator(self::$LIMIT_OP);
            $this->resultQuery .= sprintf(
                ' %s %0.0f, %0.0f',
                self::$LIMIT_OP,
                $skip,
                $take
            );
        }

        return $this;
    }

    private function _createGroupCountQuery($firstGroupField, $skip = null, $take = null)
    {
        $groupCount = $this->groupSettings['groupCount'];
        $lastGroupExpanded = $this->groupSettings['lastGroupExpanded'];

        if (!$lastGroupExpanded) {
            if ($groupCount === 2) {
                $this->groupSettings['groupItemCountQuery'] = sprintf(
                    '%s COUNT(1) %s (%s) AS %s_%d',
                    self::$SELECT_OP,
                    self::$FROM_OP,
                    $this->resultQuery,
                    $this->dbTableName,
                    $this->tableNameIndex + 1
                );

                if (isset($skip) || isset($take)) {
                    $this->skipTake($skip, $take);
                }
            }
        } else {
            $groupQuery = sprintf(
                '%s COUNT(1) %s %s %s %s',
                self::$SELECT_OP,
                self::$FROM_OP,
                $this->dbTableName,
                self::$GROUP_OP,
                $firstGroupField
            );

            $this->groupSettings['groupItemCountQuery'] = sprintf(
                '%s COUNT(1) %s (%s) AS %s_%d',
                self::$SELECT_OP,
                self::$FROM_OP,
                $groupQuery,
                $this->dbTableName,
                $this->tableNameIndex + 1
            );

            if (isset($skip) || isset($take)) {
                $this->groupSettings['skip'] = isset($skip) ? Utils::stringToNumber($skip) : 0;
                $this->groupSettings['take'] = isset($take) ? Utils::stringToNumber($take) : 0;
            }
        }
    }

    public function group($expression, $groupSummary = null, $skip = null, $take = null)
    {
        Utils::escapeExpressionValues($this->mySQL, $expression);
        Utils::escapeExpressionValues($this->mySQL, $groupSummary);

        $this->groupSettings = null;

        if (isset($expression)) {
            $groupFields = '';
            $sortFields = '';
            $selectFields = '';
            $lastGroupExpanded = true;
            $groupCount = 0;

            if (is_string($expression)) {
                $selectFields = $sortFields = $groupFields = trim($expression);
                $groupCount = count(explode(',', $expression));
            }

            if (is_array($expression)) {
                $groupCount = count($expression);
                $fieldSet = AggregateHelper::getFieldSetBySelectors($expression);
                $groupFields = $fieldSet['group'];
                $selectFields = $fieldSet['select'];
                $sortFields = $fieldSet['sort'];
                $lastGroupExpanded = AggregateHelper::isLastGroupExpanded($expression);
            }

            if ($groupCount > 0) {
                if (!$lastGroupExpanded) {
                    $groupSummaryData = isset($groupSummary) && is_array(
                        $groupSummary
                    ) ? AggregateHelper::getSummaryInfo($groupSummary) : null;

                    $selectExpression = sprintf(
                        '%s, %s(1)%s',
                        strlen($selectFields) ? $selectFields : $groupFields,
                        AggregateHelper::COUNT_OP,
                        (isset($groupSummaryData) && isset($groupSummaryData['fields']) && strlen(
                            $groupSummaryData['fields']
                        ) ?
                            ', ' . $groupSummaryData['fields'] : '')
                    );

                    $groupCount++;
                    $this->_wrapQuery();
                    $this->_selectImpl($selectExpression, false);

                    $this->resultQuery .= sprintf(
                        ' %s %s',
                        self::$GROUP_OP,
                        $groupFields
                    );

                    $this->sort($sortFields);
                } else {
                    $this->_wrapQuery();
                    $selectExpression = "{$selectFields}, {$this->lastWrappedTableName}.*";
                    $this->_selectImpl($selectExpression, false);

                    $this->resultQuery .= sprintf(
                        ' %s %s',
                        self::$ORDER_OP,
                        $sortFields
                    );
                }

                $this->groupSettings = [];
                $this->groupSettings['groupCount'] = $groupCount;
                $this->groupSettings['lastGroupExpanded'] = $lastGroupExpanded;
                $this->groupSettings['summaryTypes'] = !$lastGroupExpanded ? $groupSummaryData['summaryTypes'] ?? null : null;

                $firstGroupField = explode(',', $groupFields)[0];
                $this->_createGroupCountQuery($firstGroupField, $skip, $take);
            }
        }

        return $this;
    }

    public function getTotalSummary($expression, $filterExpression = null)
    {
        Utils::escapeExpressionValues($this->mySQL, $expression);
        Utils::escapeExpressionValues($this->mySQL, $filterExpression);

        $result = null;

        if (isset($expression) && is_array($expression)) {
            $summaryInfo = AggregateHelper::getSummaryInfo($expression);
            $fields = $summaryInfo['fields'];

            if (strlen($fields) > 0) {
                $filter = '';

                if (isset($filterExpression)) {
                    if (is_string($filterExpression)) {
                        $filter = trim($filterExpression);
                    }

                    if (is_array($filterExpression)) {
                        $filter = FilterHelper::getSqlExprByArray($filterExpression);
                    }
                }

                $totalSummaryQuery = sprintf(
                    '%s %s %s %s %s',
                    self::$SELECT_OP,
                    $fields,
                    self::$FROM_OP,
                    $this->dbTableName,
                    strlen($filter) > 0 ? self::$WHERE_OP . ' ' . $filter : $filter
                );

                $this->lastError = null;
                $queryResult = $this->mySQL->query($totalSummaryQuery);

                if (!$queryResult) {
                    $this->lastError = $this->mySQL->error;
                } else {
                    if ($queryResult->num_rows > 0) {
                        $result = $queryResult->fetch_array(MYSQLI_NUM);

                        foreach ($result as $i => $item) {
                            $result[$i] = Utils::stringToNumber($item);
                        }
                    }
                }

                if ($queryResult !== false) {
                    $queryResult->close();
                }
            }
        }

        return $result;
    }

    public function getGroupCount()
    {
        $result = 0;

        if ($this->mySQL && isset($this->groupSettings) && isset($this->groupSettings['groupItemCountQuery'])) {
            $this->lastError = null;
            $queryResult = $this->mySQL->query($this->groupSettings['groupItemCountQuery']);

            if (!$queryResult) {
                $this->lastError = $this->mySQL->error;
            } else {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_array(MYSQLI_NUM);
                    $result = Utils::stringToNumber($row[0]);
                }
            }

            if ($queryResult !== false) {
                $queryResult->close();
            }
        }

        return $result;
    }

    public function getCount()
    {
        $result = 0;

        if ($this->mySQL) {
            $countQuery = sprintf(
                '%s %s(1) %s (%s) %s %s_%d',
                self::$SELECT_OP,
                AggregateHelper::COUNT_OP,
                self::$FROM_OP,
                $this->resultQuery,
                AggregateHelper::AS_OP,
                $this->dbTableName,
                $this->tableNameIndex + 1
            );

            $this->lastError = null;
            $queryResult = $this->mySQL->query($countQuery);

            if (!$queryResult) {
                $this->lastError = $this->mySQL->error;
            } else {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_array(MYSQLI_NUM);
                    $result = Utils::stringToNumber($row[0]);
                }
            }

            if ($queryResult !== false) {
                $queryResult->close();
            }
        }

        return $result;
    }

    public function asArray()
    {
        $result = null;

        if ($this->mySQL) {
            $this->lastError = null;
            $queryResult = $this->mySQL->query($this->resultQuery);

            if (!$queryResult) {
                $this->lastError = $this->mySQL->error;
            } else {
                if (isset($this->groupSettings)) {
                    $result = AggregateHelper::getGroupedDataFromQuery($queryResult, $this->groupSettings);
                } else {
                    $result = $queryResult->fetch_all(MYSQLI_ASSOC);
                }

                $queryResult->close();
            }
        }

        return $result;
    }

    public function insert($values)
    {
        Utils::escapeExpressionValues($this->mySQL, $values);

        $result = null;

        if (isset($values) && is_array($values)) {
            $fields = '';
            $fieldValues = '';

            foreach ($values as $prop => $value) {
                $fields .= (strlen($fields) ? ', ' : '') . Utils::quoteStringValue($prop);
                $fieldValues .= (strlen($fieldValues) ? ', ' : '') . Utils::quoteStringValue($value, false);
            }

            if (strlen($fields) > 0) {
                $queryString = sprintf(
                    '%s %s (%s) %s(%s)',
                    self::$INSERT_OP,
                    $this->dbTableName,
                    $fields,
                    self::$VALUES_OP,
                    $fieldValues
                );
                $this->lastError = null;

                if ($this->mySQL->query($queryString) == true) {
                    $result = $this->mySQL->affected_rows;
                } else {
                    $this->lastError = $this->mySQL->error;
                }
            }
        }

        return $result;
    }

    public function update($key, $values)
    {
        Utils::escapeExpressionValues($this->mySQL, $key);
        Utils::escapeExpressionValues($this->mySQL, $values);

        $result = null;

        if (isset($key) && is_array($key) && isset($values) && is_array($values)) {
            $fields = '';

            foreach ($values as $prop => $value) {
                $templ = strlen($fields) == 0 ? '%s = %s' : ', %s = %s';
                $fields .= sprintf(
                    $templ,
                    Utils::quoteStringValue($prop),
                    Utils::quoteStringValue($value, false)
                );
            }

            if (strlen($fields) > 0) {
                $queryString = sprintf(
                    '%s %s %s %s %s %s',
                    self::$UPDATE_OP,
                    $this->dbTableName,
                    self::$SET_OP,
                    $fields,
                    self::$WHERE_OP,
                    FilterHelper::getSqlExprByKey($key)
                );
                $this->lastError = null;

                if ($this->mySQL->query($queryString) == true) {
                    $result = $this->mySQL->affected_rows;
                } else {
                    $this->lastError = $this->mySQL->error;
                }
            }
        }

        return $result;
    }

    public function delete($key)
    {
        Utils::escapeExpressionValues($this->mySQL, $key);
        $result = null;

        if (isset($key) && is_array($key)) {
            $queryString = sprintf(
                '%s %s %s %s %s',
                self::$DELETE_OP,
                self::$FROM_OP,
                $this->dbTableName,
                self::$WHERE_OP,
                FilterHelper::getSqlExprByKey($key)
            );
            $this->lastError = null;

            if ($this->mySQL->query($queryString) == true) {
                $result = $this->mySQL->affected_rows;
            } else {
                $this->lastError = $this->mySQL->error;
            }
        }

        return $result;
    }
}
