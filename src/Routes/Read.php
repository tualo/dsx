<?php

namespace Tualo\Office\DSX\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\IRoute;
use Tualo\Office\DSX\Read\RestApiGet;

class Read extends \Tualo\Office\Basic\RouteWrapper
{

    public static function register()
    {

        BasicRoute::add('/ds/(?P<tablename>\w+)/read', function ($matches) {

            $startTime = microtime(true);
            $db = App::get('session')->getDB();
            $tablename = $matches['tablename'];
            try {
                $request = $_REQUEST;
                $request['tablename'] = $tablename;
                if (!isset($request['count'])) $request['count'] = 0;
                if (!isset($request['comibedfieldname'])) $request['comibedfieldname'] = 0;

                $o = RestApiGet::read($db, $request);

                App::result('data', $o['data']);
                if (isset($o['debug_query'])) App::result('debug_query', $o['debug_query']);
                if (isset($o['labels'])) App::result('labels', $o['labels']);
                App::result('total', $o['total']);
                //App::result('total',$db->singleValue('select @re res',[],'res')
                App::result('success', $o['success']);
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
