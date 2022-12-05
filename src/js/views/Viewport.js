Ext.define('Ext.cmp.cmp_dsx.Viewport', {
    extend: 'Ext.tab.Panel',
    requires: [
      'Ext.cmp.cmp_dsx.controller.Viewport',
      'Ext.cmp.cmp_dsx.models.Viewport'
    ],

    controller: 'cmp_dsx_viewport',
    viewModel: {
      type: 'cmp_dsx_viewport'
    },
    listeners:{
      boxReady: 'onBoxReady'
    },

    tbar:[
        {
            xtype: 'label',
            html: 'geplante Zustellung am'
        },
        {
            xtype: 'datefield',
            reference: 'dodate',
            value: new Date( (new Date()).getTime() + (24 * 60 * 60 * 1000)  )
        }
    ],
    //xtype: 'tabpanel',
    items: [
        {
            title: 'Bundzettel',
            layout: 'card',
            items: [{
                xtype: 'form',
                reference: 'form',
                /*
                layout: {
                    type: 'vbox',
                    align: 'center',
                    pack: 'center'
                },
                */
                items: [
                    {
                        xtype: 'fieldset',
                        items: [
                            {
                                xtype: 'label',
                                anchor: '100%',
                                html: 'Bitte scannen Sie einen Bundzettel'
                            },

                            {
                                xtype: 'textfield',
                                anchor: '100%',
                                name: 'code',
                                reference: 'code',
                                enableKeyEvents: true,
                                listeners:{
                                    keydown: 'onCodeKeyCode'
                                }
                            }
                        ]
                    },
                    {
                        xtype: 'panel',
                        layout: 'hbox',
                        items: [
                            {
                                flex: 1,
                                xtype: 'fieldset',
                                title: 'Auftrag',
                                items: [
                                    {
                                        fieldLabel: 'Code',
                                        xtype: 'displayfield',
                                        name: 'scaned_code',
                                        
                                    },
                
                                    {
                                        fieldLabel: 'Auftrag',
                                        xtype: 'displayfield',
                                        name: 'auftrag',
                                        
                                    },
                                    {
                                        fieldLabel: 'Aktion',
                                        xtype: 'displayfield',
                                        name: 'aktionsnr_vkz',
                                        
                                    },
                                    {
                                        fieldLabel: 'Partnerprodukt',
                                        xtype: 'displayfield',
                                        name: 'produkt',
                                        
                                    },
                                    {
                                        fieldLabel: 'Einspeiser',
                                        xtype: 'displayfield',
                                        name: 'einspeisername',
                                        
                                    },
                                    {
                                        fieldLabel: 'Datei',
                                        xtype: 'displayfield',
                                        name: 'dateiname',
                                        
                                    }
                                ]
                            },
                
                            {
                                xtype: 'fieldset',
                                title: 'Bund',
                                flex: 1,
                                items: [
                                    {
                                        fieldLabel: 'Anzahl (Sendungen)',
                                        xtype: 'displayfield',
                                        name: 'c',
                                        
                                    },
                                    {
                                        fieldLabel: 'Gewicht je Sendung',
                                        xtype: 'displayfield',
                                        name: 'itemgewicht',
                                        
                                    },
                                    {
                                        fieldLabel: 'Format',
                                        xtype: 'displayfield',
                                        name: 'itemformat',
                                        
                                    },
                                    {
                                        fieldLabel: 'Anzahl (Bunde)',
                                        xtype: 'displayfield',
                                        name: 'bunde',
                                        
                                    }
                                ]
                            },
                        ]
                    },

                    
                    {
                        xtype: 'fieldset',
                        title: 'Planzeiten',
                        items:[
                            {
                                xtype: 'fieldcontainer',
                                fieldLabel: 'Anlieferung',
                                layout: 'hbox',
                                items:[
                                    {
                                        xtype: 'displayfield',
                                        name: 'anlieferungfruehestens',
                                        flex: 1
                                    },
                                    {
                                        xtype: 'displayfield',
                                        name: 'anlieferungspaetestens',
                                        flex: 1
                                    }
                                ]
                            },

                            {
                                xtype: 'fieldcontainer',
                                fieldLabel: 'Zustellung',
                                layout: 'hbox',
                                items:[
                                    {
                                        xtype: 'displayfield',
                                        name: 'zustellungfruehestens',
                                        flex: 1
                                    },
                                    {
                                        xtype: 'displayfield',
                                        name: 'zustellungspaetestens',
                                        flex: 1
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }]
        },

        {
            title: 'Suche',
            layout: 'fit',
            /*

            xtype: 'panel',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            */
            items: [
                {
                    xtype: 'panel',
                    layout: 'fit',
                    scrollable: true,
                    header: {
                        itemPosition: 1, // after title before collapse tool
                        items: [
                            {
                                glyph: 'xf021@FontAwesome', // Reply icon
                                callback: 'onReloadTreeClicked'
                            },

                            { xtype: 'component',width: 15 },
                            {
                                ui: 'default-toolbar',
                                xtype: 'button',
                                cls: 'dock-tab-btn',
                                text: 'Export to ...',
                                menu: {
                                    defaults: {
                                        handler: 'exportTo',
                                        width: 124
                                    },
                                    items: [{
                                        text: 'Excel xlsx',
                                        cfg: {
                                            type: 'excel07',
                                            ext: 'xlsx'
                                        }
                                    }, {
                                        text: 'Excel xml',
                                        cfg: {
                                            type: 'excel03',
                                            ext: 'xml'
                                        }
                                    }, {
                                        text: 'CSV',
                                        cfg: {
                                            type: 'csv'
                                        }
                                    }, {
                                        text: 'TSV',
                                        cfg: {
                                            type: 'tsv',
                                            ext: 'csv'
                                        }
                                    }, {
                                        text: 'HTML',
                                        cfg: {
                                            type: 'html'
                                        }
                                    }]
                                }
                            }
                        ]
                    },
                

                    items: [
                        {
        
                            xtype: 'treepanel',
                            reference: 'tree',
                            reserveScrollbar: true,
                            plugins: 'gridexporter',
                            tbar: [{
                                xtype: 'label',
                                html: 'von Tagen zurück',hidden: true
                            },{
                                xtype: 'numberfield',
                                reference: 'days_back',
                                value: 1,hidden: true
                            }, '-',{
                                xtype: 'label',
                                html: 'bis Tage voraus',hidden: true
                            },  {
                                xtype: 'numberfield',
                                reference: 'days_forward',
                                value: 1,hidden: true
                            }],
                
                            listeners: {
                                itemclick: function(n,ev) {
                                    console.log('tree click',arguments);
                                    if (n.leaf){
                                    }
                                },
                                itemdblclick: 'onTreeItemDplClick' 
                                
                            },
                            viewConfig: {
                                getRowClass: function(record, rowIndex, rowParams, store) {
                                    var cls = "row-color-gray98";
                                    if ((record.get("bunde_unbestaetigt")*1>0) && (record.get("bunde_bestaetigt")*1==0) ) {
                                        cls="row-color-yellow2";
                                    }else if ((record.get("bunde_bestaetigt")*1==0)) {
                                        cls = "row-color-seagreen1";
                                    }else if ( (record.get("bunde_unbestaetigt")*1>0) && (record.get("bunde_bestaetigt")*1>0) ){
                                        cls="row-color-orangered2";
                                    }
                                    return  cls;//(record.get("bunde_bestaetigt")*1>0)?"":;
                                }
                            },
                            bind: {
                                store: '{searchtree}'
                            },
            
                            columns: [{
                                    xtype: 'treecolumn', // this is so we know which column will show the tree
                                    text: 'Baum',
                                    dataIndex: 'text',
                                    flex: 2,
                                    sortable: true
                                }, {
                                    text: 'Aufträge',
                                    dataIndex: 'auftraege',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Aktion',
                                    dataIndex: 'aktionskennung',
                                    flex: 1,
                                    sortable: true,
                                    align: 'left',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Produkt',
                                    dataIndex: 'produkt',
                                    flex: 1,
                                    sortable: true,
                                    hidden: true,
                                    align: 'left',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'PLZs',
                                    dataIndex: 'plzs',
                                    flex: 1,
                                    sortable: true,
                                    hidden: true,
                                    align: 'left',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'gepl. Zustellung',
                                    dataIndex: 'zustellungfruehestens',
                                    flex: 1,
                                    sortable: true,
                                    align: 'center',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'best. Bunde',
                                    dataIndex: 'bunde_bestaetigt',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    xtype: 'numbercolumn'
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'unbest. Bunde',
                                    dataIndex: 'bunde_unbestaetigt',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    xtype: 'numbercolumn',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Sendungen',
                                    dataIndex: 'sendungen',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Gewicht',
                                    dataIndex: 'gewicht_kg',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    //formatter: 'this.formatHours'
                                },
                                
                            ]
                
                        }
                    ]
                }
            ]
        },


        {
            title: 'maschinelle Erfassung',
            layout: 'card',
            items: [{
                xtype: 'form',
                
                reference: 'formm',
                /*
                layout: {
                    type: 'vbox',
                    align: 'center',
                    pack: 'center'
                },
                */
                items: [
                    {
                        xtype: 'fieldset',
                        items: [
                            {
                                style:{"background-color":"orange"}, 
                                xtype: 'label',
                                anchor: '100%',
                                html: 'Bitte scannen Sie einen Bundzettel'
                            },

                            {
                                xtype: 'textfield',
                                anchor: '100%',
                                name: 'code',
                                reference: 'codem',
                                enableKeyEvents: true,
                                listeners:{
                                    keydown: 'onCodeKeyCodeM'
                                }
                            }
                        ]
                    },
                    {
                        xtype: 'panel',
                        layout: 'hbox',
                        items: [
                            {
                                flex: 1,
                                xtype: 'fieldset',
                                title: 'Auftrag',
                                items: [
                                    {
                                        fieldLabel: 'Code',
                                        xtype: 'displayfield',
                                        name: 'scaned_code',
                                        
                                    },
                
                                    {
                                        fieldLabel: 'Auftrag',
                                        xtype: 'displayfield',
                                        name: 'auftrag',
                                        
                                    },
                                    {
                                        fieldLabel: 'Aktion',
                                        xtype: 'displayfield',
                                        name: 'aktionsnr_vkz',
                                        
                                    },
                                    {
                                        fieldLabel: 'Partnerprodukt',
                                        xtype: 'displayfield',
                                        name: 'produkt',
                                        
                                    },
                                    {
                                        fieldLabel: 'Einspeiser',
                                        xtype: 'displayfield',
                                        name: 'einspeisername',
                                        
                                    },
                                    {
                                        fieldLabel: 'Datei',
                                        xtype: 'displayfield',
                                        name: 'dateiname',
                                        
                                    }
                                ]
                            },
                
                            {
                                xtype: 'fieldset',
                                title: 'Bund',
                                flex: 1,
                                items: [
                                    {
                                        fieldLabel: 'Anzahl (Sendungen)',
                                        xtype: 'displayfield',
                                        name: 'c',
                                        
                                    },
                                    {
                                        fieldLabel: 'Gewicht je Sendung',
                                        xtype: 'displayfield',
                                        name: 'itemgewicht',
                                        
                                    },
                                    {
                                        fieldLabel: 'Format',
                                        xtype: 'displayfield',
                                        name: 'itemformat',
                                        
                                    },
                                    {
                                        fieldLabel: 'Anzahl (Bunde)',
                                        xtype: 'displayfield',
                                        name: 'bunde',
                                        
                                    }
                                ]
                            },
                        ]
                    },

                    
                    {
                        xtype: 'fieldset',
                        title: 'Planzeiten',
                        items:[
                            {
                                xtype: 'fieldcontainer',
                                fieldLabel: 'Anlieferung',
                                layout: 'hbox',
                                items:[
                                    {
                                        xtype: 'displayfield',
                                        name: 'anlieferungfruehestens',
                                        flex: 1
                                    },
                                    {
                                        xtype: 'displayfield',
                                        name: 'anlieferungspaetestens',
                                        flex: 1
                                    }
                                ]
                            },

                            {
                                xtype: 'fieldcontainer',
                                fieldLabel: 'Zustellung',
                                layout: 'hbox',
                                items:[
                                    {
                                        xtype: 'displayfield',
                                        name: 'zustellungfruehestens',
                                        flex: 1
                                    },
                                    {
                                        xtype: 'displayfield',
                                        name: 'zustellungspaetestens',
                                        flex: 1
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }]
        },

        {
            title: 'Suche (maschinelle Sort.)',
            layout: 'fit',
            /*

            xtype: 'panel',
            layout: {
                type: 'hbox',
                align: 'stretch'
            },
            */
            items: [
                {
                    xtype: 'panel',
                    layout: 'fit',
                    scrollable: true,
                    header: {
                        itemPosition: 1, // after title before collapse tool
                        items: [
                            {
                                glyph: 'xf021@FontAwesome', // Reply icon
                                callback: 'onReloadTreeClicked'
                            },

                            { xtype: 'component',width: 15 },
                            {
                                ui: 'default-toolbar',
                                xtype: 'button',
                                cls: 'dock-tab-btn',
                                text: 'Export to ...',
                                menu: {
                                    defaults: {
                                        handler: 'exportTo',
                                        width: 124
                                    },
                                    items: [{
                                        text: 'Excel xlsx',
                                        cfg: {
                                            type: 'excel07',
                                            ext: 'xlsx'
                                        }
                                    }, {
                                        text: 'Excel xml',
                                        cfg: {
                                            type: 'excel03',
                                            ext: 'xml'
                                        }
                                    }, {
                                        text: 'CSV',
                                        cfg: {
                                            type: 'csv'
                                        }
                                    }, {
                                        text: 'TSV',
                                        cfg: {
                                            type: 'tsv',
                                            ext: 'csv'
                                        }
                                    }, {
                                        text: 'HTML',
                                        cfg: {
                                            type: 'html'
                                        }
                                    }]
                                }
                            }
                        ]
                    },
                

                    items: [
                        {
        
                            xtype: 'treepanel',
                            reference: 'treem',
                            reserveScrollbar: true,
                            plugins: 'gridexporter',
                            tbar: [{
                                xtype: 'label',
                                html: 'von Tagen zurück',hidden: true
                            },{
                                xtype: 'numberfield',
                                reference: 'days_back',
                                value: 1,hidden: true
                            }, '-',{
                                xtype: 'label',
                                html: 'bis Tage voraus',hidden: true
                            },  {
                                xtype: 'numberfield',
                                reference: 'days_forward',
                                value: 1,hidden: true
                            }
                            ],
                
                            listeners: {
                                itemclick: function(n,ev) {
                                    console.log('tree click',arguments);
                                    if (n.leaf){
                                    }
                                },
                                itemdblclick: 'onTreeItemDplClickM' 
                                
                            },
                            viewConfig: {
                                getRowClass: function(record, rowIndex, rowParams, store) {
                                    var cls = "row-color-gray98";
                                    if ((record.get("bunde_unbestaetigt")*1>0) && (record.get("bunde_bestaetigt")*1==0) ) {
                                        cls="row-color-yellow2";
                                    }else if ((record.get("bunde_bestaetigt")*1==0)) {
                                        cls = "row-color-seagreen1";
                                    }else if ( (record.get("bunde_unbestaetigt")*1>0) && (record.get("bunde_bestaetigt")*1>0) ){
                                        cls="row-color-orangered2";
                                    }
                                    return  cls;//(record.get("bunde_bestaetigt")*1>0)?"":;
                                }
                            },
                            bind: {
                                store: '{searchtree}'
                            },
            
                            columns: [{
                                    xtype: 'treecolumn', // this is so we know which column will show the tree
                                    text: 'Baum',
                                    dataIndex: 'text',
                                    flex: 2,
                                    sortable: true
                                }, {
                                    text: 'Aufträge',
                                    dataIndex: 'auftraege',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Aktion',
                                    dataIndex: 'aktionskennung',
                                    flex: 1,
                                    sortable: true,
                                    align: 'left',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Produkt',
                                    dataIndex: 'produkt',
                                    flex: 1,
                                    sortable: true,
                                    hidden: true,
                                    align: 'left',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'PLZs',
                                    dataIndex: 'plzs',
                                    flex: 1,
                                    sortable: true,
                                    hidden: true,
                                    align: 'left',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'gepl. Zustellung',
                                    dataIndex: 'zustellungfruehestens',
                                    flex: 1,
                                    sortable: true,
                                    align: 'center',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'best. Bunde',
                                    dataIndex: 'bunde_bestaetigt',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    xtype: 'numbercolumn'
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'unbest. Bunde',
                                    dataIndex: 'bunde_unbestaetigt',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    xtype: 'numbercolumn',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Sendungen',
                                    dataIndex: 'sendungen',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    //formatter: 'this.formatHours'
                                }, {
                                    text: 'Gewicht',
                                    dataIndex: 'gewicht_kg',
                                    width: 80,
                                    sortable: true,
                                    align: 'right',
                                    //formatter: 'this.formatHours'
                                },
                                
                            ]
                
                        }
                    ]
                }
            ]
        },
    ]

});