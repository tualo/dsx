<?php

namespace Tualo\Office\DSX\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\IRoute;

class Read implements IRoute{

    public static function register(){

        BasicRoute::add('/dsx/(?P<tablename>\w+)/read',function($matches){

            $db = App::get('session')->getDB();
            $tablename = $matches['tablename'];
            $db->direct('SET SESSION group_concat_max_len = 4294967295;');
            try{
                $db->direct('call dsx_rest_api_get({request},@result);',$_REQUEST);
                $o = $db->singleValue('select @result res',[],'res');
                App::result('o',$o);
                App::result('data',$db->direct('select * from `',$o['temptable'],'`'));
            }catch(\Exception $e){
                App::result('last_sql', $db->last_sql );
                App::result('msg', $e->getMessage());
                App::result('dq', implode("\n",$GLOBALS['debug_query']));

            }
            TualoApplication::contenttype('application/json');
        },['get'],true);
    }
}
