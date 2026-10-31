<?php

namespace Tualo\Office\Basic {
    class TestLogger
    {
        public array $records = [];
        public bool $fail = false;

        public function error(string $message, array $context): void
        {
            if ($this->fail) throw new \RuntimeException('log sink unavailable');
            $this->records[] = ['message' => $message, 'context' => $context];
        }
    }

    class TestSession
    {
        public function __construct(private object $db) {}

        public function getDB(): object
        {
            return $this->db;
        }
    }

    class TualoApplication
    {
        public static ?TestLogger $testLogger = null;
        public static ?TestSession $session = null;
        public static array $testResult = [];

        public static function logger(string $channel): TestLogger
        {
            if ($channel !== 'dstable') throw new \RuntimeException('Unexpected log channel');
            return self::$testLogger ??= new TestLogger();
        }

        public static function configuration(string $section, string $key, mixed $defaultReturn = false): mixed
        {
            return $defaultReturn;
        }

        public static function get(string $key): mixed
        {
            if ($key === 'session') return self::$session;
            throw new \RuntimeException('Unexpected application variable');
        }

        public static function result(?string $key = null, mixed $value = null): ?array
        {
            if ($key === null) return self::$testResult;
            self::$testResult[$key] = $value;
            return null;
        }

        public static function contenttype(string $contenttype): void {}
    }

    class RouteWrapper {}

    class Route
    {
        public static array $handlers = [];

        public static function add(string $path, callable $handler, array $methods, bool $authenticated): void
        {
            self::$handlers[$path] = $handler;
        }
    }
}

namespace Tualo\Office\Basic\MYSQL {
    class Database
    {
        public string $dbname = 'test_schema';
        public string $last_sql = '';
        public string $lastSQL = '';
        public mixed $mysqli = null;
        public ?\Throwable $metadataFailure = null;
        public ?\Throwable $countFailure = null;
        public ?\Throwable $startFailure = null;
        public ?\Throwable $rowFailure = null;
        public ?\Throwable $hostnameFailure = null;
        public bool $invalidFirstRow = false;
        public bool $active = false;
        public int $hostnameQueries = 0;

        public function direct(string $sql, array $parameters = []): array
        {
            $this->last_sql = $this->lastSQL = trim($sql);
            if (str_contains($sql, 'from ds_access')) {
                if ($this->metadataFailure !== null) throw $this->metadataFailure;
                return [['table_name' => 'example']];
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
            $this->last_sql = $this->lastSQL = trim($sql);
            return [
                'table_name' => 'example',
                'read_table' => 'example',
                'displayfield' => 'name',
                'searchfield' => 'name',
                'searchany' => 0,
            ];
        }

        public function singleValue(string $sql, array $parameters = [], string $key = ''): mixed
        {
            $this->last_sql = $this->lastSQL = trim($sql);
            if ($sql === 'SELECT @@hostname v') {
                $this->hostnameQueries++;
                if ($this->active) throw new \RuntimeException('Query attempted during unbuffered read');
                if ($this->hostnameFailure !== null) throw $this->hostnameFailure;
                return 'test-node';
            }
            if ($this->countFailure !== null) throw $this->countFailure;
            return 2;
        }

        public function directIterate(string $sql): \Generator
        {
            $this->last_sql = $this->lastSQL = trim($sql);
            if ($this->startFailure !== null) throw $this->startFailure;
            $this->active = true;
            try {
                yield $this->invalidFirstRow
                    ? 'invalid row'
                    : ['__table_name' => 'example', '__id' => '1', '__displayfield' => 'Alpha', 'id' => 1, 'name' => 'Alpha'];
                if ($this->rowFailure !== null) throw $this->rowFailure;
                yield ['__table_name' => 'example', '__id' => '2', '__displayfield' => 'Beta', 'id' => 2, 'name' => 'Beta'];
            } finally {
                $this->active = false;
            }
        }

        public function escape_string(string $value): string
        {
            return addslashes($value);
        }
    }

    class ReadLoggingTestDatabase extends Database
    {
        public function __construct() {}
    }
}

namespace {
    use Tualo\Office\Basic\MYSQL\ReadLoggingTestDatabase as Database;
    use Tualo\Office\Basic\Route;
    use Tualo\Office\Basic\TestLogger;
    use Tualo\Office\Basic\TestSession;
    use Tualo\Office\Basic\TualoApplication as App;
    use Tualo\Office\DSX\ErrorLogger;
    use Tualo\Office\DSX\Read\RestApiGet;
    use Tualo\Office\DSX\Routes\Read;

    $src = dirname(__DIR__, 2) . '/src/';
    require_once $src . 'ErrorLogger.php';
    foreach (['SqlQuoter', 'AccessControl', 'TableDefinition', 'FilterBuilder', 'SortBuilder', 'RestApiGet', 'StreamResponse'] as $class) {
        require_once $src . 'Read/' . $class . '.php';
    }
    require_once $src . 'Routes/Read.php';

