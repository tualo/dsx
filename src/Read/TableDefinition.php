<?php

namespace Tualo\Office\DSX\Read;

/**
 * Configuration of a dataset (ds / ds_column / ds_column_list_label / ds_column_form_label) as needed for reading.
 */
class TableDefinition
{
    private string $tableName;
    private string $readTable;
    private ?string $displayField;
    private ?string $searchField;
    private bool $searchAny;
    /** @var array<string,array> columns of the table, keyed by column_name */
    private array $columns = [];
    /** @var array<string,array> columns of the read table, keyed by column_name */
    private array $readColumns = [];
    private $db;
    /** @var array<string,array<string,array>> loaded labels, keyed by "list|form" . language */
    private array $labels = [];

    public static function load($db, string $tableName): self
    {
        $ds = $db->singleRow(
            'select table_name, displayfield, read_table, searchfield, searchany from ds where table_name = {tablename}',
            ['tablename' => $tableName]
        );
        if ($ds === false) {
            throw new \Exception('Die Tabelle `' . $tableName . '` ist nicht konfiguriert.', 15404);
        }

        $definition = new self();
        $definition->db = $db;
        $definition->tableName = $tableName;
        $definition->readTable = (is_null($ds['read_table']) || $ds['read_table'] == '') ? $ds['table_name'] : $ds['read_table'];
        $definition->displayField = ($ds['displayfield'] ?? '') == '' ? null : $ds['displayfield'];
        $definition->searchField = ($ds['searchfield'] ?? '') == '' ? null : $ds['searchfield'];
        $definition->searchAny = ($ds['searchany'] ?? 0) == 1;

        if (!SqlQuoter::isIdentifier($definition->readTable)) {
            throw new \Exception('Ungültige read_table für `' . $tableName . '`.', 15400);
        }

        $definition->columns = self::loadColumns($db, $tableName);
        $definition->readColumns = ($definition->readTable == $tableName)
            ? $definition->columns
            : self::loadColumns($db, $definition->readTable);
        return $definition;
    }

    private static function loadColumns($db, string $tableName): array
    {
        $rows = $db->direct(
            'select column_name, data_type, existsreal, is_primary, is_generated from ds_column where table_name = {tablename}',
            ['tablename' => $tableName]
        );
        $columns = [];
        if (is_array($rows)) {
            foreach ($rows as $row) $columns[$row['column_name']] = $row;
        }
        return $columns;
    }

    /**
     * Active list column labels (ds_column_list_label), ordered by position, keyed by column_name.
     */
    public function getListLabels(?string $language = null): array
    {
        return $this->loadLabels('ds_column_list_label', $language);
    }

    /**
     * Active form field labels (ds_column_form_label), ordered by position, keyed by column_name.
     */
    public function getFormLabels(?string $language = null): array
    {
        return $this->loadLabels('ds_column_form_label', $language);
    }

    private function loadLabels(string $labelTable, ?string $language): array
    {
        $cacheKey = $labelTable . '|' . ($language ?? '');
        if (isset($this->labels[$cacheKey])) return $this->labels[$cacheKey];

        $sql = 'select * from ' . $labelTable . '
            where table_name = {tablename}
                and ifnull(active, 0) > 0'
            . (is_null($language) ? '' : ' and `language` = {language}')
            . ' order by position, column_name';
        $rows = $this->db->direct($sql, ['tablename' => $this->tableName, 'language' => $language]);

        $labels = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                // several languages without a language filter: the first (by position) wins
                if (!isset($labels[$row['column_name']])) $labels[$row['column_name']] = $row;
            }
        }
        return $this->labels[$cacheKey] = $labels;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getReadTable(): string
    {
        return $this->readTable;
    }

    public function getDisplayField(): ?string
    {
        return $this->displayField;
    }

    public function getSearchField(): ?string
    {
        return $this->searchField;
    }

    public function isSearchAny(): bool
    {
        return $this->searchAny;
    }

    public function hasColumn(string $name): bool
    {
        return isset($this->columns[$name]);
    }

    public function hasReadColumn(string $name): bool
    {
        return isset($this->readColumns[$name]);
    }

    /**
     * Existing columns of the read table, in definition order.
     * @return string[]
     */
    public function getReadColumnNames(): array
    {
        $names = [];
        foreach ($this->readColumns as $name => $col) {
            if (($col['existsreal'] ?? 0) == 1) $names[] = $name;
        }
        return $names;
    }

    /**
     * Strips the old style "tablename__" prefix from a property name.
     */
    public function stripPrefix(string $property): string
    {
        $prefix = $this->tableName . '__';
        if (str_starts_with($property, $prefix)) return substr($property, strlen($prefix));
        return $property;
    }

    /**
     * SQL expression of the primary key, replaces dsx_get_key_sql_prefix / dsx_get_key_sql.
     */
    public function keySql(SqlQuoter $quoter, string $prefix = ''): string
    {
        $keys = [];
        foreach ($this->columns as $name => $col) {
            if (
                ($col['existsreal'] ?? 0) == 1
                && ($col['is_primary'] ?? 0) == 1
                && !is_null($col['is_generated'] ?? null)
                && $col['is_generated'] != 'ALWAYS'
            ) {
                $keys[] = $name;
            }
        }
        if (count($keys) == 0) return 'null';
        sort($keys, SORT_STRING);

        $parts = [];
        foreach ($keys as $key) {
            $parts[] = ($prefix == '' ? '' : $quoter->identifier($prefix) . '.') . $quoter->identifier($key);
        }
        return 'concat(' . implode(',\'|\',', $parts) . ')';
    }
}
