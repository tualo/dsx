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
        T.ds.forEach( (item) => {
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
        T.ds.forEach( (item) => {
            let dsName = this.getName('stores',item.table_name),
            definition = {
                extend: "Tualo.DataSets.store.Basic",
                statics: {
                  tablename: item.table_name.toLowerCase()
                },
                statefulFilters: true,
                // groupField: [{groupfield}],
                tablename: item.table_name.toLowerCase(),
                alias: 'store.'+this.aliasPrefix+''+item.table_name.toLowerCase()+'_store',
                model: this.getName('model',item.table_name),
                autoSync: false,
                pageSize: item.default_pagesize
              };
            Ext.define(dsName,definition);
            console.log(dsName);
        } );
    },
    factory: function() {

        this.createModels();
        this.createStores();

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
    }


});
