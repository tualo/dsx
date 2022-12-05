Ext.define('TualoLoader', {

    singleton: true,
    baseName: 'T.DataSets.',
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

        console.log('createField',resultObject);
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
        return baseFields.concat(T.ds_column.filter( (item) => { return (table_name==item.table_name) && (item.existsreal==1) } )).map(this.createField);
    },
    createModels: function(){
        
        T.ds.forEach( (item) => {
            let dsName = this.baseName + 'model.' + item.table_name;
            let fields = this.createFields(item.table_name);
            console.log(fields);
            Ext.define(dsName, {
                extend: "Ext.data.Model",
                entityName: item.table_name,
                get: function(fieldName) {
                    if (this.data.hasOwnProperty(fieldName)) return this.data[fieldName];
                    if (this.data.hasOwnProperty("__table_name") && this.data.hasOwnProperty(this.data["__table_name"]+"__"+fieldName)) return this.data[this.data["__table_name"]+"__"+fieldName];
                    return this.data[fieldName];
                },
                idProperty: "__id",
                fields: fields
            })
        } )
        /*
        Ext.define("Tualo.DataSets.model.[{ds_name}]", {
            extend: "Ext.data.Model",
            
            entityName: "[{table_name_lower}]",
            get: function(fieldName) {
              if (this.data.hasOwnProperty(fieldName)) return this.data[fieldName];
              if (this.data.hasOwnProperty("__table_name") && this.data.hasOwnProperty(this.data["__table_name"]+"__"+fieldName)) return this.data[this.data["__table_name"]+"__"+fieldName];
              return this.data[fieldName];
            },
            idProperty: "__id",
            idPropertyY: function(v){ return [{idfieldformula}].join("|");},
            fields: [{fields}]
          });
          */
    },
    factory: function() {


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
    }


});
