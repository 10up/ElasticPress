/* global indexNames */

describe('WP-CLI Commands', () => {
	let indexAllSitesNames = [];

	function checkIfNotMissingIndexes(mode = 'singleSite') {
		cy.login();

		const healthUrl =
			mode === 'network'
				? 'network/admin.php?page=elasticpress-health'
				: 'admin.php?page=elasticpress-health';
		cy.visitAdminPage(healthUrl);
		cy.get('.wrap')
			.invoke('text')
			.then((text) => {
				expect(text).to.not.contain(
					'We could not find any data for your Elasticsearch indices.',
				);
			});
		cy.get('.metabox-holder')
			.invoke('text')
			.then((text) => {
				(mode === 'singleSite' ? indexNames : indexAllSitesNames).forEach((index) => {
					expect(text).to.contains(index);
				});
			});
	}

	context('wp elasticpress sync', () => {
		it('Can index all the posts of the current blog', () => {
			cy.wpCli('wp elasticpress sync')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', 'Number of posts indexed');

			checkIfNotMissingIndexes();
		});

		it('Can clear the index in Elasticsearch, put the mapping again and then index all the posts if user specifies --setup argument', () => {
			cy.wpCli('wp elasticpress sync --setup --yes')
				.its('stdout')
				.should('contain', 'Mapping sent')
				.should('contain', 'Indexing posts')
				.should('contain', 'Number of posts indexed');

			cy.wpCli('wp elasticpress stats')
				.its('stdout')
				.should('contain', 'Documents')
				.should('contain', 'Index Size');

			checkIfNotMissingIndexes();
		});

		it('Can process that many posts in bulk index per round if user specifies --per-page parameter', () => {
			cy.wpCli('wp elasticpress sync --per-page=20')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', '20 of')
				.should('contain', '40 of')
				.should('contain', 'Number of posts indexed');
		});

		it('Can index one post at a time if user specifies --nobulk parameter', () => {
			cy.wpCli('wp elasticpress sync --nobulk')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', '1 of')
				.should('contain', '2 of')
				.should('contain', '3 of')
				.should('contain', '4 of')
				.should('contain', 'Number of posts indexed');
		});

		it('Can skip X posts and index the remaining if user specifies --offset parameter', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress sync --offset=10').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');
				expect(wpCliResponse.stdout).to.contains('Skipping 10');

				const match1 = wpCliResponse.stdout.match(/(\d+) of (?<total>\d+)./);
				const match2 = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);

				expect(10).to.equal(match1.groups.total - match2.groups.indexed);

				expect(wpCliResponse.stdout).to.contains('Number of posts indexed');
			});
		});

		it('Can index all the posts of a type if user specify --post-type parameter', () => {
			let indexPerPostType = 0;
			let indexTotal = 0;

			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress sync --post-type=post').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);
				indexPerPostType = match.groups.indexed;
			});

			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress sync').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);
				indexTotal = match.groups.indexed;

				expect(indexPerPostType).to.not.equal(indexTotal);
			});
		});

		it('Can index without using dynamic bulk requests if user specifies --static-bulk parameter', () => {
			cy.activatePlugin('fake-log-messages');

			cy.wpCli('wp elasticpress sync --static-bulk')
				.its('stdout')
				.should('contain', 'Index command with --static-bulk flag completed')
				.should('contain', 'Done');

			cy.deactivatePlugin('fake-log-messages');
		});
	});

	it('Can delete the index of current blog if user runs wp elasticpress delete-index', () => {
		cy.wpCli('wp elasticpress delete-index --yes')
			.its('stdout')
			.should('contain', 'Index deleted');

		cy.wpCli('wp elasticpress stats', true)
			.its('stderr')
			.should('contain', 'is not currently indexed');

		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);
	});

	it('Can put mapping of the current blog if user runs wp elasticpress put-mapping', () => {
		cy.wpCli('wp elasticpress put-mapping')
			.its('stdout')
			.should('contain', 'Adding post mapping')
			.should('contain', 'Mapping sent');
	});

	it('Can recreate the alias index which points to every index in the network if user runs wp elasticpress recreate-network-alias command', () => {});

	it('Can throw an error while running wp elasticpress recreate-network-alias if the plugin is not network activated', () => {
		cy.wpCli('wp elasticpress recreate-network-alias', true)
			.its('stderr')
			.should('contain', 'ElasticPress is not network activated');
	});

	it('Can activate and deactivate a feature', () => {
		cy.wpCli('wp elasticpress activate-feature search', true)
			.its('stderr')
			.should('contain', 'This feature is already active');

		cy.wpCli('wp elasticpress deactivate-feature search')
			.its('stdout')
			.should('contain', 'Feature deactivated');

		cy.wpCli('wp elasticpress deactivate-feature search', true)
			.its('stderr')
			.should('contain', 'Feature is not active');

		cy.wpCli('wp elasticpress activate-feature search')
			.its('stdout')
			.should('contain', 'Feature activated');

		cy.wpCli('wp elasticpress activate-feature invalid', true)
			.its('stderr')
			.should('contain', 'No feature with that slug is registered');

		cy.wpCli('wp elasticpress activate-feature woocommerce', true)
			.its('stderr')
			.should('contain', 'Feature requirements are not met');

		cy.wpCli('wp elasticpress activate-feature protected_content', true)
			.its('stderr')
			.should('contain', 'This feature requires a re-index')
			.should('contain', 'Feature is usable but there are warnings');
	});

	it('Can list all the active features if user runs wp elasticpress list-features command', () => {
		cy.wpCli('wp elasticpress list-features')
			.its('stdout')
			.should('contain', 'Active features');
	});

	it('Can list all the registered features if user runs wp elasticpress list-features --all command', () => {
		cy.wpCli('wp elasticpress list-features --all')
			.its('stdout')
			.should('contain', 'Registered features');
	});

	it('Can return a string indicating the index is not running', () => {
		cy.wpCli('wp elasticpress get-ongoing-sync-status')
			.its('stdout')
			.should(
				'contain',
				'{"indexing":false,"method":"none","items_indexed":0,"total_items":-1}',
			);
	});

	it('Can return a string indicating with the appropriate fields if user runs wp elasticpress get-last-cli-sync command', () => {
		cy.wpCli('wp elasticpress sync');

		cy.wpCli('wp elasticpress get-last-cli-sync --clear')
			.its('stdout')
			.should('contain', '"total_time"');

		cy.wpCli('wp elasticpress get-last-cli-sync --clear').its('stdout').should('contain', '[]');
	});

	context('multisite parameters', () => {
		before(() => {
			cy.activatePlugin('elasticpress', 'wpCli', 'network');
			cy.wpCli('elasticpress get-indices').then((wpCliResponse) => {
				indexAllSitesNames = JSON.parse(wpCliResponse.stdout);
			});
		});

		after(() => {
			cy.deactivatePlugin('elasticpress', 'wpCli', 'network');
		});

		it('Can index all blogs in network if user specifies --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress sync --network-wide')
				.its('stdout')
				.then((output) => {
					expect((output.match(/Indexing posts on site/g) || []).length).to.equal(2);
					expect(
						(output.match(/Number of posts indexed on site/g) || []).length,
					).to.equal(2);
					expect(output).to.contain('Network alias created');
				});

			checkIfNotMissingIndexes('network');
		});

		it('Can index only current site if user does not specify --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli(`wp elasticpress sync`)
				.its('stdout')
				.then((output) => {
					expect((output.match(/Indexing posts on site/g) || []).length).to.equal(1);
					expect(
						(output.match(/Number of posts indexed on site/g) || []).length,
					).to.equal(1);
					expect(output).to.not.contain('Network alias created');
				});

			checkIfNotMissingIndexes('network');
		});

		it('Can index only site in the --url parameter if user does not specify --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli(`wp elasticpress sync --url=${Cypress.config('baseUrl')}/second-site`)
				.its('stdout')
				.then((output) => {
					expect((output.match(/Indexing posts on site/g) || []).length).to.equal(1);
					expect(
						(output.match(/Number of posts indexed on site/g) || []).length,
					).to.equal(1);
					expect(output).to.not.contain('Network alias created');
				});

			checkIfNotMissingIndexes('network');
		});

		it('Can delete all the indices and put mappings for the entire network-wide', () => {
			cy.wpCli('wp elasticpress delete-index --network-wide --yes')
				.its('stdout')
				.should('contain', 'Deleting post index for site')
				.should('contain', 'Index deleted');

			cy.visitAdminPage('network/admin.php?page=elasticpress-health');
			cy.get('.wrap').should(
				'contain.text',
				'We could not find any data for your Elasticsearch indices.',
			);

			cy.wpCli('wp elasticpress put-mapping --network-wide')
				.its('stdout')
				.should('contain', 'Adding post mapping for site')
				.should('contain', 'Mapping sent');

			checkIfNotMissingIndexes('network');
		});
	});

	it('Can set the algorithm version', () => {
		cy.wpCli('wp elasticpress set-algorithm-version --default')
			.its('stdout')
			.should('contain', 'Done');

		cy.wpCli('wp elasticpress get-algorithm-version')
			.its('stdout')
			.should('contain', 'default');

		cy.wpCli('wp elasticpress set-algorithm-version --version=1.0.0')
			.its('stdout')
			.should('contain', 'Done');

		cy.wpCli('wp elasticpress get-algorithm-version').its('stdout').should('contain', '1.0.0');

		cy.wpCli('wp elasticpress set-algorithm-version', true)
			.its('stderr')
			.should('contain', 'This command expects a version number or the --default flag');
	});

	it('Can get the mapping information', () => {
		cy.wpCli('wp elasticpress get-mapping').its('stdout').should('contain', 'mapping_version');
	});

	it('Can get the cluster indices information', () => {
		cy.wpCli('wp elasticpress get-cluster-indices').its('stdout').should('contain', 'health');
	});

	it('Can get the indices names', () => {
		cy.wpCli('wp elasticpress get-indices').its('code').should('equal', 0);

		cy.wpCli('wp elasticpress get-indices --pretty').its('stdout').should('contain', '\n');
	});

	it('Can stop the sync operation and clear it', () => {
		// if no sync process is running, this will fail.
		cy.wpCli('wp elasticpress stop-sync')
			.its('stderr')
			.should('contain', 'There is no sync operation running');

		// mock the sync process
		cy.wpCliEval(
			`update_option('ep_index_meta', true); set_transient('ep_sync_interrupted', true);`,
		);

		cy.wpCli('wp elasticpress stop-sync').its('stdout').should('contain', 'Done');

		cy.wpCli('wp elasticpress clear-sync').its('stdout').should('contain', 'Index cleared');
	});

	it('can send an HTTP request to Elasticsearch', () => {
		cy.wpCli('wp elasticpress  request _cat/indices').its('code').should('equal', 0);

		// check if it throw an error if non supported method is used?
		cy.wpCli('wp elasticpress request _cat/indices --method=POST')
			.its('stdout')
			.should('contain', 'Incorrect HTTP method for uri');

		// check if it print the debugging info?
		cy.wpCli('wp elasticpress request _cat/indices --debug-http-request')
			.its('stdout')
			.should('contain', '[http_response] => WP_HTTP_Requests_Response Object');
	});
});
