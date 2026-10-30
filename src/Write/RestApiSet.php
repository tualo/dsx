<?php

namespace Tualo\Office\DSX\Write;

use Tualo\Office\DSX\Read\AccessControl;
use Tualo\Office\DSX\Read\SqlQuoter;

/**
 * PHP port of the stored procedure dsx_rest_api_set: insert, update and delete for ds tables.
 *
 * Request: tablename, type (insert|update|delete|replace), data (list of rows),
 *   useInsertUpdate, ignore, replace, update.
 * Result: success, message, tablename, writetable, type, subtype, affected_rows,
 *   data (per row: __clientid = sent __id, __id = key after the write, written column values).
 *
 * No temporary table: the rows are converted and completed with defaults in one SELECT over
 * json_table, then written with literal values. Everything runs in a transaction (savepoint
 * if a transaction is already open). Errors are thrown as exceptions (code 1001 like the procedure).
 */
class RestApiSet
{
    private $db;
    private SqlQuoter $quoter;

    public function __construct($db)
    {
        $this->db = $db;
        $this->quoter = new SqlQuoter($db);
    }

    public static function write($db, array $request): array
    {
        return (new self($db))->execute($request);
    }

    public function execute(array $request): array
    {
        $writeRequest = WriteRequest::fromArray($request);

        $access = new AccessControl($this->db);
        foreach ($writeRequest->requiredRights() as $right => $wording) {
            if (!$access->can($writeRequest->tableName, $right)) {
                throw new \Exception(
                    'Sie haben kein Recht zum ' . $wording . ' für `' . $writeRequest->tableName . '`.',
                    WriteRequest::ERROR_CODE
                );
            }
        }

        $definition = WriteDefinition::load($this->db, $writeRequest->tableName);
        return Transaction::run($this->db, fn() => $this->run($writeRequest, $definition));
    }

    private function run(WriteRequest $request, WriteDefinition $definition): array
    {
        $preparer = new DataPreparer($this->db, $this->quoter, $definition);
        $writer = new StatementWriter($this->db, $this->quoter, $definition);

        if ($request->type == 'delete') {
            $rows = $this->delete($request, $definition, $preparer, $writer);
        } else {
            $columns = $definition->templateColumns($request);
            if ($request->type == 'update') {
                // only sent columns: defaults must not invent values for missing (key) columns
                $columns = array_values(array_filter($columns, fn($c) => $request->hasKey($c)));
            }
            $withFiles = $request->hasKey('__file_data') && $request->hasKey('__file_name');
            $rows = $preparer->prepare($request, $columns, true, $withFiles);

            if ($request->type == 'insert') {
                $writer->insert($request->subtype, $columns, $rows);
                foreach ($rows as &$row) $row['id'] = $definition->keyOf($row['values']);
                unset($row);
            } else {
                $keyValuesSent = count($definition->getKeyColumns()) > 0;
                foreach ($definition->getKeyColumns() as $key) {
                    if (!$request->hasKey($key)) $keyValuesSent = false;
                }
                $writer->update($columns, $rows, $keyValuesSent);
                foreach ($rows as &$row) {
                    $row['id'] = $keyValuesSent ? $definition->keyOf($row['values']) : $row['clientid'];
                }
                unset($row);
            }

            if ($withFiles) {
                (new FileDataHandler($this->db, $this->quoter, $definition))->handle($rows);
            }
        }

        $data = [];
        foreach ($rows as $row) {
            $data[] = array_merge(['__clientid' => $row['clientid'], '__id' => $row['id']], $row['values']);
        }
        return [
            'success' => true,
            'message' => 'OK',
            'tablename' => $request->tableName,
            'writetable' => $definition->getWriteTable(),
            'type' => $request->type,
            'subtype' => $request->type == 'insert' ? $request->subtype : null,
            'affected_rows' => $writer->getAffectedRows(),
            'data' => $data,
        ];
    }

    private function delete(WriteRequest $request, WriteDefinition $definition, DataPreparer $preparer, StatementWriter $writer): array
    {
        $columns = $definition->deleteColumns($request);
        if (count($columns) > 0) {
            $rows = $preparer->prepare($request, $columns, false, false);
            $writer->delete($columns, $rows);
        } elseif ($request->hasKey('__id') && count($definition->getKeyColumns()) > 0) {
            // only the __id of the read API was sent
            $rows = $preparer->prepare($request, [], false, false);
            $writer->deleteByKeys(array_values(array_filter(array_column($rows, 'clientid'), fn($k) => !is_null($k))));
        } else {
            throw new \Exception('no fields match for deletion', WriteRequest::ERROR_CODE);
        }
        foreach ($rows as &$row) $row['id'] = $row['clientid'] ?? $definition->keyOf($row['values']);
        unset($row);
        return $rows;
    }
}
