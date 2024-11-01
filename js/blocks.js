var __ = wp.i18n.__;

var el = wp.element.createElement,
    registerBlockType = wp.blocks.registerBlockType,
    ServerSideRender = wp.components.ServerSideRender,
    SelectControl = wp.components.SelectControl,
    TextControl = wp.components.TextControl,
    blockStyle = { backgroundColor: '#900', color: '#fff', padding: '20px' };
    
var {
	PluginSidebar,
	PluginSidebarMoreMenuItem,
	PluginPostStatusInfo,
	Panel,
	PanelBody,
	PanelRow,
	InspectorControls,
	BlockControls,
	TextareaControl,
	RichText
} = wp.editor;	

var { Fragment } = wp.element;

/* Custom plugin icon */
const customIcon = el('svg', 
{ 
	width: 20, 
	height: 20,
	viewBox: "0 0 120 120",
	class: "dashicon dashicons-admin-generic",
	xmlns: "http://www.w3.org/2000/svg"
},
el( 'path', { d: 'M5 30 A5,5 0 0 1 10,25 H 85 A5,5 0 0 1 90,30 V 115 A5,5 0 0 1 85,120 H 10 A5,5 0 0 1 5,115 Z', fill: 'white',	stroke: 'black', strokeWidth: '6'}),
el( 'path', { d: 'M25 10 A5,5 0 0 1 30,5 H 110 A5,5 0 0 1 115,10 V 95 A5,5 0 0 1 110,100 H 30 A5,5 0 0 1 25,95 Z', fill: 'white',	stroke: 'black', strokeWidth: '6'}),
el( 'circle', { cx: '95', cy: '25', r: 7, fill: 'black',	stroke: 'black'}),
el( 'circle', { cx: '70', cy: '52.5', r: '20', fill: 'white',	stroke: 'black', strokeWidth: '6'}),
);

/* Registering block type */
registerBlockType( 'social-photo-blocks/social-photo-grid', {
    title: __('Social Photo Grid', 'social-photo-block'),
    icon: customIcon,
    category: 'widgets',
    
    /* Attributes used by block */
    attributes: {
		cols: {
			type: 'string',
			default: "3",
		},
		rows: {
			type: 'string',
			default: "3",
		},
		width: {
			type: 'string',
			default: "100%",
		},
		align: {
			type: 'string',
			default: "center",
		},
	},
	
	/* Block interface - editor side */
    edit: function(props) {
		return [el('div', {},
			el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes
			})), 
			el(InspectorControls, {key: 'inspector'},
				el(SelectControl, {
					label: __('Align', 'social-photo-block'), 
					id: 'insta_grid_align',
					name: "align",
					defaultValue: props.attributes.align, 
					options: [
						{ label: __('Left', 'social-photo-block'), value: 'left'},
						{ label: __('Center', 'social-photo-block'), value: 'center'},
						{ label: __('Right', 'social-photo-block'), value: 'right'},
					],
					onChange: (value)=>{ if(value!='') props.setAttributes({align: value}); }
				}),
				el(TextControl,
                    {
                        label: __('Width', 'social-photo-block'), 
                        id: 'insta_grid_width',
                        name: "width",
                        defaultValue: props.attributes.width,
                        placeholder: 'Ex: 100%',
                        onChange: function (value) {
                            if(value!='') props.setAttributes({width: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Rows', 'social-photo-block'), 
                        id: 'insta_grid_rows',
                        name: "rows",
                        defaultValue: props.attributes.rows,
                        onChange: function (value) {
                            if(value!='') props.setAttributes({rows: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Cols', 'social-photo-block'), 
                        id: 'insta_grid_cols',
                        name: "cols",
                        defaultValue: props.attributes.cols,
                        onChange: function (value) {
                        	if(value!='') props.setAttributes({cols: value});
                        }
                    }
				),
			)];
    },

	/* Block visualization for the front-end */
    save: function(props) {
    	return [el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes
			})];
    },
} );


/* Registering block type */
registerBlockType( 'social-photo-blocks/social-photo-slider', {
    title: __('Social Photo Slider', 'social-photo-slider'),
    icon: customIcon,
    category: 'widgets',
    
    /* Attributes used by block */
    attributes: {
		loop: {
			type: 'bool',
			default: true,
		},
		autostart: {
			type: 'bool',
			default: true,
		},
		height: {
			type: 'string',
			default: "400px",
		},
		width: {
			type: 'string',
			default: "100%",
		},
		delay: {
			type: 'integer',
			default: 5,
		},
		total: {
			type: 'integer',
			default: 10,
		},
		align: {
			type: 'string',
			default: "center",
		},
	},
	
	/* Block interface - editor side */
    edit: function(props) {
		return [el('div', {},
			el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes
			})), 
			el(InspectorControls, {key: 'inspector'},
				el(SelectControl, {
					label: __('Align', 'social-photo-block'), 
					id: 'insta_grid_align',
					name: "align",
					defaultValue: props.attributes.align, 
					options: [
						{ label: __('Left', 'social-photo-block'), value: 'left'},
						{ label: __('Center', 'social-photo-block'), value: 'center'},
						{ label: __('Right', 'social-photo-block'), value: 'right'},
					],
					onChange: (value)=>{ if(value!='') props.setAttributes({align: value}); }
				}),
				el(SelectControl, {
					label: __('Autostart', 'social-photo-block'), 
					id: 'insta_grid_autostart',
					name: "autostart",
					defaultValue: props.attributes.autostart, 
					options: [
						{ label: __('On', 'social-photo-block'), value: '1'},
						{ label: __('Off', 'social-photo-block'), value: '0'},
					],
					onChange: (value)=>{ if(value!='') props.setAttributes({autostart: value}); }
				}),
				el(SelectControl, {
					label: __('Loop', 'social-photo-block'), 
					id: 'insta_grid_loop',
					name: "loop",
					defaultValue: props.attributes.loop, 
					options: [
						{ label: __('On', 'social-photo-block'), value: '1'},
						{ label: __('Off', 'social-photo-block'), value: '0'},
					],
					onChange: (value)=>{ if(value!='') props.setAttributes({loop: value}); }
				}),
				el(TextControl,
                    {
                        label: __('Width', 'social-photo-block'), 
                        id: 'insta_grid_width',
                        name: "width",
                        defaultValue: props.attributes.width,
                        placeholder: 'Ex: 100%',
                        onChange: function (value) {
                            if(value!='') props.setAttributes({width: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Height', 'social-photo-block'), 
                        id: 'insta_grid_height',
                        name: "height",
						defaultValue: props.attributes.height,
						placeholder: 'Ex: 400px',
                        onChange: function (value) {
                            if(value!='') props.setAttributes({height: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Sliding delay', 'social-photo-block'), 
                        id: 'insta_grid_delay',
                        name: "delay",
                        defaultValue: props.attributes.delay,
                        onChange: function (value) {
                        	if(value!='') props.setAttributes({delay: value});
                        }
                    }
				),
				el(TextControl,
                    {
                        label: __('Total slides', 'social-photo-block'), 
                        id: 'insta_grid_total',
                        name: "total",
                        defaultValue: props.attributes.total,
                        onChange: function (value) {
                        	if(value!='') props.setAttributes({total: value});
                        }
                    }
				),
			)];
    },

	/* Block visualization for the front-end */
    save: function(props) {
    	return [el(ServerSideRender, {
				block: props.name,
				attributes: props.attributes
			})];
    },
} );