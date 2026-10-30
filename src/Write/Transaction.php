<?php

namespace Tualo\Office\DSX\Write;

/**
 * Runs a callback atomically: inside a running transaction with a savepoint,
 * otherwise in its own transaction.
 */
class Transaction
{
    public static function run($db, callable $callback): mixed
    {
        $inTransaction = (int)$db->singleValue('select @@in_transaction t', [], 't') == 1;
        $savepoint = '`dsx_write_' . bin2hex(random_bytes(6)) . '`';

        $db->direct($inTransaction ? 'savepoint ' . $savepoint : 'start transaction');
        try {
            $result = $callback();
            $db->direct($inTransaction ? 'release savepoint ' . $savepoint : 'commit');
            return $result;
        } catch (\Throwable $e) {
            try {
                $db->direct($inTransaction ? 'rollback to savepoint ' . $savepoint : 'rollback');
            } catch (\Throwable $ignored) {
                // keep the original error
            }
            throw $e;
        }
    }
}
