<?php

namespace Tualo\Office\DSX\Checks;

use Tualo\Office\Basic\Middleware\Session;
use Tualo\Office\Basic\PostCheck;
use Tualo\Office\Basic\TualoApplication as App;


class StoredProcedures extends PostCheck {
    public static function test(array $config){
        // print_r($config);
        $def = [
            'dsx_rest_api_get'=>'0a0049d380ef45a1f59e942d087ec9aa',
            'dsx_rest_api_set'=>'ff7f6e942820840addac974c791c835c'
        ];
        self::procedureCheck($def);
        
    }
}