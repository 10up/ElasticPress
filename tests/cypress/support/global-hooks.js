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
		cy.wpCli('eval "echo ElasticPress\\Utils\\get_host();"').then((epHost) => {
			// Nothing needs to be done if EP.io.
			if (!epHost.stdout.match(/elasticpress\.io/)) {
				cy.wpCli(
					'eval "echo \\ElasticPress\\Indexables::factory()->get( \'post\' )->get_index_name();"',
				).then((indexName) => {
					const publicEpHost = epHost.stdout.replace('172.17.0.1', 'localhost');
					cy.updateFeatures({
						autosuggest: {
							active: 1,
							endpoint_url: `${publicEpHost}${indexName.stdout}/_search`,
						},
					});
				});
			} else {
				cy.updateFeatures();
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
