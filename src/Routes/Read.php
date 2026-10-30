<?php

namespace Tualo\Office\DSX\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\IRoute;
use Tualo\Office\DSX\Read\StreamResponse;

class Read extends \Tualo\Office\Basic\RouteWrapper
{

    public static function register()
    {

        BasicRoute::add('/dsx/(?P<tablename>\w+)/read', function ($matches) {

            $startTime = microtime(true);
            $db = App::get('session')->getDB();
            $tablename = $matches['tablename'];
            try {
                $request = $_REQUEST;
                $request['tablename'] = $tablename;
                if (!isset($request['count'])) $request['count'] = 0;
                if (!isset($request['comibedfieldname'])) $request['comibedfieldname'] = 0;

                StreamResponse::send($db, $request, fn() => ['execution_time' => microtime(true) - $startTime]);
                // response is sent, other routes for this path must not answer again
                return true;
            } catch (\Exception $e) {
                App::result('last_sql', $db->last_sql);
                App::result('msg', $e->getMessage());
            }
            $endTime = microtime(true);
            App::result('execution_time', $endTime - $startTime);
            App::contenttype('application/json');
        }, ['get', 'post'], true);
    }
}
