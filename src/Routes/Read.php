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
                $_REQUEST['tablename']=$tablename;
                if (!isset($_REQUEST['count'])) $_REQUEST['count']=1;
                if (!isset($_REQUEST['comibedfieldname'])) $_REQUEST['comibedfieldname']=1;
                
                if (isset($_REQUEST['filter'])) $_REQUEST['filter']=json_decode($_REQUEST['filter']);
                if (isset($_REQUEST['sort'])) $_REQUEST['sort']=json_decode($_REQUEST['sort']);

                $db->direct('call dsx_rest_api_get({request},@result);',['request'=>json_encode($_REQUEST)]);
                $o = json_decode($db->singleValue('select @result res',[],'res'),true);
                
                App::result('data',$o['data']);
                if (isset($o['debug_query'])) App::result('debug_query',$o['debug_query']);
                App::result('total',$o['total']);
                //App::result('total',$db->singleValue('select @re res',[],'res')
                App::result('success', $o['success']);
            }catch(\Exception $e){
                App::result('last_sql', $db->last_sql );
                App::result('msg', $e->getMessage());
            }
            App::contenttype('application/json');
        },['get','post'],true);
    }
}
