Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
var getvars = getUrlVars();




// We need to use jQuery load function to make sure that document.namespaces are ready. Only IE
$(window).load(function() {
	
	var fieldsForStore;
	var columnsForGrid;
	var base = [];


		OpenLayers.Layer.Vector.prototype.renderers = ["SVG2","Canvas","VML"];
		$.ajax( {
			url : '/controller/tables/' + screenName + '/getcolumns/'
					+ getvars['layer'],
			async : false,
			dataType : 'json',
			type : 'GET',
			success : function(data, textStatus, http) {
				if (http.readyState == 4) {
					if (http.status == 200) {
						var response = eval('(' + http.responseText + ')'); // JSON
			fieldsForStore = response.forStore;
			columnsForGrid = response.forGrid;
			//alert(response.type);
			type = response.type;
			multi = response.multi;
			// We add an editor to the fields
			for ( var i in columnsForGrid) {
				
				// alert(columnsForGrid[i].header+"
			// "+columnsForGrid[i].typeObj.type);
			if (columnsForGrid[i].typeObj !== undefined) {
				if (columnsForGrid[i].typeObj.type == "int") {
					columnsForGrid[i].editor = new Ext.form.NumberField( {
						decimalPrecision : 0,
						decimalSeparator : '¤'// Some strange char nobody is
												// using
					});
				} else if (columnsForGrid[i].typeObj.type == "decimal") {
					columnsForGrid[i].editor = new Ext.form.NumberField( {
						decimalPrecision : columnsForGrid[i].typeObj.scale,
						decimalSeparator : '.'
					// maxLength: columnsForGrid[i].type.precision
							});
				} else if (columnsForGrid[i].typeObj.type == "string") {
					columnsForGrid[i].editor = new Ext.form.TextField();
				}
			}
		}
		;
	}
}

}
		});
		function cgMap(){
		var omap = new OpenLayers.Map("mapel", {
			controls : [ new OpenLayers.Control.Navigation(),
					new OpenLayers.Control.PanZoomBar(),
					new OpenLayers.Control.LayerSwitcher() /*,
					new OpenLayers.Control.TouchNavigation( {
						dragPanOptions : {
							interval : 100
						}
					})*/ ],
			'numZoomLevels' : 20,
			'projection' : new OpenLayers.Projection("EPSG:900913"),
			'maxResolution' : 156543.0339,
			'units' : "m"
		});
		return omap;
		}
		
		var map = new cgMap();
	
		
		var featurecollection = {
				"type": "FeatureCollection",
				"features": [
                {"geometry": {
                    "type": "GeometryCollection", 
                    "geometries": [
                        {
                            "type": "LineString", 
                            "coordinates": 
                                [[-20037508, -20037508], 
                                [20037508, 20037508]]
                        }
                    ]
                }, 
                "type": "Feature", 
                "properties": { "id": 1, "name": "Albertslund ruten" }}
              ]
				};
		var geojson_format = new OpenLayers.Format.GeoJSON();
        var layer = new OpenLayers.Layer.Vector(); 
        layer.addFeatures(geojson_format.read(featurecollection));
	
		var extent = new OpenLayers.Bounds(-20037508, -20037508, 20037508,20037508);
		map.maxExtent = extent;
		base.push(new OpenLayers.Layer.Google("Google Hybrid", {
			type : G_HYBRID_MAP,
			sphericalMercator : true
		}));

		map.addLayers(base);
		var store = new GeoExt.data.FeatureStore( {
			fields : fieldsForStore,
			layer : layer,
		});
		
		function cgMapGrid(ostore){
		var ogrid = new Ext.grid.GridPanel( {
			id : "gridpanel",
			viewConfig : {
				forceFit : true
			},
			store : ostore,
			sm : new GeoExt.grid.FeatureSelectionModel( {
				singleSelect : false
			}),
			cm : new Ext.grid.ColumnModel( {
				defaults : {
					sortable : true,
					editor : {
						xtype : "textfield"
					}
				},
				columns : columnsForGrid
			})
		});
		var panel = new Ext.Panel( {
			//layout : 'border',
			renderTo:"mb7",
			width:600,
			height:400,
			items : [ 
			{
				region : "center",
				id : "mappanel",
				//title : "Map",
				xtype : "gx_mappanel",
				map : map,
				// extent:extent,
				zoom : 5,
				split : true,
				layout : 'fit',
				height : 300,
				layers : [ ostore.layer ]
			}, {
				region : "south",
				title: "Attribut table",
				split : true,
				frame : false,
				layout : 'fit',
				collapsible: true,
				collapsed: false,
				height : 300,
				items:[ogrid]
			} ]
		});

	
		
		}
		var grid = new cgMapGrid(store);
	});	
		
function getUrlVars() {
	var mapvars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,
			function(m, key, value) {
				mapvars[key] = value;
			});
	return mapvars;
}