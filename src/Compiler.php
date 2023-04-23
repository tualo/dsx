<?php
namespace Tualo\Office\DSX;

use Tualo\Office\Basic\TualoApplication;
use Tualo\Office\ExtJSCompiler\ICompiler;


class Compiler implements ICompiler {
    public static function getFiles(){
        $db = TualoApplication::get('session')->getDB();
        $files = [];
        if (!is_null($db)){
            
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

            
            file_put_contents(
                TualoApplication::get('tempPath').'/column.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_column" m from view_ds_column' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );


            file_put_contents(
                TualoApplication::get('tempPath').'/combobox.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_combobox" m from view_ds_combobox' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );


            file_put_contents(
                TualoApplication::get('tempPath').'/displayfield.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_displayfield" m from view_ds_displayfield' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );

            file_put_contents(
                TualoApplication::get('tempPath').'/controller.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_controller" m from view_ds_controller' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );


            file_put_contents(
                TualoApplication::get('tempPath').'/list.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_list" m from view_ds_list' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );


            file_put_contents(
                TualoApplication::get('tempPath').'/form.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_form" m from view_ds_form' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );

            file_put_contents(
                TualoApplication::get('tempPath').'/dsview.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_dsview" m from view_ds_dsview' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );


            file_put_contents(
                TualoApplication::get('tempPath').'/dsviewmodel.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_viewmodel" m from view_ds_viewmodel' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );
            file_put_contents(
                TualoApplication::get('tempPath').'/dsviewcontroller.js',
                array_reduce(
                    $db->direct('select js,table_name,"view_ds_controller" m from view_ds_controller' ), 
                    function($acc,$item){
                        return $acc."\n".
                            "/* console.debug('".$item['table_name']."','".$item['m']."');*/".
                            "\n".$item['js'];
                })
            );
            
            $files[] = [
                'prio'=>'99999999999993',
                'toolkit'=>'',
                'modul'=>'dsx',
                'files'=>[
                    ['prio'=>1,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/models.js'],
                    ['prio'=>2,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/stores.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/column.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/combobox.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/displayfield.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/controller.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/list.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/form.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/dsviewmodel.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/dsviewcontroller.js'],
                    ['prio'=>3,'subpath'=>'','file'=>TualoApplication::get('tempPath').'/dsview.js']
                ]
            ];

            
        }
        return $files;
    }
}