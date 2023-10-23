window.indexNames = null;
window.isEpIo = false;
window.wpVersion = '';

before(() => {
	cy.wpCliEval(
		`
		// Clear any stuck sync process.
		\\ElasticPress\\IndexHelper::factory()->clear_index_meta();

		$features = json_decode( '${JSON.stringify(cy.elasticPress.defaultFeatures)}', true );

		$is_epio = (int) \\ElasticPress\\Utils\\is_epio();

		if ( ! $is_epio ) {
			$host            = \\ElasticPress\\Utils\\get_host();
			$host            = str_replace( '172.17.0.1', 'localhost', $host );
			$host            = str_replace( 'host.docker.internal', 'localhost', $host );
			$index_name      = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$as_endpoint_url = $host . $index_name . '/_search';
			
			$features['autosuggest']['endpoint_url'] = $as_endpoint_url;
		}

		update_option( 'ep_feature_settings', $features );

		$index_names = \\ElasticPress\\Elasticsearch::factory()->get_index_names( 'active' );
		echo wp_json_encode(
			[
				'indexNames' => $index_names,
				'isEpIo'     => $is_epio,
				'wpVersion'  => get_bloginfo( 'version' ),
			]
		);
		`,
	).then((wpCliResponse) => {
		const wpCliRespObj = JSON.parse(wpCliResponse.stdout);
		window.indexNames = wpCliRespObj.indexNames;
		window.isEpIo = wpCliRespObj.isEpIo === 1;
		window.wpVersion = wpCliRespObj.wpVersion;
	});
});

afterEach(() => {
	if (cy.state('test').state === 'failed') {
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-copy-button')
			.invoke('attr', 'data-clipboard-text')
			.then((text) => {
				if (!text) {
					return;
				}
				const parentTitle = cy.state('test').parent.title;
				const testTitle = cy.state('test').title;
				cy.writeFile(`tests/cypress/logs/${parentTitle} - ${testTitle}.log`, text);
			});
	}
});
