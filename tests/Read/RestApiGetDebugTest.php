<?php

namespace Tualo\Office\Basic {
    class TualoApplication
    {
        public static array $settings = [];

        public static function configuration(string $section, string $key, mixed $defaultReturn = false): mixed
        {
            return self::$settings[$section][$key] ?? $defaultReturn;
        }
    }
}

namespace Tualo\Office\Basic\MYSQL {
    class Database
    {
        public bool $readAllowed = true;
        public array $dataStatements = [];
        public array $countStatements = [];
        public array $rows = [[
            '__table_name' => 'example',
            '__id' => '1',
            '__displayfield' => 'Alpha',
            '__rownumber' => 3,
            'id' => 1,
            'name' => 'Alpha',
        ]];

        public function direct(string $sql, array $parameters = []): array
        {
            if (str_contains($sql, 'from ds_access')) {
                return $this->readAllowed ? [['table_name' => 'example']] : [];
            }
            if (str_contains($sql, 'from ds_column')) {
                return [
                    ['column_name' => 'id', 'data_type' => 'int', 'existsreal' => 1, 'is_primary' => 1, 'is_generated' => 'NEVER'],
                    ['column_name' => 'name', 'data_type' => 'varchar', 'existsreal' => 1, 'is_primary' => 0, 'is_generated' => 'NEVER'],
                ];
            }
            throw new \RuntimeException('Unexpected metadata query');
        }

        public function singleRow(string $sql, array $parameters = []): array
        {
            return [
                'table_name' => 'example',
                'read_table' => 'example',
                'displayfield' => 'name',
                'searchfield' => 'name',
                'searchany' => 0,
            ];
        }

        public function singleValue(string $sql, array $parameters = [], string $key = ''): int
        {
            $this->countStatements[] = $sql;
            return 7;
        }

        public function directIterate(string $sql): \Generator
        {
            $this->dataStatements[] = $sql;
            yield from $this->rows;
        }

        public function escape_string(string $value): string
        {
            return addslashes($value);
        }
    }
}

namespace {
    use Tualo\Office\Basic\MYSQL\Database;
    use Tualo\Office\Basic\TualoApplication as App;
    use Tualo\Office\DSX\Read\RestApiGet;

