before( () =>  {

	const features = {
		'search': {
			'active'           :  1,
			'highlight_enabled':  true,
			'highlight_excerpt':  true,
			'highlight_tag'    :  'mark',
			'highlight_color'  :  '#157d84',
		},
		'related_posts'     : {
			'active':  1,
		},
		'facets'            : {
			'active':  1,
		},
		'searchordering'    : {
			'active':  1,
		},
		'autosuggest'       : {
			'active':  1,
		},
		'woocommerce'       : {
			'active':  0,
		},
		'protected_content' : {
			'active':  0,
		},
		'users'             : {
			'active':  1,
		},
	}

	const escapedFeatures = JSON.stringify( features ).replace(/"/g, '\"').replace(/'/g, '\'');
	cy.exec( `npm run env run tests-cli "wp eval \\"update_option( 'ep_feature_settings', '${escapedFeatures}' );\\""` );
} );
