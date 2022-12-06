<?php
namespace Tualo\Office\DSX\Routes;

use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\Route as BasicRoute;
use Tualo\Office\Basic\IRoute;

class JsLoader implements IRoute{
    public static function register(){
        /*
        BasicRoute::add('/dsx/config.js',function($matches){

            TualoApplication::contenttype('application/json');

            TualoApplication::result('ds',$db->direct('select * from ds'));
            TualoApplication::result('ds_column',$db->direct('select * from ds_column'));
            TualoApplication::result('ds_column_form_label',$db->direct('select * from ds_column_form_label'));
            TualoApplication::result('ds_column_list_label',$db->direct('select * from ds_column_list_label'));
            TualoApplication::result('ds_reference_tables',$db->direct('select * from ds_reference_tables'));
            TualoApplication::result('ds_db_types_fieldtype',$db->direct('select * from ds_db_types_fieldtype'));

            
            TualoApplication::result('success', true);

        },['get'],true);
        */
        BasicRoute::add('/dsx/loader.js',function($matches){
            App::contenttype('application/javascript');
            $db = App::get('session')->getDB();
            $o = [
                'ds'                    =>  $db->direct('select * from ds'),
                'ds_column'             =>  $db->direct('select * from ds_column'),
                'ds_column_form_label'  =>  $db->direct('select * from ds_column_form_label'),
                'ds_column_list_label'  =>  $db->direct('select * from ds_column_list_label'),
                'ds_reference_tables'   =>  $db->direct('select * from ds_reference_tables'),
                'ds_db_types_fieldtype' =>  $db->direct('select * from ds_db_types_fieldtype')
            ];
            $list = [
                "js/store/Basic.js"
                "js/Loader.js"
            ];

            $content = 'const T='.json_encode($o).';'.PHP_EOL.PHP_EOL;
            foreach( $list as $item ){
                $content .= file_get_contents( dirname(__DIR__,1).'/'.$item ).PHP_EOL.PHP_EOL;
            }
            App::body( $content );
            
        },['get'],true);
    }
}