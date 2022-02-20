/* global indexNames */

describe('Dashboard Sync', () => {
	function setPerIndexCycle(number) {
		let oldValue;
		cy.visitAdminPage('admin.php?page=elasticpress-settings');

		cy.get('#ep_bulk_setting').then(($input) => {
			oldValue = $input.val();
			$input.val(number);
		});
		cy.get('#submit').click();

		return oldValue;
	}

	function canSeeIndexesNames() {
		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.metabox-holder')
			.invoke('text')
			.then((text) => {
				indexNames.forEach((index) => {
					expect(text).to.contains(index);
				});
			});
	}

	before(() => {
		cy.login();
	});

	after(() => {
		if (cy.state('test').state === 'failed') {
			cy.deactivatePlugin('elasticpress', 'wpCli', 'network');
			cy.wpCli('wp elasticpress clear-index', true);
			cy.visitAdminPage('admin.php?page=elasticpress-settings');
			cy.get('body').then(($body) => {
				const $cancelSyncButton = $body.find('.cancel-sync');
				if ($cancelSyncButton.length) {
					$cancelSyncButton.click();
				}
			});
		}
	});

	it('Can index content and see indexes names in the Health Screen', () => {
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		canSeeIndexesNames();
	});

	it('Can sync via Dashboard when activated in single site', () => {
		cy.wpCli('wp elasticpress delete-index --yes');

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'not.contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		canSeeIndexesNames();
	});

	it('Can sync via Dashboard when activated in multisite', () => {
		cy.wpCli('wp elasticpress delete-index --yes');

		cy.activatePlugin('elasticpress', 'wpCli', 'network');

		// Sync and remove, so EP doesn't think it is a fresh install.
		cy.wpCli('wp elasticpress index --setup --yes');
		cy.wpCli('wp elasticpress delete-index --yes');

		cy.visitAdminPage('network/admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.visitAdminPage('network/admin.php?page=elasticpress');
		cy.get('.start-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		cy.visitAdminPage('network/admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'not.contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.wpCli('elasticpress get-indexes').then((wpCliResponse) => {
			const indexes = JSON.parse(wpCliResponse.stdout);
			cy.visitAdminPage('network/admin.php?page=elasticpress-health');
			cy.get('.metabox-holder')
				.invoke('text')
				.then((text) => {
					indexes.forEach((index) => {
						expect(text).to.contains(index);
					});
				});
		});

		cy.deactivatePlugin('elasticpress', 'wpCli', 'network');
		cy.activatePlugin('elasticpress', 'wpCli');

		cy.wpCli('wp elasticpress index --setup --yes');
	});

	it('Can pause the dashboard sync if left the page', () => {
		const oldPostsPerCycle = setPerIndexCycle(10);

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.intercept('POST', '/wp-admin/admin-ajax.php').as('ajaxRequest');
		cy.get('.start-sync').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.get('.pause-sync').should('be.visible');

		cy.visitAdminPage('index.php');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.sync-status').should('contain.text', 'Sync paused');

		cy.get('.resume-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		setPerIndexCycle(oldPostsPerCycle);

		canSeeIndexesNames();
	});

	it("Can't activate features during a sync", () => {
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.intercept('POST', '/wp-admin/admin-ajax.php').as('ajaxRequest');
		cy.get('.start-sync').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.get('.pause-sync').should('be.visible');
		cy.get('.pause-sync').click();

		cy.get('.error-overlay').should('have.class', 'syncing');

		/**
		 * When clicking on the resume button, we expect two requests:
		 * 1. To index the last batch
		 * 2. To set is as done
		 */
		cy.get('.resume-sync').click();
		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.wait('@ajaxRequest').then((ajaxRequest) => {
			cy.log(ajaxRequest.response.body);
			expect(ajaxRequest.response.body.data.found_items).to.equal(
				ajaxRequest.response.body.data.offset,
			);
		});
		cy.wait('@ajaxRequest').its('response.body.data.found_items').should('eq', 0);
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);
		cy.get('.error-overlay').should('not.have.class', 'syncing');
	});

	it("Can't index via WP-CLI if indexing via Dashboard", () => {
		const oldPostsPerCycle = setPerIndexCycle(10);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.intercept('POST', '/wp-admin/admin-ajax.php').as('ajaxRequest');
		cy.get('.start-sync').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		cy.get('.pause-sync').should('be.visible');
		cy.get('.pause-sync').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		cy.wpCli('wp elasticpress index', true)
			.its('stderr')
			.should('contain', 'An index is already occurring');

		cy.get('.resume-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		setPerIndexCycle(oldPostsPerCycle);
	});
});
