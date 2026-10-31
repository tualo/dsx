<?php

namespace Tualo\Office\DSX;

use Tualo\Office\Basic\TualoApplication as App;

class ErrorLogger
{
    public static function log(
        mixed $db,
        string $operation,
        string $tablename,
        \Throwable $error,
        bool $connectionBusy = false
    ): void {
        $lastSql = $db->last_sql ?? null;
        $lastSQL = $db->lastSQL ?? null;
        $context = [
            'operation' => $operation,
            'tablename' => $tablename,
            'code' => $error->getCode(),
            'configured_schema' => $db->dbname ?? null,
            'galera_hostname' => null,
            'exceptions' => [],
        ];

        for ($exception = $error; $exception !== null; $exception = $exception->getPrevious()) {
            $details = [
                'class' => get_class($exception),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ];
            if ($exception instanceof \mysqli_sql_exception) {
                $details['sqlstate'] = $exception->getSqlState();
            }
            $context['exceptions'][] = $details;
        }

        $statement = is_string($lastSql) && $lastSql !== '' ? $lastSql : $lastSQL;
        if (
            is_string($statement) &&
            preg_match('/^\s*(CALL\s+`?[a-zA-Z0-9_]+`?|[a-zA-Z]+)/i', $statement, $matches)
        ) {
            $context['last_statement_prefix'] = $matches[1];
        }

        if (isset($db->mysqli) && $db->mysqli instanceof \mysqli) {
            try {
                $context['mysqli_errno'] = $db->mysqli->errno;
                $context['mysqli_sqlstate'] = $db->mysqli->sqlstate;
                $context['connection_id'] = $db->mysqli->thread_id;
                $context['connection_host_info'] = $db->mysqli->host_info;
            } catch (\Error $diagnosticError) {
                $context['connection_diagnostic_error'] = $diagnosticError->getMessage();
            }
        }

        if ($connectionBusy) {
            $context['galera_hostname_skipped'] = 'Unbuffered read may still be active';
        } elseif (is_object($db) && is_callable([$db, 'singleValue'])) {
            try {
                $context['galera_hostname'] = $db->singleValue('SELECT @@hostname v', [], 'v');
                $context['extended_info'] = self::extendedInfo($db);
            } catch (\Throwable $diagnosticError) {
                $context['galera_hostname_error'] = get_class($diagnosticError) . ': ' . $diagnosticError->getMessage();
            } finally {
                if ($lastSql !== null) $db->last_sql = $lastSql;
                if ($lastSQL !== null) $db->lastSQL = $lastSQL;
            }
        }

        try {
            App::logger('dstable')->error($error->getMessage(), $context);
        } catch (\Throwable $loggingError) {
            error_log('dstable logging failed: ' . $loggingError->getMessage()
                . '; ' . $operation . ' on ' . $tablename . ': ' . $error->getMessage());
        }
    }
    public static function extendedInfo(mixed $db)
    {
        $info = [];
        $infoSQL = '
        SELECT
            @@hostname AS server,
            @@port AS port,
            DATABASE() AS database,
            @@lower_case_table_names AS lower_case_table_names,
            @@GLOBAL.wsrep_on AS wsrep_global,
            @@SESSION.wsrep_on AS wsrep_session,
            @@SESSION.wsrep_OSU_method AS schema_methode
        ';
        $info = array_merge($info,  $db->singleRow($infoSQL, []));

        /*
        $statusSQL = '
        SHOW GLOBAL STATUS
        WHERE Variable_name IN (
            "wsrep_local_state_comment",
            "wsrep_cluster_size",
            "wsrep_cluster_status",
            "wsrep_ready"
        )
        ';
        $info = array_merge($info,  $db->singleRow($statusSQL, []));
        */
        return $info;
    }
}
