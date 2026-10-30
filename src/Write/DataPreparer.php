<?php

namespace Tualo\Office\DSX\Write;

use Tualo\Office\DSX\Read\SqlQuoter;

/**
 * Phase 1 of a write: converts the request rows with json_table into the column types of the
 * write table and applies the configured defaults ({#serial}, {:expr}, {DATETIME}, ...).
 * One SELECT, no temporary table. The result rows carry the final values, which are then
 * written as literals (phase 2), so generated values (uuid(), serials) are known afterwards.
 */
class DataPreparer
{
    private $db;
    private SqlQuoter $quoter;
    private WriteDefinition $definition;

    public function __construct($db, SqlQuoter $quoter, WriteDefinition $definition)
    {
        $this->db = $db;
        $this->quoter = $quoter;
        $this->definition = $definition;
    }

    /**
     * @param string[] $columns columns to read from the request rows
     * @return array<int,array{clientid:?string,values:array<string,mixed>,file:?array}>
     */
    public function prepare(WriteRequest $request, array $columns, bool $applyDefaults, bool $withFiles): array
    {
        if (count($request->rows) == 0) return [];
        $q = $this->quoter;

        $jtColumns = [
            '`_rownumber` for ordinality',
            '`__clientid` varchar(255) path \'$.__id\' null on empty',
        ];
        $select = ['jt.`_rownumber`', 'jt.`__clientid`'];
        if ($withFiles) {
            $jtColumns[] = '`__file_data` longtext path \'$.__file_data\' null on empty';
            $jtColumns[] = '`__file_name` varchar(255) path \'$.__file_name\' null on empty';
            $jtColumns[] = '`__file_id` varchar(36) path \'$.__file_id\' null on empty';
            $jtColumns[] = '`__file_type` varchar(255) path \'$.__file_type\' null on empty';
            $select[] = 'jt.`__file_data`';
            $select[] = 'jt.`__file_name`';
            $select[] = 'if(ifnull(jt.`__file_id`, \'\') = \'\', uuid(), jt.`__file_id`) `__file_id`';
            $select[] = 'jt.`__file_type`';
        }

        $serial = null;
        foreach ($columns as $i => $name) {
            $jtColumns[] = $q->identifier($name) . ' ' . $this->definition->jsonTableType($name)
                . ' path \'$."' . $name . '"\' null on empty';

            $valueSql = 'jt.' . $q->identifier($name);
            if ($applyDefaults) {
                if ($this->definition->isSerial($name)) {
                    // like the procedure: max() of the first serial column is used for all serial columns
                    if (is_null($serial)) $serial = $this->serialBase($name);
                    $valueSql = 'ifnull(' . $valueSql . ', ' . $serial . ' + jt.`_rownumber`)';
                } else {
                    $valueSql = $this->definition->defaultExpression($name, $valueSql, $q);
                }
            }
            // neutral aliases: column names must not collide with the technical columns
            $select[] = $valueSql . ' `v' . $i . '`';
        }

        $json = json_encode(
            $request->rows,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
        $sql = 'select ' . implode(', ', $select)
            . ' from json_table(' . $q->string($json) . ', \'$[*]\' columns(' . implode(', ', $jtColumns) . ')) jt'
            . ' order by jt.`_rownumber`';

        $result = [];
        // raw values: typed reading turns NULL into 0 (intval) and decimals into floats
        $typed = $this->db->dbTypes ?? true;
        $this->db->useDBTypes(false);
        try {
            $rows = $this->db->direct($sql);
        } finally {
            $this->db->useDBTypes($typed);
        }
        if (!is_array($rows)) return [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $i => $name) $values[$name] = $row['v' . $i];
            $file = null;
            if ($withFiles && !is_null($row['__file_data'])) {
                $file = [
                    'data' => $row['__file_data'],
                    'name' => $row['__file_name'],
                    'id' => $row['__file_id'],
                    'type' => $row['__file_type'],
                ];
            }
            $result[] = [
                'clientid' => is_null($row['__clientid']) ? null : (string)$row['__clientid'],
                'values' => $values,
                'file' => $file,
            ];
        }
        return $result;
    }

    /** SQL literal of max(column) of the write table (dsx_rest_api_set_loop_set_serial). */
    private function serialBase(string $column): string
    {
        $q = $this->quoter;
        $max = $this->db->singleValue(
            'select ifnull(max(' . $q->identifier($column) . '), 0) m from ' . $q->identifier($this->definition->getWriteTable()),
            [],
            'm'
        );
        if (is_int($max) || (is_string($max) && preg_match('/^-?\d+$/', $max) === 1)) return (string)$max;
        return $q->value($max === false ? 0 : $max);
    }
}
