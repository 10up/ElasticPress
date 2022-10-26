window.indexNames = null;
window.isEpIo = false;

before(() => {
	cy.wpCliEval(
		`
		// Clear any stuck sync process.
		\\ElasticPress\\IndexHelper::factory()->clear_index_meta();

		$features = json_decode( '${JSON.stringify(cy.elasticPress.defaultFeatures)}', true );
		
		if ( ! \\ElasticPress\\Utils\\is_epio() ) {
			$host            = \\ElasticPress\\Utils\\get_host();
			$host            = str_replace( '172.17.0.1', 'localhost', $host );
			$index_name      = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$as_endpoint_url = $host . $index_name . '/_search';
			
			$features['autosuggest']['endpoint_url'] = $as_endpoint_url;
		}

		update_option( 'ep_feature_settings', $features );

		WP_CLI::runcommand('elasticpress get-indices');
		`,
	).then((wpCliResponse) => {
		window.indexNames = JSON.parse(wpCliResponse.stdout);
	});

	cy.wpCli('eval "echo (int) \\ElasticPress\\Utils\\is_epio();"').then((response) => {
		window.isEpIo = response.stdout === '1';
	});
});

afterEach(() => {
	if (cy.state('test').state === 'failed') {
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress')
			.invoke('text')
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
