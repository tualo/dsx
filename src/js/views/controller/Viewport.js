Ext.define('Ext.cmp.cmp_dsx.controller.Viewport', {
    extend: 'Ext.app.ViewController',
    alias: 'controller.cmp_dsx_viewport',

    onBoxReady: function(){

        //document.querySelector("#fieldset-1096")
        let form = this.lookupReference('formm');
        form.down().setStyle('background-color','orange');

        let tree = this.lookupReference('treem');
        tree.down().setStyle('background-color','orange');
        // efe9be
    },



    onReloadTreeBeforeLoad: function(store, operation, eOpts){
        let p = operation.getParams();
        if (typeof p == 'undefined'){
            p={};
        }
        p.days_forward = this.lookupReference('days_forward').getValue();
        p.days_back = this.lookupReference('days_back').getValue();

        operation.setParams(p);
    },
    onReloadTreeClicked: function(){
        var store = this.getViewModel().getStore('searchtree');
        store.load({
            /*params: {
                days_forward: this.lookupReference('days_forward').getValue(),
                days_back: this.lookupReference('days_back').getValue()
            }*/
        });

    },

    refreshRow:function(id){
        var store = this.getViewModel().getStore('searchtree');
        var node = store.getNodeById(id);
        if (node){
            store.load({node: node});
        }
    },

    onTreeItemDplClick: function( me, record, item, index, e, eOpts ){
        console.log('record',record.data,record.get('bunde_unbestaetigt'))  
        var form = this.lookupReference('form');
        var m=this;
        var code = record.get('code');
        if (record.get('bunde_unbestaetigt')*1>-1){
          if (record.get('id').split('.').length==3){
              Ext.MessageBox.prompt(
                  'Bund '+record.get('code')+' bestätigen',
                  'Begründen Sie bitte die manuelle Bestätigung',
                  function(btn,txt){
                      if (btn=='ok'){
                          
                        Tualo.Ajax.request({
                            url: './cmp_pm_bundzettel/scan',
                            params: {
                                code: code,
                                note: txt,
                                dodate: Ext.Date.format( m.lookupReference('dodate').getValue(),'Y-m-d')
                            },
                            showWait: true,
                            scope: this,
                            json: function(o){
                                if (o.success==false){
                                    Ext.toast({
                                        html: o.msg,
                                        title: 'Fehler',
                                        align: 't',
                                        iconCls: 'fa fa-warning'
                                    });
            
                                    form.down().setStyle('background-color','red');
                                    form.getForm().setValues(
                                    {
                                        scaned_code: code,
                                        "dateiname": "---",
                                        "auftrag": "---",
                                        "einspeiserid": "---",
                                        "einspeisername": "---",
                                        "aktionsnr_vkz": "---",
                                        "anlieferungfruehestens": "---",
                                        "anlieferungspaetestens": "---",
                                        "zustellungfruehestens": "---",
                                        "zustellungspaetestens": "---",
                                        "produkt": "---",
                                        "suchfeld": "--",
                                        "belegnummer": "---",
                                        "tabellenzusatz": "---",
                                        "c": "---",
                                        "gewicht": "---",
                                        "itemgewicht": "---",
                                        "itemformat": "---",
                                        "dateiname": "---",
                                        "bunde": "---"
                                    }
                                    );
                                    
                                    
                                }else{
                                    console.log(o);
                                    form.down().setStyle('background-color','');
                                    form.getForm().setValues({ scaned_code:  code });
                                    form.getForm().setValues(o.details);
                                    form.getForm().setValues(o.auftrag);
                                    
                                }
                            }
                          });
                      }
                  },
                  false,
                  ''
              );
          }
        }
      },

      onTreeItemDplClickM: function( me, record, item, index, e, eOpts ){
          console.log('record',record.data,record.get('bunde_unbestaetigt'))  
          var form = this.lookupReference('form');
          var m = this;
          var code = record.get('code');
          if (record.get('bunde_unbestaetigt')*1>-1){
            if (record.get('id').split('.').length==3){
                Ext.MessageBox.prompt(
                    'Bund '+record.get('code')+' für die Maschine bestätigen',
                    'Begründen Sie bitte die manuelle Bestätigung',
                    function(btn,txt){
                        if (btn=='ok'){
                             
                          Tualo.Ajax.request({
                              url: './cmp_pm_bundzettel/scan',
                              params: {
                                  code: code,
                                  note: txt,
                                  scantype: 2,
                                  dodate: Ext.Date.format( m.lookupReference('dodate').getValue(),'Y-m-d')
                              },
                              showWait: true,
                              scope: this,
                              json: function(o){
                                  if (o.success==false){
                                      Ext.toast({
                                          html: o.msg,
                                          title: 'Fehler',
                                          align: 't',
                                          iconCls: 'fa fa-warning'
                                      });
              
                                      form.down().setStyle('background-color','red');
                                      form.getForm().setValues(
                                      {
                                          scaned_code: code,
                                          "dateiname": "---",
                                          "auftrag": "---",
                                          "einspeiserid": "---",
                                          "einspeisername": "---",
                                          "aktionsnr_vkz": "---",
                                          "anlieferungfruehestens": "---",
                                          "anlieferungspaetestens": "---",
                                          "zustellungfruehestens": "---",
                                          "zustellungspaetestens": "---",
                                          "produkt": "---",
                                          "suchfeld": "--",
                                          "belegnummer": "---",
                                          "tabellenzusatz": "---",
                                          "c": "---",
                                          "gewicht": "---",
                                          "itemgewicht": "---",
                                          "itemformat": "---",
                                          "dateiname": "---",
                                          "bunde": "---"
                                      }
                                      );
                                      
                                      
                                  }else{
                                      console.log(o);
                                      form.down().setStyle('background-color','');
                                      form.getForm().setValues({ scaned_code:  code });
                                      form.getForm().setValues(o.details);
                                      form.getForm().setValues(o.auftrag);
                                      
                                  }
                              }
                            });
                        }
                    },
                    false,
                    ''
                );
            }
          }
        },

    exportTo: function(btn) {
        var cfg = Ext.merge({
            title: 'Export',
            fileName: 'Export' + '.' + (btn.cfg.ext || btn.cfg.type)
        }, btn.cfg);

        this.lookupReference('tree').saveDocumentAs(cfg);
    },

    onBeforeDocumentSave: function(view) {
        view.mask({
            xtype: 'loadmask',
            message: 'Document is prepared for export. Please wait ...'
        });
    },

    onDocumentSave: function(view) {
        view.unmask();
    },

    onCodeKeyCode: function(elm,evt){
        if (evt.getKey()==13){
            Tualo.Ajax.request({
                url: './cmp_pm_bundzettel/scan',
                params: {
                    code: elm.getValue(),
                    dodate: Ext.Date.format( this.lookupReference('dodate').getValue(),'Y-m-d')
                },
                showWait: true,
                scope: this,
                json: function(o){
                    window.elm=elm;
                    if (o.success==false){
                        Ext.toast({
                            html: o.msg,
                            title: 'Fehler',
                            align: 't',
                            iconCls: 'fa fa-warning'
                        });

                        elm.up('form').down().setStyle('background-color','red');
                        elm.up('form').getForm().setValues(
                        {
                            scaned_code: elm.getValue(),
                            "dateiname": "---",
                            "auftrag": "---",
                            "einspeiserid": "---",
                            "einspeisername": "---",
                            "aktionsnr_vkz": "---",
                            "anlieferungfruehestens": "---",
                            "anlieferungspaetestens": "---",
                            "zustellungfruehestens": "---",
                            "zustellungspaetestens": "---",
                            "produkt": "---",
                            "suchfeld": "--",
                            "belegnummer": "---",
                            "tabellenzusatz": "---",
                            "c": "---",
                            "gewicht": "---",
                            "itemgewicht": "---",
                            "itemformat": "---",
                            "dateiname": "---",
                            "bunde": "---"
                        }
                        );
                        elm.setValue("");
                        
                    }else{
                        console.log(o);
                        elm.up('form').down().setStyle('background-color','');
                        elm.up('form').getForm().setValues({ scaned_code: elm.getValue() });
                        elm.up('form').getForm().setValues(o.details);
                        elm.up('form').getForm().setValues(o.auftrag);
                        
                        elm.setValue("");
                    }
                }
              });
        }
    },



    onCodeKeyCodeM: function(elm,evt){
        if (evt.getKey()==13){
            Tualo.Ajax.request({
                url: './cmp_pm_bundzettel/scan',
                params: {
                    code: elm.getValue(),
                    scantype: 2,
                    dodate: Ext.Date.format( this.lookupReference('dodate').getValue(),'Y-m-d')
                },
                showWait: true,
                scope: this,
                json: function(o){
                    window.elm=elm;
                    if (o.success==false){
                        Ext.toast({
                            html: o.msg,
                            title: 'Fehler',
                            align: 't',
                            iconCls: 'fa fa-warning'
                        });

                        elm.up('form').down().setStyle('background-color','red');
                        elm.up('form').getForm().setValues(
                        {
                            scaned_code: elm.getValue(),
                            "dateiname": "---",
                            "auftrag": "---",
                            "einspeiserid": "---",
                            "einspeisername": "---",
                            "aktionsnr_vkz": "---",
                            "anlieferungfruehestens": "---",
                            "anlieferungspaetestens": "---",
                            "zustellungfruehestens": "---",
                            "zustellungspaetestens": "---",
                            "produkt": "---",
                            "suchfeld": "--",
                            "belegnummer": "---",
                            "tabellenzusatz": "---",
                            "c": "---",
                            "gewicht": "---",
                            "itemgewicht": "---",
                            "itemformat": "---",
                            "dateiname": "---",
                            "bunde": "---"
                        }
                        );
                        elm.setValue("");
                        
                    }else{
                        console.log(o);
                        elm.up('form').down().setStyle('background-color','orange');
                        elm.up('form').getForm().setValues({ scaned_code: elm.getValue() });
                        elm.up('form').getForm().setValues(o.details);
                        elm.up('form').getForm().setValues(o.auftrag);
                        
                        elm.setValue("");
                    }
                }
              });
        }
    }
});