<?php

namespace Tualo\Office\DSX\Write;

use Tualo\Office\DSX\Read\SqlQuoter;

/**
 * Phase 2 of a write: executes insert, update and delete with the prepared values as literals.
 * Replaces dsx_rest_api_set_get_insert_statement, _get_update_statement and _get_delete_statement.
 */
class StatementWriter
{
    /** statements are split when they get larger than this (bytes) */
    private const MAX_STATEMENT_SIZE = 4194304;
    private const MAX_ROWS_PER_STATEMENT = 1000;

    private $db;
    private SqlQuoter $quoter;
    private WriteDefinition $definition;
    private int $affectedRows = 0;

    public function __construct($db, SqlQuoter $quoter, WriteDefinition $definition)
    {
        $this->db = $db;
        $this->quoter = $quoter;
        $this->definition = $definition;
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * insert into | replace into | insert ignore into ... values ... [on duplicate key update]
     * @param array $rows prepared rows (DataPreparer::prepare), values of auto increment keys are filled in
     */
    public function insert(string $subtype, array $columns, array &$rows): void
    {
        if (count($rows) == 0) return;
        if (count($columns) == 0) throw new \Exception('generated sql_command is null', WriteRequest::ERROR_CODE);
        $q = $this->quoter;

        $verb = match ($subtype) {
            'replace' => 'replace into ',
            'ignore' => 'insert ignore into ',
            default => 'insert into ',
        };
        $fields = [];
        $updates = [];
        foreach ($columns as $name) {
            $fields[] = $q->identifier($name);
            $updates[] = $q->identifier($name) . '=values(' . $q->identifier($name) . ')';
        }
        $head = $verb . $q->identifier($this->definition->getWriteTable()) . ' (' . implode(',', $fields) . ') values ';
        $tail = $subtype == 'update' ? ' on duplicate key update ' . implode(',', $updates) : '';

        $autoIncrement = $subtype == 'insert' ? $this->autoIncrementColumn($columns) : null;

        $tuples = [];
        $chunk = [];
        $size = 0;
        foreach ($rows as $index => $row) {
            $literals = [];
            foreach ($columns as $name) $literals[] = $q->value($row['values'][$name]);
            $tuple = '(' . implode(',', $literals) . ')';
            if (count($tuples) > 0 && ($size + strlen($tuple) > self::MAX_STATEMENT_SIZE || count($tuples) >= self::MAX_ROWS_PER_STATEMENT)) {
                $this->insertChunk($head, $tuples, $tail, $chunk, $rows, $autoIncrement);
                $tuples = [];
                $chunk = [];
                $size = 0;
            }
            $tuples[] = $tuple;
            $chunk[] = $index;
            $size += strlen($tuple) + 1;
        }
        $this->insertChunk($head, $tuples, $tail, $chunk, $rows, $autoIncrement);
    }

    private function insertChunk(string $head, array $tuples, string $tail, array $chunk, array &$rows, ?string $autoIncrement): void
    {
        if (count($tuples) == 0) return;
        $this->execute($head . implode(',', $tuples) . $tail);

        if (is_null($autoIncrement)) return;
        foreach ($chunk as $index) {
            // mixed explicit/generated values do not produce consecutive ids
            if (!is_null($rows[$index]['values'][$autoIncrement])) return;
        }
        // a multi row "simple insert" gets consecutive ids (innodb_autoinc_lock_mode 0 and 1)
        $first = (int)$this->db->singleValue('select last_insert_id() i', [], 'i');
        if ($first <= 0) return;
        foreach ($chunk as $offset => $index) {
            $rows[$index]['values'][$autoIncrement] = (string)($first + $offset);
        }
    }

    /** auto increment key column, if the key can be filled after a plain insert */
    private function autoIncrementColumn(array $columns): ?string
    {
        $keys = $this->definition->getKeyColumns();
        $rows = $this->db->direct(
            'select column_name from information_schema.columns
            where table_schema = database() and table_name = {tablename} and extra like \'%auto_increment%\'',
            ['tablename' => $this->definition->getWriteTable()]
        );
        if (!is_array($rows) || count($rows) != 1) return null;
        $column = $rows[0]['column_name'];
        if (!in_array($column, $keys, true) || !in_array($column, $columns, true)) return null;
        $mode = (int)$this->db->singleValue('select @@innodb_autoinc_lock_mode m', [], 'm');
        return $mode == 2 ? null : $column;
    }

    /**
     * update `table` set ... where <key> = __id, one statement per row.
     * Rows without __id are matched by their key column values, if those were sent.
     * @param string[] $setColumns columns present in the request
     * @return int number of rows that could be addressed
     */
    public function update(array $setColumns, array $rows, bool $keyValuesSent): int
    {
        if (count($setColumns) == 0) return 0;
        $q = $this->quoter;
        $table = $q->identifier($this->definition->getWriteTable());
        $addressed = 0;
        foreach ($rows as $row) {
            if (!is_null($row['clientid'])) {
                $where = $this->definition->keyCondition($row['clientid'], $q);
            } elseif ($keyValuesSent && !is_null($this->definition->keyOf($row['values']))) {
                $where = $this->definition->keyValuesCondition($row['values'], $q);
            } else {
                continue;
            }
            $sets = [];
            foreach ($setColumns as $name) $sets[] = $q->identifier($name) . ' = ' . $q->value($row['values'][$name]);
            $this->execute('update ' . $table . ' set ' . implode(', ', $sets) . ' where ' . $where);
            $addressed++;
        }
        return $addressed;
    }

    /**
     * delete from `table` where (c1, c2) in ((...), ...); null values match null like
     * ifnull(c, "I AM NULL") in the procedure.
     * @param string[] $columns
     */
    public function delete(array $columns, array $rows): void
    {
        if (count($rows) == 0) return;
        $q = $this->quoter;
        $table = $q->identifier($this->definition->getWriteTable());
        $fields = [];
        foreach ($columns as $name) $fields[] = $q->identifier($name);
        $tupleHead = count($fields) == 1 ? $fields[0] : '(' . implode(',', $fields) . ')';

        foreach (array_chunk($rows, self::MAX_ROWS_PER_STATEMENT) as $chunk) {
            $tuples = [];
            $nullConditions = [];
            foreach ($chunk as $row) {
                $hasNull = false;
                $literals = [];
                $nullSafe = [];
                foreach ($columns as $i => $name) {
                    $v = $row['values'][$name];
                    if (is_null($v)) $hasNull = true;
                    $literals[] = $q->value($v);
                    $nullSafe[] = $fields[$i] . ' <=> ' . $q->value($v);
                }
                if ($hasNull) {
                    $nullConditions[] = '(' . implode(' and ', $nullSafe) . ')';
                } else {
                    $tuples[] = count($literals) == 1 ? $literals[0] : '(' . implode(',', $literals) . ')';
                }
            }
            $conditions = $nullConditions;
            if (count($tuples) > 0) array_unshift($conditions, $tupleHead . ' in (' . implode(',', $tuples) . ')');
            $this->execute('delete from ' . $table . ' where ' . implode(' or ', $conditions));
        }
    }

    /** delete by the __id of the read API (key string) */
    public function deleteByKeys(array $keys): void
    {
        $q = $this->quoter;
        $table = $q->identifier($this->definition->getWriteTable());
        foreach ($keys as $key) {
            $this->execute('delete from ' . $table . ' where ' . $this->definition->keyCondition($key, $q));
        }
    }

    private function execute(string $sql): void
    {
        $this->db->direct($sql);
        $this->affectedRows += max(0, (int)$this->db->singleValue('select row_count() c', [], 'c'));
    }
}
