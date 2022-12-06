Ext.define('T.DataSets.store.Basic', {
  
  extend: 'Ext.data.Store',
  alias: 'store.tualobasicstore_x',
  type: 'json',
  autoLoad: false,
  autoSync: true,
  remoteFilter: true,
  remoteSort: true,
  pageSize: 100,


  listeners: {

    beforesync: function( options, eOpts ){
      if (options.destroy){
        if (options.destroy[0]){
          var table_name=options.destroy[0].store.tablename;
           
            options.destroy[0].store.getProxy().setApi({
              read: './dsx/'+table_name+'/read',
              create: './dsx/'+table_name+'/create',
              update: './dsx/'+table_name+'/update',
              destroy: './dsx/'+table_name+'/delete'
            });
          
        }
      }
      if (options.create){
        if (options.create[0]){
          var table_name=options.create[0].store.tablename;
           
            options.create[0].store.getProxy().setApi({
              read: './dsx/'+table_name+'/read',
              create: './dsx/'+table_name+'/create',
              update: './dsx/'+table_name+'/update',
              destroy: './dsx/'+table_name+'/delete'
            });
           
        }
      }
      if (options.update){
        if (options.update[0]){
          var table_name=options.update[0].store.tablename;
           
            options.update[0].store.getProxy().setApi({
              read: './dsx/'+table_name+'/read',
              create: './dsx/'+table_name+'/create',
              update: './dsx/'+table_name+'/update',
              destroy: './dsx/'+table_name+'/delete'
            });
           
        }
      }
    },


    beforeload: function(store,operation,eOpts){
      var store = this,
          request = operation.getRequest(),
          params = operation.getParams();
      if (Ext.isEmpty(params)){
        params={};
      }
      store.proxy.setTimeout(60000);
      store.proxy.tablename = store.tablename;
       
        store.getProxy().setApi({
          read: './dsx/'+store.tablename+'/read',
          create: './dsx/'+store.tablename+'/create',
          update: './dsx/'+store.tablename+'/update',
          destroy: './dsx/'+store.tablename+'/delete'
        });
      
      operation.setParams(params);
      return true;//this.callParent(arguments);
    },

    write: function(store,operation,eOpts){
     //console.log('write',store,operation,eOpts);
     return true;
   },

   datachanged: function(store,eOpts){
    //console.log('datachanged',store,eOpts);
    return true;
   }
  },
  proxy: {
    type: 'ajax',

    headers : { 'Cache-Control': 'max-age' }, //<--See note on iOS below!
    noCache: false,
    api: {
      read: './index.php?p=list/read',
      create: './index.php?p=list/create',
      update: './index.php?p=list/update',
      destroy: './index.php?p=list/delete'
    },
    extraParams: {
    },
    writer: {
      type: 'json',
      writeAllFields: true,
      rootProperty: 'data',
      //idProperty: '__id'
    },
    reader: {
      type: 'json',
      rootProperty: 'data',
      //idProperty: '__id'
    },
    listeners: {
      exception: function(proxy, response, operation,eopts){
        var o,msg;
        console.log('exception**',proxy, response, operation,eopts);
        try{

          if (typeof response.responseJson=='object'){
            o=response.responseJson;
          }else{
            o = Ext.JSON.decode(response.responseText);
          }
          
          msg = o.msg;
          Ext.toast({
           html: msg,
           title: 'Fehler ('+proxy.tablename+')',
           width: 400,
           align: 't'
         });

        }catch(e){
          msg = response.responseText;
        }

        /*
        if (operation.action=='create'){
          if (operation.success===false){
            Ext.toast({
              html: response.statusText+' ('+response.status+')',
              title: 'Fehler ',
              width: 400,
              align: 't'
            });
          }
        }
        */
        operation.setException(msg);
      }
    }
  }
});