    function check(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    function resetLogger(): void
    {
        App::$testLogger = new TestLogger();
    }

    function onlyRecord(): array
    {
        check(count(App::$testLogger->records) === 1, 'Expected exactly one error log');
        return App::$testLogger->records[0];
    }

    function expectSameError(callable $action, \Throwable $expected): void
    {
        try {
            $action();
        } catch (\Throwable $actual) {
            check($actual === $expected, 'The original error was not preserved');
            return;
        }
        throw new \RuntimeException('Expected the read to fail');
    }

    resetLogger();
    $db = new Database();
    $db->last_sql = $db->lastSQL = "DELETE FROM example WHERE name = 'private'";
    $error = new \RuntimeException('outer error', 42, new \RuntimeException('inner error', 41));
    ErrorLogger::log($db, 'update', 'example', $error);
    $record = onlyRecord();
    $context = $record['context'];
    check($record['message'] === 'outer error', 'Original error message was not logged');
    check($context['operation'] === 'update' && $context['tablename'] === 'example' && $context['code'] === 42, 'Log operation, table or code is incorrect');
    check($context['configured_schema'] === 'test_schema' && $context['galera_hostname'] === 'test-node', 'Database diagnostics are missing');
    check($context['last_statement_prefix'] === 'DELETE', 'The original statement prefix was not captured');
    check($context['exceptions'][0]['code'] === 42 && $context['exceptions'][1]['message'] === 'inner error', 'Exception chain is incomplete');
    check(!str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'private'), 'Full SQL leaked into the log context');
    check($db->last_sql === "DELETE FROM example WHERE name = 'private'" && $db->lastSQL === $db->last_sql, 'Hostname lookup changed the last SQL');

    resetLogger();
    $db = new Database();
    $db->last_sql = $db->lastSQL = 'UPDATE example SET id=1';
    $db->hostnameFailure = new \RuntimeException('hostname lookup failed');
    ErrorLogger::log($db, 'read', 'example', new \RuntimeException('original'));
    $context = onlyRecord()['context'];
    check(str_contains($context['galera_hostname_error'], 'hostname lookup failed'), 'Hostname failure was not logged');
    check($db->last_sql === 'UPDATE example SET id=1' && $db->lastSQL === $db->last_sql, 'Failed hostname lookup changed the last SQL');

    resetLogger();
    $db = new Database();
    $db->active = true;
    ErrorLogger::log($db, 'read', 'example', new \RuntimeException('stream error'), true);
    $context = onlyRecord()['context'];
    check($db->hostnameQueries === 0 && isset($context['galera_hostname_skipped']), 'Diagnostic SQL ran on a busy connection');

    if (class_exists(\mysqli::class)) {
        resetLogger();
        $db = new Database();
        $db->mysqli = new \mysqli();
        ErrorLogger::log($db, 'read', 'example', new \RuntimeException('connection error'));
        check(isset(onlyRecord()['context']['connection_diagnostic_error']), 'Uninitialized connection hid its diagnostic error');
    }

    resetLogger();
    $db = new Database();
    $failure = new \RuntimeException('access failed', 43, new \RuntimeException('access cause'));
    $db->metadataFailure = $failure;
    expectSameError(fn() => RestApiGet::read($db, ['tablename' => 'example']), $failure);
    $context = onlyRecord()['context'];
    check($context['operation'] === 'read' && $context['tablename'] === 'example', 'Direct read log context is incorrect');
    check($context['exceptions'][1]['message'] === 'access cause' && $db->hostnameQueries === 1, 'Direct read diagnostics are incomplete');
    check(str_contains($db->last_sql, 'from ds_access'), 'Direct read error lost its SQL');

    resetLogger();
    $db = new Database();
    $failure = new \RuntimeException('count failed');
    $db->countFailure = $failure;
    expectSameError(fn() => RestApiGet::read($db, ['tablename' => 'example', 'count' => 1]), $failure);
    check(onlyRecord()['message'] === 'count failed' && $db->hostnameQueries === 1, 'Count failure was not logged');

    resetLogger();
    $db = new Database();
    $failure = new \RuntimeException('query failed');
    $db->startFailure = $failure;
    $json = '';
    expectSameError(function () use ($db, &$json): void {
        RestApiGet::readStream($db, ['tablename' => 'example'], function (string $chunk) use (&$json): void {
            $json .= $chunk;
        });
    }, $failure);
    check($json === '' && $db->hostnameQueries === 1, 'Query start failure wrote JSON or missed hostname diagnostics');
    check(onlyRecord()['context']['galera_hostname'] === 'test-node', 'Query start failure was not logged with the backend hostname');

