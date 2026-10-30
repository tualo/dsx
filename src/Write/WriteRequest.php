<?php

namespace Tualo\Office\DSX\Write;

/**
 * Validated and normalized write request.
 * Replaces dsx_rest_api_set_checks (structure part), dsx_rest_api_set_operation_defaults
 * and dsx_rest_api_set_pure_insert.
 *
 * Request keys: tablename, type (insert|update|delete|replace), data (list of row objects),
 * useInsertUpdate, ignore, replace, update.
 */
class WriteRequest
{
    public const ERROR_CODE = 1001;

    public string $tableName;
    /** type as sent by the client (used for the permission checks) */
    public string $requestedType;
    /** effective operation: insert, update or delete */
    public string $type;
    /** insert variant: insert, replace, ignore or update (on duplicate key update) */
    public string $subtype = 'insert';
    public bool $useInsertUpdate;
    public bool $pureInsert;
    /** @var array<int,array> */
    public array $rows;
    /** @var array<string,bool> keys of the first row; they define the column set like JSON_EXISTS('$.data[0].x') */
    public array $firstRowKeys = [];

    public static function fromArray(array $request): self
    {
        if (!array_key_exists('tablename', $request)) self::fail('attribute tablename not found');
        if (!array_key_exists('type', $request)) self::fail('attribute type not found');
        if (!array_key_exists('data', $request)) self::fail('attribute data not found');
        if (!is_array($request['data']) || !array_is_list($request['data'])) self::fail('attribute data is not an array');
        if (!is_string($request['tablename']) || preg_match('/^\w+$/', $request['tablename']) !== 1) {
            self::fail('error on (write-) tablename. tablename: `' . (is_scalar($request['tablename']) ? $request['tablename'] : 'NULL') . '`');
        }
        if (!in_array($request['type'], ['insert', 'update', 'delete', 'replace'], true)) {
            self::fail('attribute type invalid');
        }
        foreach ($request['data'] as $row) {
            if (!is_array($row) || (count($row) > 0 && array_is_list($row))) self::fail('attribute data must contain objects');
        }

        $r = new self();
        $r->tableName = $request['tablename'];
        $r->requestedType = $request['type'];
        $r->useInsertUpdate = array_key_exists('useInsertUpdate', $request);
        $r->pureInsert = in_array($r->requestedType, ['insert', 'replace'], true) && !$r->useInsertUpdate;
        $r->rows = $request['data'];
        if (count($r->rows) > 0) {
            foreach (array_keys($r->rows[0]) as $key) $r->firstRowKeys[(string)$key] = true;
        }

        $replace = self::flag($request, 'replace');
        $ignore = self::flag($request, 'ignore');
        $update = self::flag($request, 'update');

        $r->type = $r->requestedType;
        if ($r->type == 'update' && $r->useInsertUpdate) {
            $r->type = 'insert';
            $update = true;
        }
        if ($r->type == 'replace') {
            // the procedure accepted type=replace in the checks but built no statement for it
            $r->type = 'insert';
            $replace = true;
        }
        if ($r->type == 'insert') {
            // same precedence as the procedure: update > ignore > replace
            if ($replace) $r->subtype = 'replace';
            if ($ignore) $r->subtype = 'ignore';
            if ($update) $r->subtype = 'update';
        }
        return $r;
    }

    /**
     * ds_access rights required for this request.
     * @return array<string,string> right => wording for the error message
     */
    public function requiredRights(): array
    {
        $rights = [];
        if ($this->requestedType == 'update' || $this->useInsertUpdate) $rights['write'] = 'Ändern';
        if ($this->requestedType == 'delete') $rights['delete'] = 'Löschen';
        if ($this->pureInsert) $rights['append'] = 'Anfügen';
        return $rights;
    }

    public function hasKey(string $column): bool
    {
        return isset($this->firstRowKeys[$column]);
    }

    /** JSON_VALUE(request,'$.x')='1' is true for true, 1 and "1" */
    private static function flag(array $request, string $key): bool
    {
        $v = $request[$key] ?? null;
        return $v === true || $v === 1 || $v === '1';
    }

    private static function fail(string $message): never
    {
        throw new \Exception($message, self::ERROR_CODE);
    }
}
