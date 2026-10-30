<?php

namespace Tualo\Office\DSX\Read;

/**
 * PHP implementation of the stored procedure dsx_rest_api_get.
 *
 * Request keys (all optional except tablename):
 *   tablename, filter, latefilter, concat_by, sort, reference,
 *   query, search, searchfield, filter_by_search, fulltext,
 *   start, limit, page, count, nodata, rownumber, returnfields,
 *   comibedfieldname, debug_query, concat_set_table, temporary_table_name,
 *   labels (1 = return ds_column_list_label / ds_column_form_label), language
 *
 * Returns the same structure as the procedure:
 *   [data, success, msg, total, order_by, (debug_query), (query, limitterm if nodata=1)]
 *   plus [labels => [list => [...], form => [...]]] if labels=1
 */
class RestApiGet
{
    private $db;
    private SqlQuoter $quoter;
    private AccessControl $access;
    private bool $allowExtendedWhere;

    /**
     * @param bool $allowExtendedWhere accept the raw SQL `extendedwhere` of the request,
     *                                 only enable for trusted (server side) requests
     */
    public function __construct($db, bool $allowExtendedWhere = false)
    {
        $this->db = $db;
        $this->quoter = new SqlQuoter($db);
        $this->access = new AccessControl($db);
        $this->allowExtendedWhere = $allowExtendedWhere;
    }

    public static function read($db, array $request): array
    {
        return (new self($db))->execute($request);
    }

    /**
     * Equivalent of JSON_VALUE(request, '$.key') = n
     */
    private static function is(array $request, string $key, int $n): bool
    {
        $v = $request[$key] ?? null;
        if (is_bool($v)) $v = (int)$v;
        return is_numeric($v) && (int)$v === $n;
    }

    private static function int(array $request, string $key): ?int
    {
        $v = $request[$key] ?? null;
        if (!is_numeric($v)) return null;
        return max(0, (int)$v);
    }

