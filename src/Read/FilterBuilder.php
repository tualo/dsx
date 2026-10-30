<?php

namespace Tualo\Office\DSX\Read;

/**
 * Builds WHERE / HAVING terms from ExtJS style filters.
 * Replaces dsx_filter_values, dsx_filter_values_simple and dsx_filter_operator.
 *
 * Supported filter items:
 *   {"property": "col", "operator": "eq", "value": 1}
 *   {"property": "col", "operator": "in", "value": [1, 2]}
 *   {"property": "col", "operator": "is null"}
 *   {"property": "__id", "operator": "eq", "value": "1|2"}
 *   {"concat_by": "or", "filter": [ ...filter items... ]}
 */
class FilterBuilder
{
    const OPERATORS = [
        '==' => '=',
        'eq' => '=',
        '!=' => '<>',
        'not' => '<>',
        'neq' => '<>',
        '>=' => '>=',
        'gt' => '>=',
        '<=' => '<=',
        'lt' => '<=',
        'not like' => 'not like',
        'like' => 'like',
        'in' => 'in',
        'not in' => 'not in',
        'is null' => 'is null',
        'is not null' => 'is not null',
    ];

    private TableDefinition $definition;
    private SqlQuoter $quoter;

    public function __construct(TableDefinition $definition, SqlQuoter $quoter)
    {
        $this->definition = $definition;
        $this->quoter = $quoter;
    }

    public static function operator(mixed $operator): string
    {
        if (is_string($operator) && isset(self::OPERATORS[$operator])) return self::OPERATORS[$operator];
        return '=';
    }

    public static function concatBy(mixed $concatBy): string
    {
        return (is_string($concatBy) && strtolower(trim($concatBy)) == 'or') ? 'or' : 'and';
    }

    /**
     * @return string|null null if no term was built
     */
    public function build(mixed $filters, mixed $concatBy = 'and'): ?string
    {
        if (is_string($filters)) $filters = json_decode($filters, true);
        if (!is_array($filters)) return null;

        $terms = [];
        foreach ($filters as $item) {
            if (!is_array($item)) continue;

            if (isset($item['filter'])) {
                $group = $this->build($item['filter'], $item['concat_by'] ?? 'and');
                if (!is_null($group)) $terms[] = '(' . $group . ')';
            }

            $term = $this->term($item);
            if (!is_null($term)) $terms[] = $term;
        }

        $terms = array_values(array_unique($terms));
        if (count($terms) == 0) return null;
        return implode(' ' . self::concatBy($concatBy) . ' ', $terms);
    }

    private function term(array $item): ?string
    {
        if (!isset($item['property']) || !is_string($item['property'])) return null;
        $column = $this->column($this->definition->stripPrefix($item['property']));
        if (is_null($column)) return null;

        $rawOperator = $item['operator'] ?? null;
        $operator = self::operator($rawOperator);

        if ($operator == 'is null' || $operator == 'is not null') {
            return $column . ' ' . $operator;
        }

        if (!array_key_exists('value', $item) || is_null($item['value'])) return null;
        $value = $item['value'];

        if ($operator == 'in' || $operator == 'not in') {
            return $column . ' ' . $operator . ' ' . $this->quoter->valueList(is_array($value) ? $value : [$value]);
        }

        return $column . ' ' . $operator . ' ' . $this->quoter->value($value);
    }

    private function column(string $property): ?string
    {
        if ($property == '__id') return $this->definition->keySql($this->quoter);
        if (!$this->definition->hasColumn($property)) return null;
        return $this->quoter->identifier($property);
    }
}
