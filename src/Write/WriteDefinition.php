<?php

namespace Tualo\Office\DSX\Write;

use Tualo\Office\DSX\Read\SqlQuoter;

/**
 * Write target of a ds table: resolves ds.writetable and the writable columns of ds_column.
 * Replaces dsx_rest_api_set_use_table_name, the column selection of dsx_rest_api_set and
 * the default value handling of dsx_rest_api_set_loop_set_serial / _loop_set_function.
 */
class WriteDefinition
{
    private string $tableName;
    private string $writeTable;
    /** @var array<string,array> all ds_column rows of the write table */
    private array $columns = [];
    /** @var string[] primary key columns, sorted (dsx_get_key_sql) */
    private array $keyColumns = [];

    public static function load($db, string $tableName): self
    {
        $ds = $db->singleRow(
            'select table_name, writetable from ds where table_name = {tablename} and existsreal = 1',
            ['tablename' => $tableName]
        );
        if ($ds === false) {
            throw new \Exception('error on (write-) tablename. tablename: `' . $tableName . '`', WriteRequest::ERROR_CODE);
        }
        $writeTable = ($ds['writetable'] ?? '') == '' ? $tableName : $ds['writetable'];
        if (!SqlQuoter::isIdentifier($writeTable)) {
            throw new \Exception('error on (write-) tablename. tablename: `' . $tableName . '`', WriteRequest::ERROR_CODE);
        }

        $definition = new self();
        $definition->tableName = $tableName;
        $definition->writeTable = $writeTable;
        $rows = $db->direct(
            'select column_name, data_type, column_type, default_value, is_primary, writeable, existsreal, is_generated
            from ds_column where table_name = {tablename}',
            ['tablename' => $writeTable]
        );
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!SqlQuoter::isIdentifier($row['column_name'])) continue;
                $definition->columns[$row['column_name']] = $row;
                if (
                    ($row['existsreal'] ?? 0) == 1
                    && ($row['is_primary'] ?? 0) == 1
                    && !is_null($row['is_generated'])
                    && $row['is_generated'] != 'ALWAYS'
                ) {
                    $definition->keyColumns[] = $row['column_name'];
                }
            }
        }
        ksort($definition->columns, SORT_STRING);
        sort($definition->keyColumns, SORT_STRING);
        return $definition;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getWriteTable(): string
    {
        return $this->writeTable;
    }

    public function getKeyColumns(): array
    {
        return $this->keyColumns;
    }

    public function hasRealColumn(string $name): bool
    {
        return isset($this->columns[$name]) && ($this->columns[$name]['existsreal'] ?? 0) == 1;
    }

    public function getColumn(string $name): array
    {
        return $this->columns[$name];
    }

    private function isWritable(array $col): bool
    {
        return ($col['existsreal'] ?? 0) == 1
            && ($col['writeable'] ?? 0) == 1
            && ($col['is_generated'] ?? '') != 'ALWAYS'
            && ($col['column_type'] ?? '') != '';
    }

    private static function hasDefault(array $col): bool
    {
        return !is_null($col['default_value']) && $col['default_value'] != '';
    }

    /**
     * Columns written by insert/update (sorted by name):
     * present in the first row, primary key, or (pure insert) having a default value.
     * @return string[]
     */
    public function templateColumns(WriteRequest $request): array
    {
        $result = [];
        foreach ($this->columns as $name => $col) {
            if (!$this->isWritable($col)) continue;
            if (
                $request->hasKey($name)
                || ($col['is_primary'] ?? 0) == 1
                || ($request->pureInsert && self::hasDefault($col))
            ) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /**
     * Columns used to identify rows for delete (dsx_rest_api_set_get_delete_statement):
     * writable primary key columns present in the first row, plus "id" if present.
     * @return string[]
     */
    public function deleteColumns(WriteRequest $request): array
    {
        $result = [];
        foreach ($this->columns as $name => $col) {
            if (!$request->hasKey($name)) continue;
            if (($this->isWritable($col) && ($col['is_primary'] ?? 0) == 1) || ($name == 'id' && $this->hasRealColumn('id'))) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /** Column type for json_table (enum is read as varchar like in the procedure). */
    public function jsonTableType(string $name): string
    {
        $type = strtolower(trim((string)$this->columns[$name]['column_type']));
        if (str_starts_with($type, 'enum') || str_starts_with($type, 'set')) return 'varchar(255)';
        if (preg_match('/^[a-z]+(\s*\(\s*\d+\s*(,\s*\d+\s*)?\))?(\s+(unsigned|signed|zerofill))*$/', $type) === 1) return $type;
        throw new \Exception('Nicht unterstützter Spaltentyp `' . $type . '` für `' . $name . '`.', WriteRequest::ERROR_CODE);
    }

    public function isSerial(string $name): bool
    {
        return ($this->columns[$name]['default_value'] ?? null) === '{#serial}';
    }

    /**
     * Applies the configured default to a column value expression, or returns the expression unchanged.
     * {:expr} (SQL from the configuration), {DATETIME}, {DATE}, {TIME}, {GUID}, plain values.
     * {#serial} is handled by the caller (needs max() of the table).
     */
    public function defaultExpression(string $name, string $valueSql, SqlQuoter $quoter): string
    {
        $col = $this->columns[$name];
        $default = (string)($col['default_value'] ?? '');
        $isText = in_array($col['data_type'], ['char', 'longtext', 'varchar'], true);

        if (strlen($default) >= 3 && str_starts_with($default, '{:') && str_ends_with($default, '}')) {
            $expr = substr($default, 2, -1);
            return $isText
                ? 'if(' . $valueSql . ' is null or ' . $valueSql . ' = \'\', ' . $expr . ', ' . $valueSql . ')'
                : 'ifnull(' . $valueSql . ', ' . $expr . ')';
        }
        switch ($default) {
            case '{DATETIME}':
                return 'ifnull(' . $valueSql . ', now())';
            case '{DATE}':
                return 'ifnull(' . $valueSql . ', curdate())';
            case '{TIME}':
                return 'ifnull(' . $valueSql . ', curtime())';
            case '{GUID}':
                return 'if(' . $valueSql . ' is null or ' . $valueSql . ' = \'\', uuid(), ' . $valueSql . ')';
        }
        if ($default !== '' && $default[0] !== '{' && trim($default) !== '') {
            return 'ifnull(' . $valueSql . ', ' . $quoter->string($default) . ')';
        }
        return $valueSql;
    }

    /**
     * Key of a row like dsx_get_key_sql: concat of the key columns with '|', null if one is null.
     */
    public function keyOf(array $values): ?string
    {
        if (count($this->keyColumns) == 0) return null;
        $parts = [];
        foreach ($this->keyColumns as $key) {
            $v = $values[$key] ?? null;
            if (is_null($v)) return null;
            if (is_bool($v)) $v = $v ? '1' : '0';
            $parts[] = (string)$v;
        }
        return implode('|', $parts);
    }

    /**
     * SQL condition matching the row with the given key string (the __id of the read API).
     * A single key column is compared directly so the primary index can be used.
     */
    public function keyCondition(string $key, SqlQuoter $quoter): string
    {
        if (count($this->keyColumns) == 1) {
            return $quoter->identifier($this->keyColumns[0]) . ' = ' . $quoter->string($key);
        }
        $parts = [];
        foreach ($this->keyColumns as $column) $parts[] = $quoter->identifier($column);
        return 'concat(' . implode(',\'|\',', $parts) . ') = ' . $quoter->string($key);
    }

    /** SQL condition matching the key column values of a row. */
    public function keyValuesCondition(array $values, SqlQuoter $quoter): string
    {
        $parts = [];
        foreach ($this->keyColumns as $column) {
            $parts[] = $quoter->identifier($column) . ' = ' . $quoter->value($values[$column]);
        }
        return implode(' and ', $parts);
    }
}
