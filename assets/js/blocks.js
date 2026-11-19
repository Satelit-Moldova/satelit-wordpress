/* Satelit blocks: server-side rendered, one text field per attribute. No build step. */
( function ( wp, cfg ) {
	if ( ! wp || ! wp.blocks || ! cfg ) {
		return;
	}
	var el = wp.element.createElement;
	var be = wp.blockEditor || wp.editor;
	var SSR = wp.serverSideRender || ( wp.components && wp.components.ServerSideRender ) || ( wp.editor && wp.editor.ServerSideRender );
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;

	Object.keys( cfg.blocks ).forEach( function ( feature ) {
		var def = cfg.blocks[ feature ];
		var names = Object.keys( def.atts || {} );
		var attributes = {};
		names.forEach( function ( n ) {
			attributes[ n ] = { type: 'string', default: def.atts[ n ] };
		} );

		wp.blocks.registerBlockType( 'satelit/' + feature, {
			title: def.title,
			icon: def.icon,
			category: 'widgets',
			keywords: [ 'satelit', 'moldova', feature ],
			attributes: attributes,
			supports: { html: false },
			edit: function ( props ) {
				var fields = names.map( function ( n ) {
					return el( TextControl, {
						key: n,
						label: cfg.fields[ n ] || n,
						value: props.attributes[ n ],
						onChange: function ( v ) {
							var o = {};
							o[ n ] = v;
							props.setAttributes( o );
						}
					} );
				} );
				return [
					names.length && be && be.InspectorControls
						? el( be.InspectorControls, { key: 'i' }, el( PanelBody, { title: cfg.panel }, fields ) )
						: null,
					SSR
						? el( SSR, { key: 'r', block: 'satelit/' + feature, attributes: props.attributes } )
						: el( 'p', { key: 'r' }, def.title )
				];
			},
			save: function () {
				return null;
			}
		} );
	} );
}( window.wp, window.satelitMdBlocks ) );