    private static function decodeList(mixed $value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * {"col": value, ...} => equal filters
     */
    private static function referenceFilters(mixed $reference): array
    {
        if (is_string($reference)) $reference = json_decode($reference, true);
        if (!is_array($reference)) return [];
        $filters = [];
        foreach ($reference as $property => $value) {
            $filters[] = ['property' => $property, 'operator' => '=', 'value' => $value];
        }
        return $filters;
    }

    /**
     * Replaces the SQL function fulltext_like:
     * every word has to be contained in the field.
     */
    public function fulltextLike(string $search, string $field): string
    {
        $search = preg_replace('/[^0-9a-z ]/', '%', strtolower($search));
        $terms = [];
        foreach (explode(' ', $search) as $word) {
            $terms[] = $this->quoter->identifier($field) . ' like ' . $this->quoter->string('%' . $word . '%');
        }
        return implode(' and ', $terms);
    }

    public function execute(array $request): array
    {
        // stdClass (json_decode without assoc) => array
        $request = json_decode(json_encode($request), true);
        $q = $this->quoter;

        $tableName = $request['tablename'] ?? null;
        if (!SqlQuoter::isIdentifier($tableName)) {
            throw new \Exception('Ungültiger Tabellenname.', 15400);
        }

        $result = [
            'data' => null,
            'success' => false,
            'msg' => '-',
            'total' => null,
        ];

        $this->access->assertRead($tableName);
        $definition = TableDefinition::load($this->db, $tableName);
        $filterBuilder = new FilterBuilder($definition, $q);

        $sorts = (new SortBuilder($definition, $q))->build($request['sort'] ?? null);
        $orderBy = is_null($sorts) ? '' : ' ORDER  BY ' . $sorts;

        $filter = self::decodeList($request['filter'] ?? null);
        $lateFilter = self::decodeList($request['latefilter'] ?? null);
        foreach (self::referenceFilters($request['reference'] ?? null) as $referenceFilter) {
            $filter[] = $referenceFilter;
        }

        $search = (isset($request['search']) && is_scalar($request['search'])) ? (string)$request['search'] : null;
        if (isset($request['query']) && is_scalar($request['query'])) {
            $search = '%' . $request['query'] . '%';
            $request['filter_by_search'] = 1;
        }

        if (!is_null($search)) {
            $searchField = $definition->getSearchField();
            if (array_key_exists('searchfield', $request) && !is_null($request['searchfield'])) {
                $searchField = is_string($request['searchfield']) ? $request['searchfield'] : null;
            }
            if (is_null($searchField) || $searchField == '') {
                throw new \Exception('Es ist kein Suchfeld für `' . $tableName . '` angelegt', 1001);
            }

            $wildcard = $definition->isSearchAny() ? '%' : '';
            $searchFilter = [
                'operator' => 'like',
                'property' => $searchField,
                'value' => $wildcard . $search . $wildcard
            ];

            // like the procedure: a missing "fulltext" leads to the late filter
            if (self::is($request, 'filter_by_search', 1) && self::is($request, 'fulltext', 0)) {
                array_unshift($filter, $searchFilter);
            } else if (
                self::is($request, 'filter_by_search', 2)
                && (self::is($request, 'fulltext', 1) || self::is($request, 'fulltext', 2))
            ) {
                // search is done by the fulltext join only
            } else {
                $lateFilter[] = $searchFilter;
            }
        }

        $concatBy = $request['concat_by'] ?? 'and';
        $where = $filterBuilder->build($filter, $concatBy);
        if (is_null($where)) {
            $where = ($this->allowExtendedWhere && isset($request['extendedwhere']) && is_string($request['extendedwhere']))
                ? $request['extendedwhere']
                : 'true';
        }
        $having = $filterBuilder->build($lateFilter, $concatBy) ?? 'true';

        $t = $q->identifier($tableName);
        $idSql = $definition->keySql($q, $tableName);
        $displayField = $definition->getDisplayField();
        $displaySql = is_null($displayField) ? 'null' : $t . '.' . $q->identifier($displayField);
        $concatSetTable = array_key_exists('concat_set_table', $request);

        $from = ' ' . $q->identifier($definition->getReadTable()) . ' ' . $t . ' ';
        $fulltextCondition = null;
        if (!is_null($search) && self::is($request, 'fulltext', 1)) {
            $fulltextCondition = 'match(searchvalue) against(' . $q->string($search) . ' in boolean mode)';
        } else if (!is_null($search) && self::is($request, 'fulltext', 2)) {
            $fulltextCondition = $this->fulltextLike($search, 'searchvalue');
        }
        if (!is_null($fulltextCondition)) {
            $from = '( select ' . $t . '.* from ' . $q->identifier($definition->getReadTable()) . ' ' . $t . ' '
                . ' join (select * from ' . $q->identifier('ds_ftsearch_' . $tableName) . ' where ' . $fulltextCondition . ') `_FT` '
                . ' on ' . $definition->keySql($q, '_FT') . '=' . $idSql
                . ')  ' . $t;
        }

        $join = ' ';
        if ($concatSetTable) {
            $temporaryTable = $request['temporary_table_name'] ?? null;
            if (!is_string($temporaryTable) || preg_match('/^\w+(\.\w+)?$/', $temporaryTable) !== 1) {
                throw new \Exception('Ungültiger temporary_table_name.', 15400);
            }
            $join = ' join ' . implode('.', array_map([$q, 'identifier'], explode('.', $temporaryTable)))
                . ' as `temp_alias` on ' . $definition->keySql($q, 'temp_alias') . '=' . $idSql;
        }

        $baseQuery = 'SELECT '
            . $q->string($tableName) . ' __table_name,'
            . $idSql . ' __id,'
            . $displaySql . ' __displayfield,'
            . ($concatSetTable ? '__clientid, ' : '')
            . $t . '.* '
            . 'FROM ' . $from . $join
            . 'WHERE ' . $where . ' '
            . $orderBy;

        if (self::is($request, 'debug_query', 1)) {
            $result['debug_query'] = $baseQuery;
        }

        $limit = self::int($request, 'limit');
        $start = self::int($request, 'start');
        $page = self::int($request, 'page');
        if (!is_null($limit) && is_null($start) && !is_null($page)) {
            $start = max(0, ($page - 1) * $limit);
        }
        $limitTerm = (!is_null($start) && !is_null($limit)) ? ' LIMIT ' . $start . ', ' . $limit : '';
        if (is_null($start)) $start = 0;

        if (self::is($request, 'nodata', 1)) {
            $result['limitterm'] = $limitTerm;
            $result['query'] = 'select * from ( ' . $baseQuery . ' ) y HAVING ' . $having . ' ';
        } else {
            $rowNumber = self::is($request, 'rownumber', 1);
            $sql = 'select * from ( select y.*'
                . ($rowNumber ? ', ROW_NUMBER() OVER ( ' . $orderBy . ') + ' . $start . ' `__rownumber`' : '')
                . ' from ( ' . $baseQuery . ' ) y ) x HAVING ' . $having . ' ' . $orderBy . ' ' . $limitTerm;
            $rows = $this->db->direct($sql);

            $result['data'] = $this->mapRows(
                is_array($rows) ? $rows : [],
                $definition,
                $request,
                $concatSetTable,
                $rowNumber
            );

            if (self::is($request, 'count', 1)) {
                $result['total'] = (int)$this->db->singleValue('select count(0) c from (' . $baseQuery . ') x', [], 'c');
            }
        }

        $result['order_by'] = $orderBy;
        if (self::is($request, 'labels', 1)) {
            $result['labels'] = $this->labels($definition, $request);
        }
        $result['success'] = true;
        return $result;
    }

    /**
     * Configured list and form labels; dataIndex matches the keys of the data rows.
     */
    private function labels(TableDefinition $definition, array $request): array
    {
        $language = (isset($request['language']) && is_string($request['language']) && $request['language'] != '')
            ? $request['language']
            : null;
        $prefix = self::is($request, 'comibedfieldname', 1) ? $definition->getTableName() . '__' : '';

        $result = [];
        foreach (['list' => $definition->getListLabels($language), 'form' => $definition->getFormLabels($language)] as $type => $labels) {
            $result[$type] = [];
            foreach ($labels as $column => $label) {
                $label['dataIndex'] = $prefix . $column;
                $result[$type][] = $label;
            }
        }
        return $result;
    }

    /**
     * Builds the row objects like the JSON_OBJECT(...) of the procedure.
     */
    private function mapRows(array $rows, TableDefinition $definition, array $request, bool $concatSetTable, bool $rowNumber): array
    {
        $tableName = $definition->getTableName();
        $combinedFieldName = self::is($request, 'comibedfieldname', 1);

        $returnFields = $request['returnfields'] ?? null;
        if (is_string($returnFields)) $returnFields = json_decode($returnFields, true);
        $columns = $definition->getReadColumnNames();
        if (is_array($returnFields)) {
            $columns = array_values(array_filter($columns, fn($c) => in_array($c, $returnFields, true)));
        }

        $data = [];
        foreach ($rows as $row) {
            $item = [
                '__table_name' => $row['__table_name'],
                '__id' => $row['__id'],
                '__displayfield' => $row['__displayfield'],
            ];
            if ($concatSetTable) $item['__clientid'] = $row['__clientid'] ?? null;
            if ($rowNumber) $item['__rownumber'] = (int)$row['__rownumber'];

            if (count($columns) == 0) {
                $item['_nofields_'] = 1;
            }
            foreach ($columns as $column) {
                $item[$combinedFieldName ? $tableName . '__' . $column : $column] = $row[$column] ?? null;
            }
            $data[] = $item;
        }
        return $data;
    }
}
