<?php

namespace Tualo\Office\DSX\Read;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\DSX\ErrorLogger;

/**
 * Sends a RestApiGet result directly to the client (JSON stream).
 *
 * The application output (App::result / App::end) is only switched off with the first
 * written chunk; if RestApiGet::stream() throws before, the caller can still answer
 * with App::result() as usual.
 */
class StreamResponse
{
    /**
     * @param callable|null $trailer function(): array, additional keys appended at the end (e.g. execution_time)
     */
    public static function send($db, array $request, ?callable $trailer = null): void
    {
        $started = false;
        $write = function (string $chunk) use (&$started): void {
            if (!$started) {
                $started = true;
                App::stopbuffering();
                while (ob_get_level() > 0) ob_end_clean();
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                    header('X-Accel-Buffering: no');
                }
            }
            echo $chunk;
            flush();
        };
        try {
            $reader = new RestApiGet($db);
        } catch (\Throwable $error) {
            $tablename = $request['tablename'] ?? '';
            ErrorLogger::log($db, 'read', is_string($tablename) ? $tablename : '', $error);
            throw $error;
        }
        $reader->stream($request, $write, fn(): array => self::trailer($trailer));
    }

    /**
     * Keeps the standard fields of the App::end() response; evaluated after the rows are written.
     */
    private static function trailer(?callable $trailer): array
    {
        $extra = is_null($trailer) ? [] : $trailer();
        $appResult = App::result();
        return array_merge([
            'errors' => $appResult['errors'] ?? [],
            'warnings' => $appResult['warnings'] ?? [],
        ], $extra);
    }
}
