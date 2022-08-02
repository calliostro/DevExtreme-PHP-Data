<?php

declare(strict_types=1);

namespace DevExtreme;

use PDO;

final class DbSet
{
    private const SELECT_OP = 'SELECT';
    private const FROM_OP = 'FROM';
    private const WHERE_OP = 'WHERE';
    private const ORDER_OP = 'ORDER BY';
    private const GROUP_OP = 'GROUP BY';
    private const ALL_FIELDS = '*';
    private const LIMIT_OP = 'LIMIT';
    private const INSERT_OP = 'INSERT INTO';
    private const VALUES_OP = 'VALUES';
    private const UPDATE_OP = 'UPDATE';
    private const SET_OP = 'SET';
    private const DELETE_OP = 'DELETE';
    private const MAX_ROW_INDEX = 2147483647;

    private string $dbTableName;
    private int $tableNameIndex = 0;
    private string $lastWrappedTableName;
    private string $resultQuery;
    private PDO $pdo;
    private ?array $lastError = null;
    private ?array $groupSettings = null;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->dbTableName = $table;

        $this->resultQuery = sprintf(
            '%s %s %s %s',
            self::SELECT_OP,
            self::ALL_FIELDS,
            self::FROM_OP,
            $this->dbTableName
        );
    }

    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    private function _wrapQuery(): void
    {
        $this->tableNameIndex++;
        $this->lastWrappedTableName = "{$this->dbTableName}_{$this->tableNameIndex}";

        $this->resultQuery = sprintf(
            '%s %s %s (%s) %s %s',
            self::SELECT_OP,
            self::ALL_FIELDS,
            self::FROM_OP,
            $this->resultQuery,
            AggregateHelper::AS_OP,
            $this->lastWrappedTableName
        );
    }

    private function _prepareQueryForLastOperator(string $operator): void
    {
        $operator = trim($operator);
        $lastOperatorPos = strrpos($this->resultQuery, ' ' . $operator . ' ');

        if (false !== $lastOperatorPos) {
            $lastBracketPos = strrpos($this->resultQuery, ')');

            if (((false !== $lastBracketPos) && ($lastOperatorPos > $lastBracketPos)) || (false === $lastBracketPos)) {
                $this->_wrapQuery();
            }
        }
    }

    public function select(mixed $expression): self
    {
        Utils::escapeExpressionValues($expression);

        $this->_selectImpl($expression);

        return $this;
    }

    private function _selectImpl(mixed $expression = null, bool $needQuotes = true): void
    {
        if (null !== $expression) {
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
                $allFieldOperatorPos = strpos($this->resultQuery, self::ALL_FIELDS);

                if ($allFieldOperatorPos == 7) {
                    $this->resultQuery = substr_replace($this->resultQuery, $fields, 7, strlen(self::ALL_FIELDS));
                } else {
                    $this->_wrapQuery();
                    $this->_selectImpl($expression);
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function filter(mixed $expression = null): self
    {
        Utils::escapeExpressionValues($expression);

        if (is_array($expression)) {
            $result = FilterHelper::getSqlExprByArray($expression);

            if (strlen($result)) {
                $this->_prepareQueryForLastOperator(self::WHERE_OP);

                $this->resultQuery .= sprintf(
                    ' %s %s',
                    self::WHERE_OP,
                    $result
                );
            }
        }

        return $this;
    }

    public function sort(mixed $expression = null): self
    {
        Utils::escapeExpressionValues($expression);

        if (null !== $expression) {
            $result = '';

            if (is_string($expression)) {
                $result = trim($expression);
            }

            if (is_array($expression)) {
                $fieldSet = AggregateHelper::getFieldSetBySelectors($expression);
                $result = $fieldSet['sort'];
            }

            if (strlen($result)) {
                $this->_prepareQueryForLastOperator(self::ORDER_OP);

                $this->resultQuery .= sprintf(
                    ' %s %s',
                    self::ORDER_OP,
                    $result
                );
            }
        }

        return $this;
    }

    public function skipTake(?int $skip, ?int $take): self
    {
        $skip = (!is_int($skip) ? 0 : $skip);
        $take = (!is_int($take) ? self::MAX_ROW_INDEX : $take);

        if ($skip != 0 || $take != 0) {
            $this->_prepareQueryForLastOperator(self::LIMIT_OP);
            $this->resultQuery .= sprintf(
                ' %s %0.0f, %0.0f',
                self::LIMIT_OP,
                $skip,
                $take
            );
        }

        return $this;
    }

    private function _createGroupCountQuery(string $firstGroupField, ?int $skip = null, ?int $take = null): void
    {
        $groupCount = $this->groupSettings['groupCount'];
        $lastGroupExpanded = $this->groupSettings['lastGroupExpanded'];

        if (!$lastGroupExpanded) {
            if (2 === $groupCount) {
                $this->groupSettings['groupItemCountQuery'] = sprintf(
                    '%s %s(1) %s (%s) %s %s_%d',
                    self::SELECT_OP,
                    AggregateHelper::COUNT_OP,
                    self::FROM_OP,
                    $this->resultQuery,
                    AggregateHelper::AS_OP,
                    $this->dbTableName,
                    $this->tableNameIndex + 1
                );

                if ((null !== $skip) || (null !== $take)) {
                    $this->skipTake($skip, $take);
                }
            }
        } else {
            $groupQuery = sprintf(
                '%s %s(1) %s %s %s %s',
                self::SELECT_OP,
                AggregateHelper::COUNT_OP,
                self::FROM_OP,
                $this->dbTableName,
                self::GROUP_OP,
                $firstGroupField
            );

            $this->groupSettings['groupItemCountQuery'] = sprintf(
                '%s %s(1) %s (%s) %s %s_%d',
                self::SELECT_OP,
                AggregateHelper::COUNT_OP,
                self::FROM_OP,
                $groupQuery,
                AggregateHelper::AS_OP,
                $this->dbTableName,
                $this->tableNameIndex + 1
            );

            if ((null !== $skip) || (null !== $take)) {
                $this->groupSettings['skip'] = (null !== $skip) ? Utils::stringToNumber($skip) : 0;
                $this->groupSettings['take'] = (null !== $take) ? Utils::stringToNumber($take) : 0;
            }
        }
    }

    public function group(mixed $expression, ?array $groupSummary = null, ?int $skip = null, ?int $take = null): self
    {
        Utils::escapeExpressionValues($expression);
        Utils::escapeExpressionValues($groupSummary);

        $this->groupSettings = null;

        if (null !== $expression) {
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
                    $groupSummaryData = is_array(
                        $groupSummary
                    ) ? AggregateHelper::getSummaryInfo($groupSummary) : null;

                    $selectExpression = sprintf(
                        '%s, %s(1)%s',
                        strlen($selectFields) ? $selectFields : $groupFields,
                        AggregateHelper::COUNT_OP,
                        ((null !== $groupSummaryData) && isset($groupSummaryData['fields']) && strlen(
                            $groupSummaryData['fields']
                        ) ? ', ' . $groupSummaryData['fields'] : '')
                    );

                    $groupCount++;
                    $this->_wrapQuery();
                    $this->_selectImpl($selectExpression, false);

                    $this->resultQuery .= sprintf(
                        ' %s %s',
                        self::GROUP_OP,
                        $groupFields
                    );

                    $this->sort($sortFields);
                } else {
                    $this->_wrapQuery();
                    $selectExpression = "{$selectFields}, {$this->lastWrappedTableName}.*";
                    $this->_selectImpl($selectExpression, false);

                    $this->resultQuery .= sprintf(
                        ' %s %s',
                        self::ORDER_OP,
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

    /**
     * @throws \Exception
     */
    public function getTotalSummary(mixed $expression, string|array|null $filterExpression = null): ?array
    {
        Utils::escapeExpressionValues($expression);
        Utils::escapeExpressionValues($filterExpression);

        if (!is_array($expression)) {
            return null;
        }

        $summaryInfo = AggregateHelper::getSummaryInfo($expression);
        $fields = $summaryInfo['fields'];

        if (strlen($fields) == 0) {
            return null;
        }

        $filter = '';

        if (is_string($filterExpression)) {
            $filter = trim($filterExpression);
        }

        if (is_array($filterExpression)) {
            $filter = FilterHelper::getSqlExprByArray($filterExpression);
        }

        $totalSummaryQuery = sprintf(
            '%s %s %s %s %s',
            self::SELECT_OP,
            $fields,
            self::FROM_OP,
            $this->dbTableName,
            strlen($filter) > 0 ? self::WHERE_OP . ' ' . $filter : $filter
        );

        $this->lastError = null;
        $queryResult = $this->pdo->query($totalSummaryQuery);

        if (!$queryResult) {
            $this->lastError = $this->pdo->errorInfo();
            return null;
        }

        if ($queryResult->rowCount() > 0) {
            $result = $queryResult->fetch(PDO::FETCH_NUM);

            foreach ($result as $i => $item) {
                $result[$i] = Utils::stringToNumber($item);
            }
        }

        $queryResult->closeCursor();

        return $result;
    }

    private function _getCount(string $queryString): int
    {
        $this->lastError = null;
        $queryResult = $this->pdo->query($queryString);

        if (!$queryResult) {
            $this->lastError = $this->pdo->errorInfo();
            return 0;
        }

        try {
            $row = $queryResult->fetch(PDO::FETCH_NUM);
            if (false === $row) {
                return 0;
            }

            return Utils::stringToNumber($row[0]);
        } finally {
            $queryResult->closeCursor();
        }
    }

    public function getCount(): int
    {
        $result = 0;

        $countQuery = sprintf(
            '%s %s(1) %s (%s) %s %s_%d',
            self::SELECT_OP,
            AggregateHelper::COUNT_OP,
            self::FROM_OP,
            $this->resultQuery,
            AggregateHelper::AS_OP,
            $this->dbTableName,
            $this->tableNameIndex + 1
        );

        return $this->_getCount($countQuery);
    }

    public function getGroupCount(): int
    {
        if ((null === $this->groupSettings) || !isset($this->groupSettings['groupItemCountQuery'])) {
            return 0;
        }

        return $this->_getCount($this->groupSettings['groupItemCountQuery']);
    }

    public function asArray(): ?array
    {
        $this->lastError = null;

        $queryResult = $this->pdo->query($this->resultQuery);

        if (!$queryResult) {
            $this->lastError = $this->pdo->errorInfo();
            return null;
        }

        try {
            if (null !== $this->groupSettings) {
                return AggregateHelper::getGroupedDataFromQuery($queryResult, $this->groupSettings);
            } else {
                return $queryResult->fetchAll(PDO::FETCH_ASSOC);
            }
        } finally {
            $queryResult->closeCursor();
        }
    }

    public function _manipulate(string $queryString): ?int
    {
        $this->lastError = null;
        $queryResult = $this->pdo->query($queryString);

        if (!$queryResult) {
            $this->lastError = $this->pdo->errorInfo();
            return null;
        } else {
            return $queryResult->rowCount();
        }
    }

    public function insert(array $values): ?int
    {
        Utils::escapeExpressionValues($values);

        $fields = '';
        $fieldValues = '';

        foreach ($values as $prop => $value) {
            $fields .= (strlen($fields) ? ', ' : '') . Utils::quoteStringValue($prop);
            $fieldValues .= (strlen($fieldValues) ? ', ' : '') . Utils::quoteStringValue($value, false);
        }

        if (strlen($fields) == 0) {
            return null;
        }

        $queryString = sprintf(
            '%s %s (%s) %s(%s)',
            self::INSERT_OP,
            $this->dbTableName,
            $fields,
            self::VALUES_OP,
            $fieldValues
        );

        return $this->_manipulate($queryString);
    }

    public function update(array $key, array $values): ?int
    {
        Utils::escapeExpressionValues($key);
        Utils::escapeExpressionValues($values);

        $fields = '';

        foreach ($values as $prop => $value) {
            $template = strlen($fields) == 0 ? '%s = %s' : ', %s = %s';
            $fields .= sprintf(
                $template,
                Utils::quoteStringValue($prop),
                Utils::quoteStringValue($value, false)
            );
        }

        if (0 == strlen($fields)) {
            return null;
        }

        $queryString = sprintf(
            '%s %s %s %s %s %s',
            self::UPDATE_OP,
            $this->dbTableName,
            self::SET_OP,
            $fields,
            self::WHERE_OP,
            FilterHelper::getSqlExprByKey($key)
        );

        return $this->_manipulate($queryString);
    }

    public function delete(array $key): ?int
    {
        Utils::escapeExpressionValues($key);

        $queryString = sprintf(
            '%s %s %s %s %s',
            self::DELETE_OP,
            self::FROM_OP,
            $this->dbTableName,
            self::WHERE_OP,
            FilterHelper::getSqlExprByKey($key)
        );

        return $this->_manipulate($queryString);
    }
}
