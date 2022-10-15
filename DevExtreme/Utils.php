<?php

declare(strict_types=1);

namespace DevExtreme;

final class Utils
{
    private const NULL_VAL = 'NULL';
    private const FORBIDDEN_CHARACTERS = [
        '`',
        '"',
        "'",
        '~',
        '!',
        '@',
        '#',
        '$',
        '%',
        '=',
        '[',
        ']',
        '\\',
        '/',
        '|',
        '^',
        '&',
        '*',
        '(',
        ')',
        '+',
        '<',
        '>',
        ',',
        '{',
        '}',
        '?',
        ':',
        ';',
        "\r",
        "\n",
    ];

    public static function stringToNumber(string|int|float|null $str): int|float|null
    {
        $currentLocale = localeconv();
        $decimalPoint = $currentLocale['decimal_point'];

        return !str_contains((string)$str, $decimalPoint) ? intval($str) : floatval($str);
    }

    public static function escapeExpressionValues(mixed &$expression = null): void
    {
        if (null !== $expression) {
            if (is_string($expression)) {
                $expression = self::_pdo_escape_string($expression);
            } elseif (is_array($expression)) {
                foreach ($expression as &$arr_value) {
                    self::escapeExpressionValues($arr_value);
                }

                unset($arr_value);
            } elseif (gettype($expression) == 'object') {
                foreach ($expression as $prop => $value) {
                    self::escapeExpressionValues($expression->{$prop});
                }
            }
        }
    }

    public static function quoteStringValue(mixed $value, bool $isFieldName = true): string
    {
        if (is_string($value)) {
            if (!$isFieldName) {
                $value = self::_convertDateTimeToPdoValue($value);
            } else {
                $value = str_replace(self::FORBIDDEN_CHARACTERS, '', $value);
            }
        }

        $resultPattern = $isFieldName ? '`%s`' : (is_bool($value) || (null === $value) ? '%s' : "'%s'");
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : ((null === $value) ? self::NULL_VAL : strval($value));

        return sprintf($resultPattern, $stringValue);
    }

    public static function getItemValueOrDefault(array $params, string $key, mixed $defaultValue = null): mixed
    {
        return $params[$key] ?? $defaultValue;
    }

    private static function _pdo_escape_string(string $unescaped_string): string
    {
        $replacementMap = [
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "\t" => "\\t",
            chr(26) => "\\Z",
            chr(8) => "\\b",
            '"' => '\"',
            "'" => "\'",
            '\\' => '\\\\'
        ];

        return \strtr($unescaped_string, $replacementMap);
    }

    private static function _convertDatePartToISOValue(string $date): string
    {
        $dateParts = explode('/', $date);

        return sprintf('%s-%s-%s', $dateParts[2], $dateParts[0], $dateParts[1]);
    }

    private static function _convertDateTimeToPdoValue(string $strValue): string
    {
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $strValue) === 1) {
            return self::_convertDatePartToISOValue($strValue);
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4} \d{2}:\d{2}:\d{2}\.\d{3}$/', $strValue) === 1) {
            $spacePos = strpos($strValue, ' ');
            $datePart = substr($strValue, 0, $spacePos);
            $timePart = substr($strValue, $spacePos + 1);
            return sprintf('%s %s', self::_convertDatePartToISOValue($datePart), $timePart);
        }

        return $strValue;
    }
}
