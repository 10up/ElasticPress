before(() => {
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
});