    resetLogger();
    $db = new Database();
    $db->invalidFirstRow = true;
    try {
        RestApiGet::readStream($db, ['tablename' => 'example'], fn(string $chunk) => null);
        throw new \RuntimeException('Expected an invalid first row to fail');
    } catch (\TypeError $error) {
        check(!$db->active && $db->hostnameQueries === 1, 'First-row failure left an active result during diagnostics');
        check(onlyRecord()['context']['galera_hostname'] === 'test-node', 'First-row failure was not logged safely');
    }

    resetLogger();
    $db = new Database();
    $failure = new \Error('row fetch failed');
    $db->rowFailure = $failure;
    $json = '';
    RestApiGet::readStream($db, ['tablename' => 'example', 'limit' => 5], function (string $chunk) use (&$json): void {
        $json .= $chunk;
    });
    $response = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    check($response['success'] === false && $response['msg'] === 'row fetch failed', 'Streaming failure response changed');
    check(count($response['data']) === 1 && $response['total'] === 1 && $response['has_more'] === false, 'Partial stream response is invalid');
    check(onlyRecord()['context']['exceptions'][0]['class'] === \Error::class, 'Stream failure was not logged');
    check($db->hostnameQueries === 0 && !$db->active, 'Stream failure ran SQL while reading or left the result open');

    resetLogger();
    $db = new Database();
    $failure = new \RuntimeException('row read failed');
    $db->rowFailure = $failure;
    expectSameError(fn() => RestApiGet::read($db, ['tablename' => 'example']), $failure);
    check(onlyRecord()['message'] === 'row read failed' && $db->hostnameQueries === 0, 'Direct row failure was not logged safely');
    check(!$db->active, 'Failed direct read left the result open');

    resetLogger();
    $db = new Database();
    $failure = new \RuntimeException('trailer failed');
    expectSameError(function () use ($db, $failure): void {
        RestApiGet::readStream($db, ['tablename' => 'example'], fn(string $chunk) => null, function () use ($failure): array {
            throw $failure;
        });
    }, $failure);
    check(onlyRecord()['message'] === 'trailer failed' && $db->hostnameQueries === 1, 'Trailer failure was not logged');

    resetLogger();
    $db = new Database();
    $json = '';
    RestApiGet::readStream($db, ['tablename' => 'example'], function (string $chunk) use (&$json): void {
        $json .= $chunk;
    });
    check(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['success'] === true, 'Successful stream failed');
    check(App::$testLogger->records === [] && $db->hostnameQueries === 0, 'Successful read ran logging diagnostics');

    $logFile = tempnam(sys_get_temp_dir(), 'dsx-log-');
    if ($logFile === false) throw new \RuntimeException('Could not create fallback log');
    $previousErrorLog = ini_get('error_log');
    try {
        ini_set('error_log', $logFile);
        resetLogger();
        App::$testLogger->fail = true;
        $db = new Database();
        $db->rowFailure = new \RuntimeException('stream error with broken logger');
        $json = '';
        RestApiGet::readStream($db, ['tablename' => 'example'], function (string $chunk) use (&$json): void {
            $json .= $chunk;
        });
        check(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['success'] === false, 'Logger failure corrupted the stream');
        $fallback = file_get_contents($logFile);
        check(is_string($fallback) && str_contains($fallback, 'log sink unavailable') && str_contains($fallback, 'read on example'), 'Logger failure was not surfaced');
    } finally {
        ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
        unlink($logFile);
    }

    resetLogger();
    $db = new Database();
    $db->startFailure = new \RuntimeException('route query failed');
    App::$session = new TestSession($db);
    App::$testResult = ['success' => true];
    $_REQUEST = [];
    Read::register();
    $handler = Route::$handlers['/dsx/(?P<tablename>\w+)/read'];
    $handler(['tablename' => 'example']);
    check(App::$testResult['success'] === false && App::$testResult['msg'] === 'route query failed', 'Route did not return a failed read');
    check(App::$testResult['last_sql'] === $db->last_sql && onlyRecord()['message'] === 'route query failed', 'Route error diagnostics are incorrect');

    resetLogger();
    App::$session = null;
    App::$testResult = ['success' => true];
    $handler(['tablename' => 'example']);
    check(App::$testResult['success'] === false && App::$testResult['last_sql'] === null, 'Missing session was not returned as an error');
    check(onlyRecord()['context']['tablename'] === 'example', 'Missing session was not logged');

    resetLogger();
    App::$session = new TestSession(new \stdClass());
    App::$testResult = ['success' => true];
    $handler(['tablename' => 'example']);
    check(App::$testResult['success'] === false && App::$testResult['last_sql'] === null, 'Invalid database was not returned as an error');
    check(onlyRecord()['context']['exceptions'][0]['class'] === \TypeError::class, 'Reader construction failure was not logged');

    echo "Read error logging tests passed\n";
}
