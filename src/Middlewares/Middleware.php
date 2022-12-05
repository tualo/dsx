<?php

namespace Tualo\Office\DSX\Middlewares;
use Tualo\Office\Basic\TualoApplication as App;
use Tualo\Office\Basic\IMiddleware;

class Middleware implements IMiddleware{
    public static function register(){
        App::use('ds_definition',function(){
            try{
                App::javascript('ds_definition', './dsx/loader.js',[],1000);
            }catch(\Exception $e){
                App::set('maintanceMode','on');
                App::addError($e->getMessage());
            }
        },-100);
        */
    }
}