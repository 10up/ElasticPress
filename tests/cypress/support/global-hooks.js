window.indexNames = null;

let setFeatures = false;

before(() => {
	// Clear sync from previous tests.
	cy.wpCli(
		'wp eval \'delete_transient( "ep_wpcli_sync" ); delete_option( "ep_index_meta" ); delete_site_transient( "ep_wpcli_sync" ); delete_site_option( "ep_index_meta" );\'',
	);

	if (!window.indexNames) {
		cy.wpCli('wp elasticpress get-indexes').then((wpCliResponse) => {
			window.indexNames = JSON.parse(wpCliResponse.stdout);
		});
	}

	if (!setFeatures) {
		cy.wpCliEval(
			`if ( \\ElasticPress\\Utils\\is_epio() ) {
				exit;
			}
			$host       = \\ElasticPress\\Utils\\get_host();
			$host       = str_replace( '172.17.0.1', 'localhost', $host );
			$index_name = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			echo $host . $index_name . '/_search';
			`,
		).then((searchEndpointUrl) => {
			if (searchEndpointUrl.stdout === '') {
				cy.updateFeatures();
			} else {
				cy.updateFeatures({
					autosuggest: {
						active: 1,
						endpoint_url: searchEndpointUrl.stdout,
					},
				});
			}
		});
		setFeatures = true;
	}
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
