<?php

namespace DevExtreme;

use Exception;

class FilterHelper
{
    private static $AND_OP = 'AND';
    private static $OR_OP = 'OR';
    private static $LIKE_OP = 'LIKE';
    private static $NOT_OP = 'NOT';
    private static $IS_OP = 'IS';

    private static function _getSqlFieldName($field)
    {
        $fieldParts = explode('.', $field);
        $fieldName = Utils::quoteStringValue(trim($fieldParts[0]));

        if (count($fieldParts) != 2) {
            return $fieldName;
        }

        $dateProperty = trim($fieldParts[1]);

        switch ($dateProperty) {
            case 'year':
            case 'month':
            case 'day':
                $sqlDateFunction = strtoupper($dateProperty);
                $fieldPattern = '%s(%s)';
                break;

            case 'dayOfWeek':
                $sqlDateFunction = strtoupper($dateProperty);
                $fieldPattern = '%s(%s) - 1';
                break;

            default:
                throw new Exception("The \"" . $dateProperty . "\" command is not supported");
        }

        return sprintf($fieldPattern, $sqlDateFunction, $fieldName);
    }

    private static function _getSimpleSqlExpr($expression)
    {
        $result = '';
        $itemsCount = count($expression);
        $fieldName = self::_getSqlFieldName(trim($expression[0]));

        if ($itemsCount == 2) {
            $val = $expression[1];
            $result = sprintf('%s = %s', $fieldName, Utils::quoteStringValue($val, false));
        }

        if ($itemsCount == 3) {
            $clause = trim($expression[1]);
            $val = $expression[2];
            $pattern = '';

            if (is_null($val)) {
                $val = Utils::quoteStringValue($val, false);
                $pattern = '%s %s %s';

                switch ($clause) {
                    case '=':
                        $clause = self::$IS_OP;
                        break;

                    case '<>':
                        $clause = self::$IS_OP . ' ' . self::$NOT_OP;
                        break;
                }
            } else {
                switch ($clause) {
                    case '=':
                    case '<>':
                    case '>':
                    case '>=':
                    case '<':
                    case '<=':
                        $pattern = '%s %s %s';
                        $val = Utils::quoteStringValue($val, false);
                        break;

                    case 'startswith':
                        $pattern = "%s %s '%s%%'";
                        $clause = self::$LIKE_OP;
                        $val = addcslashes($val, '%_');
                        break;

                    case 'endswith':
                        $pattern = "%s %s '%%%s'";
                        $val = addcslashes($val, '%_');
                        $clause = self::$LIKE_OP;
                        break;

                    case 'contains':
                        $pattern = "%s %s '%%%s%%'";
                        $val = addcslashes($val, '%_');
                        $clause = self::$LIKE_OP;
                        break;

                    case 'notcontains':
                        $pattern = "%s %s '%%%s%%'";
                        $val = addcslashes($val, '%_');
                        $clause = sprintf('%s %s', self::$NOT_OP, self::$LIKE_OP);
                        break;

                    default:
                        $clause = '';
                }
            }

            $result = sprintf($pattern, $fieldName, $clause, $val);
        }

        return $result;
    }

    public static function getSqlExprByArray($expression)
    {
        $result = '(';
        $prevItemWasArray = false;

        foreach ($expression as $index => $item) {
            if (is_string($item)) {
                $prevItemWasArray = false;

                if ($index == 0) {
                    if ($item == '!') {
                        $result .= sprintf('%s ', self::$NOT_OP);
                        continue;
                    }

                    $result .= (isset($expression) && is_array($expression)) ? self::_getSimpleSqlExpr(
                        $expression
                    ) : '';

                    break;
                }

                $strItem = strtoupper(trim($item));

                if ($strItem == self::$AND_OP || $strItem == self::$OR_OP) {
                    $result .= sprintf(' %s ', $strItem);
                }

                continue;
            }

            if (is_array($item)) {
                if ($prevItemWasArray) {
                    $result .= sprintf(' %s ', self::$AND_OP);
                }

                $result .= self::getSqlExprByArray($item);
                $prevItemWasArray = true;
            }
        }

        $result .= ')';

        return $result;
    }

    public static function getSqlExprByKey($key)
    {
        $result = '';

        foreach ($key as $prop => $value) {
            $templ = strlen($result) == 0 ?
                '%s = %s' :
                ' ' . self::$AND_OP . ' %s = %s';

            $result .= sprintf(
                $templ,
                Utils::quoteStringValue($prop),
                Utils::quoteStringValue($value, false)
            );
        }

        return $result;
    }
}
