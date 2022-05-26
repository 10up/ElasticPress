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

	context('wp elasticpress index', () => {
		it('Can index all the posts of the current blog', () => {
			cy.wpCli('wp elasticpress index')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', 'Number of posts indexed');

			checkIfNotMissingIndexes();
		});

		it('Can clear the index in Elasticsearch, put the mapping again and then index all the posts if user specifies --setup argument', () => {
			cy.wpCli('wp elasticpress index --setup --yes')
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
			cy.wpCli('wp elasticpress index --per-page=20')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', '20 of')
				.should('contain', '40 of')
				.should('contain', 'Number of posts indexed');
		});

		it('Can index one post at a time if user specifies --nobulk parameter', () => {
			cy.wpCli('wp elasticpress index --nobulk')
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
			cy.wpCli('wp elasticpress index --offset=10').then((wpCliResponse) => {
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
			cy.wpCli('wp elasticpress index --post-type=post').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);
				indexPerPostType = match.groups.indexed;
			});

			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);
				indexTotal = match.groups.indexed;

				expect(indexPerPostType).to.not.equal(indexTotal);
			});
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
		cy.wpCli('wp elasticpress get-indexing-status')
			.its('stdout')
			.should(
				'contain',
				'{"indexing":false,"method":"none","items_indexed":0,"total_items":-1}',
			);
	});

	it('Can return a string indicating with the appropriate fields if user runs wp elasticpress get-last-cli-index command', () => {
		cy.wpCli('wp elasticpress index');

		cy.wpCli('wp elasticpress get-last-cli-index --clear')
			.its('stdout')
			.should('contain', '"total_time"');

		cy.wpCli('wp elasticpress get-last-cli-index --clear')
			.its('stdout')
			.should('contain', '[]');
	});

	context('multisite parameters', () => {
		before(() => {
			cy.activatePlugin('elasticpress', 'wpCli', 'network');
			cy.wpCli('elasticpress get-indexes').then((wpCliResponse) => {
				indexAllSitesNames = JSON.parse(wpCliResponse.stdout);
			});
		});

		after(() => {
			cy.deactivatePlugin('elasticpress', 'wpCli', 'network');
		});

		it('Can index all blogs in network if user specifies --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index --network-wide')
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
			cy.wpCli(`wp elasticpress index`)
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
			cy.wpCli(`wp elasticpress index --url=${Cypress.config('baseUrl')}/second-site`)
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
});
