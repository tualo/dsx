<?php

namespace Tualo\Office\Basic {
    class TualoApplication
    {
        public static function configuration(string $section, string $key, mixed $default = false): mixed
        {
            return $default;
        }
    }
}

namespace Tualo\Office\Basic\MYSQL {
    class TestWarning
    {
        private int $index = 0;
        public int $errno;
        public string $message;

        public function __construct(private array $warnings)
        {
            $this->setCurrent();
        }

        private function setCurrent(): void
        {
            $this->errno = $this->warnings[$this->index]['errno'];
            $this->message = $this->warnings[$this->index]['message'];
        }

        public function next(): bool
        {
            if (++$this->index >= count($this->warnings)) return false;
            $this->setCurrent();
            return true;
        }
    }

    class TestConnection
    {
        public int $warning_count = 0;
        public bool $active = false;
        public bool $failWarnings = false;
        public array $warnings = [];

        public function get_warnings(): TestWarning|false
        {
            if ($this->active) throw new \RuntimeException('Warnings were read while the result was active');
            if ($this->failWarnings) return false;
            return $this->warnings === [] ? false : new TestWarning($this->warnings);
        }
    }

    class Database
    {
        public TestConnection $mysqli;
        public array $dataWarnings = [];
        public array $countWarnings = [];
        public array $bufferedDataWarnings = [];
        private array $bufferedWarnings = [];
        public array $rows = [[
            '__table_name' => 'example',
            '__id' => '1',
            '__displayfield' => '1',
            'id' => 1,
            'mail_addresses' => '["sample@example.invalid"]',
        ]];

        public function __construct()
        {
            $this->mysqli = new TestConnection();
        }

        public function direct(string $sql, array $parameters = []): array
        {
            if (str_contains($sql, 'from ds_access')) return [['table_name' => 'example']];
            if (str_contains($sql, 'from ds_column')) {
                return [
                    ['column_name' => 'id', 'data_type' => 'int', 'existsreal' => 1, 'is_primary' => 1, 'is_generated' => 'NEVER'],
                    ['column_name' => 'mail_addresses', 'data_type' => 'mediumtext', 'existsreal' => 1, 'is_primary' => 0, 'is_generated' => 'NEVER'],
                ];
            }
            throw new \RuntimeException('Unexpected metadata query');
        }

        public function singleRow(string $sql, array $parameters = []): array
        {
            return ['table_name' => 'example', 'read_table' => 'example', 'displayfield' => 'id', 'searchfield' => null, 'searchany' => 0];
        }

        public function singleValue(string $sql, array $parameters = [], string $key = ''): int
        {
            $this->bufferedWarnings = $this->countWarnings;
            return 1;
        }

        public function getWarnings(): array
        {
            return $this->bufferedWarnings;
        }

        public function directIterate(string $sql): \Generator
        {
            $this->mysqli->active = true;
            $this->bufferedWarnings = [];
            try {
                yield from $this->rows;
            } finally {
                $this->mysqli->active = false;
            }
            $this->bufferedWarnings = $this->bufferedDataWarnings;
            $this->mysqli->warnings = $this->dataWarnings;
            $this->mysqli->warning_count = count($this->dataWarnings);
        }

        public function escape_string(string $value): string
        {
            return addslashes($value);
        }
    }
}

namespace Tualo\Office\DSX {
    class ErrorLogger
    {
        public static array $errors = [];

        public static function log(mixed $db, string $operation, string $tablename, \Throwable $error, bool $connectionBusy = false): void
        {
            self::$errors[] = $error;
        }
    }
}

namespace {
    use Tualo\Office\Basic\MYSQL\Database;
    use Tualo\Office\DSX\ErrorLogger;
    use Tualo\Office\DSX\Read\RestApiGet;

    $src = dirname(__DIR__, 2) . '/src/Read/';
    foreach (['SqlQuoter', 'AccessControl', 'TableDefinition', 'FilterBuilder', 'SortBuilder', 'RestApiGet'] as $class) {
        require_once $src . $class . '.php';
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message);
    }

    function stream(Database $db, array $request): array
    {
        $json = '';
        RestApiGet::readStream($db, $request, function (string $chunk) use (&$json): void {
            $json .= $chunk;
        });
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    $request = ['tablename' => 'example', 'limit' => 5];
    $cut = ['errno' => 1260, 'message' => 'Row was cut by JSON_ARRAYAGG()'];
    $other = ['errno' => 999, 'message' => 'Unrelated warning'];

    $db = new Database();
    check(RestApiGet::read($db, $request)['success'] === true, 'A read without warnings failed');
    check(stream(new Database(), $request)['success'] === true, 'A stream without warnings failed');

    $db = new Database();
    $db->dataWarnings = [$other];
    check(RestApiGet::read($db, $request)['success'] === true, 'An unrelated warning failed the read');

    $db = new Database();
    $db->dataWarnings = [$other, $cut];
    ErrorLogger::$errors = [];
    try {
        RestApiGet::read($db, $request);
        throw new \RuntimeException('Expected an aggregate truncation failure');
    } catch (\RuntimeException $error) {
        check($error->getCode() === 1260 && str_contains($error->getMessage(), 'group_concat_max_len'), 'Read did not report aggregate truncation');
    }
    check(count(ErrorLogger::$errors) === 1, 'Read truncation was not logged exactly once');

    $db = new Database();
    $db->bufferedDataWarnings = [$cut];
    ErrorLogger::$errors = [];
    try {
        RestApiGet::read($db, $request);
        throw new \RuntimeException('Expected buffered iterator aggregate truncation failure');
    } catch (\RuntimeException $error) {
        check($error->getCode() === 1260, 'A buffered iterator lost its aggregate warning');
    }
    check(count(ErrorLogger::$errors) === 1, 'Buffered iterator truncation was not logged exactly once');

    $db = new Database();
    $db->dataWarnings = [$cut];
    ErrorLogger::$errors = [];
    $result = stream($db, $request);
    check($result['success'] === false && str_contains($result['msg'], 'group_concat_max_len'), 'Stream returned truncated data as success');
    check(count(ErrorLogger::$errors) === 1, 'Stream truncation was not logged exactly once');

    $db = new Database();
    $db->rows = [];
    $db->dataWarnings = [$cut];
    ErrorLogger::$errors = [];
    $json = '';
    try {
        RestApiGet::readStream($db, $request, function (string $chunk) use (&$json): void {
            $json .= $chunk;
        });
        throw new \RuntimeException('Expected an empty-result warning to fail');
    } catch (\RuntimeException $error) {
        check($error->getCode() === 1260 && $json === '', 'An empty-result warning was not reported before output');
    }
    check(count(ErrorLogger::$errors) === 1, 'The empty-result warning was not logged exactly once');

    $db = new Database();
    $db->countWarnings = [$cut];
    ErrorLogger::$errors = [];
    try {
        stream($db, $request + ['count' => 1]);
        throw new \RuntimeException('Expected an aggregate count truncation failure');
    } catch (\RuntimeException $error) {
        check($error->getCode() === 1260, 'Count truncation was not reported before streaming');
    }
    check(count(ErrorLogger::$errors) === 1, 'Count truncation was not logged exactly once');

    $db = new Database();
    $db->dataWarnings = [$cut];
    $db->mysqli->failWarnings = true;
    try {
        RestApiGet::read($db, $request);
        throw new \RuntimeException('Expected a warning retrieval failure');
    } catch (\RuntimeException $error) {
        check(str_contains(strtolower($error->getMessage()), 'warnungen'), 'Warning retrieval failure was hidden');
    }

    echo "DSX aggregate warning tests passed\n";
}
