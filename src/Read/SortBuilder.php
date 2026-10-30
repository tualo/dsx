<?php

namespace Tualo\Office\DSX\Read;

/**
 * Builds the ORDER BY list from ExtJS style sorters. Replaces dsx_read_order.
 *   [{"property": "col", "direction": "ASC"}, ...]
 */
class SortBuilder
{
    const VIRTUAL_COLUMNS = ['__id', '__displayfield', '__table_name'];

    private TableDefinition $definition;
    private SqlQuoter $quoter;

    public function __construct(TableDefinition $definition, SqlQuoter $quoter)
    {
        $this->definition = $definition;
        $this->quoter = $quoter;
    }

    /**
     * @return string|null null if there is nothing to sort by
     */
    public function build(mixed $sorters): ?string
    {
        if (is_string($sorters)) $sorters = json_decode($sorters, true);
        if (!is_array($sorters)) return null;

        $terms = [];
        foreach ($sorters as $sorter) {
            if (!is_array($sorter) || !isset($sorter['property'], $sorter['direction'])) continue;
            if (!is_string($sorter['property']) || !is_string($sorter['direction'])) continue;

            $direction = strtolower(trim($sorter['direction']));
            if ($direction != 'asc' && $direction != 'desc') continue;

            $property = $this->definition->stripPrefix($sorter['property']);
            if (
                !in_array($property, self::VIRTUAL_COLUMNS)
                && !$this->definition->hasColumn($property)
                && !$this->definition->hasReadColumn($property)
            ) {
                continue;
            }
            $terms[] = $this->quoter->identifier($property) . ' ' . $direction;
        }

        $terms = array_values(array_unique($terms));
        if (count($terms) == 0) return null;
        return implode(',', $terms);
    }
}