    $readPath = dirname(__DIR__, 2) . '/src/Read/';
    foreach (['SqlQuoter', 'AccessControl', 'TableDefinition', 'FilterBuilder', 'SortBuilder', 'RestApiGet'] as $class) {
        require_once $readPath . $class . '.php';
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    function streamed(Database $db, array $request): array
    {
        $json = '';
        RestApiGet::readStream($db, $request, function (string $chunk) use (&$json): void {
            $json .= $chunk;
        });
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    $request = [
        'tablename' => 'example',
        'filter' => [['property' => 'name', 'operator' => 'eq', 'value' => 'Alpha']],
        'latefilter' => [['property' => 'name', 'operator' => 'eq', 'value' => "O'Reilly"]],
        'sort' => [['property' => 'id', 'direction' => 'DESC']],
        'start' => 2,
        'limit' => 5,
        'rownumber' => 1,
        'count' => 1,
    ];

    $plain = RestApiGet::read(new Database(), $request);
    check(!array_key_exists('debug_sql', $plain), 'SQL was exposed without the configuration option');
    check(!array_key_exists('debug_count_sql', $plain), 'Count SQL was exposed without the configuration option');

    $db = new Database();
    $ignored = RestApiGet::read($db, $request + ['debug_sql' => 1]);
    check($ignored === $plain, 'A request parameter enabled SQL output');

    $db = new Database();
    $stream = streamed($db, $request + ['debug_sql' => '1']);
    check(!array_key_exists('debug_sql', $stream), 'A streamed request enabled SQL output');
    check(!array_key_exists('debug_count_sql', $stream), 'A streamed request enabled count SQL output');

    $db = new Database();
    $legacy = RestApiGet::read($db, ['tablename' => 'example', 'debug_query' => 1, 'limit' => 5]);
    check(isset($legacy['debug_query']), 'Legacy debug_query is missing');
    check(str_contains($db->dataStatements[0], $legacy['debug_query']), 'Legacy base query changed');
    check(!array_key_exists('debug_sql', $legacy), 'Legacy debug_query enabled the new SQL output');
    check(!array_key_exists('debug_count_sql', $legacy), 'Legacy debug_query enabled count SQL output');

    App::$settings = parse_ini_string("[ds]\nreturn_sql=1\n", true, INI_SCANNER_RAW);

    $db = new Database();
    $debug = RestApiGet::read($db, $request);
    check($debug['debug_sql'] === $db->dataStatements[0], 'Debug SQL differs from the data query');
    check($debug['debug_count_sql'] === $db->countStatements[0], 'Debug count SQL differs from the count query');
    check(str_contains($debug['debug_sql'], 'ROW_NUMBER()'), 'Debug SQL lacks row numbering');
    check(str_contains($debug['debug_sql'], 'HAVING'), 'Debug SQL lacks the late filter');
    check(str_contains($debug['debug_sql'], "O\\'Reilly"), 'Debug SQL lacks the escaped filter value');
    check(str_contains($debug['debug_sql'], 'ORDER  BY `id` desc'), 'Debug SQL lacks sorting');
    check(str_contains($debug['debug_sql'], 'LIMIT 2, 5'), 'Debug SQL lacks pagination');
    unset($debug['debug_sql'], $debug['debug_count_sql']);
    check($debug === $plain, 'Debugging changed the normal read result');

    $db = new Database();
    $configured = RestApiGet::read($db, $request + ['debug_sql' => 0]);
    check($configured['debug_sql'] === $db->dataStatements[0], 'A request parameter disabled configured SQL output');

    $db = new Database();
    $noData = RestApiGet::read($db, ['tablename' => 'example', 'nodata' => 1, 'count' => 1, 'limit' => 5]);
    check(array_key_exists('debug_sql', $noData) && $noData['debug_sql'] === null, 'nodata must not report an executed data query');
    check(isset($noData['query'], $noData['limitterm']), 'nodata query information changed');
    check(!array_key_exists('debug_count_sql', $noData), 'nodata reported a count query');
    check($db->dataStatements === [] && $db->countStatements === [], 'nodata executed a data or count query');

    $db = new Database();
    $stream = streamed($db, $request);
    check($stream['debug_sql'] === $db->dataStatements[0], 'Streamed debug SQL differs from the data query');
    check($stream['debug_count_sql'] === $db->countStatements[0], 'Streamed count SQL differs from the count query');
    check($stream['success'] === true, 'Streamed debug response failed');

    $db = new Database();
    $db->rows = [];
    $stream = streamed($db, ['tablename' => 'example', 'limit' => 5]);
    check($stream['debug_sql'] === $db->dataStatements[0], 'Empty stream lost the debug SQL');
    check($stream['data'] === [] && $stream['total'] === 0 && $stream['has_more'] === false, 'Empty debug stream is invalid');
    check(!array_key_exists('debug_count_sql', $stream), 'Empty stream reported an unrequested count query');

    $db = new Database();
    $stream = streamed($db, ['tablename' => 'example', 'nodata' => '1']);
    check(array_key_exists('debug_sql', $stream) && $stream['debug_sql'] === null, 'Streamed nodata reported an executed query');
    check(isset($stream['query']) && $db->dataStatements === [], 'Streamed nodata behavior changed');

    $db = new Database();
    $db->readAllowed = false;
    try {
        RestApiGet::read($db, ['tablename' => 'example']);
        throw new \RuntimeException('Debugging bypassed the read access check');
    } catch (\Exception $error) {
        check($error->getCode() === 15403, 'Unexpected error for denied read access');
    }
    check($db->dataStatements === [] && $db->countStatements === [], 'Denied read executed a data or count query');

    foreach (['0', 'off'] as $value) {
        App::$settings = parse_ini_string("[ds]\nreturn_sql=$value\n", true, INI_SCANNER_RAW);
        $db = new Database();
        $db->rows = [];
        $stream = streamed($db, ['tablename' => 'example', 'debug_sql' => '1', 'limit' => 5]);
        check(!array_key_exists('debug_sql', $stream) && $stream['data'] === [], 'Disabled configuration changed the empty stream');
    }

    echo "RestApiGet debug SQL tests passed\n";
}
