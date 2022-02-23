/* global indexNames */

describe('Dashboard Sync', () => {
	let oldPostsPerCycle = 0;
	function setPerIndexCycle(number = null) {
		const newValue = number || oldPostsPerCycle;
		cy.wpCli(`option set ep_bulk_setting ${newValue}`);
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

	function resumeAndWait() {
		cy.get('.resume-sync').click();
		cy.get('.sync-status', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);

		/**
		 * In some specific scenario, if Cypress leaves the page too fast, EP will think a sync is happening.
		 *
		 * @todo instead of waiting for an arbitrary time, we should investigate this further.
		 */
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(2000);
	}

	before(() => {
		cy.login();
		cy.wpCli('option get ep_bulk_setting').then((wpCliResponse) => {
			oldPostsPerCycle = JSON.parse(wpCliResponse.stdout);
		});
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
		setPerIndexCycle(10);

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.intercept('POST', '/wp-admin/admin-ajax.php').as('ajaxRequest');
		cy.get('.start-sync').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.get('.pause-sync').should('be.visible');

		cy.visitAdminPage('index.php');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.sync-status').should('contain.text', 'Sync paused');

		resumeAndWait();

		setPerIndexCycle();

		canSeeIndexesNames();
	});

	it("Can't activate features during a sync", () => {
		setPerIndexCycle(10);

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.intercept('POST', '/wp-admin/admin-ajax.php').as('ajaxRequest');
		cy.get('.start-sync').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.get('.pause-sync').should('be.visible');
		cy.get('.pause-sync').click();

		cy.get('.error-overlay').should('have.class', 'syncing');

		resumeAndWait();
		cy.get('.error-overlay').should('not.have.class', 'syncing');

		setPerIndexCycle();
	});

	it("Can't index via WP-CLI if indexing via Dashboard", () => {
		setPerIndexCycle(10);

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

		resumeAndWait();

		setPerIndexCycle();
	});
});
