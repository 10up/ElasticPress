before(() => {
	const features = {
		search: {
			active: 1,
			highlight_enabled: true,
			highlight_excerpt: true,
			highlight_tag: 'mark',
			highlight_color: '#157d84',
		},
		related_posts: {
			active: 1,
		},
		facets: {
			active: 1,
		},
		searchordering: {
			active: 1,
		},
		autosuggest: {
			active: 1,
		},
		woocommerce: {
			active: 0,
		},
		protected_content: {
			active: 0,
		},
		users: {
			active: 1,
		},
	};

	const updateFeatures = (features) => {
		const escapedFeatures = JSON.stringify(features).replace(/"/g, '"').replace(/'/g, "'");
		cy.wpCli(`eval "update_option( 'ep_feature_settings', '${escapedFeatures}' );"`);
	};

	cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
		// Nothing needs to be done if EP.io.
		if (!epHost.stdout.match(/elasticpress\.io/)) {
			cy.wpCli(
				'eval "echo \\ElasticPress\\Indexables::factory()->get( \'post\' )->get_index_name();"',
			).then((indexName) => {
				features.autosuggest.endpoint_url = `${epHost.stdout}/${indexName.stdout}/_search`;
				updateFeatures(features);
			});
		} else {
			updateFeatures(features);
		}
	});
});
