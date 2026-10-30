<?php

namespace Tualo\Office\DSX\Read;

/**
 * Builds SQL literals and identifiers. Replaces the SQL helpers
 * FIELDQUOTE, doublequote, JSON_QUOTE and dsx_filter_values_extract.
 */
class SqlQuoter
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public static function isIdentifier(mixed $name): bool
    {
        return is_string($name) && preg_match('/^\w+$/', $name) === 1;
    }

    public function identifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function string(string $value): string
    {
        // Database::direct() replaces {name} placeholders in the whole statement.
        // "\}" is read as "}" by MySQL, but no longer matches the placeholder pattern.
        return '\'' . str_replace('}', '\\}', $this->db->escape_string($value)) . '\'';
    }

    /**
     * Scalar value as SQL literal; arrays become a "(a, b, ...)" list.
     * Mirrors dsx_filter_values_extract (data type is not used there either).
     */
    public function value(mixed $value): string
    {
        if (is_null($value)) return 'NULL';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value)) return (string)$value;
        if (is_float($value)) return json_encode($value);
        if (is_string($value)) return $this->string($value);
        if (is_array($value) && array_is_list($value)) return $this->valueList($value);
        throw new \Exception('Object Type is not allowed as value', 30001);
    }

    public function valueList(array $values): string
    {
        $terms = [];
        foreach ($values as $v) {
            if (is_null($v) || is_array($v) || is_object($v)) continue;
            if (is_bool($v)) $v = $v ? '1' : '0';
            $terms[] = $this->string((string)$v);
        }
        // an empty list would be a syntax error; (NULL) matches nothing
        if (count($terms) == 0) return '(NULL)';
        return '(' . implode(', ', $terms) . ')';
    }
}
