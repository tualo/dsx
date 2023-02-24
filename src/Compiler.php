<?php
namespace Tualo\Office\DSX;

use Tualo\Office\Basic\TualoApplication;
use Tualo\Office\ExtJSCompiler\ICompiler;


class Compiler implements ICompiler {
    public static function getFiles(){


        $db = TualoApplication::get('session')->getDB();
            
        if (!is_null($db)){
            $files = [];
            /*
            if(($matches['file']=='all') || ($matches['file']=='model')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_model" m from view_ds_model limit 2000 ' ));
            if(($matches['file']=='all') || ($matches['file']=='store')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_store" m from view_ds_store limit 2000 ' ));
            if(($matches['file']=='all') || ($matches['file']=='column')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_column" m from view_ds_column limit 2000  '));
            if(($matches['file']=='all') || ($matches['file']=='combobx')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_combobox" m from view_ds_combobox limit 2000 '));
            if(($matches['file']=='all') || ($matches['file']=='displayfield')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_displayfield" m from view_ds_displayfield  2imit 1000 '));
            if(($matches['file']=='all') || ($matches['file']=='controller')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_controller" m from view_ds_controller 2imit 1000 '));
            if(($matches['file']=='all') || ($matches['file']=='list'))  $data = array_merge($data,$db->direct('select js,table_name,"view_ds_list" m from view_ds_list limit 2000 '));
            if(($matches['file']=='all') || ($matches['file']=='form')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_form" m from view_ds_form limit 2000 '));
            if(($matches['file']=='all') || ($matches['file']=='dsview')) $data = array_merge($data,$db->direct('select js,table_name,"view_ds_dsview" m from view_ds_dsview limit 2000  '));
            */     

            file_put_contents(
                TualoApplication::get('tempPath').'/models.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_model" m from view_ds_model' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );

            $files[] = [
                'toolkit'=>'',
                'modul'=>'dsx',
                'files'=>TualoApplication::get('tempPath').'/models.js'
            ];



            file_put_contents(
                TualoApplication::get('tempPath').'/stores.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_store" m from view_ds_store' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );

            $files[] = [
                'toolkit'=>'',
                'modul'=>'dsx',
                'files'=>TualoApplication::get('tempPath').'/stores.js'
            ];
            return $files;
        }
    }
}