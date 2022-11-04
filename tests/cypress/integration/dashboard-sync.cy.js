/* global indexNames */

describe('Dashboard Sync', () => {
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
		cy.get('.ep-sync-button--resume').click();
		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');
	}

	before(() => {
		cy.login();
	});

	afterEach(() => {
		if (cy.state('test').state === 'failed') {
			cy.deactivatePlugin('elasticpress', 'wpCli', 'network');
			cy.activatePlugin('elasticpress', 'wpCli');
			cy.wpCli('wp elasticpress clear-sync', true);
		}
	});

	it('Should only display a single sync option for the initial sync', () => {
		/**
		 * Reset settings and skip install.
		 */
		cy.wpCli('elasticpress settings-reset --yes');
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.setup-message a').contains('Skip Install').click();

		/**
		 * If a sync has not been performed the sync page should only show a
		 * single sync panel.
		 */
		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-panel')
			.should('have.length', 1)
			.as('syncPanel')
			.should('contain.text', 'Run a sync to index your existing content');

		/**
		 * Perform an initial sync.
		 */
		cy.get('@syncPanel').find('.ep-sync-button').click();

		/**
		 * The sync log should indicate that the sync completed and that
		 * mapping was sent.
		 */
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') })
			.should('contain.text', 'Mapping sent')
			.should('contain.text', 'Sync complete');

		/**
		 * After the initial sync is complete there should be 2 sync panels
		 * and the second should contain the delete & sync option.
		 */
		cy.get('.ep-sync-panel')
			.should('have.length', 2)
			.last()
			.should('contain.text', 'If you are still having issues with your search results');
	});

	it('Can sync via Dashboard when activated in single site', () => {
		cy.wpCli('wp elasticpress delete-index --yes');

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--delete').click();
		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.visitAdminPage('admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'not.contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		canSeeIndexesNames();
	});

	it('Can sync via Dashboard when activated in multisite', () => {
		cy.activatePlugin('elasticpress', 'wpCli', 'network');

		// Sync and remove, so EP doesn't think it is a fresh install.
		cy.wpCli('wp elasticpress sync --setup --yes');
		cy.wpCli('wp elasticpress delete-index --yes --network-wide');

		cy.visitAdminPage('network/admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.visitAdminPage('network/admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--delete').click();
		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.visitAdminPage('network/admin.php?page=elasticpress-health');
		cy.get('.wrap').should(
			'not.contain.text',
			'We could not find any data for your Elasticsearch indices.',
		);

		cy.wpCli('elasticpress get-indices').then((wpCliResponse) => {
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
	});

	it('Can pause the dashboard sync, can not activate a feature during sync nor perform a sync via WP-CLI', () => {
		cy.setPerIndexCycle(20);

		cy.visitAdminPage('admin.php?page=elasticpress-sync');

		// Start sync via dashboard and pause it
		cy.intercept('POST', '/wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('.ep-sync-button--delete').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.get('.ep-sync-button--pause').should('be.visible');

		// Can not activate a feature.
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.error-overlay').should('have.class', 'syncing');

		// Can not start a sync via WP-CLI
		cy.wpCli('wp elasticpress sync', true)
			.its('stderr')
			.should('contain', 'An index is already occurring');

		// Check if it is paused
		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--resume').should('be.visible');
		cy.get('.ep-sync-progress strong').should('contain.text', 'Sync paused');

		resumeAndWait();
		canSeeIndexesNames();

		// Features should be accessible again
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.error-overlay').should('not.have.class', 'syncing');

		cy.setPerIndexCycle();
	});
});
