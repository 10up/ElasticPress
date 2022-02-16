/* global indexNames */

describe('WP-CLI Commands', () => {
	function checkIfNotMissingIndexes() {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap')
			.invoke('text')
			.then((text) => {
				expect(text).to.not.contain(
					'We could not find any data for your Elasticsearch indices.',
				);
			});

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.metabox-holder')
			.invoke('text')
			.then((text) => {
				indexNames.forEach((index) => {
					expect(text).to.contains(index);
				});
			});
	}

	context('wp elasticpress index', () => {
		it('Can index all the posts of the current blog', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');
				expect(wpCliResponse.stdout).to.contains('Number of posts indexed');
			});

			checkIfNotMissingIndexes();
		});

		it('Can index all blogs in network if user specifies --network-wide argument', () => {
			cy.login();

			cy.activatePlugin('elasticpress', 'dashboard', 'network');

			cy.visitAdminPage('network/sites.php');
			cy.get('.index-toggle').check();

			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index --network-wide').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts on site');
				expect(wpCliResponse.stdout).to.contains('Number of posts indexed on site');
			});

			checkIfNotMissingIndexes();

			cy.deactivatePlugin('elasticpress', 'dashboard', 'network');
		});

		it('Can clear the index in Elasticsearch, put the mapping again and then index all the posts if user specifies --setup argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index --setup --yes').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Mapping sent');
				expect(wpCliResponse.stdout).to.contains('Indexing posts');
				expect(wpCliResponse.stdout).to.contains('Number of posts indexed');
			});

			checkIfNotMissingIndexes();
		});

		it('Can process that many posts in bulk index per round if user specifies --per-page parameter', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index --per-page=20').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');
				expect(wpCliResponse.stdout).to.contains('Processed 20/');
				expect(wpCliResponse.stdout).to.contains('Processed 40/');
				expect(wpCliResponse.stdout).to.contains('Number of posts indexed');
			});
		});

		it('Can index one post at a time if user specifies --nobulk parameter', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index --nobulk').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');
				expect(wpCliResponse.stdout).to.contains('Processed 1/');
				expect(wpCliResponse.stdout).to.contains('Processed 2/');
				expect(wpCliResponse.stdout).to.contains('Processed 3/');
				expect(wpCliResponse.stdout).to.contains('Processed 4/');
				expect(wpCliResponse.stdout).to.contains('Number of posts indexed');
			});
		});

		it('Can skip X posts and index the remaining if user specifies --offset parameter', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp elasticpress index --offset=10').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match1 = wpCliResponse.stdout.match(/Processed (\d+)\/(?<total>\d+)./);
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

	context('wp elasticpress delete-index', () => {
		it('Can delete the index of current blog', () => {});

		it('Can delete all the index network-wide if user runs wp elasticpress delete-index --network-wide', () => {});
	});

	context('wp elasticpress put-mapping', () => {
		it('Can put mapping of the current blog', () => {});

		it('Can put mapping network-wide if user runs wp elasticpress put-mapping --network-wide', () => {});
	});

	it('Can recreate the alias index which points to every index in the network if user runs wp elasticpress recreate-network-alias command', () => {});

	it('Can activate a feature if user runs wp elasticpress activate-feature and specify feature', () => {});

	it('Can deactivate a feature if user runs wp elasticpress deactivate-feature and specify feature', () => {});

	it('Can list all the active features if user runs wp elasticpress list-features command', () => {});

	it('Can list all the registered features if user runs wp elasticpress list-features --all command', () => {});

	it('Can return the number of documents indexed and index size if user runs wp elasticpress stats command', () => {});

	it('Can return a string indicating the index is not running', () => {});

	it('Can return a string indicating with the appropriate fields if user runs wp elasticpress get-last-cli-index command', () => {});
});
