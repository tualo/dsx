Ext.define('TualoLoader', {

    singleton: true,
    baseName: 'T.DataSets',
    aliasPrefix: '',
    createField: function(data){
        let resultObject = {},
            ds_db_types_fieldtype = T.ds_db_types_fieldtype;

        if (typeof data.column_name=='undefined'){ 
            resultObject = {}; 
        }else{
            resultObject = {
                name: data.table_name.toLowerCase()+'__'+data.column_name.toLowerCase(),
                type: (ds_db_types_fieldtype.filter(
                    (item) => { return (data.data_type==item.dbtype)  }
                ).concat([{fieldtype:'string'}]))[0].fieldtype
            };
        }
        return resultObject;
    },
    createFields: function(table_name){
        let baseFields = [
            {"name":"__id","type":"string"},
            {"name":"__displayfield","type":"string"},
            {"name":"__table_name","type":"string","defaultValue":table_name},
            {"name":"__rownumber","type":"number"},
            {"name":"__formlocked","type":"boolean"}
        ];
        return baseFields.concat(T.ds_column.filter( (item) => { return (table_name==item.table_name) && (item.existsreal==1) } ).map(this.createField));
    },
    createModels: function(){
        T.ds.filter( (item) => { return (""!=item.title) } ).forEach( (item) => {
            let dsName = this.getName('model',item.table_name),
                definition = {
                    extend: "Ext.data.Model",
                    entityName: item.table_name,
                    get: function(fieldName) {
                        if (this.data.hasOwnProperty(fieldName)) return this.data[fieldName];
                        if (this.data.hasOwnProperty("__table_name") && this.data.hasOwnProperty(this.data["__table_name"]+"__"+fieldName)) return this.data[this.data["__table_name"]+"__"+fieldName];
                        return this.data[fieldName];
                    },
                    idProperty: "__id"
                };
            definition.fields = this.createFields(item.table_name);
            Ext.define(dsName,definition);
        } );
    },
    getName: function(type,name){
        let nameParts = [this.baseName,type,this.capitalize(name)];
        return nameParts.join('.');
    },
    capitalize: function(str){
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    },
    createStores: function(){
        T.ds.filter( (item) => { return (""!=item.title) } ).forEach( (item) => {
            let dsName = this.getName('stores',item.table_name),
            definition = {
                extend: "T.DataSets.store.Basic",
                statics: {
                  tablename: item.table_name.toLowerCase()
                },
                tablename: item.table_name.toLowerCase(),
                statefulFilters: true,
                // groupField: [{groupfield}],
                alias: 'store.'+this.aliasPrefix+''+item.table_name.toLowerCase()+'_store',
                model: this.getName('model',item.table_name),
                autoSync: false,
                pageSize: item.default_pagesize
              };
            Ext.define(dsName,definition);
            console.log(dsName);
        } );
    },

    createListColumn: function(ds_column_list_label){
        /*
        '{',
      '"dataIndex":',	`DOUBLEQUOTE`( lower(concat( ds_column_list_label.table_name,'__',ds_column_list_label.column_name ) )) ,',',
      '"header":',		`DOUBLEQUOTE`(ds_column_list_label.label) ,',',
      '"xtype":',		`DOUBLEQUOTE`(ds_column_list_label.xtype) ,',',
      
      if(ds_column_list_label.summarytype<>'', concat( '"summaryType": ',`DOUBLEQUOTE`(ds_column_list_label.summarytype),',' ),'' ),
      if(ds_column_list_label.summarytype<>'', concat( '"summaryRenderer": Tualo.Renderer.getRenderer(', `DOUBLEQUOTE`(ds_column_list_label.summaryrenderer),'),' ),'' ),
      
  

      '"filter":',		if(ds_column_list_label.listfiltertype <>'',  concat(' {"type":"',ds_column_list_label.listfiltertype,'"}') ,if(ds_column.data_type='date','{"type":"date", "dateFormat": "Y-m-d"}',  if(ds_column.data_type='int','{"type":"number"}', if((ds_column.data_type in ('tinyint','boolean') or ds_column.column_type='bigint(4)' ),'{"type":"boolean", "yesText": "Ja", "noText":"Nein"}','{"type":"string"}') )   ) ),',',

      '"hidden":',		if(ds_column_list_label.hidden=0,'false','true') ,',',
      if( ifnull(ds_column_list_label.editor,'')='','', concat( '"editor": "', ds_column_list_label.editor ,'"', ', ') ),
      '"flex":',		ifnull(ds_column_list_label.flex,1) ,'',
      '}'
        */

        let resultObject = {
            dataIndex: ds_column_list_label.table_name.toLowerCase()+'__'+ds_column_list_label.column_name.toLowerCase(),
            header: ds_column_list_label.label,
            hidden: (ds_column_list_label.hidden==1),
            xtype: ds_column_list_label.xtype,
            flex: parseFloat( (ds_column_list_label.flex)?ds_column_list_label.flex:1) 
        }

        return resultObject;
    },
    createListColumns: function(table_name){
        let baseColumns = [];

        
        return baseColumns.concat(T.ds_column_list_label.filter( (item) => { return (table_name==item.table_name) } ).map(this.createListColumn));
    },
    createLists: function(){
        T.ds.filter( (item) => { return (""!=item.title) } ).forEach( (item) => {
            let dsName = this.getName('list',item.table_name),
            definition = {
                extend: 'Ext.grid.Panel',
                alias: 'widget.'+this.aliasPrefix+'listview-'+item.table_name.toLowerCase()+'',
                statics: {
                    tablename: item.table_name.toLowerCase()
                },
                tablename: item.table_name.toLowerCase(),
                selModel: item.listselectionmodel,
                store: {
                    type: this.aliasPrefix+''+item.table_name.toLowerCase()+'_store'
                },
                stateId: this.aliasPrefix+''+item.table_name.toLowerCase()+'_state',
                stateful: true,
            };
            definition.columns = this.createListColumns(item.table_name);
            console.log(definition);
            Ext.define(dsName,definition);
            console.log(dsName);
        } );

        /*



        Ext.define("Tualo.DataSets.list.[{ds_name}]",  {
            extend: "[{listviewbaseclass}]",
            alias: "widget.[{lowerprefix}]listview-[{table_name_lower}]",
            tablename: "[{table_name_lower}]",
            paramfieldPrefix: "[{table_name_lower}]__",
            lowerprefix: "[{lowerprefix}]",
            controller: "[{lowerprefix}][{table_name_lower}]_list_controller",
            viewModel: {
                type: "[{lowerprefix}][{table_name_lower}]_list_model"
            },
            selModel: "[{listselectionmodel}]",
            features: [{listfeatures}],
            columns:  [{columns}],
            store: {
                type: "[{lowerprefix}][{table_name_lower}]_store"
            },
            stateId: "[{lowerprefix}][{table_name_lower}]_state",
            stateful: true,
            viewConfig: {
                plugins: [ ].concat([{listplugins}]),
                listeners: {
                    drop: "onDropGrid"
                },
                getRowClass: function(record, rowIndex, rowParams, store){
                    var tn = store.tablename||"";
                    if ((rowIndex%2==0)&&(typeof record.data[tn+"___rowclass_even"]=="string")){
                        return record.data[tn+"___rowclass_even"];
                    }
                    if ((rowIndex%2==1)&&(typeof record.data[tn+"___rowclass_odd"]=="string")){
                        return record.data[tn+"___rowclass_odd"];
                    }
                    return "";
                }
            }
            });
        */
    },
    factory: function() {

        this.createModels();
        this.createStores();
        this.createLists();
        /*
        fetch('./ds/ds_column/read?limit=100000')
        .then( (data) => {                    return data.json(data) })
        .then( (data) => { console.log(data); return fetch('./ds/ds/read?limit=100000') })
        .then( (data) => {                    return data.json(data) })
        .then( (data) => { console.log(data); return fetch('./ds/ds_column_form_label/read?limit=100000') })
        .then( (data) => {                    return data.json(data) })
        .then( (data) => { console.log(data); return fetch('./ds/ds_column_list_label/read?limit=100000') })
        .then( (data) => {                    return data.json(data) })
        .then( (data) => { console.log(data); return fetch('./ds/ds_reference_tables/read?limit=100000') })
        .then( (data) => {                    return data.json(data) })
        .then( (data) => { console.log(data); return })

        .catch( () => {

        });
        */
    },
    test: function(){
        console.time("factory");
        this.factory();
        console.timeEnd("factory");
        console.time("Ext");
        let zr =Ext.create('T.DataSets.list.Adressen',{
            title: '',
            collapsibe: true
        });
        Application.setActiveItem(zr);
        zr.getStore().load();
        console.timeEnd("Ext");
    }


});
