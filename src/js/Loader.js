Ext.define('T.DataSets.Loader', {

    singleton: true,
    baseName: 'T.DataSets.',
    createField: function(data){
        let ds_db_types_fieldtype = T.ds_db_types_fieldtype;
        return {
            name: data.table_name.toLowerCase()+'__'+data.column_name.toLowerCase(),
            type: (ds_db_types_fieldtype.filter(
                (item) => { return (data.data_type==item.dbtype)  }
            ).concat([{fieldtype:'string'}]))[0].fieldtype
        };
    },
    createFields: function(table_name){
        let baseFields = [
            {"name":"__id","type":"string"},
            {"name":"__displayfield","type":"string"},
            {"name":"__table_name","type":"string","defaultValue":table_name},
            {"name":"__rownumber","type":"number"},
            {"name":"__formlocked","type":"boolean"}
        ];
        return baseFields.concat(ds_column.filter( (item) => { return (table_name==item.table_name) && (item.existsreal==1) } )).map(this.createField);
    },
    createModels: function(list){
        
        list.forEach( (item) => {
/*
            '{ ',
        '"name": "',lower(ds_column.table_name),'__',lower(ds_column.column_name),'",',char(10),
        getModelSingleFieldDefault( `ds_db_types_fieldtype`.`fieldtype` , `ds_column`.`default_value` ),
        getModelSingleFieldDateFormat( `ds_column`.`data_type`  ),
        '"type": "', if(ds_column.column_type='bigint(4)','boolean', ifnull(`ds_column_forcetype`.`fieldtype`, ifnull(`ds_db_types_fieldtype`.`fieldtype`,'string'))) ,'"', char(10),


        if(
            ds_column.default_value like '{%',',"allowNull": true',
            ''
        ),'',char(10),
            */

            let dsName = this.baseName + 'model.' + item.table_name;
            Ext.define("Tualo.DataSets.model."+dsName, {
                extend: "Ext.data.Model",
                entityName: item.table_name,
                get: function(fieldName) {
                    if (this.data.hasOwnProperty(fieldName)) return this.data[fieldName];
                    if (this.data.hasOwnProperty("__table_name") && this.data.hasOwnProperty(this.data["__table_name"]+"__"+fieldName)) return this.data[this.data["__table_name"]+"__"+fieldName];
                    return this.data[fieldName];
                },
                idProperty: "__id",
                fields: [{fields}]
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
