<?php

use Tualo\Office\DSX\Read\SqlQuoter;
use Tualo\Office\DSX\Write\DataPreparer;
use Tualo\Office\DSX\Write\WriteDefinition;
use Tualo\Office\DSX\Write\WriteRequest;

$src = dirname(__DIR__) . '/src/';
foreach (['Read/SqlQuoter', 'Write/WriteRequest', 'Write/WriteDefinition', 'Write/DataPreparer'] as $file) {
    require_once $src . $file . '.php';
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$db = new class {
    public bool $dbTypes = true;
    public string $sql = '';
    public function singleRow(string $sql, array $params): array
    {
        return ['table_name' => 'example', 'writetable' => ''];
    }
    public function direct(string $sql, array $params = []): array
    {
        if (str_contains($sql, 'from ds_column')) {
            return [[
                'column_name' => 'allowForm', 'data_type' => 'tinyint', 'column_type' => 'tinyint(4)',
                'default_value' => '', 'is_primary' => 0, 'writeable' => 1,
                'existsreal' => 1, 'is_generated' => 'NEVER'
            ]];
        }
        $this->sql = $sql;
        return [['_rownumber' => 1, '__clientid' => null, 'v0' => '1']];
    }
    public function useDBTypes(bool $value): void { $this->dbTypes = $value; }
    public function escape_string(string $value): string { return addslashes($value); }
};

$definition = WriteDefinition::load($db, 'example');
$request = WriteRequest::fromArray(['tablename' => 'example', 'type' => 'update', 'data' => [['allowform' => true]]]);
check($definition->templateColumns($request) === ['allowForm'], 'Lowercase key did not select the database column');
$rows = (new DataPreparer($db, new SqlQuoter($db), $definition))->prepare($request, ['allowForm'], false, false);
check(str_contains($db->sql, 'path \'$."allowform"\''), 'JSON_TABLE did not use the lowercase key');
check(str_contains($db->sql, '`allowForm` tinyint(4)'), 'Database column name changed');
check($rows[0]['values']['allowForm'] === '1', 'Prepared value is missing');

$mixed = WriteRequest::fromArray(['tablename' => 'example', 'type' => 'update', 'data' => [['allowForm' => true]]]);
check($mixed->hasKey('allowForm') && $mixed->rows[0]['allowform'] === true, 'Mixed-case input was not normalized');
try {
    WriteRequest::fromArray(['tablename' => 'example', 'type' => 'update', 'data' => [['allowForm' => null, 'allowform' => true]]]);
    throw new RuntimeException('Ambiguous input was accepted');
} catch (Exception $error) {
    check(str_contains($error->getMessage(), 'Mehrdeutiger Feldname'), 'Unexpected ambiguous-input error');
}

echo "Lowercase write tests passed\n";
